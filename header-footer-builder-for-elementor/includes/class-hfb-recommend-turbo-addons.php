<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Recommend Turbo Addons plugin if not active.
 */
class HFB_Recommend_Turbo_Addons {

    public function __construct() {
        add_action( 'admin_notices',          [ $this, 'show_recommendation_notice' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_notice_styles' ] );
        add_action( 'wp_ajax_hfb_dismiss_turbo_notice', [ $this, 'ajax_dismiss_notice' ] );
    }

    /* ── Check if Turbo Addons FREE is active ─────────────── */
    private function hfbfe_is_turbo_addons_free_version_active() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $active_plugins = get_option( 'active_plugins', [] );
        $all_plugins    = get_plugins();

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if (
                in_array( $plugin_file, $active_plugins, true ) &&
                isset( $plugin_data['Name'] ) &&
                $plugin_data['Name'] === 'Turbo Addons Elementor'
            ) {
                return true;
            }
        }
        return false;
    }

    /* ── Enqueue external CSS ──────────────────────────────── */
    public function enqueue_notice_styles() {
        if ( $this->hfbfe_is_turbo_addons_free_version_active() ) {
            return;
        }
        wp_enqueue_style(
            'hfb-recommendation-notice',
            plugins_url( 'assets/css/recomendation-noticeboard.css', dirname( __FILE__ ) ),
            [],
            defined( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION' )
                ? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
                : '1.0.0'
        );
    }

    /* ── AJAX: persist dismissal per user ─────────────────── */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'hfb_turbo_notice_dismiss', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'header-footer-builder-for-elementor' ) ], 403 );
        }

        update_user_meta( $user_id, 'hfb_turbo_notice_dismissed', '1' );
        wp_send_json_success();
    }

    /* ── Render notice HTML ────────────────────────────────── */
    public function show_recommendation_notice() {

        if ( $this->hfbfe_is_turbo_addons_free_version_active() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id && '1' === get_user_meta( $user_id, 'hfb_turbo_notice_dismissed', true ) ) {
            return;
        }

        $dismiss_nonce = wp_create_nonce( 'hfb_turbo_notice_dismiss' );

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $install_url = wp_nonce_url(
            self_admin_url( 'update.php?action=install-plugin&plugin=turbo-addons-elementor' ),
            'install-plugin_turbo-addons-elementor'
        );
        $activate_url = wp_nonce_url(
            self_admin_url( 'plugins.php?action=activate&plugin=turbo-addons-elementor%2Fturbo-addons-elementor.php' ),
            'activate-plugin_turbo-addons-elementor/turbo-addons-elementor.php'
        );
        $is_installed = file_exists( WP_PLUGIN_DIR . '/turbo-addons-elementor/turbo-addons-elementor.php' );
        $action_url   = $is_installed ? $activate_url : $install_url;
        $action_label = $is_installed
            ? esc_html__( '🗲 Activate Turbo Addons — Free', 'header-footer-builder-for-elementor' )
            : esc_html__( '🗲 Install Turbo Addons — Free', 'header-footer-builder-for-elementor' );
        $banner_src = esc_url( plugins_url( 'assets/images/turbo_recomend.webp', dirname( __FILE__ ) ) );
        ?>

        <div id="hfb-turbo-notice" class="notice is-dismissible">
            <div class="hfb-notice-inner">

                <div class="hfb-notice-stripe"></div>

                <div class="hfb-notice-body">

                    <div class="hfb-social-proof">
                        <h3 class="hfb-notice-heading"><?php esc_html_e( "Make your Elementor websites more modern and powerful", 'header-footer-builder-for-elementor' ); ?></h3>
                    </div>

                    <p class="hfb-notice-headline">
                        <?php esc_html_e( '90+ widgets, 200+ templates and powerful WooCommerce tools for Elementor. Right now!', 'header-footer-builder-for-elementor' ); ?>
                    </p>

                    <div class="hfb-notice-actions">
                        <a href="<?php echo esc_url( $action_url ); ?>" class="hfb-btn-primary">
                            <?php echo esc_html( $action_label ); ?>
                        </a>
                        <a href="https://turbo-addons.com/pricing/" target="_blank" rel="noopener noreferrer" class="hfb-btn-secondary">
                            <?php esc_html_e( 'Save 60% on Pro →', 'header-footer-builder-for-elementor' ); ?>
                        </a>
                    </div>

                </div>

                <div class="hfb-notice-image">
                    <img src="<?php echo $banner_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd ?>"
                         alt="<?php esc_attr_e( 'Turbo Addons Templates', 'header-footer-builder-for-elementor' ); ?>">
                </div>

            </div>
        </div>

        <script>
        ( function () {
            var notice = document.getElementById( 'hfb-turbo-notice' );
            if ( ! notice ) return;
            notice.addEventListener( 'click', function ( e ) {
                if ( e.target.closest( '.notice-dismiss' ) ) {
                    e.preventDefault();
                    notice.style.display = 'none';
                    if ( window.ajaxurl ) {
                        var body = new FormData();
                        body.append( 'action', 'hfb_dismiss_turbo_notice' );
                        body.append( 'nonce', <?php echo wp_json_encode( $dismiss_nonce ); ?> );
                        fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
                    }
                }
            } );
        } )();
        </script>
        <?php
    }
}

new HFB_Recommend_Turbo_Addons();
