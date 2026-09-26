<?php
/**
 * Widget builder menus, asset enqueueing and AJAX handlers.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\WidgetBuilder;

use BestAddons\Admin\AdminMenu;
use BestAddons\Assets\SpineAssets;
use BestAddons\Modules\Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: Menu registration, asset enqueuing, AJAX handlers.
 */
class AdminUi
{

	public static function init()
	{
		add_action('admin_menu',   [__CLASS__, 'register_menus']);
		add_action('admin_init',   [__CLASS__, 'maybe_render_editor']); // Must run before any HTML
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_list_assets']);
		
		// AJAX handlers.
		add_action('wp_ajax_ba_create_widget',  [__CLASS__, 'ajax_create_widget']);
		add_action('wp_ajax_ba_save_widget',    [__CLASS__, 'ajax_save_widget']);
		add_action('wp_ajax_ba_delete_widget',  [__CLASS__, 'ajax_delete_widget']);
		add_action('wp_ajax_ba_export_widget',  [__CLASS__, 'ajax_export_widget']);
	}

	// ── Menu Registration ─────────────────────────────────────────────────────

	public static function register_menus()
	{
		// The list page hangs off the React panel's parent menu. The link itself is
		// already registered by AdminMenu, which owns the ordering; this call only
		// attaches the render callback to that slug.
		add_submenu_page(
			AdminMenu::BUILDER_PARENT,
			esc_html__('Widget Builder', 'best-addons'),
			esc_html__('Widget Builder', 'best-addons'),
			'manage_options',
			AdminMenu::BUILDER_SLUG,
			[__CLASS__, 'render_list_page']
		);

		// Register the slug so WP recognises it as a valid admin page.
		// Actual rendering happens in admin_init before any HTML is printed.
		add_submenu_page(
			null,
			esc_html__('Edit Widget', 'best-addons'),
			esc_html__('Edit Widget', 'best-addons'),
			'manage_options',
			AdminMenu::BUILDER_EDIT_SLUG,
			'__return_null' // Never called; we intercept in admin_init.
		);
	}

	public static function render_list_page()
	{
		ListPage::render();
	}

