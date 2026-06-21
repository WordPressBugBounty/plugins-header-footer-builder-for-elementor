<?php
/**
 * Admin Menu — Single-page Dashboard
 * Everything (create / edit / delete / conditions) lives here via AJAX.
 *
 * @package Header_Footer_Builder_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─────────────────────────────────────────────────────────────
   1. Register single menu item — no submenus
───────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_menu_page(
        esc_html__( 'Turbo H&F Builder', 'header-footer-builder-for-elementor' ),
        esc_html__( 'Turbo H&F Builder', 'header-footer-builder-for-elementor' ),
        'edit_posts',
        'tahefobu_templates',
        'tahefobu_render_dashboard',
        TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/images/turboFile.svg',
        21
    );
} );

/* ─────────────────────────────────────────────────────────────
   2. Enqueue assets only on our page
───────────────────────────────────────────────────────────── */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'toplevel_page_tahefobu_templates' ) {
        return;
    }
    $url = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL;
    $ver = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION;

    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_style(
        'thfb-dashboard',
        $url . 'assets/css/turbo-admin-dashboard.css',
        [],
        $ver
    );
    // Select2 for condition selects
    wp_enqueue_style(
        'select2',
        $url . 'assets/vendor/select2/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_script(
        'select2',
        $url . 'assets/vendor/select2/select2.min.js',
        [ 'jquery' ],
        '4.1.0',
        true
    );
    wp_enqueue_script(
        'thfb-dashboard',
        $url . 'assets/js/turbo-dashboard.js',
        [ 'jquery', 'select2' ],
        $ver,
        true
    );
    wp_localize_script( 'thfb-dashboard', 'thfbDash', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tahefobu_dashboard_nonce' ),
        'pages'   => tahefobu_get_all_pages_for_js(),
        'strings' => [
            'confirm_delete' => __( 'Delete this template? This cannot be undone.', 'header-footer-builder-for-elementor' ),
            'saving'         => __( 'Saving…', 'header-footer-builder-for-elementor' ),
            'saved'          => __( 'Saved!', 'header-footer-builder-for-elementor' ),
            'creating'       => __( 'Creating…', 'header-footer-builder-for-elementor' ),
            'deleting'       => __( 'Deleting…', 'header-footer-builder-for-elementor' ),
            'error'          => __( 'Something went wrong. Please try again.', 'header-footer-builder-for-elementor' ),
            'select_all'     => __( 'Select All', 'header-footer-builder-for-elementor' ),
            'deselect_all'   => __( 'Deselect All', 'header-footer-builder-for-elementor' ),
        ],
    ] );
} );

/* ─────────────────────────────────────────────────────────────
   3. Helper: pages list for JS
───────────────────────────────────────────────────────────── */
function tahefobu_get_all_pages_for_js() {
    $pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
    $out   = [];
    foreach ( $pages as $p ) {
        $out[] = [ 'id' => $p->ID, 'title' => $p->post_title ];
    }
    return $out;
}

