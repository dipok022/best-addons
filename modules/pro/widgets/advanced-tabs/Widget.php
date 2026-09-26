<?php
/**
 * Advanced Tabs widget module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Feature\Pro\Widgets\AdvancedTabs;

use BestAddons\Modules\BaseElementorWidget;
use BestAddons\Modules\ProGuard;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * The reference Pro module: everything the free accordions do, plus a control
 * that reaches for a licence.
 *
 * Nothing here checks the licence. A Pro module is only ever instantiated by
 * Registry::instantiate(), which has already passed it through ProGate, and
 * ProGuard is a second line of defence for the case where Elementor restores a
 * widget from a document on a site whose licence has since lapsed.
 */
final class Widget extends BaseElementorWidget {
	use ProGuard;

	/**
	 * @inheritDoc
	 *
	 * Elementor's own signature is untyped, so this is too.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, mixed>|null $args
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		// Must follow parent::__construct(): the guard writes to a property the
		// parent has already set up, and it must run before any control is
		// registered, because that is what decides whether this widget exists.
		$this->guard_pro();
	}

	/**
	 * @inheritDoc
	 */
	protected function module_id(): string {
		return 'advanced-tabs';
	}

	/**
	 * @inheritDoc
	 */
	public function get_title(): string {
		return esc_html__( 'Advanced Tabs', 'best-addons' );
	}

	/**
	 * @inheritDoc
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'tabs_section',
			array( 'label' => esc_html__( 'Tabs', 'best-addons' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tab_title',
			array(
				'label'       => esc_html__( 'Title', 'best-addons' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Tab Title', 'best-addons' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'tab_icon',
			array(
				'label'   => esc_html__( 'Icon', 'best-addons' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-circle',
					'library' => 'fa-solid',
				),
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
			'tabs',
			array(
				'label'       => esc_html__( 'Manage Tabs', 'best-addons' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_fields(),
				'default'     => $this->default_tabs(),
				'title_field' => '{{{ tab_title }}}',
			)
		);

		$this->add_control(
			'tabs_layout',
			array(
				'label'   => esc_html__( 'Layout', 'best-addons' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => array(
						'title' => esc_html__( 'Horizontal', 'best-addons' ),
						'icon'  => 'eicon-justify-space-between-h',
					),
					'vertical'   => array(
						'title' => esc_html__( 'Vertical', 'best-addons' ),
						'icon'  => 'eicon-justify-space-between-v',
					),
				),
				'toggle'  => false,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'tab_title_tag',
			array(
				'label'   => esc_html__( 'Title Tag', 'best-addons' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => array(
					'h2'   => 'h2',
					'h3'   => 'h3',
					'h4'   => 'h4',
					'h5'   => 'h5',
					'h6'   => 'h6',
					'div'  => 'div',
					'span' => 'span',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'behaviour_section',
			array( 'label' => esc_html__( 'Behaviour', 'best-addons' ) )
		);

		$this->add_control(
			'tabs_autoplay',
			array(
				'label'        => esc_html__( 'Auto-rotate', 'best-addons' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'tabs_autoplay_speed',
			array(
				'label'     => esc_html__( 'Rotation interval', 'best-addons' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 5000,
				'min'       => 1000,
				'max'       => 20000,
				'step'      => 500,
				'condition' => array( 'tabs_autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'tabs_indicator',
			array(
				'label'   => esc_html__( 'Active indicator', 'best-addons' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'bar',
				'options' => array(
					'bar'  => esc_html__( 'Underline bar', 'best-addons' ),
					'pill' => esc_html__( 'Filled pill', 'best-addons' ),
					'none' => esc_html__( 'None', 'best-addons' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @inheritDoc
	 */
	protected function render(): void {
		if ( $this->is_locked() ) {
			$this->render_locked_notice();

			return;
		}

		$settings = $this->get_settings_for_display();
		$tabs     = array_values( (array) ( $settings['tabs'] ?? array() ) );

		if ( empty( $tabs ) ) {
			return;
		}

		$layout      = 'vertical' === ( $settings['tabs_layout'] ?? '' ) ? 'vertical' : 'horizontal';
		$title_tag   = Utils::validate_html_tag( (string) ( $settings['tab_title_tag'] ?? 'h3' ) );
		$indicator   = in_array( $settings['tabs_indicator'] ?? '', array( 'bar', 'pill', 'none' ), true )
			? (string) $settings['tabs_indicator']
			: 'bar';
		$autoplay    = 'yes' === ( $settings['tabs_autoplay'] ?? '' )
			? max( 1000, (int) ( $settings['tabs_autoplay_speed'] ?? 5000 ) )
			: 0;

		// Elementor's own frontend hook keys off this exact name; the view script
		// rebuilds it from the module id. `get_id()` is the per-instance
		// identifier, which is what keeps two tabs widgets' element ids apart.
		$base = 'best_addons_advanced_tabs-' . $this->get_id();
		?>
		<div
			class="ba-advanced-tabs ba-advanced-tabs--<?php echo esc_attr( $layout ); ?> ba-advanced-tabs--indicator-<?php echo esc_attr( $indicator ); ?>"
			data-tabs-autoplay="<?php echo esc_attr( (string) $autoplay ); ?>"
		>
			<div class="ba-advanced-tabs__list" role="tablist">
				<?php foreach ( $tabs as $index => $tab ) :
					$tab   = (array) $tab;
					$first = 0 === $index;
					?>
					<button
						class="ba-advanced-tabs__tab<?php echo $first ? ' is-active' : ''; ?>"
						type="button"
						role="tab"
						id="<?php echo esc_attr( $base . '-tab-' . $index ); ?>"
						aria-controls="<?php echo esc_attr( $base . '-panel-' . $index ); ?>"
						aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
						tabindex="<?php echo $first ? '0' : '-1'; ?>"
					>
						<?php if ( ! empty( $tab['tab_icon']['value'] ) ) : ?>
							<span class="ba-advanced-tabs__icon">
								<?php
								Icons_Manager::render_icon(
									$tab['tab_icon'],
									array( 'aria-hidden' => 'true' )
								);
								?>
							</span>
						<?php endif; ?>

						<?php echo esc_html( (string) ( $tab['tab_title'] ?? '' ) ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $tabs as $index => $tab ) :
				$tab   = (array) $tab;
				$first = 0 === $index;
				?>
				<div
					class="ba-advanced-tabs__panel"
					role="tabpanel"
					id="<?php echo esc_attr( $base . '-panel-' . $index ); ?>"
					aria-labelledby="<?php echo esc_attr( $base . '-tab-' . $index ); ?>"
					<?php echo $first ? '' : 'hidden'; ?>
				>
					<?php echo wp_kses_post( (string) ( $tab['tab_content'] ?? '' ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function default_tabs(): array {
		$tab = array( 'tab_title' => esc_html__( 'Tab Title', 'best-addons' ) );

		return array( $tab, $tab, $tab );
	}
}
