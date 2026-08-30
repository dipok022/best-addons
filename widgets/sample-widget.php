<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Best_Addons_Sample_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'best_addons_sample'; }
	public function get_title() { return esc_html__( 'Sample Content Box', 'best-addons' ); }
	public function get_icon() { return 'eicon-code'; }
	public function get_categories() { return [ 'best-addons-category' ]; }

	public function get_style_depends() { return [ 'best-addons-style-sample-widget' ]; }
	public function get_script_depends() { return [ 'best-addons-script-sample-widget' ]; }

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[ 'label' => esc_html__( 'Content Settings', 'best-addons' ) ]
		);
		$this->add_control(
			'title',
			[
				'label'   => esc_html__( 'Title text', 'best-addons' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Hello Performance World!', 'best-addons' ),
			]
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo '<div class="best-addons-sample-box">';
		echo '<h3>' . esc_html( $settings['title'] ) . '</h3>';
		echo '</div>';
	}
}
