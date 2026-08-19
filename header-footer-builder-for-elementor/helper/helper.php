<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'tahefobu_hf_allowed_html' ) ) {
    /**
     * Allowed HTML for rendering Elementor template output safely (with icon support).
     */
    function tahefobu_hf_allowed_html() {
        $allowed = wp_kses_allowed_html( 'post' );

        // ✅ Allow Elementor/FontAwesome <i> tags
        $allowed['i'] = [
            'class'       => true,
            'aria-hidden' => true,
            'data-*'      => true, // catch-all for Elementor’s dynamic data attributes
        ];

        // ✅ Allow Elementor <span> wrappers
        $allowed['span'] = [
            'class'       => true,
            'aria-hidden' => true,
            'data-*'      => true,
        ];

        // ✅ Allow SVG (used in Elementor icons)
        $allowed['svg'] = [
            'class'        => true,
            'xmlns'        => true,
            'xmlns:xlink'  => true,
            'xlink'        => true,
            'viewBox'      => true,
            'width'        => true,
            'height'       => true,
            'fill'         => true,
            'stroke'       => true,
            'aria-hidden'  => true,
            'role'         => true,
            'focusable'    => true,
            'data-*'       => true,
        ];

        // ✅ Allow <path> inside SVG
        $allowed['path'] = [
            'd'              => true,
            'fill'           => true,
            'fill-rule'      => true,
            'stroke'         => true,
            'stroke-width'   => true,
            'stroke-linecap' => true,
            'stroke-linejoin'=> true,
        ];

        // ✅ Allow <use> inside SVG for FA/Elementor icons
        $allowed['use'] = [
            'xlink:href' => true,
            'href'       => true,
        ];

        // ✅ Elementor lightbox attributes on <a>
        if ( isset( $allowed['a'] ) ) {
            $allowed['a']['data-elementor-open-lightbox']      = true;
            $allowed['a']['data-elementor-lightbox-slideshow'] = true;
            $allowed['a']['data-elementor-lightbox-title']     = true;
            $allowed['a']['data-*']                           = true;
        }

        // ✅ Extended <img> attributes
        $allowed['img'] = array_merge(
            $allowed['img'] ?? [],
            [
                'src'      => true,
                'alt'      => true,
                'srcset'   => true,
                'sizes'    => true,
                'loading'  => true,
                'decoding' => true,
                'data-*'   => true,
            ]
        );

        /**
         * Filters the allowed HTML tags/attributes for Header Footer Builder templates.
         *
         * @param array $allowed The list of allowed HTML.
         */
        return apply_filters( 'tahefobu_hf_allowed_html', $allowed );
    }
}


/**
 * Register all widget CSS & JS files from assets folder.
 * Elementor will load these when widget asks using get_style_depends().
 */

