<?php
/**
 * Defence in depth for Pro modules.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

use BestAddons\Licensing\ProGate;

defined( 'ABSPATH' ) || exit;

/**
 * A trait Pro widget modules use to refuse to construct without a licence.
 *
 * The registry already drops unlocked Pro modules, so in normal operation this
 * never fires. It exists for the paths the registry does not own: a stale
 * `_elementor_data` blob that still references a Pro widget, a third-party
 * snippet doing `new ProWidget()`, a saved template from before a licence
 * lapsed. In those cases the widget degrades to a visible upgrade notice instead
 * of rendering, or fatalling inside a file that was never meant to be read.
 */
trait ProGuard {

	/**
	 * Set when construction was refused, so `render()` can explain itself.
	 */
	private bool $locked = false;

	/**
	 * Must be the first statement of the using class's constructor.
	 */
	private function guard_pro(): void {
		if ( ProGate::allows( 'pro' ) ) {
			return;
		}

		$this->locked = true;

		if ( ! did_action( 'best_addons_pro_blocked' ) ) {
			do_action( 'best_addons_pro_blocked', static::class );
		}
	}

	/**
	 * @return bool
	 */
	protected function is_locked(): bool {
		return $this->locked;
	}

	/**
	 * The markup shown in place of a locked widget.
	 */
	protected function render_locked_notice(): void {
		?>
		<div class="ba-pro-locked" style="padding:16px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#475569;font-size:13px;text-align:center;">
			<strong><?php esc_html_e( 'This widget is a Pro feature.', 'best-addons' ); ?></strong>
			<span><?php esc_html_e( 'Activate your licence to use it.', 'best-addons' ); ?></span>
		</div>
		<?php
	}
}
