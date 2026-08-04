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
		// Admin tools: show captured errors and allow clearing them
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
		add_action( 'admin_post_hfb_clear_errors', [ __CLASS__, 'admin_clear_errors' ] );
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
			error_log( '[Header & Footer Builder] Issue reporter mail failed: ' . $wp_error->get_error_message() );
		}
	}

	/**
	 * Register an admin page under Tools to view captured errors.
	 */
	public static function register_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			'tools.php',
			__( 'HFB Error Reports', 'header-footer-builder-for-elementor' ),
			__( 'HFB Errors', 'header-footer-builder-for-elementor' ),
			'manage_options',
			'hfb-error-reports',
			[ __CLASS__, 'render_admin_page' ]
		);
		add_action( 'admin_post_hfb_update_settings', [ __CLASS__, 'admin_update_settings' ] );
	}

	/**
	 * Render admin page showing captured errors.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'header-footer-builder-for-elementor' ) );
		}

		$errors = self::get_captured_errors();
		$cleared = isset( $_GET['cleared'] ) ? boolval( $_GET['cleared'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by our own redirect, no data is processed

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Header & Footer Builder — Captured Errors', 'header-footer-builder-for-elementor' ); ?></h1>
			<?php if ( $cleared ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Captured errors cleared.', 'header-footer-builder-for-elementor' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'This list shows recent fatal or uncaught errors captured by the plugin. It is safe to share with support.', 'header-footer-builder-for-elementor' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
				<?php wp_nonce_field( 'hfb_clear_errors_action', 'hfb_clear_errors_nonce' ); ?>
				<input type="hidden" name="action" value="hfb_clear_errors">
				<button class="button button-secondary" type="submit"><?php esc_html_e( 'Clear Captured Errors', 'header-footer-builder-for-elementor' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:24px;">
				<?php wp_nonce_field( 'hfb_update_settings_action', 'hfb_update_settings_nonce' ); ?>
				<input type="hidden" name="action" value="hfb_update_settings">
				<label style="display:flex;align-items:center;gap:10px;">
					<input type="checkbox" name="enable_wp_body_open_fallback" value="1" <?php checked( get_option( 'tahefobu_enable_wp_body_open_fallback', '1' ), '1' ); ?>>
					<span><?php esc_html_e( 'Enable server-side header fallback (wp_body_open)', 'header-footer-builder-for-elementor' ); ?></span>
				</label>
				<p class="description" style="margin:8px 0 0;"><?php esc_html_e( 'When enabled the plugin will output a header fallback via wp_body_open where supported. Disable to revert to JS-only fallback.', 'header-footer-builder-for-elementor' ); ?></p>
				<button class="button button-primary" type="submit" style="margin-top:8px;"><?php esc_html_e( 'Save Settings', 'header-footer-builder-for-elementor' ); ?></button>
			</form>
			<?php if ( empty( $errors ) ) : ?>
				<p><?php esc_html_e( 'No captured errors found.', 'header-footer-builder-for-elementor' ); ?></p>
			<?php else : ?>
				<table class="widefat fixed" cellspacing="0">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Message', 'header-footer-builder-for-elementor' ); ?></th>
							<th><?php esc_html_e( 'File', 'header-footer-builder-for-elementor' ); ?></th>
							<th><?php esc_html_e( 'Line', 'header-footer-builder-for-elementor' ); ?></th>
							<th><?php esc_html_e( 'Count', 'header-footer-builder-for-elementor' ); ?></th>
							<th><?php esc_html_e( 'First Seen', 'header-footer-builder-for-elementor' ); ?></th>
							<th><?php esc_html_e( 'Last Seen', 'header-footer-builder-for-elementor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $errors as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['file'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['line'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['count'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['first_seen'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['last_seen'] ?? '' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle clearing captured errors via admin-post.
	 */
	public static function admin_clear_errors() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'header-footer-builder-for-elementor' ) );
		}

		if ( ! isset( $_POST['hfb_clear_errors_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfb_clear_errors_nonce'] ) ), 'hfb_clear_errors_action' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'header-footer-builder-for-elementor' ) );
		}

		delete_option( 'hfb_captured_errors' );

		wp_safe_redirect( admin_url( 'tools.php?page=hfb-error-reports&cleared=1' ) );
		exit;
	}

	/**
	 * Handle updating HFB settings from admin page.
	 */
	public static function admin_update_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'header-footer-builder-for-elementor' ) );
		}

		if ( ! isset( $_POST['hfb_update_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfb_update_settings_nonce'] ) ), 'hfb_update_settings_action' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'header-footer-builder-for-elementor' ) );
		}

		$enabled = isset( $_POST['enable_wp_body_open_fallback'] ) && '1' === $_POST['enable_wp_body_open_fallback'] ? '1' : '0';
		update_option( 'tahefobu_enable_wp_body_open_fallback', $enabled );

		wp_safe_redirect( admin_url( 'tools.php?page=hfb-error-reports&updated=1' ) );
		exit;
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
	 * Detect the hosting provider based on known constants, env vars, and server signatures.
	 */
	private static function detect_hosting( $server_software ) {
		$sw = strtolower( $server_software );

		// --- Constant-based detection (most reliable) ---
		if ( defined( 'WPE_APIKEY' ) )                          return 'WP Engine';
		if ( defined( 'KINSTA_DEV_ENV' ) || defined( 'KINSTA_CACHE_ZONE' ) ) return 'Kinsta';
		if ( defined( 'FLYWHEEL_CONFIG_DIR' ) )                 return 'Flywheel (WP Engine)';
		if ( defined( 'PANTHEON_ENVIRONMENT' ) )                return 'Pantheon';
		if ( defined( 'GD_SYSTEM_PLUGIN_DIR' ) )                return 'GoDaddy';
		if ( defined( 'IS_PRESSABLE' ) )                        return 'Pressable';
		if ( defined( 'WPCOMSH_VERSION' ) )                     return 'WordPress.com';
		if ( defined( 'ATOMIC_CLIENT_ID' ) )                    return 'WordPress.com Atomic';
		if ( defined( 'WPENGINE_ACCOUNT' ) )                    return 'WP Engine';
		if ( defined( 'CLOUDWAYS_APP_ID' ) )                    return 'Cloudways';

		// --- Environment variable based ---
		if ( function_exists( 'getenv' ) ) {
			if ( false !== getenv( 'SPINUPWP_SITE' ) )          return 'SpinupWP';
			if ( false !== getenv( 'KINSTA_CACHE_ZONE' ) )      return 'Kinsta';
			if ( false !== getenv( 'CLOUDWAYS_APP_ID' ) )       return 'Cloudways';
			if ( false !== getenv( 'PLATFORM_APPLICATION_NAME' ) ) return 'Platform.sh';
			if ( false !== getenv( 'LANDO_INFO' ) )             return 'Lando (Local Dev)';
			if ( false !== getenv( 'DDEV_SITENAME' ) )          return 'DDEV (Local Dev)';
			if ( false !== getenv( 'LOCALWP_SITE_ID' ) )        return 'Local by Flywheel (Local Dev)';
		}

		// --- SERVER_NAME / hostname based ---
		$hostname = isset( $_SERVER['SERVER_NAME'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) ) : '';
		if ( false !== strpos( $hostname, 'hostinger' ) )       return 'Hostinger';
		if ( false !== strpos( $hostname, 'namecheap' ) )       return 'Namecheap';
		if ( false !== strpos( $hostname, 'siteground' ) )      return 'SiteGround';
		if ( false !== strpos( $hostname, 'bluehost' ) )        return 'Bluehost';
		if ( false !== strpos( $hostname, 'dreamhost' ) )       return 'DreamHost';
		if ( false !== strpos( $hostname, 'a2hosting' ) )       return 'A2 Hosting';
		if ( false !== strpos( $hostname, 'inmotionhosting' ) ) return 'InMotion Hosting';
		if ( false !== strpos( $hostname, 'liquidweb' ) )       return 'Liquid Web';
		if ( false !== strpos( $hostname, 'wpmudev' ) )         return 'WPMU DEV Hosting';
		if ( false !== strpos( $hostname, 'ionos' ) )           return 'IONOS (1&1)';
		if ( false !== strpos( $hostname, '1and1' ) )           return 'IONOS (1&1)';
		if ( false !== strpos( $hostname, 'ovh' ) )             return 'OVHcloud';
		if ( false !== strpos( $hostname, 'hetzner' ) )         return 'Hetzner';
		if ( false !== strpos( $hostname, 'digitalocean' ) )    return 'DigitalOcean';
		if ( false !== strpos( $hostname, 'vultr' ) )           return 'Vultr';
		if ( false !== strpos( $hostname, 'linode' ) || false !== strpos( $hostname, 'akamai' ) ) return 'Linode / Akamai Cloud';
		if ( false !== strpos( $hostname, 'amazonaws' ) )       return 'Amazon AWS';
		if ( false !== strpos( $hostname, 'googleusercontent' ) || false !== strpos( $hostname, 'google.cloud' ) ) return 'Google Cloud';
		if ( false !== strpos( $hostname, 'azure' ) )           return 'Microsoft Azure';
		if ( false !== strpos( $hostname, 'wpx.net' ) )         return 'WPX Hosting';
		if ( false !== strpos( $hostname, 'rocket.net' ) )      return 'Rocket.net';
		if ( false !== strpos( $hostname, 'nexcess' ) )         return 'Nexcess';
		if ( false !== strpos( $hostname, 'cloudaccess' ) )     return 'CloudAccess.net';
		if ( false !== strpos( $hostname, 'wpbeginner' ) )      return 'Bluehost (WPBeginner)';
		if ( false !== strpos( $hostname, 'fastcomet' ) )       return 'FastComet';
		if ( false !== strpos( $hostname, 'tsohost' ) )         return 'TSO Host';
		if ( false !== strpos( $hostname, 'krystal' ) )         return 'Krystal Hosting';
		if ( false !== strpos( $hostname, 'kualo' ) )           return 'Kualo';
		if ( false !== strpos( $hostname, 'interserver' ) )     return 'InterServer';
		if ( false !== strpos( $hostname, 'hostgator' ) )       return 'HostGator';
		if ( false !== strpos( $hostname, 'ipage' ) )           return 'iPage';
		if ( false !== strpos( $hostname, 'fatcow' ) )          return 'FatCow';
		if ( false !== strpos( $hostname, 'justhost' ) )        return 'JustHost';
		if ( false !== strpos( $hostname, 'hostpapa' ) )        return 'HostPapa';
		if ( false !== strpos( $hostname, 'greengeeks' ) )      return 'GreenGeeks';
		if ( false !== strpos( $hostname, 'scalahosting' ) )    return 'Scala Hosting';
		if ( false !== strpos( $hostname, 'chemicloud' ) )      return 'ChemiCloud';
		if ( false !== strpos( $hostname, 'verpex' ) )          return 'Verpex';
		if ( false !== strpos( $hostname, 'ultahost' ) )        return 'UltaHost';
		if ( false !== strpos( $hostname, 'wphosting' ) )       return 'WP Hosting';

		// --- Server software signature based ---
		if ( false !== strpos( $sw, 'litespeed' ) )             return 'LiteSpeed Server (Hostinger / Namecheap / A2 / SiteGround possible)';
		if ( false !== strpos( $sw, 'openresty' ) )             return 'OpenResty / Nginx (Cloudways / DigitalOcean possible)';

		return 'Unknown (Server: ' . $server_software . ')';
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

		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
		$php_version     = phpversion();
		$wp_version      = get_bloginfo( 'version' );
		$hosting         = self::detect_hosting( $server_software );

		$body = sprintf(
			"Site URL: %s\nError Message: %s\nFile Path: %s\nLine Number: %d\n\n--- Server Info ---\nHosting: %s\nServer Software: %s\nPHP Version: %s\nWordPress Version: %s\n",
			esc_url( home_url( '/' ) ),
			sanitize_text_field( $message ),
			sanitize_text_field( $file ),
			$line_number,
			$hosting,
			$server_software,
			$php_version,
			$wp_version
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
			$sent = wp_mail( 'support@turbo-addons.com', '[Header & Footer Builder] Fatal error report', $body, $headers );
		}

		if ( ! $sent ) {
			$php_headers = implode( "\r\n", $headers );
			$sent = mail( 'support@turbo-addons.com', '[Header & Footer Builder] Fatal error report', $body, $php_headers );
		}

		if ( $sent ) {
			set_transient( $transient_key, 1, DAY_IN_SECONDS );
		}
	}
}
