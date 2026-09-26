<?php
/**
 * Advanced Accordion widget module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Feature\Free\Widgets\AdvancedAccordion;

use BestAddons\Modules\BaseElementorWidget;
use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * A titled, collapsible panel stack.
 *
 * The smallest useful widget module: this file is the whole of the widget's own
 * code. Its name, category, icon, keywords, stylesheet and script all come from
 * `module.json` through BaseElementorWidget, so there is nothing here that could
 * drift out of sync with the folder it lives in.
 */
final class Widget extends BaseElementorWidget {

	/**
	 * @inheritDoc
	 */
	protected function module_id(): string {
		return 'advanced-accordion';
	}

	/**
	 * @inheritDoc
	 */
	public function get_title(): string {
		return esc_html__( 'Advanced Accordion', 'best-addons' );
	}

	/**
	 * @inheritDoc
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			array( 'label' => esc_html__( 'Accordion Items', 'best-addons' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tab_title',
			array(
				'label'       => esc_html__( 'Title', 'best-addons' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Accordion Heading', 'best-addons' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'tab_content',
			array(
				'label'   => esc_html__( 'Content', 'best-addons' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => esc_html__( 'Add your content layout block.', 'best-addons' ),
			)
		);

		$this->add_control(
			'accordion_items',
			array(
				'label'       => esc_html__( 'Manage Items', 'best-addons' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_fields(),
				'default'     => array(
					array( 'tab_title' => esc_html__( 'Accordion Item #1', 'best-addons' ) ),
					array( 'tab_title' => esc_html__( 'Accordion Item #2', 'best-addons' ) ),
				),
				'title_field' => '{{{ tab_title }}}',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @inheritDoc
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$items    = $settings['accordion_items'] ?? array();

		if ( empty( $items ) ) {
			return;
		}

		?>
		<div class="ba-advanced-accordion best-advanced-accordion">
			<?php foreach ( $items as $index => $item ) : ?>
				<div class="best-accordion-item<?php echo 0 === $index ? ' is-active' : ''; ?>">
					<button
						class="best-accordion-header"
						type="button"
						aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					>
						<span class="best-accordion-title"><?php echo esc_html( $item['tab_title'] ?? '' ); ?></span>
						<span class="best-accordion-icon"></span>
					</button>
					<div
						class="best-accordion-content"
						<?php echo 0 === $index ? '' : 'hidden'; ?>
					>
						<div class="best-accordion-content-inner">
							<?php echo wp_kses_post( $item['tab_content'] ?? '' ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