if ( ! function_exists( 'tahefobu_register_assets' ) ) {

    function tahefobu_register_assets() {

        // Use the plugin version constant for cache-busting instead of filemtime()
        // (avoids a filesystem stat call on every page load in production).
        $ver = defined( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION' )
            ? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
            : '1.0.0';

        // CSS
        wp_register_style(
            'tahefobu-navigation-menu-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/navigation-menu-hf.css',
            [],
            $ver,
            'all'
        );
        wp_register_style(
            'tahefobu-icon-button-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/icon-button-hf.css',
            [],
            $ver,
            'all'
        );
        wp_register_style(
            'tahefobu-top-bar-widgets-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/top-bar-widgets-hf.css',
            [],
            $ver,
            'all'
        );

        // Site Logo & Copy Right widgets request these handles via get_style_depends().
        // They used to be missing, which left the widgets unstyled. Register them against
        // the header style file so any styling hooks still apply; guards keep them cheap.
        wp_register_style(
            'tahefobu-site-logo-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/turbo-header-style.css',
            [],
            $ver,
            'all'
        );
        wp_register_style(
            'tahefobu-copy-right-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/turbo-header-style.css',
            [],
            $ver,
            'all'
        );

        // Font Awesome (used by the mega menu icon picker + walker icons).
        wp_register_style(
            'tahefobu-font-awesome',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/vendor/font-awesome/css/all.min.css',
            [],
            '5.15.4',
            'all'
        );

        // Standalone Mega Menu widget.
        wp_register_style(
            'tahefobu-mega-menu-style',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/mega-menu-hf.css',
            [ 'tahefobu-font-awesome' ],
            $ver,
            'all'
        );
        wp_register_script(
            'tahefobu-mega-menu-script',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/mega-menu-hf.js',
            [ 'jquery' ],
            $ver,
            true
        );
        wp_localize_script(
            'tahefobu-mega-menu-script',
            'tahefobuMegaAjax',
            [
                'restUrl' => esc_url_raw( rest_url( 'tahefobu/v1/' ) ),
            ]
        );

        // JS
        wp_register_script(
            'tahefobu-navigation-menu-script',
            TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/navigation-menu-hf.js',
            [ 'jquery' ],
            $ver,
            true
        );
    }
}

/**
 * Server-side device detection for display conditions.
 *
 * Returns one of: 'desktop', 'tablet', 'mobile'. Tablet detection is a
 * best-effort heuristic; everything non-mobile/non-tablet is desktop.
 *
 * @return string
 */
function tahefobu_get_device_type() {
    if ( function_exists( 'wp_is_mobile' ) && ! wp_is_mobile() ) {
        return 'desktop';
    }

    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';

    if ( false !== strpos( $ua, 'tablet' )
        || false !== strpos( $ua, 'ipad' )
        || ( false !== strpos( $ua, 'android' ) && false === strpos( $ua, 'mobile' ) )
        || false !== strpos( $ua, 'silk' )
        || false !== strpos( $ua, 'kindle' )
    ) {
        return 'tablet';
    }

    if ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) {
        return 'mobile';
    }

    return 'desktop';
}

/**
 * Get all top-level condition option values shared by header/footer admin UI.
 *
 * Simple `target` strings are checked with in_array() against _tahefobu_display_targets.
 * Compound values use a prefix + ":" separator (e.g. tax:category:5, author:3, role:administrator,
 * device:tablet) and are evaluated by tahefobu_condition_matches().
 *
 * @return array<string,string> value => label
 */
