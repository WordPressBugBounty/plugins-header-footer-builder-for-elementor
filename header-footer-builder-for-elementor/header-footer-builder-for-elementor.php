<?php
/**
 * Plugin Name: Header Footer Builder for Elementor
 * Plugin URI: https://wp-turbo.com/header-footer-builder-for-elementor/
 * Description: Header Footer Builder for Elementor & WooCommerce. Easy, customizable plugin for headers/footers with display rules, sticky header & include/exclude.
 * Version: 1.1.5
 * Requires Plugins: elementor
 * Author: turbo addons 
 * Author URI: https://wp-turbo.com/
 * License: GPLv3
 * License URI: https://opensource.org/licenses/GPL-3.0
 * Text Domain: header-footer-builder-for-elementor
 * Elementor tested up to: 4.0.2
 * Elementor Pro tested up to: 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// wp-pulse integration
if ( ! class_exists( 'WPPulse_SDK' ) ) {
    require_once __DIR__ . '/wppulse/wppulse-plugin-analytics-engine-sdk.php';
}

    // Fetch plugin data automatically
    $tahefobu_plugin_data = get_file_data( __FILE__, [
        'Name'       => 'Plugin Name',
        'Version'    => 'Version',
        'TextDomain' => 'Text Domain',
    ] );

    $tahefobu_plugin_slug = dirname( plugin_basename( __FILE__ ) );

    // Initialize SDK
    if ( class_exists( 'WPPulse_SDK' ) ) {
        WPPulse_SDK::init( __FILE__, [
            'name'     => $tahefobu_plugin_data['Name'],
            'slug'     => $tahefobu_plugin_slug,
            'version'  => $tahefobu_plugin_data['Version'],
            'endpoint' => 'https://wp-turbo.com/wp-json/wppulse/v1/collect',
        ] );
    }


/**
 * Main Plugin Class
 * @since 1.0.0
 */
final class TAHEFOBU_Header_Footer_Builder_For_Elementor {
    const TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_ELEMENTOR_VERSION = '3.0.0';
    const TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_MIN_PHP_VERSION = '7.4';
    
    private static $_instance = null;

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
        // Define constants first
        $this->define_constants();
        
        // Check for version upgrade and handle migration
        $this->handle_version_upgrade();
        
        // Initialize performance improvements
        $this->init_performance_optimizations();
        
