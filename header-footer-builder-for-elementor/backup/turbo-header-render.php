<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_body_open', function () {
    if ( is_admin() || wp_doing_ajax() ){
        return;
    }

    // Avoid output while editing or previewing our CPTs in Elementor
    if ( is_singular( 'tahefobu_header' ) || is_singular( 'tahefobu_footer' ) ){
        return;
        }

    // Strict handling of Elementor preview param
    if ( defined( 'ELEMENTOR_VERSION' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
            $pid = get_the_ID();

            // Only enforce nonce/caps for our custom CPT previews
            if ( $pid && in_array( get_post_type( $pid ), [ 'tahefobu_header', 'tahefobu_footer' ], true ) ) {
                $nonce = isset( $_GET['tahefobu_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['tahefobu_nonce'] ) ) : '';

                // Fail early if nonce missing/invalid
                if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tahefobu_preview_' . $pid ) ) {
                    return;
                }

                // Enforce authorization
                if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $pid ) ) {
                    return;
                }
            }

            // Always skip rendering in preview
            return;
        }

    require_once plugin_dir_path( __FILE__ ) . 'turbo-header-template.php';
    if ( ! function_exists( 'tahefobu_get_matching_header_template_id' ) ) return;

    $header_template_id = tahefobu_get_matching_header_template_id();

    if ( $header_template_id
        && class_exists( '\Elementor\Plugin' )
        && get_post_type( $header_template_id ) === 'tahefobu_header'
    ) {
        $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $header_template_id );

        if ( ! empty( $content ) ) {
            $classes = [ 'turbo-header-template' ];

            $is_sticky     = get_post_meta( $header_template_id, '_tahefobu_is_sticky', true );
            $has_animation = get_post_meta( $header_template_id, '_tahefobu_has_animation', true );


            if ( ! empty( $is_sticky ) )     $classes[] = 'ta-sticky-header';
            if ( ! empty( $has_animation ) ) $classes[] = 'ta-header-scroll-animation';

            if ( did_action( 'elementor/loaded' ) ) {
                $frontend = \Elementor\Plugin::instance()->frontend;
                $frontend->enqueue_styles();
                $frontend->enqueue_scripts();
            }

            $sticky_attr = ! empty( $is_sticky ) ? '1' : '0';
            $anim_attr   = ! empty( $has_animation ) ? '1' : '0';

            echo '<div id="tahefobu-header" class="' . esc_attr( implode( ' ', $classes ) ) . '" data-sticky="' . esc_attr( $sticky_attr ) . '" data-animation="' . esc_attr( $anim_attr ) . '">';
                // Elementor already escapes/sanitizes template content.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $content;
            echo '</div>';

            // Ensure a handle exists before adding inline style
            if ( ! wp_style_is( 'tahefobu-header-render-style', 'registered' ) ) {
                wp_register_style( 'tahefobu-header-render-style', false, [], TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION );
            }
            wp_enqueue_style( 'tahefobu-header-render-style' );
            wp_add_inline_style(
                'tahefobu-header-render-style',
                'body .elementor-location-header{display:block!important;}
                 header,.site-header,.main-header,.woocommerce-header,.ast-site-header{display:block!important;}'
            );
        }
    }
} );