/* ─────────────────────────────────────────────────────────────
   4. AJAX — Create template (header or footer)
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_create', function () {
    check_ajax_referer( 'tahefobu_dashboard_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'header-footer-builder-for-elementor' ) ] );
    }

    $type  = isset( $_POST['type'] ) && $_POST['type'] === 'footer' ? 'tahefobu_footer' : 'tahefobu_header';
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    if ( empty( $title ) ) {
        wp_send_json_error( [ 'message' => __( 'Template name is required.', 'header-footer-builder-for-elementor' ) ] );
    }

    $post_id = wp_insert_post( [
        'post_type'   => $type,
        'post_title'  => $title,
        'post_status' => 'publish',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not create template.', 'header-footer-builder-for-elementor' ) ] );
    }

    update_post_meta( $post_id, '_tahefobu_is_enabled', '1' );

    $include  = isset( $_POST['include_pages'] )   ? array_map( 'intval', (array) wp_unslash( $_POST['include_pages'] ) )   : [];
    $exclude  = isset( $_POST['exclude_pages'] )   ? array_map( 'intval', (array) wp_unslash( $_POST['exclude_pages'] ) )   : [];
    $targets  = isset( $_POST['display_targets'] ) && is_array( $_POST['display_targets'] )
        ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['display_targets'] ) ) : [];
    $sticky   = ( $type === 'tahefobu_header' ) ? ( ! empty( $_POST['is_sticky'] ) ? 1 : 0 ) : 0;
    $anim     = ( $type === 'tahefobu_header' ) ? ( ! empty( $_POST['has_animation'] ) ? 1 : 0 ) : 0;

    update_post_meta( $post_id, '_tahefobu_include_pages',   $include );
    update_post_meta( $post_id, '_tahefobu_exclude_pages',   $exclude );
    update_post_meta( $post_id, '_tahefobu_display_targets', $targets );
    update_post_meta( $post_id, '_tahefobu_is_sticky',       $sticky );
    update_post_meta( $post_id, '_tahefobu_has_animation',   $anim );

    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    wp_send_json_success( [
        'post_id'  => $post_id,
        'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
        'template' => tahefobu_get_template_row_data( $post_id ),
    ] );
} );

/* ─────────────────────────────────────────────────────────────
   5. AJAX — Save conditions
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_save_conditions', function () {
    check_ajax_referer( 'tahefobu_dashboard_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'header-footer-builder-for-elementor' ) ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
    $pt      = get_post_type( $post_id );
    if ( ! $post_id || ! in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true )
        || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid request.', 'header-footer-builder-for-elementor' ) ] );
    }

    $include = isset( $_POST['include_pages'] )   ? array_map( 'intval', (array) wp_unslash( $_POST['include_pages'] ) )   : [];
    $exclude = isset( $_POST['exclude_pages'] )   ? array_map( 'intval', (array) wp_unslash( $_POST['exclude_pages'] ) )   : [];
    $targets = isset( $_POST['display_targets'] ) && is_array( $_POST['display_targets'] )
        ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['display_targets'] ) ) : [];
    $sticky  = ( $pt === 'tahefobu_header' ) ? absint( $_POST['is_sticky'] ?? 0 )     : 0;
    $anim    = ( $pt === 'tahefobu_header' ) ? absint( $_POST['has_animation'] ?? 0 ) : 0;

    update_post_meta( $post_id, '_tahefobu_include_pages',   $include );
    update_post_meta( $post_id, '_tahefobu_exclude_pages',   $exclude );
    update_post_meta( $post_id, '_tahefobu_display_targets', $targets );
    update_post_meta( $post_id, '_tahefobu_is_sticky',       $sticky );
    update_post_meta( $post_id, '_tahefobu_has_animation',   $anim );

    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    wp_send_json_success( [ 'template' => tahefobu_get_template_row_data( $post_id ) ] );
} );

/* ─────────────────────────────────────────────────────────────
   6. AJAX — Delete template
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_delete', function () {
    check_ajax_referer( 'tahefobu_dashboard_nonce', 'nonce' );
    if ( ! current_user_can( 'delete_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'header-footer-builder-for-elementor' ) ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
    $pt      = get_post_type( $post_id );
    if ( ! $post_id || ! in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true )
        || ! current_user_can( 'delete_post', $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid request.', 'header-footer-builder-for-elementor' ) ] );
    }

    wp_delete_post( $post_id, true );
    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    wp_send_json_success();
} );

/* ─────────────────────────────────────────────────────────────
   7. AJAX — Toggle active/draft
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_toggle_status', function () {
    check_ajax_referer( 'tahefobu_dashboard_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'header-footer-builder-for-elementor' ) ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
    $pt      = get_post_type( $post_id );
    if ( ! $post_id || ! in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true )
        || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid request.', 'header-footer-builder-for-elementor' ) ] );
    }

    $current    = get_post_status( $post_id );
    $new_status = ( $current === 'publish' ) ? 'draft' : 'publish';
    wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    wp_send_json_success( [ 'new_status' => $new_status ] );
} );

/* ─────────────────────────────────────────────────────────────
   8. AJAX — Export template as JSON
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_export', function () {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'tahefobu_dashboard_nonce' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'header-footer-builder-for-elementor' ) );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'header-footer-builder-for-elementor' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
    $pt      = get_post_type( $post_id );

    if ( ! $post_id
        || ! in_array( $pt, [ 'tahefobu_header', 'tahefobu_footer' ], true )
        || ! current_user_can( 'edit_post', $post_id )
    ) {
        wp_die( esc_html__( 'Invalid template.', 'header-footer-builder-for-elementor' ) );
    }

    $post = get_post( $post_id );

    // Elementor data — stored as a JSON string, decode it so the final file is clean JSON.
    $elementor_raw  = get_post_meta( $post_id, '_elementor_data', true );
    $elementor_data = json_decode( $elementor_raw ?: '[]', true );

    // Build in Elementor's standard export format so it can be re-imported
    // via Elementor Editor → Templates → Import Template.
    $export = [
        'version'       => '0.4',
        'title'         => $post->post_title,
        'type'          => 'page',
        'content'       => is_array( $elementor_data ) ? $elementor_data : [],
        'page_settings' => get_post_meta( $post_id, '_elementor_page_settings', true ) ?: (object)[],
    ];

    $type_slug = ( $pt === 'tahefobu_header' ) ? 'header' : 'footer';
    $filename  = sanitize_file_name( $type_slug . '-' . $post->post_title . '-' . gmdate( 'Y-m-d' ) . '.json' );
    $json      = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $json ) );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download, not HTML output
    echo $json;
    exit;
} );

/* ─────────────────────────────────────────────────────────────
   8b. AJAX — Import template from JSON
───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_tahefobu_dashboard_import', function () {
    check_ajax_referer( 'tahefobu_dashboard_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'header-footer-builder-for-elementor' ) ] );
    }

    // File must be uploaded — check index existence before accessing sub-keys
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- sub-keys validated below
    if ( empty( $_FILES['import_file'] ) || ! isset( $_FILES['import_file']['error'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => __( 'No file uploaded or upload error.', 'header-footer-builder-for-elementor' ) ] );
    }

    // Collect and sanitize individual $_FILES fields — direct array assign is safe here
    // because we validate name/type/tmp_name individually before use.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized individually below
    $file = $_FILES['import_file'];

    // Validate mime — must be JSON
    $finfo    = finfo_open( FILEINFO_MIME_TYPE );
    $mime     = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );
    $ext      = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );

    if ( ! in_array( $mime, [ 'application/json', 'text/plain', 'text/x-json' ], true ) || $ext !== 'json' ) {
        wp_send_json_error( [ 'message' => __( 'Invalid file type. Please upload a .json file exported by this plugin.', 'header-footer-builder-for-elementor' ) ] );
    }

    // Read and decode
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    $raw  = file_get_contents( $file['tmp_name'] );
    $data = json_decode( $raw, true );

    if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not parse JSON file. Make sure it was exported by this plugin.', 'header-footer-builder-for-elementor' ) ] );
    }

    // Accept both our Elementor-standard export format (version 0.4, content key)
    // and any older format that had the plugin signature.
    $is_elementor_format = isset( $data['version'] ) && isset( $data['content'] ) && is_array( $data['content'] );
    $is_legacy_format    = isset( $data['plugin'] ) && $data['plugin'] === 'turbo-header-footer-builder';

    if ( ! $is_elementor_format && ! $is_legacy_format ) {
        wp_send_json_error( [ 'message' => __( 'Invalid file. Please use a .json file exported by Turbo H&F Builder.', 'header-footer-builder-for-elementor' ) ] );
    }

    // Determine the template type from the import modal selection (user picks header or footer)
    $import_type = isset( $_POST['import_type'] ) && $_POST['import_type'] === 'footer' ? 'tahefobu_footer' : 'tahefobu_header';

    // In legacy format the type is stored in the file
    if ( $is_legacy_format && isset( $data['type'] ) ) {
        $import_type = $data['type'] === 'tahefobu_footer' ? 'tahefobu_footer' : 'tahefobu_header';
    }

    $pt = $import_type;

    // Allow user to override the title
    $title = '';
    if ( isset( $_POST['import_title'] ) ) {
        $title = sanitize_text_field( wp_unslash( $_POST['import_title'] ) );
    }
    if ( empty( $title ) ) {
        $title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'Imported Template', 'header-footer-builder-for-elementor' );
    }

    // Create the post
    $post_id = wp_insert_post( [
        'post_type'   => $pt,
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_content'=> '',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not create template.', 'header-footer-builder-for-elementor' ) ] );
    }

    // Restore Elementor data — new format uses 'content', legacy uses 'elementor_data'
    $elementor_content = [];
    if ( $is_elementor_format && ! empty( $data['content'] ) ) {
        $elementor_content = $data['content'];
    } elseif ( $is_legacy_format && ! empty( $data['elementor_data'] ) ) {
        $elementor_content = $data['elementor_data'];
    }

    if ( ! empty( $elementor_content ) ) {
        update_post_meta( $post_id, '_elementor_data', wp_json_encode( $elementor_content ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    }

    // Page settings
    $page_settings = [];
    if ( ! empty( $data['page_settings'] ) && is_array( $data['page_settings'] ) ) {
        $page_settings = $data['page_settings'];
    } elseif ( ! empty( $data['elementor_page_settings'] ) && is_array( $data['elementor_page_settings'] ) ) {
        $page_settings = $data['elementor_page_settings'];
    }
    if ( ! empty( $page_settings ) ) {
        update_post_meta( $post_id, '_elementor_page_settings', $page_settings );
    }

    // Restore conditions (only in legacy format — Elementor format doesn't carry conditions)
    $cond = ( $is_legacy_format && isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) ? $data['conditions'] : [];
    update_post_meta( $post_id, '_tahefobu_display_targets', isset( $cond['display_targets'] ) ? (array) $cond['display_targets'] : [] );
    update_post_meta( $post_id, '_tahefobu_include_pages',   isset( $cond['include_pages'] )   ? array_map( 'intval', (array) $cond['include_pages'] ) : [] );
    update_post_meta( $post_id, '_tahefobu_exclude_pages',   isset( $cond['exclude_pages'] )   ? array_map( 'intval', (array) $cond['exclude_pages'] ) : [] );
    update_post_meta( $post_id, '_tahefobu_is_sticky',       ! empty( $cond['is_sticky'] )     ? 1 : 0 );
    update_post_meta( $post_id, '_tahefobu_has_animation',   ! empty( $cond['has_animation'] ) ? 1 : 0 );
    update_post_meta( $post_id, '_tahefobu_is_enabled',      '1' );

    // Bust caches
    delete_transient( 'tahefobu_header_templates_meta' );
    delete_transient( 'tahefobu_footer_templates_meta' );

    wp_send_json_success( [
        'template' => tahefobu_get_template_row_data( $post_id ),
        'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
        'message'  => __( 'Template imported successfully!', 'header-footer-builder-for-elementor' ),
    ] );
} );

/* ─────────────────────────────────────────────────────────────
   9. Helper — build template row data array for JS
───────────────────────────────────────────────────────────── */
function tahefobu_get_template_row_data( $post_id ) {
    $post    = get_post( $post_id );
    $pt      = get_post_type( $post_id );
    $targets = (array) ( get_post_meta( $post_id, '_tahefobu_display_targets', true ) ?: [] );
    $include = (array) ( get_post_meta( $post_id, '_tahefobu_include_pages',   true ) ?: [] );
    $exclude = (array) ( get_post_meta( $post_id, '_tahefobu_exclude_pages',   true ) ?: [] );
    $sticky  = (bool)    get_post_meta( $post_id, '_tahefobu_is_sticky',       true );
    $anim    = (bool)    get_post_meta( $post_id, '_tahefobu_has_animation',   true );

    return [
        'id'           => $post_id,
        'title'        => $post->post_title,
        'status'       => get_post_status( $post_id ),
        'type'         => $pt,
        'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
        'modified'     => get_the_modified_date( 'M j, Y', $post_id ),
        'targets'      => array_map( 'sanitize_key', $targets ),
        'include'      => array_map( 'strval', $include ),
        'exclude'      => array_map( 'strval', $exclude ),
        'is_sticky'    => $sticky,
        'has_animation'=> $anim,
    ];
}

