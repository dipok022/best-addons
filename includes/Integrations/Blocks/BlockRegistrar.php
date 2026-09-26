<?php
/**
 * Gutenberg block modules.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Integrations\Blocks;

use BestAddons\Modules\BaseBlock;
use BestAddons\Modules\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates every `type: block` module and registers it with WordPress.
 *
 * Runs on `init` rather than an Elementor hook, so block modules work on a site
 * where Elementor is not installed at all — the registry's type filter is what
 * keeps the two worlds apart.
 */
final class BlockRegistrar {

	/**
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		foreach ( Registry::of_type( 'block' ) as $module ) {
			$block = Registry::instantiate( $module, BaseBlock::class );

			if ( null === $block ) {
				continue;
			}

			$block->register();
		}
	}
}