        if ( ! function_exists( 'hfbfe_fs' ) ) {
            // Create a helper function for easy SDK access.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Freemius SDK function
            function hfbfe_fs() {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Freemius SDK variable
                global $hfbfe_fs;

                if ( ! isset( $hfbfe_fs ) ) {
                    // Include Freemius SDK.
                    require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

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
            
            // Optional: Add WordPress.org compliant opt-in message
            // hfbfe_fs()->add_filter('connect_message', 'hfbfe_custom_connect_message', 10, 6);
            // hfbfe_fs()->add_filter('connect_message_on_update', 'hfbfe_custom_connect_message_on_update', 10, 6);
            
            // function hfbfe_custom_connect_message($message, $user_first_name, $product_title, $user_login, $site_link, $freemius_link) {
            //     return sprintf(
            //         __( 'Hey %1$s', 'header-footer-builder-for-elementor' ) . ',<br>' .
            //         __( 'Never miss an important update! Opt-in to receive security & feature updates, educational content, and occasional deals.', 'header-footer-builder-for-elementor' ) . '<br>' .
            //         __( 'If you skip this, that\'s okay! %2$s will still work just fine.', 'header-footer-builder-for-elementor' ),
            //         $user_first_name,
            //         '<b>' . $product_title . '</b>'
            //     );
            // }
            
            // function hfbfe_custom_connect_message_on_update($message, $user_first_name, $product_title, $user_login, $site_link, $freemius_link) {
            //     return sprintf(
            //         __( 'Hey %1$s', 'header-footer-builder-for-elementor' ) . ',<br>' .
            //         __( 'Please help us improve %2$s by allowing tracking of usage data.', 'header-footer-builder-for-elementor' ) . '<br>' .
            //         __( 'This will help us make better decisions about future features.', 'header-footer-builder-for-elementor' ),
            //         $user_first_name,
            //         '<b>' . $product_title . '</b>'
            //     );
            // }
            
            // Signal that SDK was initiated.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Freemius SDK hook
            do_action( 'hfbfe_fs_loaded' );
        }
        include_once plugin_dir_path(__FILE__) . 'helper/helper.php';
        add_action( 'wp_enqueue_scripts', [ $this, 'tahefobu_header_footer_builder_for_elementor_enqueue_scripts_styles' ] );
        add_action( 'init', [ $this, 'tahefobu_header_footer_builder_for_elementor_load_textdomain' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'tahefobu_header_footer_builder_for_elementor_editor_icon_enqueue_scripts' ] );
       
       // Widget category
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_widgets_category' ] );
       
        // widgets = style + script//
        add_action( 'elementor/widgets/register', [ $this, 'register_new_hf_widgets' ] );
        add_action( 'wp_enqueue_scripts', 'tahefobu_register_assets' );
        add_action( 'elementor/frontend/before_enqueue_scripts', 'tahefobu_register_assets' );
    }
    
    /**
     * Handle version upgrade and migration
     * @since 1.1.5
     */
    private function handle_version_upgrade() {
        $current_version = get_option('tahefobu_plugin_version', '1.0.0');
        $new_version = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION;
        
        if (version_compare($current_version, $new_version, '<')) {
            // Clear any existing caches on upgrade
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Clear plugin-specific caches
            wp_cache_delete('tahefobu_template_count', 'tahefobu');
            
            // Update version
            update_option('tahefobu_plugin_version', $new_version);
        }
    }

    /**
     * Initialize Performance Optimizations (Safe Mode)
     * @since 1.1.5
     */
    private function init_performance_optimizations() {
        // Only load performance improvements if the directory exists
        $performance_dir = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'performance-improvements/';
        
        if (!is_dir($performance_dir)) {
            // Performance improvements not installed yet
            return;
        }
        
        // Load only the database optimization for now (safest approach)
        $file_path = $performance_dir . 'query-optimization.php';
        if (file_exists($file_path)) {
            try {
                require_once $file_path;
                
                if (class_exists('TAHEFOBU_Performance_DB')) {
                    TAHEFOBU_Performance_DB::init();
                }
            } catch (Exception $e) {
                // Silently fail - don't break the plugin
                return;
            }
        }
        
        // Load rollback handler for emergency situations
        $rollback_path = $performance_dir . 'rollback-handler.php';
        if (file_exists($rollback_path)) {
            require_once $rollback_path;
        }
    }

    /**
     * Define Plugin Constants
     * @since 1.0.0
     */
    private function define_constants() {
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL', trailingslashit( plugins_url( '/', __FILE__ ) ) );
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
        define( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION', '1.1.5' );
    }

    /**
     * Enqueue Scripts & Styles (Simplified for debugging)
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_enqueue_scripts_styles() {   
        // Skip loading in admin or AJAX
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // For now, always load header assets to debug the issue
        // TODO: Re-enable conditional loading after fixing the functionality
        
        // Check if minified version exists, otherwise use regular version
        $css_file = 'turbo-header-style.css';
        $js_file = 'turbo-header-behavior.js';
        
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
            // Use regular versions in debug mode
        } else {
            // Check if minified versions exist
            $min_css_path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'assets/css/turbo-header-style.min.css';
            $min_js_path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'assets/js/turbo-header-behavior.min.js';
            
            if (file_exists($min_css_path)) {
                $css_file = 'turbo-header-style.min.css';
            }
            if (file_exists($min_js_path)) {
                $js_file = 'turbo-header-behavior.min.js';
            }
        }
        
        // Always load header assets for debugging
        wp_enqueue_style( 
            'tahefobu-header-style', 
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/' . $css_file, 
            [], 
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION, 
            'all' 
        );
        
        wp_enqueue_script( 
            'tahefobu-header-behavior', 
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/' . $js_file, 
            ['jquery'], 
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION, 
            true 
        );
        
        // Always register widget assets
        $this->register_widget_assets();
    }
    
    /**
     * Check if current page has a header template
     * @since 1.1.5
     */
    private function page_has_header_template() {
        // Use the global variable set by template_redirect hook for better performance
        if (isset($GLOBALS['tahefobu_header_will_render']) && $GLOBALS['tahefobu_header_will_render']) {
            return true;
        }
        
        // Fallback: Check if header template functionality is loaded
        if (!function_exists('tahefobu_get_matching_header_template_id')) {
            $header_template_path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/header-builder/turbo-header-template.php';
            if (file_exists($header_template_path)) {
                require_once $header_template_path;
            }
        }
        
        // Get matching header template
        if (function_exists('tahefobu_get_matching_header_template_id')) {
            $header_id = tahefobu_get_matching_header_template_id();
            return !empty($header_id);
        }
        
        return false;
    }
    
    /**
     * Check if current page has a footer template
     * @since 1.1.5
     */
    private function page_has_footer_template() {
        // Cache the result to avoid multiple database queries
        static $has_footer = null;
        
        if ($has_footer !== null) {
            return $has_footer;
        }
        
        // Check if footer template functionality is loaded
        if (!function_exists('tahefobu_get_matching_footer_template_id')) {
            $footer_template_path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/footer-builder/turbo-footer-template.php';
            if (file_exists($footer_template_path)) {
                require_once $footer_template_path;
            }
        }
        
        // Get matching footer template
        if (function_exists('tahefobu_get_matching_footer_template_id')) {
            $footer_id = tahefobu_get_matching_footer_template_id();
            $has_footer = !empty($footer_id);
        } else {
            $has_footer = false;
        }
        
        return $has_footer;
    }
    
    /**
     * Register widget assets when templates exist
     * @since 1.1.5
     */
    private function register_widget_assets() {
        // Register navigation menu assets (most commonly used)
        wp_register_style(
            'tahefobu-navigation-menu-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/navigation-menu-hf.css',
            [],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
        );
        
        wp_register_script(
            'tahefobu-navigation-menu-script',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/navigation-menu-hf.js',
            ['jquery'],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION,
            true
        );
        
        // Register other widget assets
        wp_register_style(
            'tahefobu-icon-button-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/icon-button-hf.css',
            [],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
        );
        
        wp_register_style(
            'tahefobu-top-bar-widgets-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/top-bar-widgets-hf.css',
            [],
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
        );
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
        filemtime( TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'assets/css/editor-warning.css' ),
        'all'
    );
}

    /**
     * Load Text Domain for Translations
     * @since 1.0.0
     */
    public function tahefobu_header_footer_builder_for_elementor_load_textdomain() {
        load_plugin_textdomain( 'header-footer-builder-for-elementor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

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
        
        // Add cache clearing hooks
        add_action('save_post', [$this, 'clear_template_cache']);
        add_action('delete_post', [$this, 'clear_template_cache']);

    }
    
    /**
     * Clear template cache when templates are saved/deleted
     * @since 1.1.5
     */
    public function clear_template_cache($post_id) {
        $post_type = get_post_type($post_id);
        
        if (in_array($post_type, ['tahefobu_header', 'tahefobu_footer'])) {
            // Clear all template-related caches
            wp_cache_flush_group('tahefobu');
            wp_cache_flush_group('tahefobu_templates');
            
            // Clear specific cache keys
            wp_cache_delete('tahefobu_template_count', 'tahefobu');
        }
    }

    /**
     * Load Header and Footer Template Files
     * @since 1.0.0
     */
    private function load_header_footer_templates() {
        // Load header template functionality
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/header-builder/turbo-header-template.php';
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/header-builder/turbo-header-render.php';
        
        // Load footer template functionality
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/footer-builder/turbo-footer-template.php';
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/footer-builder/turbo-footer-render.php';
        
        // Load admin menu functionality
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/header-footer-menu/header-footer-menu.php';

        //helper allow wp_kses-post
        require_once TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'helper/helper.php';


        // Ensure Elementor CSS for the matched Header is enqueued in <head> to avoid FOUC
       add_action( 'wp_enqueue_scripts', function () {
            // Register a base stylesheet (can be empty if you don’t have a file)
            wp_register_style(
                'tahefobu-frontend',
                false, // no file, just for inline use
                [],
                '1.1.5'
            );
            wp_enqueue_style( 'tahefobu-frontend' );

            // Add your dynamic CSS inline
            // Start header visually hidden (opacity 0) but present in layout; apply a very short fade when ready.
            $dynamic_css = '#tahefobu-header { opacity: 0; transform: none; pointer-events: none; } #tahefobu-header.tahefobu-ready { opacity: 1; pointer-events: auto; transition: opacity .25s linear; }';
            wp_add_inline_style( 'tahefobu-frontend', $dynamic_css );
        }, 1 );


        // Ensure Elementor preview has the_content() for our CPTs on any theme
        add_filter( 'template_include', function ( $template ) {

            // Elementor preview handling — must be nonce + caps gated
            if ( isset( $_GET['elementor-preview'] ) ) {
                // Sanitize and validate input using WordPress functions
                $raw_id = isset( $_GET['elementor-preview'] ) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
                $pid    = absint( $raw_id );
                $nonce  = isset( $_GET['tahefobu_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['tahefobu_nonce'] ) ) : '';

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
            'navigation-menu-hf.php' => 'TAHEFOBU_Navigation_Menu',
            'icon-button-hf.php' => 'TAHEFOBU_Icon_Button',
            'top-bar-hf.php' => 'TAHEFOBU_Top_Bar',
            'copy-right-hf.php' => 'TAHEFOBU_Copy_Right',
            'site-logo-hf.php' => 'TAHEFOBU_Site_Logo',
        ];

        foreach ( $new_widgets as $file => $class ) {
            $path = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'widgets/' . $file;

            if ( file_exists( $path ) ) {
                require_once $path;
                
                if ( class_exists( $class ) ) {
                    $widgets_manager->register( new $class() );
                }
            }
        }
    }

}

/**
 * Recommend Turbo Addons if Elementor Pro is not active
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hfb-recommend-turbo-addons.php';


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

