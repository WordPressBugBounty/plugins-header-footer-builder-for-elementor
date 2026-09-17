<?php
/**
 * Plugin Name: Header Footer Builder for Elementor
 * Plugin URI: https://wp-turbo.com/header-footer-builder-for-elementor/
 * Description: Header Footer Builder for Elementor & WooCommerce. Easy, customizable plugin for headers/footers with display rules, sticky header & include/exclude.
 * Version: 1.3.3
 * Requires at least: 4.7.0
 * Author: turbo addons
 * Author URI: https://wp-turbo.com/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: header-footer-builder-for-elementor
 * Elementor tested up to: 4.2.3
 * Elementor Pro tested up to: 4.2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// wp-pulse integration — must run at top level (not inside plugins_loaded) so that
// register_activation_hook() inside the SDK is registered in time. During plugin
// activation, plugins_loaded has already fired before the plugin file is included,
// so initializing the SDK on plugins_loaded would skip the activation hook entirely
// and the "activated" status would never be sent.
if ( ! class_exists( 'WPPulse_SDK' ) ) {
    $sdk_file = __DIR__ . '/wppulse/wppulse-plugin-analytics-engine-sdk.php';
    if ( file_exists( $sdk_file ) ) {
        require_once $sdk_file;
    }
}

if ( class_exists( 'WPPulse_SDK' ) ) {
    $plugin_data = get_file_data( __FILE__, [
        'Name'    => 'Plugin Name',
        'Version' => 'Version',
    ] );
    WPPulse_SDK::init( __FILE__, [
        'name'     => $plugin_data['Name'],
        'slug'     => dirname( plugin_basename( __FILE__ ) ),
        'version'  => $plugin_data['Version'],
        'endpoint' => 'https://wp-turbo.com/wp-json/wppulse/v1/collect',
    ] );
}


/**
 * Guarded require_once for plugin components.
 *
 * A plain `require_once` on a missing file fatals the whole site (e.g. after an
 * interrupted or partial plugin update). This helper loads the file only when it
 * actually exists and is readable, so a single missing file degrades gracefully
 * instead of white-screening the site.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return bool True if the file was loaded, false otherwise.
 */
function tahefobu_require_component( $relative_path ) {
    $absolute_path = plugin_dir_path( __FILE__ ) . $relative_path;

    if ( ! is_readable( $absolute_path ) ) {
        return false;
    }

    require_once $absolute_path;
    return true;
}

/**
 * Main Plugin Class
 * @since 1.0.0
 */
final class TAHEFOBU_Header_Footer_Builder_For_Elementor {
    const TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_ELEMENTOR_VERSION = '3.5.0';
    const TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_PHP_VERSION = '7.4';
    const TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_DB_VERSION = '1';
    
    private static $_instance = null;
    private $skipped_components = [];