/* ─────────────────────────────────────────────────────────────
   9. Helper — render all templates as JSON for initial page load
───────────────────────────────────────────────────────────── */
function tahefobu_get_all_templates_json() {
    $types = [ 'tahefobu_header', 'tahefobu_footer' ];
    $out   = [];
    foreach ( $types as $pt ) {
        $posts = get_posts( [
            'post_type'      => $pt,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );
        foreach ( $posts as $p ) {
            $out[] = tahefobu_get_template_row_data( $p->ID );
        }
    }
    return $out;
}

/* ─────────────────────────────────────────────────────────────
   10. Dashboard HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_dashboard() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have permission to view this page.', 'header-footer-builder-for-elementor' ) );
    }

    $ver       = defined( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION' )
                    ? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION : '';
    $logo      = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/images/turbo-logo.png';
    $templates = tahefobu_get_all_templates_json();
    $headers   = array_filter( $templates, fn($t) => $t['type'] === 'tahefobu_header' );
    $footers   = array_filter( $templates, fn($t) => $t['type'] === 'tahefobu_footer' );

    $tag_labels = [
        'entire_site'  => __( 'Entire Site',   'header-footer-builder-for-elementor' ),
        'all_posts'    => __( 'All Posts',     'header-footer-builder-for-elementor' ),
        'all_archives' => __( 'Archives',      'header-footer-builder-for-elementor' ),
        'all_products' => __( 'Products',      'header-footer-builder-for-elementor' ),
        'all_woo'      => __( 'WooCommerce',   'header-footer-builder-for-elementor' ),
        'all_pages'    => __( 'All Pages',     'header-footer-builder-for-elementor' ),
    ];
    ?>
    <div id="thfb-dashboard">

    <!-- ══ HERO ══════════════════════════════════════════════ -->
    <div class="thfb-hero">
        <div class="thfb-hero-text">
            <h1><?php esc_html_e( 'Turbo Header Footer Builder', 'header-footer-builder-for-elementor' ); ?></h1>
            <p><?php esc_html_e( 'Design pixel-perfect headers & footers with Elementor — sticky, animated, with smart display conditions.', 'header-footer-builder-for-elementor' ); ?></p>
            <span class="thfb-version-badge">
                <span class="dashicons dashicons-admin-plugins"></span>
                v<?php echo esc_html( $ver ); ?>
            </span>
        </div>

        <div>
            <img src="<?php echo esc_url( $logo ); ?>" alt="Turbo Logo" class="thfb-hero-logo">
            <div class="thfb-hero-actions">

            <!-- <button class="thfb-btn thfb-btn-white" id="thfb-new-header-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'New Header', 'header-footer-builder-for-elementor' ); ?>
            </button>
            <button class="thfb-btn thfb-btn-outline-white" id="thfb-new-footer-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'New Footer', 'header-footer-builder-for-elementor' ); ?>
            </button> -->
            <button class="thfb-btn thfb-btn-outline-white" id="thfb-import-btn">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e( 'Import Header/Footer', 'header-footer-builder-for-elementor' ); ?>
            </button>
          </div>
        </div>

        
    </div>

    <!-- ══ MAIN GRID ═════════════════════════════════════════ -->
    <div class="thfb-main-grid">
    <div class="thfb-left-col">

    <!-- Header Templates Panel -->
    <div class="thfb-panel">
        <div class="thfb-panel-header">
            <h2 class="thfb-panel-title"><span class="dashicons dashicons-layout"></span><?php esc_html_e( 'Header', 'header-footer-builder-for-elementor' ); ?></h2>
            <button class="thfb-btn thfb-btn-primary thfb-sm" id="thfb-new-header-btn2">
                <span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Create New Header', 'header-footer-builder-for-elementor' ); ?>
            </button>
        </div>
        <div class="thfb-panel-body thfb-table-wrap" id="thfb-header-table-wrap">
            <?php tahefobu_render_template_rows( $headers, $tag_labels ); ?>
        </div>
    </div>

    <!-- Footer Templates Panel -->
    <div class="thfb-panel">
        <div class="thfb-panel-header">
            <h2 class="thfb-panel-title"><span class="dashicons dashicons-align-center"></span><?php esc_html_e( 'Footer', 'header-footer-builder-for-elementor' ); ?></h2>
            <button class="thfb-btn thfb-btn-success thfb-sm" id="thfb-new-footer-btn2">
                <span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Create New Footer', 'header-footer-builder-for-elementor' ); ?>
            </button>
        </div>
        <div class="thfb-panel-body thfb-table-wrap" id="thfb-footer-table-wrap">
            <?php tahefobu_render_template_rows( $footers, $tag_labels ); ?>
        </div>
    </div>

    </div><!-- /.thfb-left-col -->

    <!-- ══ SIDEBAR ═══════════════════════════════════════════ -->
    <div class="thfb-right-col">


    <!-- How it works -->
        <div class="thfb-panel">
            <div class="thfb-panel-header">
                <h2 class="thfb-panel-title"><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'How It Works', 'header-footer-builder-for-elementor' ); ?></h2>
                <button id="thfb-watch-video-btn" class="thfb-video-btn" title="<?php esc_attr_e( 'Watch Tutorial Video', 'header-footer-builder-for-elementor' ); ?>">
                    <span class="thfb-video-btn-icon">&#9654;</span>
                    <?php esc_html_e( 'Watch Video', 'header-footer-builder-for-elementor' ); ?>
                </button>
            </div>
            <div class="thfb-panel-body">
                <ul class="thfb-tip-list">
                    <li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Click "Create New Header " or "Create New Footer " to create a header or footer template.', 'header-footer-builder-for-elementor' ); ?></li>
                    <li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Give the title name and set the condition.', 'header-footer-builder-for-elementor' ); ?></li>
                    <li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Set display conditions — entire site, specific pages, or WooCommerce.', 'header-footer-builder-for-elementor' ); ?></li>
                    <li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Design it with Elementor using the Edit button.', 'header-footer-builder-for-elementor' ); ?></li>  
                    <li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Toggle Active/Draft to show or hide without deleting.', 'header-footer-builder-for-elementor' ); ?></li>
                </ul>
            </div>
        </div>

        <!-- Promo -->
        <div class="thfb-promo">
            <h3>🔥 <?php esc_html_e( '200+ Premium Templates', 'header-footer-builder-for-elementor' ); ?></h3>
            <p><?php esc_html_e( 'Import full website templates in 1 click with Turbo Addons. Special discount available now.', 'header-footer-builder-for-elementor' ); ?></p>
            <a href="https://turbo-addons.com/templates/" target="_blank" class="thfb-btn-promo">
                <span class="dashicons dashicons-star-filled"></span>
                <?php esc_html_e( 'Claim Discount', 'header-footer-builder-for-elementor' ); ?>
            </a>
        </div>

        

        <!-- Rate -->
        <div class="thfb-panel">
            <div class="thfb-panel-body" style="text-align:center;">
                <p style="font-size:13px;color:#6b7280;margin:0 0 12px;"><?php esc_html_e( 'Enjoying the plugin? Leave us a review!', 'header-footer-builder-for-elementor' ); ?></p>
                <a href="https://wordpress.org/support/plugin/header-footer-builder-for-elementor/reviews/#new-post" target="_blank" class="thfb-btn thfb-btn-primary" style="width:100%;justify-content:center;">
                    <!-- <span class="dashicons dashicons-star-filled"></span> -->
                    <?php esc_html_e( 'Help Us to Improve ★★★★★', 'header-footer-builder-for-elementor' ); ?>
                </a>
            </div>
        </div>

    </div><!-- /.thfb-right-col -->
    </div><!-- /.thfb-main-grid -->

    <?php tahefobu_render_create_modal(); ?>
    <?php tahefobu_render_conditions_modal(); ?>
    <?php tahefobu_render_video_modal(); ?>
    <?php tahefobu_render_import_modal(); ?>

    <!-- Pass template data to JS -->
    <script>
    window.thfbTemplates = <?php echo wp_json_encode( array_values( $templates ) ); ?>;
    window.thfbTagLabels = <?php echo wp_json_encode( $tag_labels ); ?>;
    </script>

    </div><!-- /#thfb-dashboard -->
    <?php
}

/* ─────────────────────────────────────────────────────────────
   11. Helper — render template rows HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_template_rows( $templates, $tag_labels ) {
    if ( empty( $templates ) ) {
        echo '<div class="thfb-empty-state">';
        echo '<div class="thfb-empty-icon"><span class="dashicons dashicons-layout"></span></div>';
        echo '<h3>' . esc_html__( 'No templates yet', 'header-footer-builder-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Click "Create New Heder/Footer" above to create your first template.', 'header-footer-builder-for-elementor' ) . '</p>';
        echo '</div>';
        return;
    }
    echo '<table class="thfb-template-table"><thead><tr>';
    echo '<th>' . esc_html__( 'Name', 'header-footer-builder-for-elementor' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'header-footer-builder-for-elementor' ) . '</th>';
    echo '<th>' . esc_html__( 'Display Conditions', 'header-footer-builder-for-elementor' ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'header-footer-builder-for-elementor' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $templates as $t ) {
        $is_active = ( $t['status'] === 'publish' );
        $badge_cls = $is_active ? 'thfb-badge-active' : 'thfb-badge-draft';
        $badge_lbl = $is_active
            ? esc_html__( 'Active', 'header-footer-builder-for-elementor' )
            : esc_html__( 'Draft',  'header-footer-builder-for-elementor' );
        $is_header = ( $t['type'] === 'tahefobu_header' );
        // $icon      = $is_header ? 'dashicons-layout' : 'dashicons-align-center';

        echo '<tr data-id="' . esc_attr( $t['id'] ) . '" data-type="' . esc_attr( $t['type'] ) . '">';

        // Name
        echo '<td>';
        echo '<div class="thfb-template-name">';
        // echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
        echo '<div>';
        echo '<strong>' . esc_html( $t['title'] ) . '</strong>';
        if ( $is_header && ! empty( $t['is_sticky'] ) ) {
            echo ' <span class="thfb-badge" style="background:#f3f0ff;color:#7c3aed;font-size:10px;padding:2px 7px;">'
                . esc_html__( 'Sticky', 'header-footer-builder-for-elementor' ) . '</span>';
        }
        if ( $is_header && ! empty( $t['has_animation'] ) ) {
            echo ' <span class="thfb-badge" style="background:#fff8ee;color:#e67e22;font-size:10px;padding:2px 7px;">'
                . esc_html__( 'Animated', 'header-footer-builder-for-elementor' ) . '</span>';
        }
        echo '<div class="thfb-template-meta">' . esc_html__( 'Modified:', 'header-footer-builder-for-elementor' ) . ' ' . esc_html( $t['modified'] ) . '</div>';
        echo '</div></div></td>';

        // Status toggle
        echo '<td>';
        echo '<button class="thfb-badge ' . esc_attr( $badge_cls ) . ' thfb-toggle-status" '
            . 'data-id="' . esc_attr( $t['id'] ) . '" '
            . 'title="' . esc_attr__( 'Click to toggle', 'header-footer-builder-for-elementor' ) . '" '
            . 'style="cursor:pointer;border:none;background:none;padding:0;">'
            . esc_html( $badge_lbl ) . '</button>';
        echo '</td>';

        // Conditions
        echo '<td class="thfb-conditions-cell">';
        if ( ! empty( $t['targets'] ) ) {
            foreach ( $t['targets'] as $tgt ) {
                $lbl = isset( $tag_labels[ $tgt ] ) ? $tag_labels[ $tgt ] : $tgt;
                echo '<span class="thfb-condition-tag">' . esc_html( $lbl ) . '</span>';
            }
        } else {
            echo '<span class="thfb-condition-none">' . esc_html__( 'Not set', 'header-footer-builder-for-elementor' ) . '</span>';
        }
        echo '</td>';

        // Actions
        echo '<td><div class="thfb-row-actions">';
        echo '<a href="' . esc_url( $t['edit_url'] ) . '" class="thfb-action-btn thfb-action-edit">'
            . '<span class="dashicons dashicons-edit"></span>' . esc_html__( 'Edit', 'header-footer-builder-for-elementor' ) . '</a>';
        echo '<button class="thfb-action-btn thfb-action-conditions thfb-open-conditions" data-id="' . esc_attr( $t['id'] ) . '">'
            . '<span class="dashicons dashicons-admin-settings"></span>' . esc_html__( 'Conditions', 'header-footer-builder-for-elementor' ) . '</button>';
        echo '<button class="thfb-action-btn thfb-action-export thfb-export-tpl" data-id="' . esc_attr( $t['id'] ) . '">'
            . '<span class="dashicons dashicons-download"></span>' . esc_html__( 'Export', 'header-footer-builder-for-elementor' ) . '</button>';
        echo '<button class="thfb-action-btn thfb-action-delete thfb-delete-tpl" data-id="' . esc_attr( $t['id'] ) . '">'
            . '<span class="dashicons dashicons-trash"></span>' . esc_html__( 'Delete', 'header-footer-builder-for-elementor' ) . '</button>';
        echo '</div></td>';

        echo '</tr>';
    }
    echo '</tbody></table>';
}

/* ─────────────────────────────────────────────────────────────
   12. Create Modal HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_create_modal() {
    $woo = class_exists( 'WooCommerce' );
    ?>
    <div id="thfb-create-modal" class="thfb-modal-overlay" style="display:none;">
        <div class="thfb-modal">
            <div class="thfb-modal-header">
                <h2 id="thfb-create-modal-title"><?php esc_html_e( 'Create New Template', 'header-footer-builder-for-elementor' ); ?></h2>
                <button class="thfb-modal-close" id="thfb-create-close">&times;</button>
            </div>
            <div class="thfb-modal-body">
                <input type="hidden" id="thfb-create-type" value="header">

                <label class="thfb-field-label"><?php esc_html_e( 'Template Name', 'header-footer-builder-for-elementor' ); ?> <span style="color:red">*</span></label>
                <input type="text" id="thfb-create-title" class="thfb-input" placeholder="<?php esc_attr_e( 'e.g. Main Header', 'header-footer-builder-for-elementor' ); ?>">
                <label class="thfb-field-label" style="margin-top:16px;"><?php esc_html_e( 'Display Conditions', 'header-footer-builder-for-elementor' ); ?></label>
                <select id="thfb-create-targets" multiple class="thfb-select">
                    <option value="entire_site"><?php esc_html_e( 'Entire Site', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_posts"><?php esc_html_e( 'All Blog Posts', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_archives"><?php esc_html_e( 'All Archive Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_pages"><?php esc_html_e( 'All Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <?php if ( $woo ) : ?>
                    <option value="all_products"><?php esc_html_e( 'All WooCommerce Products', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_woo"><?php esc_html_e( 'All WooCommerce Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <?php endif; ?>
                </select>

                <div class="thfb-field-label-row" style="margin-top:16px;">
                    <label class="thfb-field-label"><?php esc_html_e( 'Include Specific Pages', 'header-footer-builder-for-elementor' ); ?> <span class="thfb-optional"><?php esc_html_e( '(optional)', 'header-footer-builder-for-elementor' ); ?></span></label>
                    <button type="button" class="thfb-select-all-btn" data-target="thfb-create-include" data-deselect="0"><?php esc_html_e( 'Select All', 'header-footer-builder-for-elementor' ); ?></button>
                </div>
                <select id="thfb-create-include" multiple class="thfb-select thfb-pages-select"></select>

                <div class="thfb-field-label-row" style="margin-top:16px;">
                    <label class="thfb-field-label"><?php esc_html_e( 'Exclude Specific Pages', 'header-footer-builder-for-elementor' ); ?> <span class="thfb-optional"><?php esc_html_e( '(optional)', 'header-footer-builder-for-elementor' ); ?></span></label>
                    <button type="button" class="thfb-select-all-btn" data-target="thfb-create-exclude" data-deselect="0"><?php esc_html_e( 'Select All', 'header-footer-builder-for-elementor' ); ?></button>
                </div>
                <select id="thfb-create-exclude" multiple class="thfb-select thfb-pages-select"></select>

                <div id="thfb-create-header-opts" class="thfb-header-opts">
                    <div class="thfb-divider"></div>
                    <label class="thfb-field-label"><?php esc_html_e( 'Header Style', 'header-footer-builder-for-elementor' ); ?></label>
                    <div class="thfb-toggle-row">
                        <label class="thfb-toggle-label">
                            <input type="checkbox" id="thfb-create-sticky">
                            <span class="thfb-toggle-switch"></span>
                            <?php esc_html_e( 'Sticky Header', 'header-footer-builder-for-elementor' ); ?>
                        </label>
                        <label class="thfb-toggle-label">
                            <input type="checkbox" id="thfb-create-animation">
                            <span class="thfb-toggle-switch"></span>
                            <?php esc_html_e( 'Scroll Animation', 'header-footer-builder-for-elementor' ); ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="thfb-modal-footer">
                <button class="thfb-btn thfb-btn-primary" id="thfb-create-submit">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Create & Edit with Elementor', 'header-footer-builder-for-elementor' ); ?>
                </button>
                <button class="thfb-btn thfb-btn-ghost" id="thfb-create-cancel"><?php esc_html_e( 'Cancel', 'header-footer-builder-for-elementor' ); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/* ─────────────────────────────────────────────────────────────
   13. Conditions Modal HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_conditions_modal() {
    $woo = class_exists( 'WooCommerce' );
    ?>
    <div id="thfb-conditions-modal" class="thfb-modal-overlay" style="display:none;">
        <div class="thfb-modal">
            <div class="thfb-modal-header">
                <h2><?php esc_html_e( 'Edit Conditions', 'header-footer-builder-for-elementor' ); ?></h2>
                <button class="thfb-modal-close" id="thfb-cond-close">&times;</button>
            </div>
            <div class="thfb-modal-body">
                <input type="hidden" id="thfb-cond-post-id">
                <input type="hidden" id="thfb-cond-post-type">

                <label class="thfb-field-label"><?php esc_html_e( 'Display Conditions', 'header-footer-builder-for-elementor' ); ?></label>
                <select id="thfb-cond-targets" multiple class="thfb-select">
                    <option value="entire_site"><?php esc_html_e( 'Entire Site', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_posts"><?php esc_html_e( 'All Blog Posts', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_archives"><?php esc_html_e( 'All Archive Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_pages"><?php esc_html_e( 'All Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <?php if ( $woo ) : ?>
                    <option value="all_products"><?php esc_html_e( 'All WooCommerce Products', 'header-footer-builder-for-elementor' ); ?></option>
                    <option value="all_woo"><?php esc_html_e( 'All WooCommerce Pages', 'header-footer-builder-for-elementor' ); ?></option>
                    <?php endif; ?>
                </select>

                <div class="thfb-field-label-row" style="margin-top:16px;">
                    <label class="thfb-field-label"><?php esc_html_e( 'Include Specific Pages', 'header-footer-builder-for-elementor' ); ?> <span class="thfb-optional"><?php esc_html_e( '(optional)', 'header-footer-builder-for-elementor' ); ?></span></label>
                    <button type="button" class="thfb-select-all-btn" data-target="thfb-cond-include" data-deselect="0"><?php esc_html_e( 'Select All', 'header-footer-builder-for-elementor' ); ?></button>
                </div>
                <select id="thfb-cond-include" multiple class="thfb-select thfb-pages-select"></select>

                <div class="thfb-field-label-row" style="margin-top:16px;">
                    <label class="thfb-field-label"><?php esc_html_e( 'Exclude Specific Pages', 'header-footer-builder-for-elementor' ); ?> <span class="thfb-optional"><?php esc_html_e( '(optional)', 'header-footer-builder-for-elementor' ); ?></span></label>
                    <button type="button" class="thfb-select-all-btn" data-target="thfb-cond-exclude" data-deselect="0"><?php esc_html_e( 'Select All', 'header-footer-builder-for-elementor' ); ?></button>
                </div>
                <select id="thfb-cond-exclude" multiple class="thfb-select thfb-pages-select"></select>

                <div id="thfb-cond-header-opts" class="thfb-header-opts">
                    <div class="thfb-divider"></div>
                    <label class="thfb-field-label"><?php esc_html_e( 'Header Style', 'header-footer-builder-for-elementor' ); ?></label>
                    <div class="thfb-toggle-row">
                        <label class="thfb-toggle-label">
                            <input type="checkbox" id="thfb-cond-sticky">
                            <span class="thfb-toggle-switch"></span>
                            <?php esc_html_e( 'Sticky Header', 'header-footer-builder-for-elementor' ); ?>
                        </label>
                        <label class="thfb-toggle-label">
                            <input type="checkbox" id="thfb-cond-animation">
                            <span class="thfb-toggle-switch"></span>
                            <?php esc_html_e( 'Scroll Animation', 'header-footer-builder-for-elementor' ); ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="thfb-modal-footer">
                <button class="thfb-btn thfb-btn-primary" id="thfb-cond-save">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Conditions', 'header-footer-builder-for-elementor' ); ?>
                </button>
                <button class="thfb-btn thfb-btn-ghost" id="thfb-cond-cancel"><?php esc_html_e( 'Cancel', 'header-footer-builder-for-elementor' ); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/* ─────────────────────────────────────────────────────────────
   Import Modal HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_import_modal() {
    ?>
    <div id="thfb-import-modal" class="thfb-modal-overlay" style="display:none;">
        <div class="thfb-modal">
            <div class="thfb-modal-header">
                <h2><?php esc_html_e( 'Import Template', 'header-footer-builder-for-elementor' ); ?></h2>
                <button class="thfb-modal-close" id="thfb-import-close">&times;</button>
            </div>
            <div class="thfb-modal-body">

                <!-- Drop zone -->
                <div id="thfb-import-dropzone" class="thfb-import-dropzone">
                    <span class="dashicons dashicons-upload thfb-import-dropzone-icon"></span>
                    <p><?php esc_html_e( 'Drop your .json file here, or', 'header-footer-builder-for-elementor' ); ?>
                        <label for="thfb-import-file" class="thfb-import-browse"><?php esc_html_e( 'browse', 'header-footer-builder-for-elementor' ); ?></label>
                    </p>
                    <p class="thfb-import-hint"><?php esc_html_e( 'Only .json files exported by Turbo H&F Builder are supported.', 'header-footer-builder-for-elementor' ); ?></p>
                    <input type="file" id="thfb-import-file" accept=".json" style="display:none;">
                </div>

                <!-- Selected file info -->
                <div id="thfb-import-file-info" style="display:none;" class="thfb-import-file-info">
                    <span class="dashicons dashicons-media-default"></span>
                    <span id="thfb-import-filename"></span>
                    <button id="thfb-import-clear" class="thfb-import-clear-btn">&times;</button>
                </div>

                <label class="thfb-field-label" style="margin-top:16px;">
                    <?php esc_html_e( 'Import As', 'header-footer-builder-for-elementor' ); ?>
                </label>
                <div style="display:flex;gap:12px;margin-bottom:4px;">
                    <label class="thfb-toggle-label" style="gap:8px;font-weight:600;">
                        <input type="radio" name="thfb_import_type" id="thfb-import-type-header" value="header" checked>
                        <?php esc_html_e( 'Header Template', 'header-footer-builder-for-elementor' ); ?>
                    </label>
                    <label class="thfb-toggle-label" style="gap:8px;font-weight:600;">
                        <input type="radio" name="thfb_import_type" id="thfb-import-type-footer" value="footer">
                        <?php esc_html_e( 'Footer Template', 'header-footer-builder-for-elementor' ); ?>
                    </label>
                </div>

                <label class="thfb-field-label" style="margin-top:16px;">
                    <?php esc_html_e( 'Template Name', 'header-footer-builder-for-elementor' ); ?>
                    <span class="thfb-optional"><?php esc_html_e( '(leave blank to use original name)', 'header-footer-builder-for-elementor' ); ?></span>
                </label>
                <input type="text" id="thfb-import-title" class="thfb-input" placeholder="<?php esc_attr_e( 'e.g. Imported Header', 'header-footer-builder-for-elementor' ); ?>">
                <div id="thfb-import-error" class="thfb-import-error" style="display:none;"></div>
                <div id="thfb-import-success" class="thfb-import-success" style="display:none;"></div>

            </div>
            <div class="thfb-modal-footer">
                <button class="thfb-btn thfb-btn-primary" id="thfb-import-submit" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Import Template', 'header-footer-builder-for-elementor' ); ?>
                </button>
                <button class="thfb-btn thfb-btn-ghost" id="thfb-import-cancel"><?php esc_html_e( 'Cancel', 'header-footer-builder-for-elementor' ); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/* ─────────────────────────────────────────────────────────────
   Video Modal HTML
───────────────────────────────────────────────────────────── */
function tahefobu_render_video_modal() {
    // YouTube video ID extracted from the tutorial URL
    $video_id = '4psOlhRV78E';
    ?>
    <div id="thfb-video-modal" class="thfb-modal-overlay" style="display:none;">
        <div class="thfb-modal thfb-video-modal-inner">
            <div class="thfb-modal-header">
                <h2><?php esc_html_e( 'How It Works — Tutorial', 'header-footer-builder-for-elementor' ); ?></h2>
                <button class="thfb-modal-close" id="thfb-video-close">&times;</button>
            </div>
            <div class="thfb-video-wrap">
                <iframe
                    id="thfb-video-iframe"
                    src=""
                    data-src="https://www.youtube.com/embed/<?php echo esc_attr( $video_id ); ?>?autoplay=1&rel=0"
                    title="<?php esc_attr_e( 'Turbo H&F Builder Tutorial', 'header-footer-builder-for-elementor' ); ?>"
                    frameborder="0"
                    allow="autoplay; encrypted-media; picture-in-picture"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </div>
    <script>
    jQuery( function ( $ ) {
        function openVideoModal() {
            var $iframe = $( '#thfb-video-iframe' );
            $iframe.attr( 'src', $iframe.data( 'src' ) );
            $( '#thfb-video-modal' ).fadeIn( 180 );
        }
        function closeVideoModal() {
            $( '#thfb-video-modal' ).fadeOut( 180 );
            $( '#thfb-video-iframe' ).attr( 'src', '' ); // stop video
        }
        $( '#thfb-watch-video-btn' ).on( 'click', openVideoModal );
        $( '#thfb-video-close' ).on( 'click', closeVideoModal );
        $( '#thfb-video-modal' ).on( 'click', function ( e ) {
            if ( $( e.target ).hasClass( 'thfb-modal-overlay' ) ) closeVideoModal();
        } );
        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closeVideoModal();
        } );
    } );
    </script>
    <?php
}

/* ─────────────────────────────────────────────────────────────
   14. Elementor / WooCommerce support (unchanged)
───────────────────────────────────────────────────────────── */
add_action( 'elementor/init', function () {
    if ( post_type_exists( 'tahefobu_single_template' ) ) {
        add_post_type_support( 'tahefobu_single_template', 'elementor' );
    }
} );

if ( class_exists( 'WooCommerce' ) ) {
    add_action( 'wp_enqueue_scripts', function () {
        if ( is_product() && class_exists( '\Elementor\Plugin' ) ) {
            $frontend = \Elementor\Plugin::instance()->frontend;
            $frontend->enqueue_styles();
            $frontend->enqueue_scripts();
        }
    } );
}

add_filter( 'elementor/frontend/print_css', '__return_true' );
