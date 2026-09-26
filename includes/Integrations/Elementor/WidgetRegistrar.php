<?php
/**
 * Elementor widget modules.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Integrations\Elementor;

use BestAddons\Modules\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates every `type: widget` module and hands it to Elementor.
 *
 * The only work done here is a loop. Which widgets exist, what they are called,
 * which CSS and JS they need, and whether they may load at all were all settled
 * by the build-time manifest and the registry's three filters.
 */
final class WidgetRegistrar {

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register( $widgets_manager ): void {
		foreach ( Registry::of_type( 'widget' ) as $module ) {
			$widget = Registry::instantiate( $module, \Elementor\Widget_Base::class );

			if ( null === $widget ) {
				continue;
			}

			$widgets_manager->register( $widget );
		}
	}

}
