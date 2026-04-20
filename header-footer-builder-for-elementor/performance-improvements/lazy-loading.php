<?php
/**
 * Performance Improvement: Lazy Loading & Code Splitting
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

class TAHEFOBU_Performance_Lazy {
    
    private static $widgets_loaded = false;
    
    public static function init() {
        // Lazy load widgets only when needed
        add_action('elementor/widgets/register', [__CLASS__, 'lazy_register_widgets'], 5);
        
        // Defer non-critical scripts
        add_filter('script_loader_tag', [__CLASS__, 'defer_non_critical_scripts'], 10, 3);
        
        // Preload critical resources
        add_action('wp_head', [__CLASS__, 'preload_critical_resources'], 1);
        
        // Remove unused Elementor assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'optimize_elementor_assets'], 20);
    }
    
    /**
     * Only register widgets when Elementor is actually editing
     */
    public static function lazy_register_widgets($widgets_manager) {
        // Skip if not in editor and no templates on page
        if (!self::should_load_widgets()) {
            return;
        }
        
        self::$widgets_loaded = true;
        
        $widget_files = [
            'navigation-menu-hf.php' => 'TAHEFOBU_Navigation_Menu',
            'icon-button-hf.php' => 'TAHEFOBU_Icon_Button',
            'top-bar-hf.php' => 'TAHEFOBU_Top_Bar',
            'copy-right-hf.php' => 'TAHEFOBU_Copy_Right',
            'site-logo-hf.php' => 'TAHEFOBU_Site_Logo',
        ];
        
        $plugin_path = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH') ? 
                      TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_PATH : 
                      plugin_dir_path(__FILE__ . '/../');
        
        foreach ($widget_files as $file => $class) {
            $path = $plugin_path . 'widgets/' . $file;
            
            if (file_exists($path)) {
                require_once $path;
                
                if (class_exists($class)) {
                    $widgets_manager->register(new $class());
                }
            }
        }
    }
    
    /**
     * Determine if widgets should be loaded
     */
    private static function should_load_widgets() {
        // Always load in admin/editor
        if (is_admin() || (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->editor->is_edit_mode())) {
            return true;
        }
        
        // Check if page has header/footer templates
        $page_id = get_queried_object_id();
        $cache_key = "tahefobu_has_templates_{$page_id}";
        
        $has_templates = wp_cache_get($cache_key, 'tahefobu');
        if ($has_templates === false) {
            $has_templates = self::page_has_templates();
            wp_cache_set($cache_key, $has_templates, 'tahefobu', HOUR_IN_SECONDS);
        }
        
        return $has_templates;
    }
    
    /**
     * Check if current page has header/footer templates
     */
    private static function page_has_templates() {
        // Use WordPress functions instead of direct database queries when possible
        $headers = get_posts([
            'post_type' => 'tahefobu_header',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids'
        ]);
        
        $footers = get_posts([
            'post_type' => 'tahefobu_footer', 
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids'
        ]);
        
        return !empty($headers) || !empty($footers);
    }
    
    /**
     * Defer non-critical JavaScript
     */
    public static function defer_non_critical_scripts($tag, $handle, $src) {
        // Scripts to defer (not critical for initial render)
        $defer_scripts = [
            'tahefobu-navigation-menu-script',
            'tahefobu-header-behavior'
        ];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Preload critical resources
     */
    public static function preload_critical_resources() {
        // Only preload if templates exist on this page
        if (!self::should_load_widgets()) {
            return;
        }
        
        $plugin_url = defined('TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL') ? 
                     TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL : 
                     plugin_dir_url(__FILE__ . '/../');
        
        // Preload critical CSS
        echo '<link rel="preload" href="' . esc_url($plugin_url . 'assets/css/turbo-header-style.css') . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        
        // Preload critical fonts (if using custom fonts)
        echo '<link rel="preconnect" href="' . esc_url('https://fonts.googleapis.com') . '">' . "\n";
        echo '<link rel="preconnect" href="' . esc_url('https://fonts.gstatic.com') . '" crossorigin>' . "\n";
    }
    
    /**
     * Remove unused Elementor assets on pages without templates
     */
    public static function optimize_elementor_assets() {
        if (!self::should_load_widgets()) {
            // Remove Elementor frontend assets if no templates
            wp_dequeue_style('elementor-frontend');
            wp_dequeue_script('elementor-frontend');
            wp_dequeue_style('elementor-icons');
        }
    }
    
    /**
     * Critical CSS inlining for above-the-fold content
     */
    public static function inline_critical_css() {
        if (!self::should_load_widgets()) return;
        
        $critical_css = '#tahefobu-header{opacity:0;transform:none;pointer-events:none}#tahefobu-header.tahefobu-ready{opacity:1;pointer-events:auto;transition:opacity .25s linear}.ta-sticky-header{position:sticky;top:0;z-index:9999}.ta-header-spacer{display:none;width:100%}';
        
        // Properly escape CSS output
        echo '<style id="tahefobu-critical-css">' . esc_html($critical_css) . '</style>';
    }
}

TAHEFOBU_Performance_Lazy::init();