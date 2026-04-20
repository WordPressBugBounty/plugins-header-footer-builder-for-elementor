<?php
/**
 * Rollback Handler for Performance Optimizations
 * Provides emergency rollback if issues occur
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TAHEFOBU_Rollback_Handler {
    
    /**
     * Initialize rollback handler
     */
    public static function init() {
        // Add rollback option to admin menu
        add_action('admin_menu', [__CLASS__, 'add_rollback_menu']);
        
        // Handle rollback request
        add_action('admin_post_tahefobu_rollback', [__CLASS__, 'handle_rollback']);
        
        // Handle performance settings
        add_action('admin_post_tahefobu_performance_settings', [__CLASS__, 'handle_performance_settings']);
        
        // Check for performance issues
        add_action('wp_footer', [__CLASS__, 'check_performance_issues']);
    }
    
    /**
     * Add rollback option to admin menu
     */
    public static function add_rollback_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'edit.php?post_type=tahefobu_header',
            'Performance Settings',
            'Performance',
            'manage_options',
            'tahefobu-performance',
            [__CLASS__, 'performance_settings_page']
        );
    }
    
    /**
     * Performance settings page
     */
    public static function performance_settings_page() {
        // Only show notices if user can manage options
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Show notices using transients (more secure)
        if (get_transient('tahefobu_settings_saved')) {
            delete_transient('tahefobu_settings_saved');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'header-footer-builder-for-elementor') . '</p></div>';
        }
        if (get_transient('tahefobu_rollback_complete')) {
            delete_transient('tahefobu_rollback_complete');
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Performance mode has been disabled.', 'header-footer-builder-for-elementor') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Header Footer Builder - Performance Settings', 'header-footer-builder-for-elementor'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php echo esc_html__('Performance Mode:', 'header-footer-builder-for-elementor'); ?></strong> 
                <?php echo self::is_performance_mode_active() ? esc_html__('Active', 'header-footer-builder-for-elementor') : esc_html__('Disabled', 'header-footer-builder-for-elementor'); ?></p>
            </div>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tahefobu_performance_settings'); ?>
                <input type="hidden" name="action" value="tahefobu_performance_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Performance Optimizations', 'header-footer-builder-for-elementor'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_performance" value="1" <?php checked(get_option('tahefobu_performance_enabled', 1)); ?>>
                                <?php echo esc_html__('Enable performance optimizations (database caching, optimized queries)', 'header-footer-builder-for-elementor'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Disable this if you experience any issues after the update.', 'header-footer-builder-for-elementor'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(esc_html__('Save Settings', 'header-footer-builder-for-elementor')); ?>
            </form>
            
            <hr>
            
            <h2><?php echo esc_html__('Emergency Actions', 'header-footer-builder-for-elementor'); ?></h2>
            <p><?php echo esc_html__('Use these options if you experience issues:', 'header-footer-builder-for-elementor'); ?></p>
            
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=tahefobu_flush_cache'), 'tahefobu_flush_cache')); ?>" 
                   class="button"><?php echo esc_html__('Clear All Caches', 'header-footer-builder-for-elementor'); ?></a>
                <span class="description"><?php echo esc_html__('Clear all plugin caches if you see stale content.', 'header-footer-builder-for-elementor'); ?></span>
            </p>
            
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=tahefobu_rollback'), 'tahefobu_rollback')); ?>" 
                   class="button button-secondary" 
                   onclick="return confirm('<?php echo esc_js(__('This will disable performance optimizations. Continue?', 'header-footer-builder-for-elementor')); ?>')">
                   <?php echo esc_html__('Disable Performance Mode', 'header-footer-builder-for-elementor'); ?>
                </a>
                <span class="description"><?php echo esc_html__('Disable performance optimizations if they cause issues.', 'header-footer-builder-for-elementor'); ?></span>
            </p>
        </div>
        <?php
    }
    
    /**
     * Handle performance settings form submission
     */
    public static function handle_performance_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tahefobu_performance_settings')) {
            wp_die('Nonce verification failed');
        }
        
        $enable_performance = isset($_POST['enable_performance']) ? 1 : 0;
        update_option('tahefobu_performance_enabled', $enable_performance);
        
        // Clear caches when settings change
        self::clear_all_cache();
        
        // Set transient for success message
        set_transient('tahefobu_settings_saved', 1, 30);
        
        wp_safe_redirect(esc_url_raw(admin_url('edit.php?post_type=tahefobu_header&page=tahefobu-performance')));
        exit;
    }
    
    /**
     * Clear all caches (static method for external access)
     */
    public static function clear_all_cache() {
        if (class_exists('TAHEFOBU_Performance_DB')) {
            TAHEFOBU_Performance_DB::clear_all_cache();
        } else {
            // Fallback cache clearing
            wp_cache_flush();
            delete_transient('tahefobu_template_count');
        }
    }
    
    /**
     * Handle rollback request
     */
    public static function handle_rollback() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'tahefobu_rollback')) {
            wp_die('Nonce verification failed');
        }
        
        // Disable performance mode
        update_option('tahefobu_performance_enabled', 0);
        
        // Clear all caches
        wp_cache_flush();
        delete_transient('tahefobu_template_count');
        
        // Set transient for rollback message
        set_transient('tahefobu_rollback_complete', 1, 30);
        
        wp_safe_redirect(esc_url_raw(admin_url('edit.php?post_type=tahefobu_header&page=tahefobu-performance')));
        exit;
    }
    
    /**
     * Check if performance mode is active
     */
    public static function is_performance_mode_active() {
        return get_option('tahefobu_performance_enabled', 1) == 1;
    }
    
    /**
     * Check for performance issues (basic monitoring)
     */
    public static function check_performance_issues() {
        if (!current_user_can('manage_options') || !self::is_performance_mode_active()) {
            return;
        }
        
        // Simple performance check - if page load is very slow, suggest rollback
        $start_time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime(true);
        $load_time = microtime(true) - $start_time;
        
        if ($load_time > 5) { // If page takes more than 5 seconds
            echo '<!-- TAHEFOBU Performance Warning: Slow page load detected (' . esc_html(round($load_time, 2)) . 's) -->';
        }
    }
}

// Initialize rollback handler
TAHEFOBU_Rollback_Handler::init();