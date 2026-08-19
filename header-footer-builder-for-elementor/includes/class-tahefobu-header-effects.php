<?php
/**
 * Header Effects — Transparent → Solid on Scroll
 *
 * Registers a "Header Effects" controls section on Elementor Section and
 * Container elements, but ONLY when editing a Header Footer Builder header
 * template (tahefobu_header). Settings are stored inside the Elementor
 * template data (not post meta), exposed to the frontend via
 * `frontend_available => true` (data-settings) and `prefix_class`, and applied
 * by `assets/js/turbo-header-effects.js` on scroll.
 *
 * Mirrors the pattern used by "Sticky Header Effects for Elementor":
 *   - controls registered on elementor/element/{section,container}/...after_section_end
 *   - transparent state via a prefix_class + selector (no JS needed to position)
 *   - scroll distance / background color / shadow applied by frontend JS reading data-settings
 *
 * @package Header_Footer_Builder_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAHEFOBU_Header_Effects {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		// Register controls for both classic sections and flex containers.
		add_action( 'elementor/element/section/section_effects/after_section_end', [ __CLASS__, 'register_controls' ], 10, 2 );
		add_action( 'elementor/element/container/section_effects/after_section_end', [ __CLASS__, 'register_controls' ], 10, 2 );

		// Frontend assets (only when a header renders on this page).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ], 20 );
	}

	/**
	 * Whether controls should be shown for the current document.
	 *
	 * Gated to our header CPT so the "Header Effects" panel does not pollute
	 * regular Elementor sections/containers elsewhere.
	 *
	 * @return bool
	 */
	private static function is_header_document() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$document = \Elementor\Plugin::$instance->documents->get_current();
		if ( ! $document || ! method_exists( $document, 'get_main_id' ) ) {
			return false;
		}

		$post_id = (int) $document->get_main_id();
		return $post_id && get_post_type( $post_id ) === 'tahefobu_header';
	}

	/**
	 * Register the Header Effects controls on Section/Container.
	 *
	 * Elementor caches the controls stack per element type for the whole
	 * request. On the frontend the header template is rendered via
	 * `get_builder_content_for_display()` *after* (or interleaved with) the
	 * page's own sections, so document gating would leave the header sections
	 * without these controls (and without their `data-settings` / prefix_class).
	 *
	 * Therefore controls are registered on every Section/Container on the
	 * frontend — harmless, since they only emit output when their settings are
	 * actually set inside a header template. In the Elementor editor the panel
	 * is shown only for header documents, so it does not pollute regular pages.
	 *
	 * @param \Elementor\Controls_Stack $element Section or container.
	 * @param array                     $args    Element args.
	 */
	public static function register_controls( $element, $args ) {
		// In the editor, restrict the panel to header templates.
		// On the frontend, register unconditionally so data-settings print.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() && ! self::is_header_document() ) {
			return;
		}

		$element->start_controls_section(
			'tahefobu_header_effects_section',
			[
				'label' => esc_html__( 'Header Effects', 'header-footer-builder-for-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$element->add_control(
			'tahefobu_header_effects',
			[
				'label'              => esc_html__( 'Enable Header Effects', 'header-footer-builder-for-elementor' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'On', 'header-footer-builder-for-elementor' ),
				'label_off'          => esc_html__( 'Off', 'header-footer-builder-for-elementor' ),
				'return_value'       => 'yes',
				'default'            => '',
				'frontend_available' => true,
			]
		);

		$element->add_control(
			'tahefobu_effects_scroll_distance',
			[
				'label'              => esc_html__( 'Scroll Distance (px)', 'header-footer-builder-for-elementor' ),
				'type'               => \Elementor\Controls_Manager::SLIDER,
				'default'            => [
					'size' => 200,
				],
				'range'              => [
					'px' => [
						'min' => 0,
						'max' => 1000,
					],
				],
				'size_units'         => [ 'px' ],
				'description'        => esc_html__( 'After this scroll distance the header turns solid.', 'header-footer-builder-for-elementor' ),
				'frontend_available' => true,
				'condition'          => [
					'tahefobu_header_effects!' => '',
				],
			]
		);

		$element->add_control(
			'tahefobu_transparent_header',
			[
				'label'              => esc_html__( 'Transparent Header', 'header-footer-builder-for-elementor' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'On', 'header-footer-builder-for-elementor' ),
				'label_off'          => esc_html__( 'Off', 'header-footer-builder-for-elementor' ),
				'return_value'       => 'yes',
				'default'            => '',
				'separator'          => 'before',
				'frontend_available' => true,
				'prefix_class'       => 'tahefobu-effects-transparent-',
				'selectors'          => [
					'{{WRAPPER}}.tahefobu-effects-transparent-yes' => 'background-color: transparent !important; position: absolute; width: 100%; left: 0; top: 0;',
				],
				'description'        => esc_html__( 'Sets the header section to absolute + transparent so content overlaps beneath it.', 'header-footer-builder-for-elementor' ),
				'condition'          => [
					'tahefobu_header_effects!' => '',
				],
			]
		);

		$element->add_control(
			'tahefobu_effects_background',
			[
				'label'              => esc_html__( 'Solid Background Color', 'header-footer-builder-for-elementor' ),
				'type'               => \Elementor\Controls_Manager::COLOR,
				'render_type'        => 'none',
				'frontend_available' => true,
				'description'        => esc_html__( 'Background color applied after the scroll distance. Leave empty to keep the header transparent.', 'header-footer-builder-for-elementor' ),
				'condition'          => [
					'tahefobu_header_effects!' => '',
				],
			]
		);

		$element->add_control(
			'tahefobu_effects_shadow',
			[
				'label'              => esc_html__( 'Bottom Shadow After Scroll', 'header-footer-builder-for-elementor' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'On', 'header-footer-builder-for-elementor' ),
				'label_off'          => esc_html__( 'Off', 'header-footer-builder-for-elementor' ),
				'return_value'       => 'yes',
				'default'            => '',
				'frontend_available' => true,
				'condition'          => [
					'tahefobu_header_effects!' => '',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Enqueue the header-effects JS + CSS.
	 *
	 * Loads when a header renders on the frontend, OR inside the Elementor
	 * editor preview iframe (authenticated, nonce-verified) so the effect is
	 * visible while editing the header.
	 */
	public static function enqueue_frontend_assets() {
		$renders_header = ! empty( $GLOBALS['tahefobu_header_will_render'] );

		// Allow enqueue inside a legitimate Elementor editor preview request.
		// Guard with auth + capability to match the nonce-gating used elsewhere
		// in the plugin — unauthenticated requests with ?elementor-preview are ignored.
		$is_editor_preview = isset( $_GET['elementor-preview'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET check; auth verified below
			&& is_user_logged_in()
			&& current_user_can( 'edit_posts' );

		if ( ! $renders_header && ! $is_editor_preview ) {
			return;
		}

		$ver = defined( 'TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION' )
			? TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_VERSION
			: '1.3.0';

		wp_enqueue_script(
			'tahefobu-header-effects',
			TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/js/turbo-header-effects.js',
			[ 'jquery' ],
			$ver,
			true
		);

		// The solid/shadow state lives in turbo-header-style.css alongside the
		// existing header behavior styles, so we only need a handle enqueue here.
		$handle = 'tahefobu-header-style';
		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style(
				$handle,
				TAHEFOBU_HEADER_FOOTER_BUILDER_FOR_ELEMENTOR_PLUGIN_URL . 'assets/css/turbo-header-style.css',
				[],
				$ver,
				'all'
			);
		}
	}
}
