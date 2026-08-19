<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Standalone Mega Menu widget.
 *
 * Renders a WordPress navigation menu using TAHEFOBU_Mega_Menu_Walker. Items
 * marked as mega menu items (in Appearance → Menus) open a full-width panel
 * built with an Elementor template. Completely separate from the Menu widget.
 */
class TAHEFOBU_Mega_Menu_Widget extends Widget_Base {

	public function get_name() {
		return 'tahefobu-mega-menu';
	}

	public function get_title() {
		return esc_html__( 'Mega Menu', 'header-footer-builder-for-elementor' );
	}

	public function get_icon() {
		return 'eicon-nav-menu tahefobu-icon';
	}

	public function get_categories(): array {
		return [ 'tahefobu-hf-widgets' ];
	}

	public function get_keywords(): array {
		return [ 'mega', 'menu', 'mega-menu', 'navigation', 'nav', 'header' ];
	}

	public function get_style_depends() {
		return [ 'tahefobu-mega-menu-style' ];
	}

	public function get_script_depends() {
		return [ 'tahefobu-mega-menu-script' ];
	}

	private function tahefobu_get_menus() {
		$menus = wp_get_nav_menus();
		$options = [];
		foreach ( $menus as $menu ) {
			$options[ $menu->slug ] = $menu->name;
		}
		return $options;
	}

