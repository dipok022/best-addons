<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Best_Addons_Accordion_Widget extends \Elementor\Widget_Base {
  
  public function get_name() { return 'best_addons_accordion'; }
  public function get_title() { return esc_html__( 'Accordion', 'best-addons' ); }
  public function get_icon() { return 'eicon-accordion'; }

  public function get_categories() { return [ 'best-addons-category' ]; }


  public function get_style_depends() { return [ 'best-addons-style-accordion' ]; }
	public function get_script_depends() { return [ 'best-addons-script-accordion' ]; }


  protected function register_controls() {

   $this->start_controls_section(
			'preset_section',
			[
				'label' => esc_html__( 'Accordion Layout', 'best-addons' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

    $this->add_control(
			'design_preset',
			[
				'label'   => esc_html__( 'Select Preset', 'best-addons' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'preset_1',
				'options' => [
					'preset_1' => esc_html__( 'Default', 'best-addons' ),
					'preset_2' => esc_html__( 'Glass Background', 'best-addons' ),
					'preset_3' => esc_html__( 'Facts', 'best-addons' ),
					'preset_4' => esc_html__( 'Hero Background', 'best-addons' ),
				],
			]
		);

		$repeater = new \Elementor\Repeater();

    $repeater->add_control(
			'tab_title',
			[ 
			'label' => esc_html__( 'Title', 'best-addons' ), 
			'type' => \Elementor\Controls_Manager::TEXT, 
			'default' => esc_html__( 'Accordion Heading', 'best-addons' ), 
			'label_block' => true ]
		);

    $repeater->add_control(
			'tab_content',
			[ 'label' => esc_html__( 'Content', 'best-addons' ), 'type' => \Elementor\Controls_Manager::WYSIWYG, 'default' => esc_html__( 'Add your content layout block.', 'best-addons' ) ]
		);
		$this->add_control(
			'accordion_items',
			[
				'label' => esc_html__( 'Items', 'best-addons' ),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_fields(),
				'default' => [
					[ 'tab_title' => esc_html__( 'Accordion Item', 'best-addons' ) ],
					[ 'tab_title' => esc_html__( 'Accordion Item', 'best-addons' ) ],
					[ 'tab_title' => esc_html__( 'Accordion Item', 'best-addons' ) ],
				],
				'title_field' => '{{{ tab_title }}}',
			]
		);

		$this->add_control(
			'layout_divider',
			[
				'type' => \Elementor\Controls_Manager::DIVIDER,
			]
		);

    $this->end_controls_section();

    $this->start_controls_section(
			'content_section',
			[ 'label' => esc_html__( 'Accordion', 'best-addons' ) ]
		);


		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['accordion_items'] ) ) return;

		$preset = ! empty( $settings['design_preset'] )
			? $settings['design_preset']
			: 'preset_1';

		$widget_id = $this->get_id();
		?>
		<div 
				id="best-accordion-<?php echo esc_attr( $widget_id ); ?>" 
				class="best-advanced-accordion best-accordion-<?php echo esc_attr( $preset ); ?>"
				data-preset="<?php echo esc_attr( $preset ); ?>"
			>
			<?php foreach ( $settings['accordion_items'] as $index => $item ) : 
				$active_class = ( $index === 0 ) ? ' is-active' : '';
				$display_style = ( $index === 0 ) ? 'style="display: block;"' : 'style="display: none;"';
				?>
				<div class="best-accordion-item<?php echo esc_attr( $active_class ); ?>">
					<button class="best-accordion-header" type="button">
						<span class="best-accordion-title"><?php echo esc_html( $item['tab_title'] ); ?></span>
						<span class="best-accordion-icon"></span>
					</button>
					<div class="best-accordion-content" <?php echo $display_style; ?>>
						<div class="best-accordion-content-inner">
							<?php echo wp_kses_post( $item['tab_content'] ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	
  }
}