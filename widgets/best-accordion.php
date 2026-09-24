<?php
if (! defined('ABSPATH')) exit;

class Best_Addons_BEST_Accordion_Widget extends \Elementor\Widget_Base
{

	public function get_name()
	{
		return 'best_addons_best_accordion';
	}
	public function get_title()
	{
		return esc_html__('Best Accordion', 'best-addons');
	}
	public function get_icon()
	{
		return 'eicon-accordion';
	}

	public function get_categories()
	{
		return ['best-addons-category'];
	}


	public function get_style_depends()
	{
		return ['best-addons-style-best-accordion'];
	}
	public function get_script_depends()
	{
		return ['best-addons-script-best-accordion'];
	}


	protected function register_controls()
	{
		// Accordion Layout Section
		$this->start_controls_section(
			'preset_section',
			[
				'label' => esc_html__('Accordion Layout', 'best-addons'),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Design Preset Control
		$this->add_control(
			'design_preset',
			[
				'label'   => esc_html__('Select Preset', 'best-addons'),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'preset_1',
				'options' => [
					'preset_1' => esc_html__('Default', 'best-addons'),
					'preset_2' => esc_html__('Glass Background', 'best-addons'),
					'preset_3' => esc_html__('Facts', 'best-addons'),
					'preset_4' => esc_html__('Hero Background', 'best-addons'),
				],
			]
		);

		$this->add_control(
			'accordion_layout_orientation',
			[
				'label'   => esc_html__('Layout Orientation', 'best-addons'),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'vertical'   => [
						'title' => esc_html__('Vertical', 'best-addons'),
						'icon'  => 'eicon-justify-space-between-v',
					],
					'horizontal' => [
						'title' => esc_html__('Horizontal', 'best-addons'),
						'icon'  => 'eicon-justify-space-between-h',
					],
				],
				'default' => 'vertical',
				'toggle'  => false,
			]
		);

		// Repeater Control
		$repeater = new \Elementor\Repeater();

		// Accordion Title
		$repeater->add_control(
			'accordion_title',
			[
				'label' => esc_html__('Accordion Title', 'best-addons'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('Accordion Heading', 'best-addons'),
				'label_block' => true
			]
		);

		// Accordion Headings
		$repeater->add_control(
			'accordion_heading_notice',
			[
				'type'        => \Elementor\Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'dismiss'     => false,
				'content'     => esc_html__('Note: The Heading and Sub Heading fields are only used in Glass Background and Hero Background Preset.', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_heading',
			[
				'label' => esc_html__('Heading', 'best-addons'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('Heading', 'best-addons'),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'accordion_sub_heading',
			[
				'label' => esc_html__('Sub Heading', 'best-addons'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('Sub Heading', 'best-addons'),
				'label_block' => true,
			]
		);

		// Accordion Content
		$repeater->add_control(
			'accordion_content',
			[
				'label' => esc_html__('Content', 'best-addons'),
				'type' => \Elementor\Controls_Manager::WYSIWYG,
				'default' => esc_html__('Add your content layout block.', 'best-addons')
			]
		);

		// Accordion Image
		$repeater->add_control(
			'accordion_image_notice',
			[
				'type'        => \Elementor\Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'dismiss'     => false,
				'content'     => esc_html__('Note: This Image fields are only used in Glass Background and Hero Background Preset.', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_image',
			[
				'label' => esc_html('Image', 'best-addons'),
				'type' => \Elementor\Controls_Manager::MEDIA,
			]
		);

		// Accordion Facts
		$repeater->add_control(
			'accordion_facts_notice',
			[
				'type'        => \Elementor\Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'dismiss'     => false,
				'content'     => esc_html__('Note: The Facts fields are only used in the Facts Preset and Separate each fact item with a comma (,).', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_facts',
			[
				'label'       => esc_html__('Facts', 'best-addons'),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => esc_html__('Week 01, 2 sessions, Remote or on site', 'best-addons'),
				'label_block' => true,
			]
		);

		// Icon control
		$repeater->add_control(
			'item_icon_heading',
			[
				'label' => esc_html__('Accordion Icon', 'best-addons'),
				'type' => \Elementor\Controls_Manager::HEADING,
			]
		);
		$repeater->start_controls_tabs('accordion_icon_tab');

		$repeater->start_controls_tab(
			'collapsed_icon_tab',
			[
				'label' => esc_html__('Collapsed', 'best-addons'),
			]
		);
		$repeater->add_control(
			'collapsed_icon',
			[
				'label' => esc_html__('', 'best-addons'),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-plus',
					'library' => 'fa-solid',
				],
			]
		);
		$repeater->end_controls_tab();

		$repeater->start_controls_tab(
			'expanded_icon_tab',
			[
				'label' => esc_html__('Expanded', 'best-addons'),
			]
		);
		$repeater->add_control(
			'expanded_icon',
			[
				'label' => esc_html__('Icon', 'best-addons'),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-minus',
					'library' => 'fa-solid',
				],
			]
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		// Accordion Buttons
		$repeater->add_control(
			'accordion_buttons_notice',
			[
				'type'        => \Elementor\Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'dismiss'     => false,
				'content'     => esc_html__('Note: The Buttons fields are only used in Glass Background and Hero Background Preset.', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_buttons_heading',
			[
				'label' => esc_html('Buttons', 'best-addons'),
				'type' => \Elementor\Controls_Manager::HEADING,
			]
		);

		$repeater->start_controls_tabs('accordion_buttons_tabs');

		$repeater->start_controls_tab(
			'accordion_primary_btn',
			[
				'label' => esc_html__('Primary Button', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_primary_title',
			[
				'label' => esc_html('Btn Title', 'bast-addons'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 'Plan this trip',
			]
		);

		$repeater->add_control(
			'accordion_primary_icon',
			[
				'label' => esc_html__('Btn Icon', 'best-addons'),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-paper-plane',
					'library' => 'fa-solid',
				],
			]
		);

		$repeater->add_control(
			'accordion_primary_link',
			[
				'label' => esc_html__('Link', 'best-addons'),
				'type' => \Elementor\Controls_Manager::URL,
				'options' => ['url', 'is_external', 'nofollow'],
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
					// 'custom_attributes' => '',
				],
				'label_block' => true,
			]
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab(
			'accordion_secondary_btn',
			[
				'label' => esc_html__('Secondary Button', 'best-addons'),
			]
		);

		$repeater->add_control(
			'accordion_secondary_title',
			[
				'label' => esc_html('Btn Title', 'bast-addons'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 'See the itinerary',
			]
		);

		$repeater->add_control(
			'accordion_secondary_icon',
			[
				'label' => esc_html__('Btn Icon', 'best-addons'),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-shield-alt',
					'library' => 'fa-solid',
				],
			]
		);

		$repeater->add_control(
			'accordion_secondary_link',
			[
				'label' => esc_html__('Link', 'best-addons'),
				'type' => \Elementor\Controls_Manager::URL,
				'options' => ['url', 'is_external', 'nofollow'],
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
					// 'custom_attributes' => '',
				],
				'label_block' => true,
			]
		);

		$repeater->end_controls_tab();
		$repeater->end_controls_tabs();

		// End Repeater Control 

		// Add the repeater control to the main widget
		$this->add_control(
			'accordion_items',
			[
				'label' => esc_html__('Items', 'best-addons'),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_fields(),
				'default' => [
					[
						'accordion_title' => esc_html__('Accordion Item', 'best-addons'),
						'accordion_sub_heading' => esc_html__('Sub Heading', 'best-addons'),
					],
					[
						'accordion_title' => esc_html__('Accordion Item', 'best-addons'),
						'accordion_sub_heading' => esc_html__('Sub Heading', 'best-addons'),
					],
					[
						'accordion_title' => esc_html__('Accordion Item', 'best-addons'),
						'accordion_sub_heading' => esc_html__('Sub Heading', 'best-addons'),
					],
				],
				'title_field' => '{{{ accordion_title }}}',
				'separator' => 'after',
			]
		);

		// Heading alignment control
		$this->add_control(
			"heading_alignment",
			[
				'label' => esc_html__('Heading Alignment', 'best-addons'),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__('Left', 'best-addons'),
						'icon' => 'eicon-order-start',
					],
					'center' => [
						'title' => esc_html__('Center', 'best-addons'),
						'icon' => 'eicon-h-align-center',
					],
					'right' => [
						'title' => esc_html__('Right', 'best-addons'),
						'icon' => 'eicon-order-end',
					],
					'space-between' => [
						'title' => esc_html__('Stretch', 'best-addons'),
						'icon' => 'eicon-grow',
					],
				],
				'default' => 'left',
				'toggle' => false,
				'selectors' => [
					'{{WRAPPER}} .best-accordion-item .best-accordion-header' => 'justify-content: {{VALUE}};',
				],
			]
		);

		// Icon position control
		$this->add_control(
			"icon_position",
			[
				'label' => esc_html__('Icon Position', 'best-addons'),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__('Left', 'best-addons'),
						'icon' => 'eicon-h-align-left',
					],
					'right' => [
						'title' => esc_html__('Right', 'best-addons'),
						'icon' => 'eicon-h-align-right',
					],
				],
				'default' => 'right',
				'toggle' => false,
				'responsive' => true,
				'separator' => 'before',
			]
		);

		// Accordion Heading Selected Tag
		$this->add_control(
			'accordion_title_tag',
			[
				'label' => esc_html('Title Tag', 'best-addons'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h1' => esc_html('h1', 'best-addons'),
					'h2' => esc_html('h2', 'best-addons'),
					'h3' => esc_html('h3', 'best-addons'),
					'h4' => esc_html('h4', 'best-addons'),
					'h5' => esc_html('h5', 'best-addons'),
					'h6' => esc_html('h6', 'best-addons'),
					'div' => esc_html('div', 'best-addons'),
					'span' => esc_html('span', 'best-addons'),
					'p' => esc_html('p', 'best-addons'),
				],
				'separator' => 'before'
			]
		);

		$this->end_controls_section();
		// End Accordion Layout Section

		$this->start_controls_section(
			'content_section',
			['label' => esc_html__('Interactions', 'best-addons')]
		);

		$this->add_control(
			'accordion_trigger',
			[
				'label'   => esc_html__('Trigger', 'best-addons'),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'click',
				'options' => [
					'click' => esc_html__('On Click', 'best-addons'),
					'hover' => esc_html__('On Hover', 'best-addons'),
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'accordion_interaction',
			[
				'label' => esc_html__('Interaction', 'best-addons'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'single',
				'options' => [
					'single' => esc_html__('Single', 'best-addons'),
					'multiple' => esc_html__('Multiple', 'best-addons'),
					'all_collapsed' => esc_html__('All Collapsed', 'best-addons')
				],
				'condition' => [
					'accordion_layout_orientation' => 'vertical',
				],
			]
		);

		$this->add_control(
			'accordion_active_items',
			[
				'label' => esc_html__('Active Item Index', 'best-addons'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default'     => 1,
				'min'         => 1,
				'step'        => 1,
				'placeholder' => '1',
				'classes'     => 'best-addons-active-index',
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'relation' => 'and',
							'terms' => [
								[
									'name' => 'accordion_layout_orientation',
									'operator' => '==',
									'value' => 'vertical',
								],
								[
									'name' => 'accordion_interaction',
									'operator' => '==',
									'value' => 'single',
								],
							],
						],
						[
							'name' => 'accordion_layout_orientation',
							'operator' => '==',
							'value' => 'horizontal',
						],
					],
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'accordion_animation',
			[
				'label' => esc_html__('Animation', 'best-addons'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'slide',
				'options' => [
					'slide' => esc_html__('Slide', 'best-addons'),
					'fade' => esc_html__('Fade', 'best-addons'),
					'none' => esc_html__('None', 'best-addons'),
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$items = ! empty($settings['accordion_items'])
			? $settings['accordion_items']
			: [];

		$preset = ! empty($settings['design_preset'])
			? $settings['design_preset']
			: 'preset_1';


		$widget_id = $this->get_id();
?>

		<div
			id="best-accordion-<?php echo esc_attr($widget_id); ?>"
			class="best-advanced-accordion best-accordion-<?php echo esc_attr($preset); ?>"
			data-preset="<?php echo esc_attr($preset); ?>">

			<?php foreach ($items as $index => $item) :

				$active_class = ($index === 0) ? ' is-active' : '';
				$display_style = ($index === 0)
					? 'style="display: block;"'
					: 'style="display: none;"';

				// Collapsed icon
				$collapsed_icon = ! empty($item['collapsed_icon'])
					? $item['collapsed_icon']
					: [];

				// Expanded icon
				$expanded_icon = ! empty($item['expanded_icon'])
					? $item['expanded_icon']
					: [];

				// Title Tag
				$title_tag = \Elementor\Utils::validate_html_tag($settings['accordion_title_tag']);

			?>

				<div class="best-accordion-item<?php echo esc_attr($active_class); ?>">

					<<?php echo $title_tag; ?> class="best-accordion-heading">
						<button
							class="best-accordion-header"
							type="button"
							aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>">

							<?php if ($settings['icon_position'] === 'left') : ?>

								<span class="best-accordion-icon">

									<span class="best-accordion-icon-collapsed">
										<?php
										if (! empty($collapsed_icon['value'])) {
											\Elementor\Icons_Manager::render_icon(
												$collapsed_icon,
												[
													'aria-hidden' => 'true',
												]
											);
										}
										?>
									</span>

									<span class="best-accordion-icon-expanded">
										<?php
										if (! empty($expanded_icon['value'])) {
											\Elementor\Icons_Manager::render_icon(
												$expanded_icon,
												[
													'aria-hidden' => 'true',
												]
											);
										}
										?>
									</span>

								</span>

							<?php endif; ?>


							<span class="best-accordion-title">
								<?php echo esc_html($item['accordion_title']); ?>
							</span>


							<?php if ($settings['icon_position'] === 'right') : ?>

								<span class="best-accordion-icon">

									<span class="best-accordion-icon-collapsed">
										<?php
										if (! empty($collapsed_icon['value'])) {
											\Elementor\Icons_Manager::render_icon(
												$collapsed_icon,
												[
													'aria-hidden' => 'true',
												]
											);
										}
										?>
									</span>

									<span class="best-accordion-icon-expanded">
										<?php
										if (! empty($expanded_icon['value'])) {
											\Elementor\Icons_Manager::render_icon(
												$expanded_icon,
												[
													'aria-hidden' => 'true',
												]
											);
										}
										?>
									</span>

								</span>

							<?php endif; ?>

						</button>

					</<?php echo $title_tag; ?>>
					<div
						class="best-accordion-content"
						<?php echo $display_style; ?>>

						<?php if ('preset_2' === $preset && ! empty($item['accordion_sub_heading'])) : ?>
							<div class="best-accordion-sub-title">
								<?php echo esc_html($item['accordion_sub_heading']); ?>
							</div>
						<?php endif; ?>

						<div class="best-accordion-content-inner">
							<?php echo wp_kses_post($item['accordion_content']); ?>
						</div>
					</div>

				</div>

			<?php endforeach; ?>

		</div>

<?php
	}
}