    /**
     * Singleton Instance Method
     * @since 1.0.0
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct() {
        if ( ! function_exists( 'hfbfe_fs' ) ) {
            // Create a helper function for easy SDK access.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Freemius SDK function
            function hfbfe_fs() {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Freemius SDK variable
                global $hfbfe_fs;

                if ( ! isset( $hfbfe_fs ) ) {
                    // Include Freemius SDK (guarded — a missing vendor file must not fatal the site).
                    $freemius_start = dirname( __FILE__ ) . '/vendor/freemius/start.php';
                    if ( file_exists( $freemius_start ) ) {
                        require_once $freemius_start;
                    }

                    if ( ! function_exists( 'fs_dynamic_init' ) ) {
                        return null;
                    }

                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Freemius SDK variable
                    $hfbfe_fs = fs_dynamic_init( array(
                        'id'                  => '22909',
                        'slug'                => 'header-footer-builder-for-elementor',
                        'type'                => 'plugin',
                        'public_key'          => 'pk_092670a4b0e91a5ad9dc497efbf71',
                        'is_premium'          => false,
                        'has_addons'          => false,
                        'has_paid_plans'      => false, // Must be false for WordPress.org
                        'menu'                => array(
                            'slug'           => 'edit.php?post_type=tahefobu_header',
                            // For WordPress.org, only these menu items are allowed:
                            'account'        => false, // Must be false on .org
                            'contact'        => false, // Must be false on .org
                            'support'        => false, // Must be false on .org
                            'pricing'        => false, // Must be false on .org
                            'addons'         => false, // Must be false on .org
                            'affiliation'    => false, // Must be false on .org
                        ),
                        // WordPress.org specific settings:
                        'is_live'             => true,
                        'is_org_compliant'    => true, // Important: Mark as .org compliant
                    ) );
                }

                return $hfbfe_fs;
            }

            // Init Freemius - but with WordPress.org restrictions
            hfbfe_fs();
            
            // Signal that SDK was initiated.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Freemius SDK hook
            do_action( 'hfbfe_fs_loaded' );
        }

        // Header Effects (transparent → solid on scroll) — registered directly
        // on Elementor Section/Container elements inside header templates.
        tahefobu_require_component( 'includes/class-tahefobu-header-effects.php' );
        add_action( 'elementor/init', [ 'TAHEFOBU_Header_Effects', 'init' ] );

        // Mega Menu — per-menu-item settings in the WordPress menu editor +
        // custom Walker used by the Mega Menu widget.
        tahefobu_require_component( 'includes/class-tahefobu-megamenu.php' );
        add_action( 'plugins_loaded', [ 'TAHEFOBU_Mega_Menu', 'init' ] );



        // Load helper once — only here, not again in load_header_footer_templates().
        if ( ! tahefobu_require_component( 'helper/helper.php' ) ) {
            return;
        }
        $this->define_constants();
        // Frontend assets are enqueued conditionally inside load_header_footer_templates()
        // after template matching runs (template_redirect priority 9).
        // The global enqueue hook below only loads assets that are always needed.
        add_action( 'wp_enqueue_scripts', [ $this, 'tahefobu_header_footer_builder_for_elementor_enqueue_scripts_styles' ] );
        // add_action( 'init', [ $this, 'tahefobu_header_footer_builder_for_elementor_load_textdomain' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'tahefobu_header_footer_builder_for_elementor_editor_icon_enqueue_scripts' ] );
        add_action( 'admin_notices', [ $this, 'tahefobu_header_footer_builder_for_elementor_admin_notice_missing_components' ] );

        // Widget category
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_widgets_category' ] );

        // widgets = style + script
        add_action( 'elementor/widgets/register', [ $this, 'register_new_hf_widgets' ] );
        add_action( 'wp_enqueue_scripts', 'tahefobu_register_assets' );
        add_action( 'elementor/frontend/before_enqueue_scripts', 'tahefobu_register_assets' );
    }
    
    /**
     * Define Plugin Constants
     * @since 1.0.0
     */
    private function define_constants() {
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL', trailingslashit( plugins_url( '/', __FILE__ ) ) );
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION', '1.3.3' );
    }

