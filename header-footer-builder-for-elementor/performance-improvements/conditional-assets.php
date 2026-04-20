<?php
/**
 * Performance Improvement: Conditional Asset Loading
 * Only load CSS/JS when widgets are actually used
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if constants are defined
if (!defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH') || 
    !defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL')) {
    return;
}

class TAHEFOBU_Performance_Assets {
    
    private static $loaded_widgets = [];
    private static $page_has_header = false;
    private static $page_has_footer = false;
    
    public static function init() {
        // Check if header/footer exists on current page
        add_action('template_redirect', [__CLASS__, 'detect_templates'], 5);
        
        // Conditional asset loading
        add_action('wp_enqueue_scripts', [__CLASS__, 'conditional_enqueue'], 15);
        
        // Widget-specific asset loading
        add_action('elementor/widget/before_render_content', [__CLASS__, 'track_widget_usage']);
    }
    
    public static function detect_templates() {
        if (is_admin() || wp_doing_ajax()) return;
        
        // Cache template detection
        $cache_key = 'tahefobu_page_templates_' . get_queried_object_id();
        $cached = wp_cache_get($cache_key, 'tahefobu');
        
        if ($cached !== false) {
            self::$page_has_header = $cached['header'];
            self::$page_has_footer = $cached['footer'];
            return;
        }
        
        // Detect header
        $header_template_path = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH') ? 
                               TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH . 'header-footer-template/header-builder/turbo-header-template.php' :
                               '';
        
        if ($header_template_path && file_exists($header_template_path)) {
            require_once $header_template_path;
            if (function_exists('tahefobu_get_matching_header_template_id')) {
                self::$page_has_header = (bool) tahefobu_get_matching_header_template_id();
            }
        }
        
        // Detect footer (simplified logic without meta_query)
        $footers = get_posts([
            'post_type' => 'tahefobu_footer',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids'
        ]);
        
        // Simple check - if any footer exists, assume it might be used
        self::$page_has_footer = !empty($footers);
        
        // Cache for 1 hour
        wp_cache_set($cache_key, [
            'header' => self::$page_has_header,
            'footer' => self::$page_has_footer
        ], 'tahefobu', HOUR_IN_SECONDS);
    }
    
    public static function conditional_enqueue() {
        // Only load core assets if templates exist
        if (!self::$page_has_header && !self::$page_has_footer) {
            return;
        }
        
        // Load minified versions in production
        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        $plugin_url = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL') ? 
                     TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL : 
                     plugin_dir_url(__FILE__ . '/../');
        $plugin_version = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION') ? 
                         TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION : 
                         '1.1.4';
        
        if (self::$page_has_header) {
            wp_enqueue_style(
                'tahefobu-header-style',
                $plugin_url . "assets/css/turbo-header-style{$suffix}.css",
                [],
                $plugin_version
            );
            
            wp_enqueue_script(
                'tahefobu-header-behavior',
                $plugin_url . "assets/js/turbo-header-behavior{$suffix}.js",
                ['jquery'],
                $plugin_version,
                true
            );
        }
        
        // Always register widget assets when templates exist (they might be used)
        self::register_widget_assets();
    }
    
    /**
     * Register widget assets when templates exist
     */
    private static function register_widget_assets() {
        $plugin_url = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL') ? 
                     TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL : 
                     plugin_dir_url(__FILE__ . '/../');
        $plugin_version = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION') ? 
                         TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION : 
                         '1.1.4';
        
        // Register navigation menu assets (most commonly used)
        wp_register_style(
            'tahefobu-navigation-menu-style',
            $plugin_url . 'assets/css/navigation-menu-hf.css',
            [],
            $plugin_version
        );
        
        wp_register_script(
            'tahefobu-navigation-menu-script',
            $plugin_url . 'assets/js/navigation-menu-hf.js',
            ['jquery'],
            $plugin_version,
            true
        );
        
        // Register other widget assets
        wp_register_style(
            'tahefobu-icon-button-style',
            $plugin_url . 'assets/css/icon-button-hf.css',
            [],
            $plugin_version
        );
        
        wp_register_style(
            'tahefobu-top-bar-widgets-style',
            $plugin_url . 'assets/css/top-bar-widgets-hf.css',
            [],
            $plugin_version
        );
    }
    
    public static function track_widget_usage($widget) {
        $widget_name = $widget->get_name();
        
        // Track which widgets are used
        if (strpos($widget_name, 'tahefobu-') === 0) {
            self::$loaded_widgets[] = $widget_name;
            
            // Load widget-specific assets
            self::load_widget_assets($widget_name);
        }
    }
    
    private static function load_widget_assets($widget_name) {
        $asset_map = [
            'tahefobu-navigation-menu' => [
                'css' => 'navigation-menu-hf.css',
                'js' => 'navigation-menu-hf.js'
            ],
            'tahefobu-icon-button' => [
                'css' => 'icon-button-hf.css'
            ],
            'tahefobu-top-bar' => [
                'css' => 'top-bar-widgets-hf.css'
            ]
        ];
        
        if (isset($asset_map[$widget_name])) {
            $assets = $asset_map[$widget_name];
            $plugin_url = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL') ? 
                         TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL : 
                         plugin_dir_url(__FILE__ . '/../');
            $plugin_version = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION') ? 
                             TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION : 
                             '1.1.4';
            
            if (isset($assets['css'])) {
                wp_enqueue_style(
                    $widget_name . '-style',
                    $plugin_url . 'assets/css/' . $assets['css'],
                    [],
                    $plugin_version
                );
            }
            
            if (isset($assets['js'])) {
                wp_enqueue_script(
                    $widget_name . '-script',
                    $plugin_url . 'assets/js/' . $assets['js'],
                    ['jquery'],
                    $plugin_version,
                    true
                );
            }
        }
    }
}

// Initialize performance improvements
TAHEFOBU_Performance_Assets::init();