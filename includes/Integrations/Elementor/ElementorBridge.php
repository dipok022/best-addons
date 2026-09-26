<?php
/**
 * The Elementor bridge.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Integrations\Elementor;

use BestAddons\Integrations\Blocks\BlockRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the plugin does inside Elementor, in one place.
 *
 * The single decision made here is whether Elementor is present. Widget and
 * category modules are meaningless without it, so when it is absent the bridge
 * attaches nothing and the request pays nothing for an integration it cannot
 * use. Block modules deliberately live outside this class — see BlockRegistrar.
 */
final class ElementorBridge {

	/**
	 * Attach to Elementor if it is active.
	 */
	public function init(): void {
		if ( ! did_action( 'elementor/loaded' ) ) {
			// Elementor loads on `plugins_loaded`. The plugin's own boot runs
			// after that, so reaching here means Elementor is genuinely absent.
			return;
		}

		$categories = new CategoryRegistrar();
		$widgets    = new WidgetRegistrar();

		add_action(
			'elementor/elements/categories_registered',
			static fn ( $manager ) => $categories->register( $manager )
		);

		add_action(
			'elementor/widgets/register',
			static fn ( $manager ) => $widgets->register( $manager )
		);
	}

	/**
	 * Register block modules. Unconditional, because blocks do not need Elementor.
	 */
	public function init_blocks(): void {
		$blocks = new BlockRegistrar();

		add_action( 'init', array( $blocks, 'register' ), 20 );
	}
}