    /**
     * Enqueue Scripts & Styles
     * Only loads on pages where a header template is active to avoid
     * unnecessary asset loading on every frontend page.
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_enqueue_scripts_styles() {
        // Enqueue only when a header or footer will actually render on this page.
        $header_will_render = ! empty( $GLOBALS['tahefobu_header_will_render'] );
        $footer_will_render = ! empty( $GLOBALS['tahefobu_footer_rendered'] );

        if ( ! $header_will_render && ! $footer_will_render ) {
            return;
        }

        // Ensure the widget base styles are registered before enqueuing them. The
        // register hook runs on the same `wp_enqueue_scripts` action but is added
        // after this callback, so call it directly to guarantee registration order.
        if ( function_exists( 'tahefobu_register_assets' ) ) {
            tahefobu_register_assets();
        }

        // Enqueue only the base stylesheets for widgets actually present in the
        // matched header/footer template (early, into <head>) so the widgets are
        // never painted as an unstyled list — the "menu without CSS" flash happens
        // when these are enqueued too late (during wp_body_open / wp_footer
        // Elementor rendering). Loading only what is used keeps pages fast.
        $this->enqueue_matched_template_styles();

        if ( ! $header_will_render ) {
            return;
        }

        // turbo header css (registered in tahefobu_register_assets())
        wp_enqueue_style( 'tahefobu-header-style' );

        // turbo header js
        wp_enqueue_script(
            'tahefobu-header-behavior',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/turbo-header-behavior.js',
            [ 'jquery' ],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Enqueue the base styles for the widgets used by the matched header/footer.
     *
     * Loads only the stylesheets for widget types actually present in the
     * template's Elementor data, falling back to all widget styles when the
     * template cannot be analyzed (so the header/footer never paints unstyled).
     *
     * @since 1.3.3
     */
    private function enqueue_matched_template_styles() {
        $map = function_exists( 'tahefobu_widget_asset_map' ) ? tahefobu_widget_asset_map() : [];

        $widget_types = [];
        if ( function_exists( 'tahefobu_get_template_widget_types' ) ) {
            foreach ( [ 'tahefobu_header_template_id', 'tahefobu_footer_template_id' ] as $global_key ) {
                if ( empty( $GLOBALS[ $global_key ] ) ) {
                    continue;
                }
                $widget_types = array_merge( $widget_types, tahefobu_get_template_widget_types( $GLOBALS[ $global_key ] ) );
            }
        }
        $widget_types = array_values( array_unique( $widget_types ) );

        // Safety net: if no widget types could be determined, load every widget
        // style so the header/footer never renders as an unstyled list.
        if ( empty( $widget_types ) ) {
            $widget_types = array_keys( $map );
        }

        $handles = [];
        foreach ( $widget_types as $type ) {
            if ( ! empty( $map[ $type ] ) ) {
                $handles[] = $map[ $type ];
            }
        }
        // Font Awesome loads only when the Mega Menu widget is present: it is a
        // registered dependency of tahefobu-mega-menu-style, so enqueuing that
        // style pulls it in automatically.

        foreach ( array_unique( $handles ) as $handle ) {
            if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
                wp_enqueue_style( $handle );
            }
        }
    }

    /**
     * Enqueue Styles For Widget Icon
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_editor_icon_enqueue_scripts() {
        wp_enqueue_style(
            'tahefobu-editor-icon',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/editor-warning.css',
            [],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION,
            'all'
        );
    }

    /**
     * Load Text Domain for Translations
     * @since 1.0.0
     */
    // public function tahefobu_header_footer_builder_for_elementor_load_textdomain() {
    //     load_plugin_textdomain( 'header-footer-builder-for-elementor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    // }

    /**
     * Initialize the plugin
     * @since 1.0.0
     */
    public function init() {
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'tahefobu_header_footer_builder_for_elementor_admin_notice_missing_main_plugin' ] );
            return;
        }

        if ( ! version_compare( ELEMENTOR_VERSION, self::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_ELEMENTOR_VERSION, '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'tahefobu_header_footer_builder_for_elementor_admin_notice_minimum_elementor_version' ] );
            return;
        }

        if ( ! version_compare( PHP_VERSION, self::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_PHP_VERSION, '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'tahefobu_header_footer_builder_for_elementor_admin_notice_minimum_php_version' ] );
            return;
        }
        // Auto-append the preview nonce for your CPTs (prevents broken preview)
        add_filter( 'elementor/document/urls/preview', function( $url, $document ) {
            $post_id = 0;
            if ( method_exists( $document, 'get_main_id' ) ) {
                $post_id = (int) $document->get_main_id();
            }
            if ( ! $post_id && method_exists( $document, 'get_id' ) ) {
                $post_id = (int) $document->get_id();
            }
            if ( ! $post_id ) {
                return $url;
            }

            $pt = get_post_type( $post_id );
            if ( in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true ) ) {
                $url = add_query_arg(
                    'tahefobu_nonce',
                    wp_create_nonce( 'tahefobu_preview_' . $post_id ),
                    $url
                );
            }
            return $url;
        }, 10, 2 );

        // Load header and footer template functionality
        $this->load_header_footer_templates();

    }

    /**
     * Load Header and Footer Template Files
     * @since 1.0.0
     */
    private function load_header_footer_templates() {
        $template_files = [
            'header-footer-template/header-builder/turbo-header-template.php',
            'header-footer-template/header-builder/turbo-header-render.php',
            'header-footer-template/footer-builder/turbo-footer-template.php',
            'header-footer-template/footer-builder/turbo-footer-render.php',
            'header-footer-template/header-footer-menu/header-footer-menu.php',
        ];

        foreach ( $template_files as $template_file ) {
            $this->include_plugin_component( $template_file );
        }

        // Note: helper.php is already loaded in __construct() — no need to load it again here.


        // Ensure Elementor CSS for the matched Header is enqueued in <head> to avoid FOUC
       add_action( 'wp_enqueue_scripts', function () {
            // Register a base stylesheet (can be empty if you don’t have a file)
            wp_register_style(
                'tahefobu-frontend',
                false, // no file, just for inline use
                [],
                TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
            );
            wp_enqueue_style( 'tahefobu-frontend' );

            // Skip the opacity gate inside the Elementor editor preview so the header is immediately visible.
            $is_elementor_preview = ( isset( $_GET['elementor-preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( ! $is_elementor_preview ) {
                // Fail-safe opacity gate: the header is hidden by default and revealed by JS
                // (turbo-header-behavior.js) to avoid an unstyled flash. To guarantee the header
                // is never invisible when JS is unavailable, the hide rule is scoped to
                // `html.tahefobu-js` (a class only added by our inline script) and a
                // <noscript> override forces full visibility without JavaScript.
                $dynamic_css = 'html.tahefobu-js #tahefobu-header { opacity: 0; transform: none; pointer-events: none; } html.tahefobu-js #tahefobu-header.tahefobu-ready { opacity: 1; pointer-events: auto; transition: opacity .25s linear; }';
                wp_add_inline_style( 'tahefobu-frontend', $dynamic_css );
            }
        }, 1 );

        // Add the `tahefobu-js` class to <html> as early as possible. Without JS this
        // never runs, so the opacity gate above never applies and the header stays visible.
        add_action( 'wp_head', function () {
            if ( empty( $GLOBALS['tahefobu_header_will_render'] ) ) {
                return;
            }
            $inline = 'document.documentElement.classList.add("tahefobu-js");';
            if ( ! wp_script_is( 'tahefobu-js-detection', 'registered' ) ) {
                wp_register_script( 'tahefobu-js-detection', false, [], TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION, false );
            }
            if ( ! wp_script_is( 'tahefobu-js-detection', 'enqueued' ) ) {
                wp_enqueue_script( 'tahefobu-js-detection' );
            }
            wp_add_inline_script( 'tahefobu-js-detection', $inline );
        }, 1 );


        // Ensure Elementor preview has the_content() for our CPTs on any theme
        add_filter( 'template_include', function ( $template ) {

            // Elementor preview handling — must be nonce + caps gated
            if ( isset( $_GET['elementor-preview'] ) ) {
                $raw_id = filter_input( INPUT_GET, 'elementor-preview', FILTER_SANITIZE_NUMBER_INT );
                $pid    = absint( $raw_id );
                $nonce  = filter_input( INPUT_GET, 'tahefobu_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

                // Fail early if nonce missing/invalid
                if ( ! $pid || ! $nonce || ! wp_verify_nonce( $nonce, 'tahefobu_preview_' . $pid ) ) {
                    return $template;
                }

                // Capability check (nonces aren’t auth)
                if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $pid ) ) {
                    return $template;
                }

                $pt = get_post_type( $pid );
                if ( in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true ) ) {
                    return ( 'tahefobu_header' === $pt )
                        ? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'templates/single-tahefobu_header_template.php'
                        : TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'templates/single-tahefobu_footer_template.php';
                }
            }

            // Normal singular views (safe)
            if ( is_singular( 'tahefobu_header' ) ) {
                return TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'templates/single-tahefobu_header_template.php';
            }
            if ( is_singular( 'tahefobu_footer' ) ) {
                return TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'templates/single-tahefobu_footer_template.php';
            }

            return $template;
        }, 99 );
    }

     /**
     * Admin Notice: Elementor not installed/activated
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_admin_notice_missing_main_plugin() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            wp_kses_post( sprintf(
                /* translators: 1: Plugin name (Header Footer Builder), 2: Dependency name (Elementor) */
                esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'header-footer-builder-for-elementor' ),
                '<strong>' . esc_html__( 'Turbo Header Footer Builder For Elementor', 'header-footer-builder-for-elementor' ) . '</strong>',
                '<strong>' . esc_html__( 'Elementor', 'header-footer-builder-for-elementor' ) . '</strong>'
            ) )
        );
    }

    /**
     * Admin Notice for Minimum Elementor Version
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_admin_notice_minimum_elementor_version() {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                wp_kses_post( sprintf(
                    /* translators: 1: Plugin name (Header Footer Builder), 2: Dependency name (Elementor), 3: Minimum required Elementor version */
                    esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'header-footer-builder-for-elementor' ),
                    '<strong>' . esc_html__( 'Turbo Header Footer Builder For Elementor', 'header-footer-builder-for-elementor' ) . '</strong>',
                    '<strong>' . esc_html__( 'Elementor', 'header-footer-builder-for-elementor' ) . '</strong>',
                    esc_html( self::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_ELEMENTOR_VERSION )
                ) )
            );
        }

   /**
     * Admin Notice for Minimum PHP Version
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_admin_notice_minimum_php_version() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            wp_kses_post( sprintf(
                /* translators: 1: Plugin name (Header Footer Builder), 2: Software name (PHP), 3: Minimum required PHP version */
                esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'header-footer-builder-for-elementor' ),
                '<strong>' . esc_html__( 'Turbo Header Footer Builder For Elementor', 'header-footer-builder-for-elementor' ) . '</strong>',
                '<strong>' . esc_html__( 'PHP', 'header-footer-builder-for-elementor' ) . '</strong>',
                esc_html( self::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_PHP_VERSION )
            ) )
        );
    }

    // category register//
    public function register_widgets_category( $elements_manager ) {

        $elements_manager->add_category(
            'tahefobu-hf-widgets',
            [
                'title' => __( 'Turbo H&F Builder', 'header-footer-builder-for-elementor' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    public function register_new_hf_widgets( $widgets_manager ) {

        $new_widgets = [
            'navigation-menu-hf.php',
            'icon-button-hf.php',
            'top-bar-hf.php',
            'copy-right-hf.php',
            'site-logo-hf.php',
            'mega-menu-hf.php',
        ];

        foreach ( $new_widgets as $file ) {
            $this->include_plugin_component( 'widgets/' . $file );
        }

        // Register one by one
        if ( class_exists( 'TAHEFOBU_Navigation_Menu' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Navigation_Menu() );
        }

        if ( class_exists( 'TAHEFOBU_Icon_Button' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Icon_Button() );
        }

        if ( class_exists( 'TAHEFOBU_Top_Bar' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Top_Bar() );
        }

        if ( class_exists( 'TAHEFOBU_Copy_Right' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Copy_Right() );
        }

        if ( class_exists( 'TAHEFOBU_Site_Logo' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Site_Logo() );
        }

        if ( class_exists( 'TAHEFOBU_Mega_Menu_Widget' ) ) {
            $widgets_manager->register( new \TAHEFOBU_Mega_Menu_Widget() );
        }
    }

    private function include_plugin_component( $relative_path ) {
        $absolute_path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . $relative_path;

        if ( ! is_readable( $absolute_path ) ) {
            $this->skipped_components[] = $relative_path;
            return false;
        }

        require_once $absolute_path;
        return true;
    }

    public function tahefobu_header_footer_builder_for_elementor_admin_notice_missing_components() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( empty( $this->skipped_components ) ) {
            return;
        }

        $skipped_components = array_unique( $this->skipped_components );
        $component_list     = implode( ', ', array_map( 'esc_html', $skipped_components ) );

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            wp_kses_post(
                sprintf(
                    /* translators: %s: list of skipped plugin components */
                    __( 'Turbo Header Footer Builder could not load the following components: %s. The plugin will continue without them.', 'header-footer-builder-for-elementor' ),
                    '<code>' . esc_html( $component_list ) . '</code>'
                )
            )
        );
    }

}

/**
 * Recommend Turbo Addons if Elementor Pro is not active
 */
tahefobu_require_component( 'includes/class-hfb-recommend-turbo-addons.php' );

/**
 * On plugin activation — set a flag so we can redirect to our dashboard.
 * Uses register_activation_hook (runs before headers are sent, so we use
 * a transient and redirect on the next admin_init).
 */
function tahefobu_plugin_activate() {
    set_transient( 'tahefobu_activation_redirect', true, 30 );
}
register_activation_hook( __FILE__, 'tahefobu_plugin_activate' );

/**
 * DB version / upgrade routine foundation.
 *
 * Stores a `tahefobu_db_version` option and compares it against the current
 * constant on every load. When a new version ships, we run upgrade steps (for
 * now: flush the template-meta transients so cached matching data cannot carry
 * a stale shape into the new release) and record the new version.
 *
 * This gives future releases a safe, standard place to run data migrations
 * without ever needing to touch saved post meta in place.
 */
add_action( 'plugins_loaded', 'tahefobu_maybe_run_upgrade_routine' );
function tahefobu_maybe_run_upgrade_routine() {
    // Reference the class constant explicitly — bare-name access would be an
    // undefined-constant fatal and `defined()` cannot see class constants.
    if ( ! defined( 'TAHEFOBU_Header_Footer_Builder_For_Elementor::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_DB_VERSION' ) ) {
        return;
    }

    $current = (string) get_option( 'tahefobu_db_version', '' );
    $target  = TAHEFOBU_Header_Footer_Builder_For_Elementor::TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_DB_VERSION;

    if ( version_compare( $current, $target, '>=' ) ) {
        return;
    }

    // New release → drop cached template meta so matchers rebuild with current
    // condition semantics. Safe on every load; a no-op if nothing is cached.
    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    /**
     * Runs once per upgrade so plugins/theme code can migrate data safely.
     *
     * @param string $previous Previous stored DB version, or '' on first run.
     * @param string $target   Version we are upgrading to.
     */
    do_action( 'tahefobu_upgrade', $current, $target );

    update_option( 'tahefobu_db_version', $target, false );
}

/**
 * Redirect to our dashboard after activation.
 * Skips bulk-activation (multiple plugins activated at once).
 */
add_action( 'admin_init', function () {
    if ( ! get_transient( 'tahefobu_activation_redirect' ) ) {
        return;
    }
    delete_transient( 'tahefobu_activation_redirect' );

    // Don't redirect during bulk activation
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param check, no data written
    if ( isset( $_GET['activate-multi'] ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=tahefobu_templates' ) );
    exit;
} );

/**
 * Redirect the old CPT list pages to our dashboard.
 * Users who bookmarked edit.php?post_type=tahefobu_header will land on the dashboard.
 */
add_action( 'admin_init', function () {
    if ( ! is_admin() || wp_doing_ajax() ) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param, used only for navigation redirect
    $screen_id = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
    $base      = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';

    // Only intercept the list table pages (edit.php), not post.php (Elementor editor)
    if ( $base === 'edit.php'
        && in_array( $screen_id, [ 'tahefobu_header', 'tahefobu_footer' ], true )
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param check, no data written
        && ! isset( $_GET['page'] )
    ) {
        wp_safe_redirect( admin_url( 'admin.php?page=tahefobu_templates' ) );
        exit;
    }
} );


/**
 * Initializes the Plugin
 * @since 1.0.0
 */
/**
 * Initializes the Plugin only if Turbo Addons Pro is NOT active
 */

function tahefobu_header_footer_builder_for_elementor() {

    return TAHEFOBU_Header_Footer_Builder_For_Elementor::instance();
}

tahefobu_header_footer_builder_for_elementor();

