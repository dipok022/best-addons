<?php
/**
 * Registration of the `best_widget` custom post type.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\WidgetBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `best_widget` Custom Post Type.
 * 
 * No standard edit.php UI — we'll use completely custom admin pages.
 */
class Cpt
{

	public static function init()
	{
		add_action('init', [__CLASS__, 'register_cpt']);
	}

	public static function register_cpt()
	{
		register_post_type('best_widget', [
			'labels'              => [
				'name'               => esc_html__('Widgets', 'best-addons'),
				'singular_name'      => esc_html__('Widget', 'best-addons'),
				'add_new'            => esc_html__('Add New Widget', 'best-addons'),
				'add_new_item'       => esc_html__('Add New Widget', 'best-addons'),
				'edit_item'          => esc_html__('Edit Widget', 'best-addons'),
				'menu_name'          => esc_html__('Widget Builder', 'best-addons'),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false, // No WP UI
			'show_in_menu'        => false, // We'll add custom menus
			'supports'            => ['title', 'custom-fields'],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'rewrite'             => false,
			'query_var'           => false,
			'has_archive'         => false,
			'hierarchical'        => false,
		]);
	}
}
