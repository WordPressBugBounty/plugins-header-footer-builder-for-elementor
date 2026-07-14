<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFB_Issue_Reporter {

	/**
	 * Preserve the previously registered handlers so WordPress and other plugins keep their behavior.
	 */
	private static $previous_error_handler = null;
	private static $previous_exception_handler = null;

	/**
	 * Register the handlers as early as possible during the plugins_loaded phase.
	 */
	public static function bootstrap() {
		if ( null !== self::$previous_error_handler || null !== self::$previous_exception_handler ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- intentional production feature: silent fatal-error reporting, not leftover debug code.
		self::$previous_error_handler = set_error_handler( [ __CLASS__, 'handle_php_error' ] );
		self::$previous_exception_handler = set_exception_handler( [ __CLASS__, 'handle_exception' ] );
		register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );
		add_action( 'wp_mail_failed', [ __CLASS__, 'log_mail_failure' ] );
	}

	/**
	 * wp_mail() only returns false on failure — without this, a failed
	 * report send is indistinguishable from "no fatal error occurred".
	 */
	public static function log_mail_failure( $wp_error ) {
		// Only write to the error log on sites where debugging has been explicitly
		// enabled — never on a default production install (WP_DEBUG off).
		if ( $wp_error instanceof WP_Error && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG above; only runs when the site owner has opted into debug logging.
			error_log( '[Turbo Addons] Issue reporter mail failed: ' . $wp_error->get_error_message() );
		}
	}

	/**
	 * Capture PHP errors that are likely to be fatal or otherwise critical.
	 */
	public static function handle_php_error( $errno, $errstr, $errfile, $errline ) {
		if ( ! self::should_capture( $errfile ) ) {
			return self::call_previous_error_handler( $errno, $errstr, $errfile, $errline );
		}

		$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR ];
		if ( ! in_array( $errno, $fatal_types, true ) ) {
			return self::call_previous_error_handler( $errno, $errstr, $errfile, $errline );
		}

		self::send_report( $errstr, $errfile, $errline );
		return self::call_previous_error_handler( $errno, $errstr, $errfile, $errline );
	}

	/**
	 * Capture uncaught exceptions from plugin code only.
	 */
	public static function handle_exception( $exception ) {
		if ( $exception instanceof Throwable ) {
			if ( self::should_capture( $exception->getFile() ) ) {
				self::send_report( $exception->getMessage(), $exception->getFile(), $exception->getLine() );
			}
		}

		if ( is_callable( self::$previous_exception_handler ) ) {
			return call_user_func( self::$previous_exception_handler, $exception );
		}
	}

	/**
	 * Capture fatal shutdown errors after PHP has finished execution.
	 */
	public static function handle_shutdown() {
		$error = error_get_last();
		if ( empty( $error ) || ! is_array( $error ) ) {
			return;
		}

		$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];
		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		if ( self::should_capture( $error['file'] ) ) {
			self::send_report( $error['message'], $error['file'], $error['line'] );
		}
	}

	/**
	 * Only report errors that clearly originate from the Turbo Addons codebase.
	 */
	private static function should_capture( $file ) {
		$file = is_string( $file ) ? $file : '';
		if ( '' === $file ) {
			return false;
		}

		$normalized = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $file ) : str_replace( '\\', '/', $file );
		$normalized = strtolower( $normalized );

		$plugin_folder = basename( dirname( __DIR__ ) );
		$plugin_folder = strtolower( $plugin_folder );

		return false !== strpos( $normalized, 'turbo-addons' )
			|| false !== strpos( $normalized, $plugin_folder );
	}

	/**
	 * Delegate to the previous error handler if one exists.
	 */
	private static function call_previous_error_handler( $errno, $errstr, $errfile, $errline ) {
		if ( is_callable( self::$previous_error_handler ) ) {
			return call_user_func( self::$previous_error_handler, $errno, $errstr, $errfile, $errline );
		}

		return false;
	}

	/**
	 * Read back the errors captured on this site, most recent first.
	 * This is the source of truth for the on-demand diagnostic report —
	 * it does not depend on outbound email ever working.
	 */
	public static function get_captured_errors() {
		$errors = get_option( 'hfb_captured_errors', [] );
		return is_array( $errors ) ? $errors : [];
	}

	/**
	 * Persist a captured error locally so it can be read back on demand,
	 * regardless of whether the email alert below ever gets delivered.
	 * Repeat occurrences of the same error bump a count instead of
	 * growing the list, and are moved to the front as "most recent".
	 */
	private static function store_captured_error( $message, $file, $line ) {
		$errors = self::get_captured_errors();
		$key    = md5( $message . '|' . $file . '|' . $line );
		$match  = null;

		foreach ( $errors as $index => $entry ) {
			if ( isset( $entry['key'] ) && $key === $entry['key'] ) {
				$match = $entry;
				unset( $errors[ $index ] );
				break;
			}
		}

		if ( null === $match ) {
			$match = [
				'key'        => $key,
				'message'    => sanitize_text_field( $message ),
				'file'       => sanitize_text_field( $file ),
				'line'       => intval( $line ),
				'first_seen' => current_time( 'mysql' ),
				'count'      => 0,
			];
		}

		$match['count']     = (int) $match['count'] + 1;
		$match['last_seen'] = current_time( 'mysql' );

		array_unshift( $errors, $match );
		update_option( 'hfb_captured_errors', array_slice( array_values( $errors ), 0, 15 ), false );
	}

	/**
	 * Send a minimal, GDPR-friendly report to support.
	 */
	private static function send_report( $message, $file, $line ) {
		self::store_captured_error( $message, $file, $line );

		$site_url = esc_url( home_url( '/' ) );
		$site_name = sanitize_text_field( get_bloginfo( 'name' ) );
		$site_email = sanitize_email( get_option( 'admin_email' ) );
		$line_number = intval( $line );

		if ( empty( $site_name ) ) {
			$site_name = 'WordPress Site';
		}

		if ( empty( $site_email ) ) {
			$site_email = 'support@turbo-addons.com';
		}

		$transient_key = 'tahefobu_error_report_' . md5( $site_url );
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}

		$body = sprintf(
			"Site URL: %s\nError Message: %s\nFile Path: %s\nLine Number: %d\n",
			esc_url( home_url( '/' ) ),
			sanitize_text_field( $message ),
			sanitize_text_field( $file ),
			$line_number
		);

		$headers = [
			'From: ' . sanitize_text_field( $site_name ) . ' <' . sanitize_email( $site_email ) . '>',
			'Reply-To: ' . sanitize_text_field( $site_name ) . ' <' . sanitize_email( $site_email ) . '>',
		];

		if ( ! function_exists( 'wp_mail' ) ) {
			$pluggable_file = ABSPATH . WPINC . '/pluggable.php';
			if ( file_exists( $pluggable_file ) ) {
				require_once $pluggable_file;
			}
		}

		$sent = false;
		if ( function_exists( 'wp_mail' ) ) {
			$sent = wp_mail( 'support@turbo-addons.com', '[Turbo Addons] Fatal error report', $body, $headers );
		}

		if ( ! $sent ) {
			$php_headers = implode( "\r\n", $headers );
			$sent = mail( 'support@turbo-addons.com', '[Turbo Addons] Fatal error report', $body, $php_headers );
		}

		if ( $sent ) {
			set_transient( $transient_key, 1, DAY_IN_SECONDS );
		}
	}
}