	/**
	 * Intercepts the editor page request BEFORE WordPress prints any HTML.
	 * Called via admin_init so we can output a full standalone HTML page.
	 */
	public static function maybe_render_editor()
	{
		if (
			! is_admin() ||
			! isset($_GET['page']) ||
			AdminMenu::BUILDER_EDIT_SLUG !== sanitize_key(wp_unslash($_GET['page']))
		) {
			return;
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'best-addons'));
		}

		EditorPage::render(); // outputs full HTML + exit
	}

	// ── Asset Enqueuing ───────────────────────────────────────────────────────

	public static function enqueue_list_assets($hook)
	{
		// Only on our list page.
		if ('best-addons_page_' . AdminMenu::BUILDER_SLUG !== $hook) return;

		// jQuery UI for sortable.
		wp_enqueue_script('jquery-ui-sortable');

		$handle = SpineAssets::enqueue_script(
			SpineAssets::BUILDER_LIST,
			['jquery', 'jquery-ui-sortable']
		);

		if ( $handle ) {
			wp_localize_script($handle, 'baWidgetBuilder', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('ba_widget_builder'),
				'l10n'     => [
					'creating'     => esc_html__('Creating…', 'best-addons'),
					'created'      => esc_html__('Widget created!', 'best-addons'),
					'create_error' => esc_html__('Failed to create widget.', 'best-addons'),
					'copied'       => esc_html__('Copied!', 'best-addons'),
					'delete_confirm' => esc_html__('Delete this widget? This cannot be undone.', 'best-addons'),
				],
			]);
		}

		SpineAssets::enqueue_style( SpineAssets::BUILDER_EDITOR_CSS );
	}

	// ── AJAX: Create Widget ───────────────────────────────────────────────────

	public static function ajax_create_widget()
	{
		check_ajax_referer('ba_widget_builder', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => esc_html__('Unauthorized', 'best-addons')]);
		}

		$title    = isset($_POST['title'])    ? sanitize_text_field(wp_unslash($_POST['title']))    : '';
		$category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : Categories::DEFAULT;

		if (empty($title)) {
			wp_send_json_error(['message' => esc_html__('Widget title is required.', 'best-addons')]);
		}

		$post_id = wp_insert_post([
			'post_type'   => 'best_widget',
			'post_title'  => $title,
			'post_status' => 'draft',
		]);

		if (is_wp_error($post_id)) {
			wp_send_json_error(['message' => $post_id->get_error_message()]);
		}

		update_post_meta($post_id, '_ba_category', $category);
		update_post_meta($post_id, '_ba_icon',     'dashicons-layout');
		update_post_meta($post_id, '_ba_controls', []);
		update_post_meta($post_id, '_ba_html',     '');
		update_post_meta($post_id, '_ba_css',      '');
		update_post_meta($post_id, '_ba_js',       '');
		update_post_meta($post_id, '_ba_includes', ['css' => [], 'js' => []]);

		wp_send_json_success([
			'widget_id'  => $post_id,
			'edit_url'   => admin_url('admin.php?page=ba-widget-builder-edit&widget_id=' . $post_id),
			'message'    => esc_html__('Widget created successfully!', 'best-addons'),
		]);
	}

	// ── AJAX: Save Widget ─────────────────────────────────────────────────────

	public static function ajax_save_widget()
	{
		check_ajax_referer('ba_save_widget', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => esc_html__('Unauthorized', 'best-addons')]);
		}

		$widget_id = isset($_POST['widget_id']) ? absint($_POST['widget_id']) : 0;

		if (! $widget_id) {
			// Create new.
			$widget_id = wp_insert_post([
				'post_type'   => 'best_widget',
				'post_title'  => sanitize_text_field(wp_unslash($_POST['title'] ?? 'Untitled Widget')),
				'post_status' => sanitize_key(wp_unslash($_POST['status'] ?? 'draft')),
			]);

			if (is_wp_error($widget_id)) {
				wp_send_json_error(['message' => $widget_id->get_error_message()]);
			}
		} else {
			// Update existing.
			wp_update_post([
				'ID'          => $widget_id,
				'post_title'  => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
				'post_status' => sanitize_key(wp_unslash($_POST['status'] ?? 'draft')),
			]);
		}

		// Save meta.
		$icon      = isset($_POST['icon'])     ? sanitize_text_field(wp_unslash($_POST['icon']))     : 'dashicons-layout';
		$category  = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : Categories::DEFAULT;
		$controls  = isset($_POST['controls']) ? json_decode(wp_unslash($_POST['controls']), true) : [];
		$html      = isset($_POST['html'])     ? wp_unslash($_POST['html']) : '';
		$css       = isset($_POST['css'])      ? wp_strip_all_tags(wp_unslash($_POST['css'])) : '';
		$js        = isset($_POST['js'])       ? wp_unslash($_POST['js']) : '';
		$includes  = isset($_POST['includes']) ? json_decode(wp_unslash($_POST['includes']), true) : ['css' => [], 'js' => []];

		update_post_meta($widget_id, '_ba_icon',     $icon);
		update_post_meta($widget_id, '_ba_category', $category);
		update_post_meta($widget_id, '_ba_controls', $controls);
		update_post_meta($widget_id, '_ba_html',     $html);
		update_post_meta($widget_id, '_ba_css',      $css);
		update_post_meta($widget_id, '_ba_js',       $js);
		update_post_meta($widget_id, '_ba_includes', $includes);

		wp_send_json_success([
			'widget_id' => $widget_id,
			'message'   => esc_html__('Widget saved successfully!', 'best-addons'),
		]);
	}

	// ── AJAX: Delete Widget ───────────────────────────────────────────────────

	public static function ajax_delete_widget()
	{
		check_ajax_referer('ba_widget_builder', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => esc_html__('Unauthorized', 'best-addons')]);
		}

		$widget_id = isset($_POST['widget_id']) ? absint($_POST['widget_id']) : 0;

		if (! $widget_id) {
			wp_send_json_error(['message' => esc_html__('Invalid widget ID.', 'best-addons')]);
		}

		$result = wp_delete_post($widget_id, true);

		if (! $result) {
			wp_send_json_error(['message' => esc_html__('Failed to delete widget.', 'best-addons')]);
		}

		wp_send_json_success(['message' => esc_html__('Widget deleted.', 'best-addons')]);
	}

	// ── AJAX: Export Widget ───────────────────────────────────────────────────

	public static function ajax_export_widget()
	{
		check_ajax_referer('ba_widget_builder', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => esc_html__('Unauthorized', 'best-addons')]);
		}

		$widget_id = isset($_POST['widget_id']) ? absint($_POST['widget_id']) : 0;

		if (! $widget_id) {
			wp_send_json_error(['message' => esc_html__('Invalid widget ID.', 'best-addons')]);
		}

		$post = get_post($widget_id);
		if (! $post || 'best_widget' !== $post->post_type) {
			wp_send_json_error(['message' => esc_html__('Widget not found.', 'best-addons')]);
		}

		$data = [
			'title'    => $post->post_title,
			'icon'     => get_post_meta($widget_id, '_ba_icon',     true),
			'category' => get_post_meta($widget_id, '_ba_category', true),
			'controls' => get_post_meta($widget_id, '_ba_controls', true),
			'html'     => get_post_meta($widget_id, '_ba_html',     true),
			'css'      => get_post_meta($widget_id, '_ba_css',      true),
			'js'       => get_post_meta($widget_id, '_ba_js',       true),
			'includes' => get_post_meta($widget_id, '_ba_includes', true),
		];

		wp_send_json_success([
			'json'     => wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'filename' => sanitize_file_name($post->post_title) . '.json',
		]);
	}
}
