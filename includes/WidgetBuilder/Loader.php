<?php
/**
 * Registers published builder widgets as Elementor widgets.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\WidgetBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and registers all published `best_widget` posts as Elementor widgets.
 */
class Loader
{

	public static function init()
	{
		add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);
	}

	public static function register_widgets($widgets_manager)
	{
		$posts = get_posts([
			'post_type'      => 'best_widget',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);

		if (empty($posts)) return;

		foreach ($posts as $post_id) {
			$widget = new DynamicWidget([], ['post_id' => $post_id]);
			$widgets_manager->register($widget);
		}
	}
}