function tahefobu_get_condition_options() {
    static $cached = null;
    if ( null !== $cached ) {
        return $cached;
    }

    $options = [
        'entire_site' => __( 'Entire Site', 'header-footer-builder-for-elementor' ),
        'front_page'  => __( 'Front Page', 'header-footer-builder-for-elementor' ),
        'all_pages'   => __( 'All Pages', 'header-footer-builder-for-elementor' ),
        'all_posts'   => __( 'All Blog Posts', 'header-footer-builder-for-elementor' ),
        'all_archives'=> __( 'All Archive Pages', 'header-footer-builder-for-elementor' ),
        'is_404'      => __( '404 Error Page', 'header-footer-builder-for-elementor' ),
        'is_search'   => __( 'Search Results', 'header-footer-builder-for-elementor' ),
        'date_archive'=> __( 'Date Archives', 'header-footer-builder-for-elementor' ),
        'logged_in'   => __( 'Logged-in Users', 'header-footer-builder-for-elementor' ),
        'logged_out'  => __( 'Logged-out Users', 'header-footer-builder-for-elementor' ),
        'device:desktop' => __( 'Desktop Devices', 'header-footer-builder-for-elementor' ),
        'device:tablet'  => __( 'Tablet Devices', 'header-footer-builder-for-elementor' ),
        'device:mobile'  => __( 'Mobile Devices', 'header-footer-builder-for-elementor' ),
    ];

    if ( class_exists( 'WooCommerce' ) ) {
        $options['all_products'] = __( 'All WooCommerce Products', 'header-footer-builder-for-elementor' );
        $options['all_woo']      = __( 'All WooCommerce Pages', 'header-footer-builder-for-elementor' );
    }

    // Taxonomy term archives (categories, tags, and any public taxonomies).
    $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
    foreach ( $taxonomies as $tax ) {
        $terms = get_terms( [
            'taxonomy'   => $tax->name,
            'hide_empty' => false,
            'number'     => 200,
        ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }
        foreach ( $terms as $term ) {
            $options[ 'tax:' . $tax->name . ':' . $term->term_id ] = sprintf(
                /* translators: 1: Taxonomy label, 2: Term name */
                __( 'Archive: %1$s → %2$s', 'header-footer-builder-for-elementor' ),
                $tax->labels->singular_name,
                $term->name
            );
        }
    }

    // Author archives. `capability => 'edit_posts'` avoids the deprecated
    // `who => 'authors'` argument (WP_User_Query, deprecated since WP 5.9).
    $authors = get_users( [ 'capability' => 'edit_posts', 'number' => 100 ] );
    foreach ( $authors as $author ) {
        $options[ 'author:' . $author->ID ] = sprintf(
            /* translators: %s: display name of the author */
            __( 'Author: %s', 'header-footer-builder-for-elementor' ),
            $author->display_name
        );
    }

    // User roles.
    if ( function_exists( 'wp_roles' ) ) {
        foreach ( wp_roles()->roles as $role_key => $role ) {
            $options[ 'role:' . $role_key ] = sprintf(
                /* translators: %s: user role name */
                __( 'Role: %s', 'header-footer-builder-for-elementor' ),
                $role['name']
            );
        }
    }

    $cached = apply_filters( 'tahefobu_condition_options', $options );

    return $cached;
}

/**
 * Evaluate a single stored condition target against the current request.
 *
 * Supports the simple site-wide tokens plus compound values with the ":" separator:
 *   - tax:{taxonomy}:{term_id}   → taxonomy term archive (or singular post in the term)
 *   - author:{user_id}           → author archive (or singular post by author)
 *   - role:{role_slug}           → current logged-in user has the role
 *   - device:{desktop|tablet|mobile}
 *
 * @param string $target Stored display target value.
 * @return bool
 */
function tahefobu_condition_matches( $target ) {
    if ( ! is_string( $target ) || '' === $target ) {
        return false;
    }

    // Simple tokens that map 1:1 to template tags.
    switch ( $target ) {
        case 'front_page':
            return is_front_page();
        case 'all_pages':
            return is_page();
        case 'is_404':
            return is_404();
        case 'is_search':
            return is_search();
        case 'date_archive':
            return is_date();
        case 'logged_in':
            return is_user_logged_in();
        case 'logged_out':
            return ! is_user_logged_in();
    }

    // Compound values.
    $parts = explode( ':', $target );

    if ( 3 === count( $parts ) && 'tax' === $parts[0] ) {
        $tax  = $parts[1];
        $term = absint( $parts[2] );
        if ( ! $term || ! taxonomy_exists( $tax ) ) {
            return false;
        }
        if ( is_tax( $tax, $term ) || is_category( $term ) || is_tag( $term ) ) {
            return true;
        }
        return is_singular() && has_term( $term, $tax, get_queried_object_id() );
    }

    if ( 2 === count( $parts ) && 'author' === $parts[0] ) {
        $author_id = absint( $parts[1] );
        if ( ! $author_id ) {
            return false;
        }
        if ( is_author( $author_id ) ) {
            return true;
        }
        $queried = get_queried_object_id();
        return is_singular() && (int) get_post_field( 'post_author', $queried ) === $author_id;
    }

    if ( 2 === count( $parts ) && 'role' === $parts[0] ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        return in_array( $parts[1], $roles, true );
    }

    if ( 2 === count( $parts ) && 'device' === $parts[0] ) {
        return tahefobu_get_device_type() === $parts[1];
    }

    return false;
}

/**
 * Check whether ANY stored display target matches the current request.
 *
 * `entire_site` is intentionally excluded here — callers treat it as a
 * low-priority fallback so more specific headers/footers win.
 *
 * @param array $targets Stored _tahefobu_display_targets values.
 * @return bool
 */
function tahefobu_targets_any_match( $targets ) {
    foreach ( (array) $targets as $target ) {
        if ( 'entire_site' === $target ) {
            continue;
        }
        if ( tahefobu_condition_matches( $target ) ) {
            return true;
        }
    }
    return false;
}


