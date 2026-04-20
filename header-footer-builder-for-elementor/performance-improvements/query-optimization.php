<?php
/**
 * Performance Improvement: Database Query Optimization & Caching
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TAHEFOBU_Performance_DB {
    
    private static $cache_group = 'tahefobu_templates';
    private static $cache_expiry = 12 * HOUR_IN_SECONDS; // 12 hours
    
    /**
     * Initialize database performance optimizations
     * @since 1.1.5
     */
    public static function init() {
        // Cache template queries
        add_action('save_post', [__CLASS__, 'clear_template_cache']);
        add_action('delete_post', [__CLASS__, 'clear_template_cache']);
        add_action('wp_trash_post', [__CLASS__, 'clear_template_cache']);
        add_action('untrash_post', [__CLASS__, 'clear_template_cache']);
        
        // Clear cache on plugin activation/deactivation
        add_action('activated_plugin', [__CLASS__, 'clear_all_cache']);
        add_action('deactivated_plugin', [__CLASS__, 'clear_all_cache']);
        
        // Optimize header/footer queries
        add_filter('tahefobu_get_matching_header', [__CLASS__, 'get_cached_header_template']);
        add_filter('tahefobu_get_matching_footer', [__CLASS__, 'get_cached_footer_template']);
        
        // Add cache flush option for admins
        add_action('admin_bar_menu', [__CLASS__, 'add_cache_flush_button'], 100);
    }
    
    /**
     * Clear all plugin caches (emergency function)
     * @since 1.1.5
     */
    public static function clear_all_cache() {
        wp_cache_flush_group('tahefobu');
        wp_cache_flush_group('tahefobu_templates');
        delete_transient('tahefobu_template_count');
        
        // Clear any persistent object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Add cache flush button to admin bar for debugging
     * @param WP_Admin_Bar $wp_admin_bar
     * @since 1.1.5
     */
    public static function add_cache_flush_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node([
            'id' => 'tahefobu-flush-cache',
            'title' => 'Flush HF Cache',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=tahefobu_flush_cache'), 'tahefobu_flush_cache'),
            'meta' => ['title' => 'Clear Header Footer Builder cache']
        ]);
    }
    
    /**
     * Get cached header template with optimized query
     * 
     * @return array|null Template data or null if not found
     * @since 1.1.5
     */
    public static function get_cached_header_template() {
        $page_id = get_queried_object_id();
        $cache_key = "header_template_{$page_id}";
        
        $cached = wp_cache_get($cache_key, self::$cache_group);
        if ($cached !== false) {
            return $cached;
        }
        
        // Optimized single query instead of multiple
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Optimized query with caching implemented
        $headers = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm1.meta_value as include_pages, pm2.meta_value as exclude_pages, 
                   pm3.meta_value as display_targets, pm4.meta_value as is_sticky, 
                   pm5.meta_value as has_animation
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tahefobu_include_pages'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tahefobu_exclude_pages'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_tahefobu_display_targets'
            LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_tahefobu_is_sticky'
            LEFT JOIN {$wpdb->postmeta} pm5 ON p.ID = pm5.post_id AND pm5.meta_key = '_tahefobu_has_animation'
            WHERE p.post_type = %s 
            AND p.post_status = %s
            ORDER BY p.menu_order ASC, p.post_date DESC
        ", 'tahefobu_header', 'publish'));
        $matched_header = null;
        
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $include = maybe_unserialize($header->include_pages) ?: [];
                $exclude = maybe_unserialize($header->exclude_pages) ?: [];
                $targets = maybe_unserialize($header->display_targets) ?: [];
                
                // Quick exclusion check
                if (in_array($page_id, $exclude)) continue;
                
                // Match logic (simplified for performance)
                if (self::matches_display_conditions($page_id, $include, $targets)) {
                    $matched_header = [
                        'id' => $header->ID,
                        'sticky' => $header->is_sticky,
                        'animation' => $header->has_animation
                    ];
                    break;
                }
            }
        }
        
        // Cache result
        wp_cache_set($cache_key, $matched_header, self::$cache_group, self::$cache_expiry);
        
        return $matched_header;
    }
    
    /**
     * Optimized display condition matching
     * 
     * @param int   $page_id Current page ID
     * @param array $include Array of included page IDs
     * @param array $targets Array of target conditions
     * @return bool Whether conditions match
     * @since 1.1.5
     */
    private static function matches_display_conditions($page_id, $include, $targets) {
        // Entire site
        if (in_array('entire_site', $targets)) return true;
        
        // Specific pages
        if (!empty($include) && in_array($page_id, $include)) return true;
        
        // Page type conditions
        if (is_page() && in_array('all_pages', $targets)) return true;
        if (is_single() && in_array('all_posts', $targets)) return true;
        if (is_home() && in_array('blog_page', $targets)) return true;
        
        // WooCommerce conditions (only check if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            if (is_shop() && in_array('woo_shop', $targets)) return true;
            if (is_product() && in_array('woo_product', $targets)) return true;
        }
        
        return false;
    }
    
    /**
     * Clear template cache when posts are saved/deleted
     * 
     * @param int $post_id The post ID being saved/deleted
     * @return void
     * @since 1.1.5
     */
    public static function clear_template_cache($post_id) {
        $post_type = get_post_type($post_id);
        
        if (in_array($post_type, ['tahefobu_header', 'tahefobu_footer'])) {
            wp_cache_flush_group(self::$cache_group);
            
            // Also clear object cache if using persistent caching
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            }
        }
    }
    
    /**
     * Preload critical templates on high-traffic pages
     */
    public static function preload_templates() {
        if (is_front_page() || is_home()) {
            // Preload homepage templates
            self::get_cached_header_template();
            self::get_cached_footer_template();
        }
    }
    
    /**
     * Get cached footer template (placeholder for future implementation)
     */
    public static function get_cached_footer_template() {
        // Placeholder for footer template caching
        return null;
    }
}

TAHEFOBU_Performance_DB::init();

// Handle cache flush request
add_action('admin_post_tahefobu_flush_cache', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'tahefobu_flush_cache')) {
        wp_die('Nonce verification failed');
    }
    
    TAHEFOBU_Performance_DB::clear_all_cache();
    
    // Set transient for success message
    set_transient('tahefobu_cache_flushed', 1, 30);
    
    wp_safe_redirect(esc_url_raw(wp_get_referer() ?: admin_url()));
    exit;
});

// Show cache flush notice using transients
add_action('admin_notices', function() {
    // Only show notice if user can manage options
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (get_transient('tahefobu_cache_flushed')) {
        delete_transient('tahefobu_cache_flushed');
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Header Footer Builder cache cleared successfully!', 'header-footer-builder-for-elementor') . '</p></div>';
    }
});