	protected function register_controls() {

		$this->start_controls_section(
			'tahefobu_mega_menu_content_section',
			[
				'label' => esc_html__( 'Menu', 'header-footer-builder-for-elementor' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$menus = $this->tahefobu_get_menus();

		if ( ! empty( $menus ) ) {
			$this->add_control(
				'tahefobu_mega_menu_select',
				[
					'label'   => esc_html__( 'Select Menu', 'header-footer-builder-for-elementor' ),
					'type'    => Controls_Manager::SELECT,
					'options' => $menus,
					'default' => array_keys( $menus )[0],
				]
			);
		} else {
			$this->add_control(
				'tahefobu_mega_menu_empty_notice',
				[
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => sprintf(
						/* translators: %s: admin menu editor URL */
						__( '<strong>No menus found.</strong> <a href="%s" target="_blank">Create a menu first</a>, then enable Mega Menu on individual items in Appearance → Menus.', 'header-footer-builder-for-elementor' ),
						esc_url( admin_url( 'nav-menus.php' ) )
					),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				]
			);
		}

		$this->add_control(
			'tahefobu_mega_menu_setup_notice',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: admin menu editor URL */
					__( '<strong>How it works:</strong> Open <a href="%s" target="_blank">Appearance → Menus</a>, pick this menu, and enable "Turbo Mega Menu" on any top-level item to assign an Elementor template to it.', 'header-footer-builder-for-elementor' ),
					esc_url( admin_url( 'nav-menus.php' ) )
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_trigger',
			[
				'label'   => esc_html__( 'Trigger', 'header-footer-builder-for-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'click',
				'options' => [
					'hover' => esc_html__( 'Hover', 'header-footer-builder-for-elementor' ),
					'click' => esc_html__( 'Click', 'header-footer-builder-for-elementor' ),
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_dropdown_icon',
			[
				'label'        => esc_html__( 'Dropdown Icon', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'angle-down',
				'options'      => [
					'none'         => esc_html__( 'None', 'header-footer-builder-for-elementor' ),
					'caret-down'   => esc_html__( 'Triangle', 'header-footer-builder-for-elementor' ),
					'angle-down'   => esc_html__( 'Angle', 'header-footer-builder-for-elementor' ),
					'chevron-down' => esc_html__( 'Chevron', 'header-footer-builder-for-elementor' ),
					'plus'         => esc_html__( 'Plus', 'header-footer-builder-for-elementor' ),
				],
				'prefix_class' => 'tahefobu-mega-drop-icon-',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_transition_duration',
			[
				'label'   => esc_html__( 'Transition Duration (s)', 'header-footer-builder-for-elementor' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0.3,
				'min'     => 0,
				'max'     => 2,
				'step'    => 0.05,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li > a,
					 {{WRAPPER}} .tahefobu-megamenu-panel,
					 {{WRAPPER}} .tahefobu-dropdown' => 'transition-duration: {{VALUE}}s;',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_responsive',
			[
				'label'        => esc_html__( 'Responsive / Mobile Menu', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'label_on'     => esc_html__( 'On', 'header-footer-builder-for-elementor' ),
				'label_off'    => esc_html__( 'Off', 'header-footer-builder-for-elementor' ),
				'return_value' => 'yes',
				'render_type'  => 'template',
				'description'  => esc_html__( 'Shows a hamburger toggle and a vertical mobile menu on small screens.', 'header-footer-builder-for-elementor' ),
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_mobile_breakpoint',
			[
				'label'       => esc_html__( 'Mobile Menu Breakpoint', 'header-footer-builder-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'tablet',
				'options'     => [
					'mobile'  => esc_html__( 'Mobile (767px)', 'header-footer-builder-for-elementor' ),
					'tablet'  => esc_html__( 'Tablet (1024px)', 'header-footer-builder-for-elementor' ),
					'laptop'  => esc_html__( 'Laptop (1366px)', 'header-footer-builder-for-elementor' ),
					'desktop' => esc_html__( 'All Sizes (always)', 'header-footer-builder-for-elementor' ),
				],
				'render_type'  => 'template',
				'description'  => esc_html__( 'Below this width the hamburger + mobile menu are used.', 'header-footer-builder-for-elementor' ),
				'condition'    => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_mobile_width',
			[
				'label'        => esc_html__( 'Dropdown Width', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'full-width',
				'options'      => [
					'full-width'   => esc_html__( 'Full Width', 'header-footer-builder-for-elementor' ),
					'auto-width'   => esc_html__( 'Auto', 'header-footer-builder-for-elementor' ),
					'custom-width' => esc_html__( 'Custom Width', 'header-footer-builder-for-elementor' ),
				],
				'prefix_class' => 'tahefobu-mega-mobile-menu-',
				'render_type'  => 'template',
				'condition'    => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_mobile_custom_width',
			[
				'label'      => esc_html__( 'Width', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [ 'size' => 300, 'unit' => 'px' ],
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [ 'min' => 150, 'max' => 1000, 'step' => 5 ],
					'%'  => [ 'min' => 10,  'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}}.tahefobu-mega-mobile-menu-custom-width .tahefobu-mega-mobile-menu' => 'width: {{SIZE}}{{UNIT}};',
				],
				'condition'  => [
					'tahefobu_mega_menu_responsive'     => 'yes',
					'tahefobu_mega_menu_mobile_width'   => 'custom-width',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_mobile_dropdown_align',
			[
				'label'   => esc_html__( 'Dropdown Alignment', 'header-footer-builder-for-elementor' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => [
					'left'   => [ 'title' => esc_html__( 'Left', 'header-footer-builder-for-elementor' ),   'icon' => 'eicon-h-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'header-footer-builder-for-elementor' ), 'icon' => 'eicon-h-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'header-footer-builder-for-elementor' ),  'icon' => 'eicon-h-align-right' ],
				],
				'prefix_class' => 'tahefobu-mega-mobile-drdown-align-',
				'condition'    => [
					'tahefobu_mega_menu_responsive'   => 'yes',
					'tahefobu_mega_menu_mobile_width' => [ 'auto-width', 'custom-width' ],
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_mobile_item_align',
			[
				'label'   => esc_html__( 'Item Alignment', 'header-footer-builder-for-elementor' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => [
					'left'   => [ 'title' => esc_html__( 'Left', 'header-footer-builder-for-elementor' ),   'icon' => 'eicon-h-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'header-footer-builder-for-elementor' ), 'icon' => 'eicon-h-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'header-footer-builder-for-elementor' ),  'icon' => 'eicon-h-align-right' ],
				],
				'prefix_class' => 'tahefobu-mega-mobile-item-align-',
				'condition'    => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_toggle_icon',
			[
				'label'        => esc_html__( 'Toggle Icon', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'v1',
				'options'      => [
					'v1' => esc_html__( 'Icon 1', 'header-footer-builder-for-elementor' ),
					'v2' => esc_html__( 'Icon 2', 'header-footer-builder-for-elementor' ),
					'v3' => esc_html__( 'Icon 3', 'header-footer-builder-for-elementor' ),
					'v4' => esc_html__( 'Icon 4', 'header-footer-builder-for-elementor' ),
					'v5' => esc_html__( 'Icon 5', 'header-footer-builder-for-elementor' ),
				],
				'prefix_class' => 'tahefobu-mega-toggle-',
				'condition'    => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_menu_toggle_align',
			[
				'label'     => esc_html__( 'Toggle Alignment', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'options'   => [
					'left'   => [ 'title' => esc_html__( 'Left', 'header-footer-builder-for-elementor' ),   'icon' => 'eicon-h-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'header-footer-builder-for-elementor' ), 'icon' => 'eicon-h-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'header-footer-builder-for-elementor' ),  'icon' => 'eicon-h-align-right' ],
				],
				'selectors_dictionary' => [
					'left'   => 'text-align: left',
					'center' => 'text-align: center',
					'right'  => 'text-align: right',
				],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle-wrap' => '{{VALUE}}',
				],
				'condition' => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_menu_align',
			[
				'label'     => esc_html__( 'Align', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'options'   => [
					'left'   => [
						'title' => esc_html__( 'Left', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-center',
					],
					'right'  => [
						'title' => esc_html__( 'Right', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-right',
					],
				],
				'prefix_class' => 'tahefobu-mega-menu-align-%s',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'tahefobu_mega_menu_style_section',
			[
				'label' => esc_html__( 'Menu Item', 'header-footer-builder-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'tahefobu_mega_menu_item_tabs' );

		// ── Normal ────────────────────────────────────────────────────────
		$this->start_controls_tab(
			'tahefobu_mega_menu_item_tab_normal',
			[ 'label' => esc_html__( 'Normal', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'tahefobu_mega_menu_item_typo',
				'label'    => esc_html__( 'Typography', 'header-footer-builder-for-elementor' ),
				'selector' => '{{WRAPPER}} .tahefobu-mega-nav-menu > li > a',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_color',
			[
				'label'     => esc_html__( 'Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li > a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li > a' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_active_highlight',
			[
				'label'        => esc_html__( 'Highlight Active Item', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_drop_icon_size',
			[
				'label'     => esc_html__( 'Dropdown Icon Size', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 11 ],
				'range'     => [ 'px' => [ 'min' => 6, 'max' => 24 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-submenu-indicator' => 'font-size: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_menu_item_padding',
			[
				'label'      => esc_html__( 'Padding', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top'      => 10,
					'right'    => 16,
					'bottom'   => 10,
					'left'     => 16,
					'unit'     => 'px',
					'isLinked' => false,
				],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li > a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_menu_item_spacing',
			[
				'label'     => esc_html__( 'Item Spacing', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 0 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'tahefobu_mega_menu_item_border',
				'selector' => '{{WRAPPER}} .tahefobu-mega-nav-menu > li > a',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li > a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		// ── Active / Hover ────────────────────────────────────────────────
		$this->start_controls_tab(
			'tahefobu_mega_menu_item_tab_hover',
			[ 'label' => esc_html__( 'Active', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_color_hover',
			[
				'label'     => esc_html__( 'Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li:hover > a,
					 {{WRAPPER}} .tahefobu-mega-nav-menu > li > a:hover,
					 {{WRAPPER}} .tahefobu-mega-nav-menu > li.current-menu-item > a,
					 {{WRAPPER}} .tahefobu-mega-nav-menu > li.current-menu-ancestor > a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_item_bg_hover',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-nav-menu > li:hover > a,
					 {{WRAPPER}} .tahefobu-mega-nav-menu > li.current-menu-item > a,
					 {{WRAPPER}} .tahefobu-mega-nav-menu > li.current-menu-ancestor > a' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

		// ── Dropdown (regular sub-menu) style ─────────────────────────────
		$this->start_controls_section(
			'tahefobu_mega_menu_dropdown_style_section',
			[
				'label' => esc_html__( 'Dropdown', 'header-footer-builder-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'tahefobu_mega_dropdown_tabs' );

		$this->start_controls_tab(
			'tahefobu_mega_dropdown_tab_normal',
			[ 'label' => esc_html__( 'Normal', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'tahefobu_mega_dropdown_typo',
				'selector' => '{{WRAPPER}} .tahefobu-dropdown > li > a',
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_color',
			[
				'label'     => esc_html__( 'Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown > li > a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f4f4f4',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_dropdown_padding',
			[
				'label'      => esc_html__( 'Item Padding', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top'    => 10, 'right' => 15,
					'bottom' => 10, 'left'  => 15,
					'unit'   => 'px', 'isLinked' => false,
				],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown > li > a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_dropdown_offset',
			[
				'label'     => esc_html__( 'Vertical Gap', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 0 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_dropdown_offset_x',
			[
				'label'      => esc_html__( 'Offset X', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [ 'min' => -200, 'max' => 200 ],
					'%'  => [ 'min' => -100, 'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-dropdown' => 'margin-left: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_divider',
			[
				'label'        => esc_html__( 'Divider', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'prefix_class' => 'tahefobu-mega-dropdown-divider-',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_divider_color',
			[
				'label'     => esc_html__( 'Divider Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#e8e8e8',
				'selectors' => [
					'{{WRAPPER}}.tahefobu-mega-dropdown-divider-yes .tahefobu-dropdown > li:not(:last-child)' => 'border-bottom-color: {{VALUE}};',
				],
				'condition' => [ 'tahefobu_mega_dropdown_divider' => 'yes' ],
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_divider_height',
			[
				'label'     => esc_html__( 'Divider Height', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 1 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 10 ] ],
				'selectors' => [
					'{{WRAPPER}}.tahefobu-mega-dropdown-divider-yes .tahefobu-dropdown > li:not(:last-child)' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [ 'tahefobu_mega_dropdown_divider' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => 'tahefobu_mega_dropdown_border',
				'selector'  => '{{WRAPPER}} .tahefobu-dropdown',
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'tahefobu_mega_dropdown_shadow',
				'selector' => '{{WRAPPER}} .tahefobu-dropdown',
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tahefobu_mega_dropdown_tab_hover',
			[ 'label' => esc_html__( 'Hover', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_color_hover',
			[
				'label'     => esc_html__( 'Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0d3a4f',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown > li > a:hover,
					 {{WRAPPER}} .tahefobu-dropdown > li.current-menu-item > a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_dropdown_bg_hover',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-dropdown > li > a:hover,
					 {{WRAPPER}} .tahefobu-dropdown > li.current-menu-item > a' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'tahefobu_mega_menu_panel_section',
			[
				'label' => esc_html__( 'Panel', 'header-footer-builder-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_panel_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-megamenu-panel' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_menu_panel_width',
			[
				'label'          => esc_html__( 'Panel Width', 'header-footer-builder-for-elementor' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', '%', 'vw' ],
				'default'        => [
					'size' => 100,
					'unit' => 'vw',
				],
				'mobile_default' => [
					'size' => 100,
					'unit' => '%',
				],
				'range'          => [
					'px' => [ 'min' => 100, 'max' => 2000, 'step' => 10 ],
					'%'  => [ 'min' => 10,  'max' => 100 ],
					'vw' => [ 'min' => 10,  'max' => 100 ],
				],
				'selectors'      => [
					'{{WRAPPER}} .tahefobu-megamenu-panel' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_panel_align',
			[
				'label'        => esc_html__( 'Panel Alignment', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'left',
				'options'      => [
					'left'   => [
						'title' => esc_html__( 'Left', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-center',
					],
					'right'  => [
						'title' => esc_html__( 'Right', 'header-footer-builder-for-elementor' ),
						'icon'  => 'eicon-h-align-right',
					],
				],
				'prefix_class' => 'tahefobu-mega-panel-align-',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_panel_padding',
			[
				'label'      => esc_html__( 'Padding', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top'      => 20,
					'right'    => 20,
					'bottom'   => 20,
					'left'     => 20,
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-megamenu-panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'tahefobu_mega_menu_panel_shadow',
				'selector' => '{{WRAPPER}} .tahefobu-megamenu-panel',
			]
		);

		$this->add_control(
			'tahefobu_mega_menu_panel_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => 4,
					'right'    => 4,
					'bottom'   => 4,
					'left'     => 4,
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-megamenu-panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'tahefobu_mega_menu_mobile_section',
			[
				'label'     => esc_html__( 'Mobile Menu', 'header-footer-builder-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'tahefobu_mega_menu_responsive' => 'yes',
				],
			]
		);

		$this->start_controls_tabs( 'tahefobu_mega_mobile_style_tabs' );

		// ── Normal ────────────────────────────────────────────────────────
		$this->start_controls_tab(
			'tahefobu_mega_mobile_tab_normal',
			[ 'label' => esc_html__( 'Normal', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_mobile_item_color',
			[
				'label'     => esc_html__( 'Item Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_item_active',
			[
				'label'        => esc_html__( 'Highlight Active Item', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_padding_h',
			[
				'label'     => esc_html__( 'Horizontal Spacing', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 18 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_padding_v',
			[
				'label'     => esc_html__( 'Vertical Spacing', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 12 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_divider',
			[
				'label'        => esc_html__( 'Divider', 'header-footer-builder-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'prefix_class' => 'tahefobu-mega-mobile-divider-',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_divider_color',
			[
				'label'     => esc_html__( 'Divider Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f0f0f0',
				'selectors' => [
					'{{WRAPPER}}.tahefobu-mega-mobile-divider-yes .tahefobu-mega-mobile-menu .tahefobu-mega-mobile-nav > li' => 'border-bottom-color: {{VALUE}};',
				],
				'condition' => [ 'tahefobu_mega_mobile_divider' => 'yes' ],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_divider_height',
			[
				'label'     => esc_html__( 'Divider Height', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 1 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 10 ] ],
				'selectors' => [
					'{{WRAPPER}}.tahefobu-mega-mobile-divider-yes .tahefobu-mega-mobile-menu .tahefobu-mega-mobile-nav > li' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [ 'tahefobu_mega_mobile_divider' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'tahefobu_mega_mobile_item_width',
			[
				'label'      => esc_html__( 'Menu Item Width', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [ 'size' => 100, 'unit' => '%' ],
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [ 'min' => 50, 'max' => 500 ],
					'%'  => [ 'min' => 10, 'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a' => 'width: {{SIZE}}{{UNIT}};',
				],
				'separator'  => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_sub_font_size',
			[
				'label'     => esc_html__( 'Sub Menu Font Size', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 13 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 30 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu .tahefobu-dropdown a,
					 {{WRAPPER}} .tahefobu-mega-mobile-menu .tahefobu-mobile-panel a' => 'font-size: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_sub_padding_v',
			[
				'label'     => esc_html__( 'Sub Menu Vertical Spacing', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 8 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 30 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu .tahefobu-dropdown a,
					 {{WRAPPER}} .tahefobu-mega-mobile-menu .tahefobu-mobile-panel a' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_offset',
			[
				'label'     => esc_html__( 'Dropdown Offset', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 0 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'tahefobu_mega_mobile_typo',
				'selector' => '{{WRAPPER}} .tahefobu-mega-mobile-menu a',
			]
		);

		$this->end_controls_tab();

		// ── Hover ─────────────────────────────────────────────────────────
		$this->start_controls_tab(
			'tahefobu_mega_mobile_tab_hover',
			[ 'label' => esc_html__( 'Hover', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_mobile_item_hover_color',
			[
				'label'     => esc_html__( 'Item Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#7c3aed',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a:hover,
					 {{WRAPPER}} .tahefobu-mega-mobile-menu li.current-menu-item > a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_mobile_item_hover_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-mobile-menu a:hover,
					 {{WRAPPER}} .tahefobu-mega-mobile-menu li.current-menu-item > a' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

		// ── Hamburger button style ─────────────────────────────────────────
		$this->start_controls_section(
			'tahefobu_mega_hamburger_style_section',
			[
				'label'     => esc_html__( 'Hamburger', 'header-footer-builder-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'tahefobu_mega_menu_responsive' => 'yes' ],
			]
		);

		$this->start_controls_tabs( 'tahefobu_mega_hamburger_tabs' );

		$this->start_controls_tab(
			'tahefobu_mega_hamburger_tab_normal',
			[ 'label' => esc_html__( 'Normal', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_toggle_color',
			[
				'label'     => esc_html__( 'Lines Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle-line' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_bg',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_size',
			[
				'label'      => esc_html__( 'Size (px)', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [ 'size' => 32 ],
				'range'      => [ 'px' => [ 'min' => 20, 'max' => 80 ] ],
				'size_units' => [ 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
				'separator'  => 'before',
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_line_height',
			[
				'label'     => esc_html__( 'Line Height', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 3 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 10 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle-line' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_line_spacing',
			[
				'label'     => esc_html__( 'Line Spacing', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 4 ],
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 20 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle-line' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_padding',
			[
				'label'      => esc_html__( 'Padding', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_border_width',
			[
				'label'     => esc_html__( 'Border Width', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [ 'size' => 0 ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 10 ] ],
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_border_color',
			[
				'label'     => esc_html__( 'Border Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'border-color: {{VALUE}};',
				],
				'condition' => [ 'tahefobu_mega_toggle_border_width[size]!' => 0 ],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'header-footer-builder-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .tahefobu-mega-toggle' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tahefobu_mega_hamburger_tab_hover',
			[ 'label' => esc_html__( 'Hover', 'header-footer-builder-for-elementor' ) ]
		);

		$this->add_control(
			'tahefobu_mega_toggle_color_hover',
			[
				'label'     => esc_html__( 'Lines Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle:hover .tahefobu-mega-toggle-line' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tahefobu_mega_toggle_bg_hover',
			[
				'label'     => esc_html__( 'Background Color', 'header-footer-builder-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .tahefobu-mega-toggle:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

	} // end register_controls()

	protected function render() {
		$menus = $this->tahefobu_get_menus();
		if ( empty( $menus ) || ! class_exists( 'TAHEFOBU_Mega_Menu_Walker' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		$menu_slug = isset( $settings['tahefobu_mega_menu_select'] ) ? $settings['tahefobu_mega_menu_select'] : '';
		if ( empty( $menu_slug ) ) {
			return;
		}

		$trigger    = isset( $settings['tahefobu_mega_menu_trigger'] ) ? $settings['tahefobu_mega_menu_trigger'] : 'click';
		$responsive = isset( $settings['tahefobu_mega_menu_responsive'] ) && 'yes' === $settings['tahefobu_mega_menu_responsive'];
		$breakpoint = $responsive && ! empty( $settings['tahefobu_mega_menu_mobile_breakpoint'] )
			? $settings['tahefobu_mega_menu_mobile_breakpoint']
			: 'tablet';

		// Submenu indicator icon (mapped from the widget dropdown icon control).
		$indicator_map = [
			'none'         => '',
			'caret-down'   => 'fas fa-caret-down',
			'angle-down'   => 'fas fa-angle-down',
			'chevron-down' => 'fas fa-chevron-down',
			'plus'         => 'fas fa-plus',
		];
		$dropdown_icon  = isset( $settings['tahefobu_mega_menu_dropdown_icon'] ) ? $settings['tahefobu_mega_menu_dropdown_icon'] : 'angle-down';
		$indicator_cls  = isset( $indicator_map[ $dropdown_icon ] ) ? $indicator_map[ $dropdown_icon ] : 'fas fa-angle-down';
		$indicator      = $indicator_cls ? '<i class="tahefobu-submenu-indicator ' . esc_attr( $indicator_cls ) . '" aria-hidden="true"></i>' : '';

		// Desktop menu (mega panels enabled).
		$desktop_walker = new TAHEFOBU_Mega_Menu_Walker();
		$desktop_walker->is_mobile = false;
		$desktop_walker->submenu_indicator_icon = $indicator;

		$desktop_args = [
			'echo'         => false,
			'menu'         => $menu_slug,
			'menu_class'   => 'tahefobu-mega-nav-menu',
			'menu_id'      => 'tahefobu-mega-menu-' . $this->get_id(),
			'container'    => '',
			'fallback_cb'  => '__return_empty_string',
			'walker'       => $desktop_walker,
		];

		$menu_html = wp_nav_menu( $desktop_args );

		if ( empty( $menu_html ) ) {
			return;
		}

		// Build nav classes — responsive + breakpoint go directly on the <nav>
		// so CSS media-query selectors work without depending on the Elementor
		// wrapper prefix_class (which is a different DOM element).
		$nav_classes = 'tahefobu-mega-menu-container tahefobu-mega-trigger-' . esc_attr( $trigger );
		if ( $responsive ) {
			$nav_classes .= ' tahefobu-mega-responsive tahefobu-mega-bp-' . esc_attr( $breakpoint );
		}

		echo '<nav class="' . esc_attr( $nav_classes ) . '">';

		// Hamburger toggle (shown on mobile via CSS).
		if ( $responsive ) {
			echo '<div class="tahefobu-mega-toggle-wrap">';
			echo '<button type="button" class="tahefobu-mega-toggle" aria-label="' . esc_attr__( 'Toggle menu', 'header-footer-builder-for-elementor' ) . '" aria-expanded="false">';
			echo '<span class="tahefobu-mega-toggle-line"></span>';
			echo '<span class="tahefobu-mega-toggle-line"></span>';
			echo '<span class="tahefobu-mega-toggle-line"></span>';
			echo '</button>';
			echo '</div>';
		}

		// Desktop menu.
		echo '<div class="tahefobu-mega-desktop-menu">';
		echo $menu_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu output
		echo '</div>';

		// Mobile menu (mega panels disabled — shown when the toggle is active).
		if ( $responsive ) {
			$mobile_walker = new TAHEFOBU_Mega_Menu_Walker();
			$mobile_walker->is_mobile = true;
			$mobile_walker->submenu_indicator_icon = $indicator;

			$mobile_args = [
				'echo'         => false,
				'menu'         => $menu_slug,
				'menu_class'   => 'tahefobu-mega-mobile-nav',
				'menu_id'      => 'tahefobu-mega-mobile-' . $this->get_id(),
				'container'    => '',
				'fallback_cb'  => '__return_empty_string',
				'walker'       => $mobile_walker,
				'depth'        => 0,
			];

			$mobile_html = wp_nav_menu( $mobile_args );
			echo '<div class="tahefobu-mega-mobile-menu">';
			echo $mobile_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu output
			echo '</div>';
		}

		echo '</nav>';
	}
}
