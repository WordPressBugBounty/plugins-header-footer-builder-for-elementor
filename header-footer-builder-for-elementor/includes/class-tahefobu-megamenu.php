<?php
/**
 * Mega Menu — ElementsKit-style mega menu module.
 *
 * Mirrors the ElementsKit Mega Menu feature set:
 *   - Per-menu-item settings (enable, icon, width type, position type,
 *     Ajax load, mobile submenu content type) stored as JSON post meta.
 *   - A tabbed admin modal (Content / Icon / Settings) opened from a
 *     "Mega Menu" button on each top-level menu item in Appearance → Menus.
 *   - A template select + "Create Mega Menu Template" button in the Content tab.
 *   - A menu-level "Enable this menu for Megamenu content" metabox.
 *   - REST endpoints to save/get settings and fetch megamenu content (Ajax load).
 *   - A custom Walker that renders icons, submenu indicators, width
 *     data attributes, position classes, and the Elementor megamenu panel.
 *
 * @package Header_Footer_Builder_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAHEFOBU_Mega_Menu {

	const META_KEY             = '_tahefobu_megamenu_settings';
	const SETTINGS_OPTION      = 'tahefobu_options';
	const MEGAMENU_SETTINGS_KEY = 'megamenu_settings';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		// Per-menu-item "Mega Menu" trigger in Appearance → Menus.
		add_action( 'wp_nav_menu_item_custom_fields', [ __CLASS__, 'render_item_fields' ], 10, 5 );

		// Settings modal.
		add_action( 'admin_footer', [ __CLASS__, 'render_modals' ] );

		// Admin assets on the nav-menus screen.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

		// Menu-level metabox save (runs during the nav-menu update).
		add_action( 'admin_head', [ __CLASS__, 'save_menu_settings' ] );

		// REST API.
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	/**
	 * Default per-item settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return [
			'menu_id'                         => 0,
			'menu_has_child'                  => '',
			'menu_enable'                     => 0,
			'menu_icon'                       => '',
			'menu_icon_color'                 => '',
			'mobile_submenu_content_type'     => 'builder_content',
			'vertical_megamenu_position_type' => 'relative_position',
			'vertical_menu_width'             => '',
			'megamenu_width_type'             => 'default_width',
			'megamenu_ajax_load'              => 'no',
			'template'                        => 0,
		];
	}

	/**
	 * Get a menu item's mega menu settings, migrating any legacy array format.
	 *
	 * @param int $item_id Nav menu item ID.
	 * @return array<string,mixed>
	 */
	public static function get_item_settings( $item_id ) {
		$item_id = absint( $item_id );
		if ( ! $item_id ) {
			return self::defaults();
		}

		$raw = get_post_meta( $item_id, self::META_KEY, true );

		$data = [];
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$data    = is_array( $decoded ) ? $decoded : [];
		} elseif ( is_array( $raw ) ) {
			// Legacy settings were stored as a serialized array.
			$data = $raw;
		}

		// Migrate legacy format (enable / template / width / width_value).
		if ( isset( $data['enable'] ) || isset( $data['template'] ) || isset( $data['width'] ) ) {
			$migrated = self::defaults();
			$migrated['menu_enable'] = empty( $data['enable'] ) ? 0 : 1;
			if ( ! empty( $data['template'] ) ) {
				$migrated['template'] = absint( $data['template'] );
			}
			if ( ! empty( $data['width'] ) ) {
				$migrated['megamenu_width_type'] = sanitize_key( $data['width'] );
			}
			if ( ! empty( $data['width_value'] ) ) {
				$migrated['vertical_menu_width'] = preg_replace( '/[^0-9]/', '', $data['width_value'] );
			}
			$data = array_merge( $migrated, $data );
		}

		// Migrate the earlier "content_post" experiment to the template field.
		if ( empty( $data['template'] ) && ! empty( $data['content_post'] ) ) {
			$data['template'] = absint( $data['content_post'] );
		}

		return wp_parse_args( $data, self::defaults() );
	}

	/**
	 * Save a menu item's settings as JSON.
	 *
	 * @param int   $item_id  Nav menu item ID.
	 * @param array $settings Settings to persist (already sanitized by caller).
	 */
	public static function save_item_settings( $item_id, $settings ) {
		$item_id = absint( $item_id );
		if ( ! $item_id ) {
			return false;
		}
		$settings = wp_parse_args( (array) $settings, self::defaults() );
		$settings['menu_id'] = $item_id;
		return update_post_meta( $item_id, self::META_KEY, wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Menu-level megamenu settings.
	 *
	 * @param string $menu_slug Menu slug.
	 * @return array<string,mixed>
	 */
	public static function get_menu_settings( $menu_slug ) {
		$term = get_term_by( 'slug', $menu_slug, 'nav_menu' );
		if ( ! $term ) {
			return [ 'is_enabled' => '1' ];
		}

		$all  = get_option( self::SETTINGS_OPTION, [] );
		$data = isset( $all[ self::MEGAMENU_SETTINGS_KEY ] ) ? (array) $all[ self::MEGAMENU_SETTINGS_KEY ] : [];
		return isset( $data[ 'menu_location_' . $term->term_id ] ) ? (array) $data[ 'menu_location_' . $term->term_id ] : [ 'is_enabled' => '1' ];
	}

	/**
	 * Whether the given menu has megamenu enabled.
	 *
	 * Enabled by default so the dedicated Mega Menu widget works out of the box;
	 * explicitly disabling the metabox turns it off.
	 *
	 * @param string $menu_slug Menu slug (or ID/name).
	 * @return bool
	 */
	public static function is_megamenu( $menu_slug ) {
		$settings   = self::get_menu_settings( $menu_slug );
		$is_enabled = isset( $settings['is_enabled'] ) ? $settings['is_enabled'] : '1';
		return '0' !== $is_enabled;
	}

	/**
	 * Whether a menu item is a megamenu item.
	 *
	 * @param array<string,mixed> $settings Item settings.
	 * @return bool
	 */
	public static function is_megamenu_item( $settings ) {
		return ! empty( $settings['menu_enable'] ) && class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Published Elementor-eligible templates for the template select.
	 *
	 * @return array<int,string> template_id => label
	 */
	public static function get_template_options() {
		static $options = null;
		if ( null !== $options ) {
			return $options;
		}

		$options = [];

		$post_types = [ 'elementor_library', 'page', 'tahefobu_header', 'tahefobu_footer' ];

		foreach ( $post_types as $pt ) {
			if ( ! post_type_exists( $pt ) ) {
				continue;
			}

			$pt_object = get_post_type_object( $pt );
			$pt_label  = $pt_object ? $pt_object->labels->singular_name : $pt;

			$posts = get_posts( [
				'post_type'      => $pt,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			] );

			foreach ( $posts as $post ) {
				if ( ! get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
					continue;
				}
				$options[ $post->ID ] = $post->post_title . ' (' . $pt_label . ')';
			}
		}

		return $options;
	}

	/**
	 * Create a new Elementor "Section" template for a mega menu.
	 *
	 * @param string $title Template title.
	 * @return int Template post ID, or 0 on failure.
	 */
	public static function create_template( $title ) {
		if ( ! post_type_exists( 'elementor_library' ) || ! current_user_can( 'edit_posts' ) ) {
			return 0;
		}

		$title = $title ? sanitize_text_field( $title ) : __( 'Mega Menu Template', 'header-footer-builder-for-elementor' );

		$post_id = wp_insert_post( [
			'post_title'  => $title,
			'post_type'   => 'elementor_library',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'section' );
		update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );

		return (int) $post_id;
	}

	/**
	 * Render Elementor content for a post, including its CSS.
	 *
	 * @param int $content_id Elementor-enabled post ID.
	 * @return string
	 */
	public static function render_elementor_content( $content_id ) {
		$content_id = absint( $content_id );
		if ( ! $content_id || 'publish' !== get_post_status( $content_id ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}

		static $rendering = [];
		if ( isset( $rendering[ $content_id ] ) ) {
			return '';
		}
		$rendering[ $content_id ] = true;
		$output = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $content_id, true );
		unset( $rendering[ $content_id ] );

		return $output;
	}

	/**
	 * Curated Font Awesome icon list for the icon picker.
	 *
	 * @return array<int,string>
	 */
	public static function get_icons() {
		return [
			'fas fa-home', 'fas fa-user', 'fas fa-users', 'fas fa-user-plus', 'fas fa-user-cog',
			'fas fa-address-book', 'fas fa-address-card', 'fas fa-id-card', 'fas fa-id-badge',
			'fas fa-envelope', 'fas fa-envelope-open', 'fas fa-phone', 'fas fa-phone-alt',
			'fas fa-fax', 'fas fa-mobile-alt', 'fas fa-tablet-alt', 'fas fa-laptop', 'fas fa-desktop',
			'fas fa-globe', 'fas fa-globe-americas', 'fas fa-map', 'fas fa-map-marker-alt', 'fas fa-map-pin',
			'fas fa-location-arrow', 'fas fa-compass', 'fas fa-directions', 'fas fa-route',
			'fas fa-search', 'fas fa-search-plus', 'fas fa-search-location', 'fas fa-filter',
			'fas fa-cog', 'fas fa-cogs', 'fas fa-wrench', 'fas fa-tools', 'fas fa-sliders-h',
			'fas fa-tachometer-alt', 'fas fa-chart-bar', 'fas fa-chart-line', 'fas fa-chart-pie',
			'fas fa-chart-area', 'fas fa-percentage', 'fas fa-calculator', 'fas fa-dollar-sign',
			'fas fa-coins', 'fas fa-money-bill', 'fas fa-credit-card', 'fas fa-wallet', 'fas fa-piggy-bank',
			'fas fa-shopping-cart', 'fas fa-shopping-bag', 'fas fa-shopping-basket', 'fas fa-tags', 'fas fa-tag',
			'fas fa-gift', 'fas fa-trophy', 'fas fa-award', 'fas fa-medal', 'fas fa-star', 'fas fa-star-half-alt',
			'fas fa-heart', 'fas fa-thumbs-up', 'fas fa-thumbs-down', 'fas fa-smile', 'fas fa-frown',
			'fas fa-grin', 'fas fa-laugh', 'fas fa-fire', 'fas fa-bolt', 'fas fa-lightbulb', 'fas fa-moon',
			'fas fa-sun', 'fas fa-cloud', 'fas fa-cloud-sun', 'fas fa-umbrella', 'fas fa-snowflake',
			'fas fa-calendar', 'fas fa-calendar-alt', 'fas fa-calendar-check', 'fas fa-calendar-plus',
			'fas fa-clock', 'fas fa-hourglass-half', 'fas fa-history', 'fas fa-stopwatch', 'fas fa-bell',
			'fas fa-bell-slash', 'fas fa-broadcast-tower', 'fas fa-rss', 'fas fa-newspaper', 'fas fa-file',
			'fas fa-file-alt', 'fas fa-file-pdf', 'fas fa-file-image', 'fas fa-file-video', 'fas fa-folder',
			'fas fa-folder-open', 'fas fa-clipboard', 'fas fa-clipboard-check', 'fas fa-clipboard-list',
			'fas fa-book', 'fas fa-book-open', 'fas fa-bookmark', 'fas fa-graduation-cap', 'fas fa-university',
			'fas fa-school', 'fas fa-flask', 'fas fa-atom', 'fas fa-dna', 'fas fa-microscope',
			'fas fa-heartbeat', 'fas fa-briefcase-medical', 'fas fa-hospital', 'fas fa-ambulance',
			'fas fa-user-md', 'fas fa-stethoscope', 'fas fa-pills', 'fas fa-prescription', 'fas fa-syringe',
			'fas fa-lock', 'fas fa-lock-open', 'fas fa-shield-alt', 'fas fa-user-shield', 'fas fa-key',
			'fas fa-check', 'fas fa-check-circle', 'fas fa-check-double', 'fas fa-times', 'fas fa-times-circle',
			'fas fa-plus', 'fas fa-plus-circle', 'fas fa-minus', 'fas fa-minus-circle', 'fas fa-pencil-alt',
			'fas fa-edit', 'fas fa-trash', 'fas fa-trash-alt', 'fas fa-print', 'fas fa-download', 'fas fa-upload',
			'fas fa-share', 'fas fa-share-alt', 'fas fa-link', 'fas fa-unlink', 'fas fa-eye', 'fas fa-eye-slash',
			'fas fa-camera', 'fas fa-video', 'fas fa-image', 'fas fa-images', 'fas fa-music', 'fas fa-headphones',
			'fas fa-microphone', 'fas fa-play', 'fas fa-pause', 'fas fa-stop', 'fas fa-forward', 'fas fa-backward',
			'fas fa-bus', 'fas fa-car', 'fas fa-car-side', 'fas fa-taxi', 'fas fa-truck', 'fas fa-shipping-fast',
			'fas fa-plane', 'fas fa-plane-departure', 'fas fa-train', 'fas fa-subway', 'fas fa-bicycle',
			'fas fa-ship', 'fas fa-anchor', 'fas fa-rocket', 'fas fa-satellite', 'fas fa-robot',
			'fas fa-building', 'fas fa-store', 'fas fa-store-alt', 'fas fa-industry', 'fas fa-warehouse',
			'fas fa-home', 'fas fa-city', 'fas fa-archway', 'fas fa-couch', 'fas fa-bed', 'fas fa-bath',
			'fas fa-utensils', 'fas fa-coffee', 'fas fa-beer', 'fas fa-wine-glass-alt', 'fas fa-pizza-slice',
			'fas fa-hamburger', 'fas fa-ice-cream', 'fas fa-apple-alt', 'fas fa-carrot', 'fas fa-leaf',
			'fas fa-tree', 'fas fa-seedling', 'fas fa-paw', 'fas fa-crow', 'fas fa-dove', 'fas fa-fish',
			'fas fa-paper-plane', 'fas fa-comment', 'fas fa-comments', 'fas fa-comment-dots', 'fas fa-quote-left',
			'fas fa-exclamation', 'fas fa-exclamation-circle', 'fas fa-exclamation-triangle',
			'fas fa-info', 'fas fa-info-circle', 'fas fa-question', 'fas fa-question-circle',
			'fas fa-bug', 'fas fa-code', 'fas fa-code-branch', 'fas fa-terminal', 'fas fa-database',
			'fas fa-server', 'fas fa-cloud-upload-alt', 'fas fa-cloud-download-alt', 'fas fa-sync-alt',
			'fas fa-redo-alt', 'fas fa-undo-alt', 'fas fa-sitemap', 'fas fa-layer-group', 'fas fa-project-diagram',
			'fas fa-boxes', 'fas fa-box', 'fas fa-box-open', 'fas fa-archive', 'fas fa-cube', 'fas fa-cubes',
			'fas fa-puzzle-piece', 'fas fa-chess', 'fas fa-gamepad', 'fas fa-dice', 'fas fa-futbol',
			'fas fa-basketball-ball', 'fas fa-baseball-ball', 'fas fa-football-ball', 'fas fa-golf-ball',
			'fas fa-swimmer', 'fas fa-dumbbell', 'fas fa-running', 'fas fa-hiking', 'fas fa-mountain',
			'fas fa-flag', 'fas fa-flag-checkered', 'fas fa-bullhorn', 'fas fa-megaphone', 'fas fa-ad',
			'fas fa-crown', 'fas fa-gem', 'fas fa-ring', 'fas fa-magic', 'fas fa-broom', 'fas fa-spray-can',
			'fas fa-brush', 'fas fa-palette', 'fas fa-paint-brush', 'fas fa-paint-roller', 'fas fa-pen-nib',
			'fas fa-swatchbook', 'fas fa-vector-square', 'fas fa-drafting-compass', 'fas fa-ruler',
			'fas fa-ruler-combined', 'fas fa-tshirt', 'fas fa-socks', 'fas fa-shoe-prints', 'fas fa-hat-cowboy',
			'fas fa-mask', 'fas fa-theater-masks', 'fas fa-music', 'fas fa-guitar', 'fas fa-drum',
			'fas fa-camera-retro', 'fas fa-film', 'fas fa-ticket-alt', 'fas fa-theater-masks',
			'fas fa-signal', 'fas fa-wifi', 'fas fa-bluetooth', 'fas fa-battery-full', 'fas fa-battery-half',
			'fas fa-plug', 'fas fa-power-off', 'fas fa-toggle-on', 'fas fa-toggle-off', 'fas fa-random',
			'fas fa-sort', 'fas fa-sort-down', 'fas fa-sort-up', 'fas fa-chevron-up', 'fas fa-chevron-down',
			'fas fa-chevron-left', 'fas fa-chevron-right', 'fas fa-arrow-up', 'fas fa-arrow-down',
			'fas fa-arrow-left', 'fas fa-arrow-right', 'fas fa-arrows-alt', 'fas fa-expand',
			'fas fa-compress', 'fas fa-expand-arrows-alt', 'fas fa-angle-up', 'fas fa-angle-down',
			'fas fa-angle-left', 'fas fa-angle-right', 'fas fa-angle-double-up', 'fas fa-angle-double-down',
		];
	}

	/**
	 * Enqueue admin assets on the nav-menus screen.
	 */
	public static function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'nav-menus' !== $screen->base ) {
			return;
		}

		$ver = defined( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION' )
			? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
			: '1.3.0';
		$url = TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/';

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Font Awesome (icons in the picker).
		wp_enqueue_style( 'tahefobu-font-awesome', $url . 'vendor/font-awesome/css/all.min.css', [], '5.15.4' );

		// Icon picker.
		wp_enqueue_style( 'tahefobu-fonticonpicker', $url . 'vendor/fonticonpicker/css/jquery.fonticonpicker.css', [], $ver );
		wp_enqueue_script( 'tahefobu-fonticonpicker', $url . 'vendor/fonticonpicker/jquery.fonticonpicker.min.js', [ 'jquery' ], $ver, true );

		wp_enqueue_style( 'tahefobu-megamenu-admin', $url . 'css/megamenu-admin.css', [], $ver );
		wp_enqueue_script( 'tahefobu-megamenu-admin', $url . 'js/megamenu-admin.js', [ 'jquery', 'wp-color-picker', 'tahefobu-fonticonpicker' ], $ver, true );

		$menu_id     = self::current_menu_id();
		$is_enabled  = '1';
		if ( $menu_id ) {
			$term = get_term( $menu_id, 'nav_menu' );
			if ( $term && ! is_wp_error( $term ) ) {
				$menu_settings = self::get_menu_settings( $term->slug );
				$is_enabled    = isset( $menu_settings['is_enabled'] ) ? $menu_settings['is_enabled'] : '1';
			}
		}

		wp_localize_script( 'tahefobu-megamenu-admin', 'tahefobuMegaMenu', [
			'restUrl'           => esc_url_raw( rest_url( 'tahefobu/v1/' ) ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'menuId'            => absint( $menu_id ),
			'megamenuIsEnabled' => ( '1' === $is_enabled ) ? '1' : '0',
		] );
	}

	/**
	 * Determine the currently selected nav menu ID on the nav-menus screen.
	 *
	 * @return int
	 */
	public static function current_menu_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu id from URL.
		$selected = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : 0;
		if ( $selected ) {
			return $selected;
		}

		$nav_menus = wp_get_nav_menus( [ 'orderby' => 'name' ] );
		if ( ! empty( $nav_menus ) ) {
			return (int) $nav_menus[0]->term_id;
		}

		return 0;
	}

	/**
	 * Render the per-item "Mega Menu" trigger in Appearance → Menus.
	 *
	 * @param int    $item_id Current menu item ID.
	 * @param object $item    Menu item data object.
	 * @param int    $depth   Menu item depth.
	 * @param array  $args    Menu args.
	 * @param int    $id      Current object ID.
	 */
	public static function render_item_fields( $item_id, $item, $depth, $args, $id ) {
		if ( $depth > 0 ) {
			return;
		}

		$settings = self::get_item_settings( $item_id );
		$enabled  = ! empty( $settings['menu_enable'] );
		?>
		<p class="field-tahefobu-megamenu description description-wide">
			<button type="button"
				class="button-link tahefobu-megamenu-trigger"
				data-item-id="<?php echo esc_attr( $item_id ); ?>">
				<span class="dashicons dashicons-menu-alt"></span>
				<?php esc_html_e( 'Mega Menu', 'header-footer-builder-for-elementor' ); ?>
			</button>
			<span class="tahefobu-megamenu-status <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
				<?php echo $enabled ? esc_html__( 'Enabled', 'header-footer-builder-for-elementor' ) : esc_html__( 'Disabled', 'header-footer-builder-for-elementor' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Render the settings modal + builder (iframe) modal in the admin footer.
	 */
	public static function render_modals() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'nav-menus' !== $screen->base ) {
			return;
		}

		?>
		<div class="tahefobu-megamenu-modal" id="tahefobu-megamenu-modal" aria-hidden="true">
			<div class="tahefobu-megamenu-modal-backdrop"></div>
			<div class="tahefobu-megamenu-modal-dialog" role="dialog" aria-modal="true">
				<div class="tahefobu-megamenu-modal-content">
					<div class="tahefobu-megamenu-modal-header">
						<ul class="tahefobu-megamenu-tabs" role="tablist">
							<li role="presentation" class="tahefobu-active">
								<a href="#tahefobu-tab-content" class="tahefobu-tab-link" data-tab="content" role="tab"><?php esc_html_e( 'Content', 'header-footer-builder-for-elementor' ); ?></a>
							</li>
							<li role="presentation">
								<a href="#tahefobu-tab-icon" class="tahefobu-tab-link" data-tab="icon" role="tab"><?php esc_html_e( 'Icon', 'header-footer-builder-for-elementor' ); ?></a>
							</li>
							<li role="presentation">
								<a href="#tahefobu-tab-settings" class="tahefobu-tab-link" data-tab="settings" role="tab"><?php esc_html_e( 'Settings', 'header-footer-builder-for-elementor' ); ?></a>
							</li>
						</ul>
					</div>

					<div class="tahefobu-megamenu-modal-body">
						<div role="tabpanel" class="tahefobu-tab-pane tahefobu-active" id="tahefobu-tab-content">
							<?php if ( defined( 'ELEMENTOR_VERSION' ) ) : ?>
								<div class="tahefobu-switch-wrap">
									<input type="checkbox" value="1" id="tahefobu-menu-item-enable" />
									<label for="tahefobu-menu-item-enable"><span><em></em></span></label>
								</div>
								<div id="tahefobu-menu-builder-warper">
									<small class="tahefobu-menu-mega-submenu enabled_item"><?php esc_html_e( 'Megamenu enabled', 'header-footer-builder-for-elementor' ); ?></small>
									<small class="tahefobu-menu-mega-submenu disabled_item"><?php esc_html_e( 'Megamenu disabled', 'header-footer-builder-for-elementor' ); ?></small>

									<div class="tahefobu-template-select-wrap">
										<label for="tahefobu-menu-template-field"><strong><?php esc_html_e( 'Select Template', 'header-footer-builder-for-elementor' ); ?></strong></label>
										<select id="tahefobu-menu-template-field">
											<option value=""><?php esc_html_e( '— Select Template —', 'header-footer-builder-for-elementor' ); ?></option>
											<?php foreach ( self::get_template_options() as $template_id => $template_label ) : ?>
												<option value="<?php echo esc_attr( $template_id ); ?>"><?php echo esc_html( $template_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>

									<button type="button" id="tahefobu-menu-create-template" class="button tahefobu-create-template">
										<span class="dashicons dashicons-plus-alt2"></span>
										<?php esc_html_e( 'Create Mega Menu Template', 'header-footer-builder-for-elementor' ); ?>
									</button>

									<div id="tahefobu-mobile-submenu-content-type">
										<strong><?php esc_html_e( 'Mobile menu shows:', 'header-footer-builder-for-elementor' ); ?></strong>
										<span><input type="radio" name="content_type" checked value="builder_content"> <?php esc_html_e( 'Mega menu template', 'header-footer-builder-for-elementor' ); ?></span>
										<span><input type="radio" name="content_type" value="submenu_list"> <?php esc_html_e( 'WordPress submenu', 'header-footer-builder-for-elementor' ); ?></span>
									</div>
								</div>
							<?php else : ?>
								<p class="tahefobu-no-elementor-notice">
									<?php esc_html_e( 'This plugin requires Elementor to edit megamenu content.', 'header-footer-builder-for-elementor' ); ?>
								</p>
							<?php endif; ?>
						</div>

						<div role="tabpanel" class="tahefobu-tab-pane" id="tahefobu-tab-icon">
							<table class="tahefobu-option-table">
								<tbody>
									<tr>
										<td><strong><?php esc_html_e( 'Choose icon color', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright">
											<input type="text" value="#bada55" class="tahefobu-menu-wpcolor-picker" id="tahefobu-menu-icon-color-field" />
										</td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Select icon', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright">
											<select id="tahefobu-menu-icon-field" class="tahefobu-menu-icon-picker">
												<option value=""><?php esc_html_e( 'No icon', 'header-footer-builder-for-elementor' ); ?></option>
												<?php foreach ( self::get_icons() as $icon_class ) : ?>
													<option value="<?php echo esc_attr( $icon_class ); ?>"><?php echo esc_html( $icon_class ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div role="tabpanel" class="tahefobu-tab-pane" id="tahefobu-tab-settings">
							<table class="tahefobu-option-table">
								<tbody class="tahefobu-menu-settings-panel">
									<tr id="tahefobu-megamenu-width-type">
										<td><strong><?php esc_html_e( 'Mega Menu Width as:', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright tahefobu-width-lists">
											<input type="radio" name="width_type" id="width_type_default" value="default_width" checked>
											<label for="width_type_default"><?php esc_html_e( 'Default Width', 'header-footer-builder-for-elementor' ); ?></label>
											<input type="radio" id="width_type_full" name="width_type" value="full_width">
											<label for="width_type_full"><?php esc_html_e( 'Full Width', 'header-footer-builder-for-elementor' ); ?></label>
											<input type="radio" id="width_type_custom" name="width_type" value="custom_width">
											<label for="width_type_custom"><?php esc_html_e( 'Custom Width', 'header-footer-builder-for-elementor' ); ?></label>
										</td>
									</tr>
									<tr class="tahefobu-menu-width-container">
										<td><strong><?php esc_html_e( 'Menu Width', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright">
											<input type="text" placeholder="<?php esc_attr_e( '750px', 'header-footer-builder-for-elementor' ); ?>" id="tahefobu-menu-vertical-menu-width-field" />
										</td>
									</tr>
									<tr id="tahefobu-vertical-megamenu-position-type">
										<td><strong><?php esc_html_e( 'Mega Menu Position as:', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright">
											<input type="radio" id="position_type_top" name="position_type" value="top_position">
											<label for="position_type_top"><?php esc_html_e( 'Default', 'header-footer-builder-for-elementor' ); ?></label>
											<input type="radio" name="position_type" id="position_type_relative" checked value="relative_position">
											<label for="position_type_relative"><?php esc_html_e( 'Relative', 'header-footer-builder-for-elementor' ); ?></label>
										</td>
									</tr>
									<tr id="tahefobu-enable-ajax-load">
										<td><strong><?php esc_html_e( 'Enable Ajax Load:', 'header-footer-builder-for-elementor' ); ?></strong></td>
										<td class="tahefobu-alignright">
											<input type="radio" id="ajax_load_yes" name="megamenu_ajax_load" value="yes">
											<label for="ajax_load_yes"><?php esc_html_e( 'Yes', 'header-footer-builder-for-elementor' ); ?></label>
											<input type="radio" id="ajax_load_no" name="megamenu_ajax_load" checked value="no">
											<label for="ajax_load_no"><?php esc_html_e( 'No', 'header-footer-builder-for-elementor' ); ?></label>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div class="tahefobu-megamenu-modal-footer">
						<input type="hidden" id="tahefobu-menu-modal-menu-id">
						<input type="hidden" id="tahefobu-menu-modal-menu-has-child">
						<div class="tahefobu-modal-controls">
							<div class="tahefobu-left-content">
								<button class="tahefobu-btn-modal-close button" type="button"><?php esc_html_e( 'Cancel', 'header-footer-builder-for-elementor' ); ?></button>
							</div>
							<div class="tahefobu-right-content">
								<span class="spinner"></span>
								<button type="button" class="tahefobu-menu-item-save button button-primary"><?php esc_html_e( 'Save', 'header-footer-builder-for-elementor' ); ?></button>
							</div>
						</div>
					</div>
					<span id="tahefobu-menu-modal-spinner" class="spinner"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the menu-level megamenu setting during a nav-menu update.
	 */
	public static function save_menu_settings() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'nav-menus' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		if ( ! isset( $_POST['update-nav-menu-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ), 'update-nav_menu' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu id from the URL, nonce verified above.
		$menu_id    = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$is_enabled = isset( $_POST['tahefobu_megamenu_is_enabled'] ) ? '1' : '0';

		if ( ! $menu_id ) {
			return;
		}

		$all                                     = get_option( self::SETTINGS_OPTION, [] );
		$all                                     = is_array( $all ) ? $all : [];
		$all[ self::MEGAMENU_SETTINGS_KEY ]       = isset( $all[ self::MEGAMENU_SETTINGS_KEY ] ) && is_array( $all[ self::MEGAMENU_SETTINGS_KEY ] ) ? $all[ self::MEGAMENU_SETTINGS_KEY ] : [];
		$all[ self::MEGAMENU_SETTINGS_KEY ][ 'menu_location_' . $menu_id ] = [ 'is_enabled' => $is_enabled ];
		update_option( self::SETTINGS_OPTION, $all, false );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_rest_routes() {
		register_rest_route( 'tahefobu/v1', '/megamenu/save_menuitem_settings', [
			'methods'             => [ 'POST', 'GET' ],
			'callback'            => [ __CLASS__, 'rest_save_menuitem_settings' ],
			'permission_callback' => [ __CLASS__, 'rest_permission_save' ],
		] );

		register_rest_route( 'tahefobu/v1', '/megamenu/get_menuitem_settings', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'rest_get_menuitem_settings' ],
			'permission_callback' => [ __CLASS__, 'rest_permission_manage' ],
		] );

		register_rest_route( 'tahefobu/v1', '/megamenu/get_megamenu_content', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'rest_get_megamenu_content' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'tahefobu/v1', '/megamenu/create_template', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rest_create_template' ],
			'permission_callback' => [ __CLASS__, 'rest_permission_edit' ],
		] );
	}

	/**
	 * Permission: save settings.
	 *
	 * @return bool
	 */
	public static function rest_permission_save() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission: read settings.
	 *
	 * @return bool
	 */
	public static function rest_permission_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission: edit content.
	 *
	 * @return bool
	 */
	public static function rest_permission_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize a hex color.
	 *
	 * @param mixed $color Raw color.
	 * @return string
	 */
	private static function sanitize_color( $color ) {
		$color = sanitize_text_field( wp_unslash( $color ) );
		return sanitize_hex_color( $color ) ? $color : '';
	}

	/**
	 * Sanitize icon classes.
	 *
	 * @param mixed $icon_class Raw classes.
	 * @return string
	 */
	private static function sanitize_icon_class( $icon_class ) {
		$classes = preg_split( '/\s+/', sanitize_text_field( wp_unslash( $icon_class ) ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		return implode( ' ', $classes );
	}

	/**
	 * Sanitize the incoming menuitem settings payload.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string,mixed>
	 */
	private static function sanitize_menuitem_settings( $settings ) {
		$settings = is_array( $settings ) ? wp_unslash( $settings ) : [];

		return [
			'menu_id'                         => isset( $settings['menu_id'] ) ? absint( $settings['menu_id'] ) : 0,
			'menu_has_child'                  => isset( $settings['menu_has_child'] ) ? sanitize_text_field( $settings['menu_has_child'] ) : '',
			'menu_enable'                     => empty( $settings['menu_enable'] ) ? 0 : 1,
			'menu_icon'                       => isset( $settings['menu_icon'] ) ? self::sanitize_icon_class( $settings['menu_icon'] ) : '',
			'menu_icon_color'                 => isset( $settings['menu_icon_color'] ) ? self::sanitize_color( $settings['menu_icon_color'] ) : '',
			'mobile_submenu_content_type'     => isset( $settings['mobile_submenu_content_type'] ) ? sanitize_key( $settings['mobile_submenu_content_type'] ) : 'builder_content',
			'vertical_megamenu_position_type' => isset( $settings['vertical_megamenu_position_type'] ) ? sanitize_key( $settings['vertical_megamenu_position_type'] ) : 'relative_position',
			'vertical_menu_width'             => isset( $settings['vertical_menu_width'] ) ? sanitize_text_field( $settings['vertical_menu_width'] ) : '',
			'megamenu_width_type'             => isset( $settings['megamenu_width_type'] ) ? sanitize_key( $settings['megamenu_width_type'] ) : 'default_width',
			'megamenu_ajax_load'              => isset( $settings['megamenu_ajax_load'] ) && 'yes' === $settings['megamenu_ajax_load'] ? 'yes' : 'no',
			'template'                        => isset( $settings['template'] ) ? absint( $settings['template'] ) : 0,
		];
	}

	/**
	 * REST: save menuitem settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function rest_save_menuitem_settings( $request ) {
		$settings = self::sanitize_menuitem_settings( $request->get_param( 'settings' ) );
		$item_id  = $settings['menu_id'];

		if ( ! $item_id ) {
			return new WP_Error( 'tahefobu_invalid_menu', __( 'Invalid menu item.', 'header-footer-builder-for-elementor' ), [ 'status' => 400 ] );
		}

		$existing          = self::get_item_settings( $item_id );
		$settings          = array_merge( $existing, $settings );
		self::save_item_settings( $item_id, $settings );

		return [
			'saved'   => 1,
			'message' => __( 'Saved', 'header-footer-builder-for-elementor' ),
		];
	}

	/**
	 * REST: get menuitem settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function rest_get_menuitem_settings( $request ) {
		$item_id = absint( $request->get_param( 'menu_id' ) );
		return self::get_item_settings( $item_id );
	}

	/**
	 * REST: get megamenu content (Ajax load).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|WP_Error
	 */
	public static function rest_get_megamenu_content( $request ) {
		$content_id = absint( $request->get_param( 'id' ) );

		if ( ! $content_id || 'publish' !== get_post_status( $content_id ) ) {
			return new WP_Error( 'tahefobu_invalid_content', __( 'Invalid content.', 'header-footer-builder-for-elementor' ), [ 'status' => 404 ] );
		}

		return self::render_elementor_content( $content_id );
	}

	/**
	 * REST: create a new Elementor template for a mega menu.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function rest_create_template( $request ) {
		$title = sanitize_text_field( $request->get_param( 'title' ) );

		$template_id = self::create_template( $title );
		if ( ! $template_id ) {
			return new WP_Error( 'tahefobu_template_error', __( 'Could not create template.', 'header-footer-builder-for-elementor' ), [ 'status' => 500 ] );
		}

		return [
			'id'    => $template_id,
			'title' => get_the_title( $template_id ),
			'url'   => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
		];
	}
}

/**
 * Custom Walker for the Mega Menu widget.
 *
 * Reads per-item mega menu settings and renders icons, submenu
 * indicators, width data attributes, position classes, and the Elementor
 * megamenu panel. Mirrors ElementsKit's mega menu walker.
 */
class TAHEFOBU_Mega_Menu_Walker extends Walker_Nav_Menu {

	/**
	 * Whether this walker is rendering the mobile menu.
	 *
	 * @var bool
	 */
	public $is_mobile = false;

	/**
	 * Submenu indicator icon markup/classes.
	 *
	 * @var string
	 */
	public $submenu_indicator_icon = '';

	/**
	 * Get item meta (settings) for a menu item.
	 *
	 * @param int $menu_item_id Menu item ID.
	 * @return array<string,mixed>
	 */
	public function get_item_meta( $menu_item_id ) {
		return TAHEFOBU_Mega_Menu::get_item_settings( $menu_item_id );
	}

	/**
	 * Whether the current menu has megamenu enabled.
	 *
	 * @param string $menu_slug Menu slug.
	 * @return bool
	 */
	public function is_megamenu( $menu_slug ) {
		return TAHEFOBU_Mega_Menu::is_megamenu( $menu_slug );
	}

	/**
	 * Whether the item is a megamenu item.
	 *
	 * @param array<string,mixed> $item_meta Item settings.
	 * @return bool
	 */
	public function is_megamenu_item( $item_meta ) {
		return TAHEFOBU_Mega_Menu::is_megamenu_item( $item_meta );
	}

	/**
	 * Start a sub-menu level.
	 *
	 * @param string   $output Passed by reference.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class=\"tahefobu-dropdown tahefobu-submenu-panel\">\n";
	}

	/**
	 * End a sub-menu level.
	 *
	 * @param string   $output Passed by reference.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Start an element.
	 *
	 * @param string   $output            Passed by reference.
	 * @param WP_Post  $data_object       Menu item.
	 * @param int      $depth             Depth.
	 * @param stdClass $args              Args.
	 * @param int      $current_object_id Current object ID.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$menu_item = $data_object;
		$classes   = empty( $menu_item->classes ) ? [] : (array) $menu_item->classes;
		$classes[] = 'menu-item-' . $menu_item->ID;
		$classes[] = 'nav-item';

		$item_meta        = $this->get_item_meta( $menu_item->ID );
		$is_megamenu_item = $this->is_megamenu_item( $item_meta );
		$has_children     = in_array( 'menu-item-has-children', $classes, true );

		if ( $has_children || $is_megamenu_item ) {
			$classes[] = 'tahefobu-dropdown-has';
			$classes[] = $item_meta['vertical_megamenu_position_type'];
			$classes[] = 'tahefobu-dropdown-menu-' . $item_meta['megamenu_width_type'];
		}

		if ( $is_megamenu_item ) {
			$classes[] = 'tahefobu-megamenu-has';
		}

		if ( 'builder_content' === $item_meta['mobile_submenu_content_type'] ) {
			$classes[] = 'tahefobu-mobile-builder-content';
		}

		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$classes[] = 'active';
		}

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $menu_item, $args, $depth ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $menu_item->ID, $menu_item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
		$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

		$data_attr = '';
		switch ( $item_meta['megamenu_width_type'] ) {
			case 'full_width':
				$data_attr = ' data-vertical-menu=""';
				break;
			case 'custom_width':
				$data_attr = '' === $item_meta['vertical_menu_width'] ? ' data-vertical-menu="750px"' : ' data-vertical-menu="' . esc_attr( $item_meta['vertical_menu_width'] ) . 'px"';
				break;
			default:
				$data_attr = ' data-vertical-menu="750px"';
				break;
		}

		$indent  = str_repeat( "\t", $depth );
		$output .= $indent . '<li' . $id . $class_names . $data_attr . '>';

		$atts           = [];
		$atts['title']  = ! empty( $menu_item->attr_title ) ? $menu_item->attr_title : '';
		$atts['target'] = ! empty( $menu_item->target ) ? $menu_item->target : '';
		$atts['rel']    = ! empty( $menu_item->xfn ) ? $menu_item->xfn : '';
		$atts['href']   = ! empty( $menu_item->url ) ? $menu_item->url : '';

		$submenu_indicator = '';

		if ( 0 === $depth ) {
			$atts['class'] = 'tahefobu-menu-nav-link';
		}

		if ( $has_children || $is_megamenu_item ) {
			if ( 0 === $depth ) {
				$atts['class'] = ( ! empty( $atts['class'] ) ? $atts['class'] . ' ' : '' ) . 'tahefobu-dropdown-toggle';
			}

			if ( ! empty( $this->submenu_indicator_icon ) ) {
				$submenu_indicator .= $this->submenu_indicator_icon;
			} else {
				$submenu_indicator .= '<i class="tahefobu-submenu-indicator fas fa-angle-down" aria-hidden="true"></i>';
			}
		}

		if ( $depth > 0 ) {
			$manual_class   = array_values( $classes )[0] . ' tahefobu-dropdown-item';
			$atts['class'] = $manual_class;
		}

		if ( in_array( 'current-menu-item', (array) $menu_item->classes, true ) ) {
			$atts['class'] = ( ! empty( $atts['class'] ) ? $atts['class'] . ' ' : '' ) . 'active';
		}

		$atts       = apply_filters( 'nav_menu_link_attributes', $atts, $menu_item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$item_output  = isset( $args->before ) ? $args->before : '';
		$item_output .= '<a' . $attributes . '>';

		// Menu icon.
		if ( $this->is_megamenu( $args->menu ) && '' !== $item_meta['menu_icon'] ) {
			$icon_style   = 'color:' . sanitize_hex_color( $item_meta['menu_icon_color'] );
			$item_output .= '<i class="tahefobu-menu-icon ' . esc_attr( $item_meta['menu_icon'] ) . '" style="' . esc_attr( $icon_style ) . '"></i>';
		}

		$item_output .= isset( $args->link_before ) ? $args->link_before : '';
		$item_output .= apply_filters( 'the_title', $menu_item->title, $menu_item->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
		$item_output .= isset( $args->link_after ) ? $args->link_after : '';
		$item_output .= $submenu_indicator . '</a>';
		$item_output .= isset( $args->after ) ? $args->after : '';

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $menu_item, $depth, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
	}

	/**
	 * End an element.
	 *
	 * @param string   $output      Passed by reference.
	 * @param WP_Post  $data_object Menu item.
	 * @param int      $depth       Depth.
	 * @param stdClass $args        Args.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		if ( 0 === $depth ) {
			$item_meta = $this->get_item_meta( $data_object->ID );

			if ( $this->is_megamenu( $args->menu ) && $this->is_megamenu_item( $item_meta ) ) {
				$template_id = absint( $item_meta['template'] );

				if ( ! $this->is_mobile ) {
					// Desktop: render the Elementor panel.
					$output .= '<div class="tahefobu-megamenu-panel">';
					if ( $template_id ) {
						if ( 'yes' === $item_meta['megamenu_ajax_load'] ) {
							$output .= '<div class="tahefobu-megamenu-ajax-load" data-id="' . esc_attr( $template_id ) . '"></div>';
						} else {
							$output .= TAHEFOBU_Mega_Menu::render_elementor_content( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor output
						}
					} else {
						$output .= esc_html__( 'No template selected', 'header-footer-builder-for-elementor' );
					}
					$output .= '</div>';
				} else {
					// Mobile: builder content shows the panel; otherwise a
					// direct link keeps the item navigable.
					if ( 'builder_content' === $item_meta['mobile_submenu_content_type'] && $template_id ) {
						$output .= '<div class="tahefobu-megamenu-panel tahefobu-mobile-panel">';
						if ( 'yes' === $item_meta['megamenu_ajax_load'] ) {
							$output .= '<div class="tahefobu-megamenu-ajax-load" data-id="' . esc_attr( $template_id ) . '"></div>';
						} else {
							$output .= TAHEFOBU_Mega_Menu::render_elementor_content( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor output
						}
						$output .= '</div>';
					} else {
						$output .= '<ul class="tahefobu-dropdown tahefobu-mobile-mega-sub">';
						$output .= '<li class="menu-item"><a href="' . esc_url( $data_object->url ) . '" class="tahefobu-dropdown-item">' . esc_html( $data_object->title ) . '</a></li>';
						$output .= '</ul>';
					}
				}
			}
		}
		$output .= "</li>\n";
	}
}
