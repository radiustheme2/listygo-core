<?php
/**
 * This file can be overridden by copying it to yourtheme/elementor-custom/accordion/class.php
 * 
 * @author  RadiusTheme
 * @since   1.0
 * @version 1.0
 */

namespace radiustheme\Listygo_Core;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;

if ( ! defined( 'ABSPATH' ) ) exit;

class Rt_Accordion extends Custom_Widget_Base {

	public function __construct( $data = [], $args = null ){
		$this->rt_name = __( 'Accordion', 'listygo-core' );
		$this->rt_base = 'rt-accordion';
		parent::__construct( $data, $args );
	}

	public function rt_fields(){

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'title', [
				'label' => __( 'Title', 'listygo-core' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'List Title' , 'listygo-core' ),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'accodion_text', [
				'label' => __( 'Content', 'listygo-core' ),
				'type' => \Elementor\Controls_Manager::WYSIWYG,
				'default' => __( 'List Content' , 'listygo-core' ),
				'show_label' => false,
			]
		);


		$fields = array(
			array(
				'mode'    => 'section_start',
				'id'      => 'sec_general',
				'label'   => esc_html__( 'General', 'listygo-core' ),
			),
			array(
				'type'    => Controls_Manager::REPEATER,
				'id'      => 'accordion_repeat',
				'label'   => esc_html__( 'Faq', 'listygo-core' ),
				'prevent_empty' => false,
				'title_field' => '{{{ title }}}',
				'fields' => $repeater->get_controls(),
			),
			array(
				'id'          => 'icon_display',
				'type'        => Controls_Manager::SWITCHER,
				'label'       => esc_html__( 'Icon', 'listygo-core' ),
				'label_on'    => esc_html__( 'On', 'listygo-core' ),
				'label_off'   => esc_html__( 'Off', 'listygo-core' ),
				'description' => esc_html__( 'Enable or disable icon. Default: Off', 'listygo-core' ),
				'default' => 'on',
			),
			array(
				'type'    => Controls_Manager::ICONS,
				'id'      => 'icon',
				'label'   => esc_html__( 'Icon', 'listygo-core' ),
				'default' => array(
					'value' => 'fas fa-smile-wink',
					'library' => 'fa-solid',
				),
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'type'    => Controls_Manager::ICONS,
				'id'      => 'active_icon',
				'label'   => esc_html__( 'Active Icon', 'listygo-core' ),
				'default' => array(
					'value' => 'far fa-smile',
					'library' => 'fa-solid',
				),
				'condition'   => [ 'icon_display!' => ''],
			),
			array(	
				'id'      => 'icon_position',				 
				'type'    => Controls_Manager::CHOOSE,
				'label'   => esc_html__( 'Icon Position', 'listygo-core' ),
				'options' => [
					'left' => [
						'title' => esc_html__( 'Left', 'listygo-core' ),
						'icon' => 'far fa-hand-point-left',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'listygo-core' ),
						'icon' => 'far fa-hand-point-right',
					],		     
				],
				'default' => 'right',
				'label_block' => false,
				'toggle' => false,
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'id'      => 'space_bottom',
				'mode'    => 'responsive',
				'type' => Controls_Manager::SLIDER,
				'label' => esc_html__( 'Space Bottom', 'listygo-core' ),
				'range' => [
					'px' => [
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .faq-box .panel' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			),
			array(
				'mode' => 'section_end',
			),

			// Title
			array(
				'id'      => 'title_style',
				'mode'    => 'section_start',
				'label'   => esc_html__( 'Title', 'listygo-core' ),
				'tab'     => Controls_Manager::TAB_STYLE,
			),
			array(
				'type'    => Controls_Manager::COLOR,
				'id'      => 'title_color',
				'label'   => esc_html__( 'Color', 'listygo-core' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .accordion-button' => 'color: {{VALUE}}',
				),
			),
			array(
				'name'     => 'title_typo',
				'mode'     => 'group',
				'type'     => Group_Control_Typography::get_type(),
				'label'    => __( 'Typography', 'listygo-core' ),
				'selector' => '{{WRAPPER}} .accordion-button',
			),
			array(
				'mode' => 'section_end',
			),
			// Description
			array(
				'id'      => 'desc_style',
				'mode'    => 'section_start',
				'label'   => esc_html__( 'Description', 'listygo-core' ),
				'tab'     => Controls_Manager::TAB_STYLE,
			),
			array(
				'type'    => Controls_Manager::COLOR,
				'id'      => 'desc_color',
				'label'   => esc_html__( 'Color', 'listygo-core' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .faq-box .panel-body p' => 'color: {{VALUE}}',
				),
			),
			array(
				'name'     => 'desc_typo',
				'mode'     => 'group',
				'type'     => Group_Control_Typography::get_type(),
				'label'    => __( 'Typography', 'listygo-core' ),
				'selector' => '{{WRAPPER}} .faq-box .panel-body p',
			),
			array(
				'mode' => 'section_end',
			),
			// Icon
			array(
				'mode'    => 'section_start',
				'id'      => 'sec_style',
				'label'   => esc_html__( 'Icon', 'listygo-core' ),
				'tab'     => Controls_Manager::TAB_STYLE,
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'type'    => Controls_Manager::COLOR,
				'id'      => 'icon_color',
				'label'   => esc_html__( 'Icon Color', 'listygo-core' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .rtin-icon i' => 'color: {{VALUE}}',
				),
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'type'        => Controls_Manager::NUMBER,
				'id'          => 'icon_size',
				'label'       => esc_html__( 'Icon Size', 'listygo-core' ),
				'default'     => '',
				'selectors' => array(
					'{{WRAPPER}} .rtin-icon i' => 'font-size: {{VALUE}}px',
					'{{WRAPPER}} .rtin-icon svg' => 'width: {{VALUE}}px',
				),
				'description' => esc_html__( 'icon size default: 16px', 'listygo-core' ),
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'mode' => 'section_end',
				'condition'   => [ 'icon_display!' => ''],
			),

			// Active Button
			array(
				'mode'    => 'section_start',
				'id'      => 'active_btn_style',
				'label'   => esc_html__( 'Active Button', 'listygo-core' ),
				'tab'     => Controls_Manager::TAB_STYLE,
			),
			array(
				'type'    => Controls_Manager::COLOR,
				'id'      => 'title_active_color',
				'label'   => esc_html__( 'Title', 'listygo-core' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .faq-box .panel-heading .accordion-button:not(.collapsed)' => 'color: {{VALUE}}',
				),
			),
			array(
				'type'    => Controls_Manager::COLOR,
				'id'      => 'icon_active_color',
				'label'   => esc_html__( 'Icon', 'listygo-core' ),
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .faq-box .panel-heading .accordion-button:not(.collapsed) .rtin-icon i' => 'color: {{VALUE}}',
				),
				'condition'   => [ 'icon_display!' => ''],
			),
			array(
				'name'      => 'active_btn_bdcolor',
				'mode'     => 'group',
				'type'     => Group_Control_Background::get_type(),
				'label'    => __( 'Background', 'listygo-core' ),
				'selector' => '{{WRAPPER}} .faq-box .panel-heading .accordion-button:not(.collapsed)',
			),
			array(
				'mode' => 'section_end',
			),

		);
		return $fields;
	}

	protected function render() {
		$data = $this->get_settings();

		$template = 'view';

		return $this->rt_template( $template, $data );
	}
}