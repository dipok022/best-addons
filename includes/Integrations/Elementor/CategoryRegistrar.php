<?php
/**
 * Elementor category modules.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Integrations\Elementor;

use BestAddons\Modules\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one Elementor panel category per `type: category` module.
 *
 * This is why there is no `add_category()` call anywhere in the spine. A new
 * category is a folder, and the extractor's uniqueness rule guarantees two
 * categories cannot fight over the same slug.
 */
final class CategoryRegistrar {

	/**
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public function register( $elements_manager ): void {
		foreach ( Registry::of_type( 'category' ) as $category ) {
			$elements_manager->add_category(
				$category->id(),
				array(
					'title' => $category->title(),
					'icon'  => $category->icon(),
				)
			);
		}
	}
}
