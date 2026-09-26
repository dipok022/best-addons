<?php
/**
 * Best Accordion widget module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Feature\Free\Widgets\BestAccordion;

use BestAddons\Modules\BaseElementorWidget;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * A titled accordion with configurable orientation, icon position and trigger.
 *
 * The four `design_preset` values are surfaced as a wrapper modifier class so the
 * visual variants are a pure CSS concern. Only the two fields the template reads
 * are declared as controls — the previous version of this widget also declared
 * Heading, Image, Facts and two Buttons per item, and rendered none of them.
 */
final class Widget extends BaseElementorWidget {

	/**
	 * The presets this widget ships.
	 */
	private const PRESETS = array( 'preset_1', 'preset_2', 'preset_3', 'preset_4' );

	/**
	 * @inheritDoc
	 */
	protected function module_id(): string {
		return 'best-accordion';
	}

	/**
	 * @inheritDoc
	 */
	public function get_title(): string {
		return esc_html__( 'Best Accordion', 'best-addons' );
	}

	/**
	 * @inheritDoc
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'layout_section',
			array( 'label' => esc_html__( 'Accordion Layout', 'best-addons' ) )
		);

		$this->add_control(
			'design_preset',
			array(
				'label'   => esc_html__( 'Select Preset', 'best-addons' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'preset_1',
				'options' => array(
					'preset_1' => esc_html__( 'Default', 'best-addons' ),
					'preset_2' => esc_html__( 'Glass Background', 'best-addons' ),
					'preset_3' => esc_html__( 'Facts', 'best-addons' ),
					'preset_4' => esc_html__( 'Hero Background', 'best-addons' ),
				),
			)
		);

		$this->add_control(
			'accordion_layout_orientation',
			array(
				'label'   => esc_html__( 'Layout Orientation', 'best-addons' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'vertical'   => array(
						'title' => esc_html__( 'Vertical', 'best-addons' ),
						'icon'  => 'eicon-justify-space-between-v',
					),
					'horizontal' => array(
						'title' => esc_html__( 'Horizontal', 'best-addons' ),
						'icon'  => 'eicon-justify-space-between-h',
					),
				),
				'default' => 'vertical',
				'toggle'  => false,
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'accordion_title',
			array(
				'label'       => esc_html__( 'Accordion Title', 'best-addons' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Accordion Heading', 'best-addons' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'accordion_sub_heading',
			array(
				'type'        => Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'dismiss'     => false,
				'content'     => esc_html__( 'The Sub Heading field is only used in the Glass Background preset.', 'best-addons' ),
			)
		);

		$repeater->add_control(
			'accordion_sub_title',
			array(
				'label'       => esc_html__( 'Sub Heading', 'best-addons' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Sub Heading', 'best-addons' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'accordion_content',
			array(
				'label'   => esc_html__( 'Content', 'best-addons' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => esc_html__( 'Add your content layout block.', 'best-addons' ),
			)
		);

		$repeater->start_controls_tabs( 'accordion_icon_tab' );

		$repeater->start_controls_tab(
			'collapsed_icon_tab',
			array( 'label' => esc_html__( 'Collapsed', 'best-addons' ) )
		);

		$repeater->add_control(
			'collapsed_icon',
			array(
				'label'   => esc_html__( 'Collapsed icon', 'best-addons' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-plus',
					'library' => 'fa-solid',
				),
			)
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab(
			'expanded_icon_tab',
			array( 'label' => esc_html__( 'Expanded', 'best-addons' ) )
		);

		$repeater->add_control(
			'expanded_icon',
			array(
				'label'   => esc_html__( 'Expanded icon', 'best-addons' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-minus',
					'library' => 'fa-solid',
				),
			)
		);

		$repeater->end_controls_tab();
		$repeater->end_controls_tabs();

		$this->add_control(
			'accordion_items',
			array(
				'label'       => esc_html__( 'Items', 'best-addons' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_fields(),
				'default'     => $this->default_items(),
				'title_field' => '{{{ accordion_title }}}',
				'separator'   => 'after',
			)
		);

		$this->add_control(
			'heading_alignment',
			array(
				'label'     => esc_html__( 'Heading Alignment', 'best-addons' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => $this->alignment_options(),
				'default'   => 'left',
				'toggle'    => false,
				'selectors' => array(
					'{{WRAPPER}} .best-accordion-header' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_position',
			array(
				'label'      => esc_html__( 'Icon Position', 'best-addons' ),
				'type'       => Controls_Manager::CHOOSE,
				'options'    => array(
					'left'  => array(
						'title' => esc_html__( 'Left', 'best-addons' ),
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'best-addons' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'    => 'right',
				'toggle'     => false,
				'responsive' => true,
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'accordion_title_tag',
			array(
				'label'     => esc_html__( 'Title Tag', 'best-addons' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => array(
					'h1'   => 'h1',
					'h2'   => 'h2',
					'h3'   => 'h3',
					'h4'   => 'h4',
					'h5'   => 'h5',
					'h6'   => 'h6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'interaction_section',
			array( 'label' => esc_html__( 'Interactions', 'best-addons' ) )
		);

		$this->add_control(
			'accordion_trigger',
			array(
				'label'     => esc_html__( 'Trigger', 'best-addons' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'click',
				'options'   => array(
					'click' => esc_html__( 'On Click', 'best-addons' ),
					'hover' => esc_html__( 'On Hover', 'best-addons' ),
				),
				'separator' => 'after',
			)
		);

		$this->add_control(
			'accordion_interaction',
			array(
				'label'     => esc_html__( 'Interaction', 'best-addons' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'single',
				'options'   => array(
					'single'        => esc_html__( 'Single', 'best-addons' ),
					'multiple'      => esc_html__( 'Multiple', 'best-addons' ),
					'all_collapsed' => esc_html__( 'All Collapsed', 'best-addons' ),
				),
				'condition' => array( 'accordion_layout_orientation' => 'vertical' ),
			)
		);

		$this->add_control(
			'accordion_active_items',
			array(
				'label'       => esc_html__( 'Active Item Index', 'best-addons' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 1,
				'min'         => 1,
				'step'        => 1,
				'placeholder' => '1',
				'classes'     => 'best-addons-active-index',
				'conditions'  => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'relation' => 'and',
							'terms'    => array(
								array(
									'name'     => 'accordion_layout_orientation',
									'operator' => '==',
									'value'    => 'vertical',
								),
								array(
									'name'     => 'accordion_interaction',
									'operator' => '==',
									'value'    => 'single',
								),
							),
						),
						array(
							'name'     => 'accordion_layout_orientation',
							'operator' => '==',
							'value'    => 'horizontal',
						),
					),
				),
				'separator'   => 'after',
			)
		);

		$this->add_control(
			'accordion_animation',
			array(
				'label'   => esc_html__( 'Animation', 'best-addons' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'slide',
				'options' => array(
					'slide' => esc_html__( 'Slide', 'best-addons' ),
					'fade'  => esc_html__( 'Fade', 'best-addons' ),
					'none'  => esc_html__( 'None', 'best-addons' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @inheritDoc
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$items    = array_values( (array) ( $settings['accordion_items'] ?? array() ) );

		if ( empty( $items ) ) {
			return;
		}

		$preset = in_array( $settings['design_preset'] ?? '', self::PRESETS, true )
			? (string) $settings['design_preset']
			: 'preset_1';

		$orientation = 'horizontal' === ( $settings['accordion_layout_orientation'] ?? '' )
			? 'horizontal'
			: 'vertical';

		$interaction = in_array(
			$settings['accordion_interaction'] ?? '',
			array( 'single', 'multiple', 'all_collapsed' ),
			true
		) ? (string) $settings['accordion_interaction'] : 'single';

		$icon_position = 'left' === ( $settings['icon_position'] ?? '' ) ? 'left' : 'right';
		$title_tag     = Utils::validate_html_tag( (string) ( $settings['accordion_title_tag'] ?? 'h3' ) );
		$trigger       = 'hover' === ( $settings['accordion_trigger'] ?? '' ) ? 'hover' : 'click';
		$animation     = in_array( $settings['accordion_animation'] ?? '', array( 'slide', 'fade', 'none' ), true )
			? (string) $settings['accordion_animation']
			: 'slide';

		$active_index = max( 1, (int) ( $settings['accordion_active_items'] ?? 1 ) ) - 1;
		$all_closed   = 'all_collapsed' === $interaction;

		// The first panel's open state is markup, not script: getting it wrong here
		// would paint the wrong panel open and then let the script correct it.
		$is_open = static function ( int $index ) use ( $active_index, $all_closed ): bool {
			if ( $all_closed ) {
				return false;
			}

			return $index === $active_index;
		};
		?>
		<div
			class="ba-best-accordion ba-best-accordion--<?php echo esc_attr( $preset ); ?> ba-best-accordion--<?php echo esc_attr( $orientation ); ?> ba-best-accordion--icon-<?php echo esc_attr( $icon_position ); ?>"
			data-preset="<?php echo esc_attr( $preset ); ?>"
			data-orientation="<?php echo esc_attr( $orientation ); ?>"
			data-trigger="<?php echo esc_attr( $trigger ); ?>"
			data-interaction="<?php echo esc_attr( $interaction ); ?>"
			data-animation="<?php echo esc_attr( $animation ); ?>"
			data-active-index="<?php echo esc_attr( (string) ( $active_index + 1 ) ); ?>"
		>
			<?php foreach ( $items as $index => $item ) :
				$item     = (array) $item;
				$is_first = $is_open( $index );
				?>
				<div class="best-accordion-item<?php echo $is_first ? ' is-active' : ''; ?>">
					<<?php echo esc_attr( $title_tag ); ?> class="best-accordion-heading">
						<button
							class="best-accordion-header"
							type="button"
							aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
						>
							<?php if ( 'left' === $icon_position ) {
								$this->render_icon_pair( $item );
							} ?>

							<span class="best-accordion-title">
								<?php echo esc_html( (string) ( $item['accordion_title'] ?? '' ) ); ?>
							</span>

							<?php if ( 'right' === $icon_position ) {
								$this->render_icon_pair( $item );
							} ?>
						</button>
					</<?php echo esc_attr( $title_tag ); ?>>

					<div class="best-accordion-content" <?php echo $is_first ? '' : 'hidden'; ?>>
						<?php if ( 'preset_2' === $preset && ! empty( $item['accordion_sub_title'] ) ) : ?>
							<div class="best-accordion-sub-title">
								<?php echo esc_html( (string) $item['accordion_sub_title'] ); ?>
							</div>
						<?php endif; ?>

						<div class="best-accordion-content-inner">
							<?php echo wp_kses_post( (string) ( $item['accordion_content'] ?? '' ) ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * The collapsed/expanded icon pair. The expanded copy is hidden by CSS until
	 * the panel opens, so the swap costs no JavaScript.
	 *
	 * @param array<string, mixed> $item
	 */
	private function render_icon_pair( array $item ): void {
		?>
		<span class="best-accordion-icon">
			<span class="best-accordion-icon-collapsed">
				<?php $this->render_icon( $item['collapsed_icon'] ?? null ); ?>
			</span>
			<span class="best-accordion-icon-expanded">
				<?php $this->render_icon( $item['expanded_icon'] ?? null ); ?>
			</span>
		</span>
		<?php
	}

	/**
	 * @param mixed $icon Elementor icon value.
	 */
	private function render_icon( $icon ): void {
		if ( is_array( $icon ) && ! empty( $icon['value'] ) ) {
			Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		}
	}

	/**
	 * @return array<string, array{title: string, icon: string}>
	 */
	private function alignment_options(): array {
		return array(
			'left'          => array(
				'title' => esc_html__( 'Left', 'best-addons' ),
				'icon'  => 'eicon-order-start',
			),
			'center'        => array(
				'title' => esc_html__( 'Center', 'best-addons' ),
				'icon'  => 'eicon-h-align-center',
			),
			'right'         => array(
				'title' => esc_html__( 'Right', 'best-addons' ),
				'icon'  => 'eicon-order-end',
			),
			'space-between' => array(
				'title' => esc_html__( 'Stretch', 'best-addons' ),
				'icon'  => 'eicon-grow',
			),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function default_items(): array {
		$default = array(
			'accordion_title'     => esc_html__( 'Accordion Item', 'best-addons' ),
			'accordion_sub_title' => esc_html__( 'Sub Heading', 'best-addons' ),
		);

		return array( $default, $default, $default );
	}
}
