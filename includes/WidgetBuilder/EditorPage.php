<?php
/**
 * The full-screen React editor page for one builder widget.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\WidgetBuilder;

use BestAddons\Assets\SpineAssets;
use BestAddons\Modules\Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Full-screen Widget Builder Editor page.
 *
 * Layout (matching screenshots — and better):
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  TOP BAR: [Widget Name] [⚙] ────────────── [PREVIEW] [SAVE SETTINGS]  │
 * ├────────────┬──────────────────────────┬──────────────────┬─────────────┤
 * │ LEFT       │ MIDDLE                   │ RIGHT (code)     │ FAR-RIGHT   │
 * │ Control    │ CONTENT / STYLE /        │ HTML / CSS / JS  │ Docs:       │
 * │ Palette    │ ADVANCED tabs +          │ INCLUDES tabs    │ {{tokens}}  │
 * │ (icons)    │ Added controls list      │ CodeMirror       │ copy btn    │
 * └────────────┴──────────────────────────┴──────────────────┴─────────────┘
 */
class EditorPage
{

	// All available control types with icon, label.
	public static $control_types = [
		// Layout
		'heading'       => ['icon' => 'dashicons-heading',        'label' => 'Heading',        'group' => 'layout'],
		'divider'       => ['icon' => 'dashicons-minus',           'label' => 'Divider',        'group' => 'layout'],
		'hidden'        => ['icon' => 'dashicons-hidden',          'label' => 'Hidden',         'group' => 'layout'],
		'tabs'          => ['icon' => 'dashicons-table-col-before', 'label' => 'Tabs',           'group' => 'layout'],
		'section_start' => ['icon' => 'dashicons-editor-indent',   'label' => 'Section Start',  'group' => 'layout'],
		'section_end'   => ['icon' => 'dashicons-editor-outdent',  'label' => 'Section End',    'group' => 'layout'],
		// Text
		'text'         => ['icon' => 'dashicons-editor-paragraph', 'label' => 'Text',       'group' => 'text'],
		'textarea'     => ['icon' => 'dashicons-editor-alignleft',  'label' => 'Textarea',  'group' => 'text'],
		'wysiwyg'      => ['icon' => 'dashicons-editor-kitchensink', 'label' => 'WYSIWYG',  'group' => 'text'],
		'code'         => ['icon' => 'dashicons-editor-code',       'label' => 'Code',      'group' => 'text'],
		// Numbers & Range
		'number'       => ['icon' => 'dashicons-calculator',    'label' => 'Number',        'group' => 'number'],
		'slider'       => ['icon' => 'dashicons-leftright',      'label' => 'Slider',       'group' => 'number'],
		'dimensions'   => ['icon' => 'dashicons-editor-expand', 'label' => 'Dimensions',    'group' => 'number'],
		// Toggles
		'switcher'     => ['icon' => 'dashicons-controls-play', 'label' => 'Switcher',      'group' => 'toggle'],
		'choose'       => ['icon' => 'dashicons-grid-view',     'label' => 'Choose',        'group' => 'toggle'],
		'visual_choice' => ['icon' => 'dashicons-images-alt2',   'label' => 'Visual Choice', 'group' => 'toggle'],
		// Dropdowns
		'select'       => ['icon' => 'dashicons-arrow-down-alt2', 'label' => 'Select',       'group' => 'select'],
		'select2'      => ['icon' => 'dashicons-editor-ul',     'label' => 'Select2',       'group' => 'select'],
		// Style
		'color'        => ['icon' => 'dashicons-art',           'label' => 'Color',         'group' => 'style'],
		'typography'   => ['icon' => 'dashicons-editor-textcolor', 'label' => 'Typography',   'group' => 'style'],
		'font'         => ['icon' => 'dashicons-editor-textcolor', 'label' => 'Font',         'group' => 'style'],
		'background'   => ['icon' => 'dashicons-format-image',  'label' => 'Background',    'group' => 'style'],
		'border'       => ['icon' => 'dashicons-table-col-after', 'label' => 'Border',        'group' => 'style'],
		'box_shadow'   => ['icon' => 'dashicons-marker',        'label' => 'Box Shadow',    'group' => 'style'],
		'text_shadow'  => ['icon' => 'dashicons-text',          'label' => 'Text Shadow',   'group' => 'style'],
		// Media
		'url'          => ['icon' => 'dashicons-admin-links',   'label' => 'URL',           'group' => 'media'],
		'media'        => ['icon' => 'dashicons-format-image',  'label' => 'Media',         'group' => 'media'],
		'gallery'      => ['icon' => 'dashicons-images-alt',    'label' => 'Gallery',       'group' => 'media'],
		'icons'        => ['icon' => 'dashicons-star-filled',   'label' => 'Icons',         'group' => 'media'],
		// Advanced
		'repeater'     => ['icon' => 'dashicons-menu',          'label' => 'Repeater',      'group' => 'advanced'],
		'popover'      => ['icon' => 'dashicons-admin-comments', 'label' => 'Popover Toggle', 'group' => 'advanced'],
		'datetime'     => ['icon' => 'dashicons-calendar-alt',  'label' => 'Date Time',     'group' => 'advanced'],
	];

	public static function render()
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'best-addons'));
		}

		$widget_id = isset($_GET['widget_id']) ? absint($_GET['widget_id']) : 0;

		// Load existing widget data or create blank.
		if ($widget_id) {
			$post = get_post($widget_id);
			if (! $post || 'best_widget' !== $post->post_type) {
				wp_die(esc_html__('Widget not found.', 'best-addons'));
			}
			$widget_title = $post->post_title;
			$widget_status = $post->post_status;
		} else {
			$widget_title  = esc_html__('Untitled Widget', 'best-addons');
			$widget_status = 'draft';
		}

		$icon      = $widget_id ? (get_post_meta($widget_id, '_ba_icon',     true) ?: 'dashicons-layout') : 'dashicons-layout';
		$category  = $widget_id ? (get_post_meta($widget_id, '_ba_category', true) ?: Categories::DEFAULT) : Categories::DEFAULT;
		$controls  = $widget_id ? (get_post_meta($widget_id, '_ba_controls', true) ?: []) : [];
		$html_code = $widget_id ? (get_post_meta($widget_id, '_ba_html',     true) ?: '') : '';
		$css_code  = $widget_id ? (get_post_meta($widget_id, '_ba_css',      true) ?: '') : '';
		$js_code   = $widget_id ? (get_post_meta($widget_id, '_ba_js',       true) ?: '') : '';
		$includes  = $widget_id ? (get_post_meta($widget_id, '_ba_includes', true) ?: ['css' => [], 'js' => []]) : ['css' => [], 'js' => []];

		if (! is_array($controls)) $controls = [];
		if (! is_array($includes)) $includes = ['css' => [], 'js' => []];

		// Available Elementor widget categories. The plugin's own categories come
		// from the manifest, so a category added as a module appears in the builder's
		// dropdown with no change here. Elementor's own categories stay selectable
		// because a builder widget is not restricted to this plugin's panels.
		$el_categories = apply_filters('ba_widget_builder_categories', array_merge(
			Categories::all(),
			[
				'general' => esc_html__('General', 'best-addons'),
				'basic'   => esc_html__('Basic', 'best-addons'),
			]
		));

		// Control type groups for the palette.
		$palette_groups = [
			'layout'   => ['label' => 'Layout',   'types' => ['heading', 'divider', 'hidden', 'tabs']],
			'text'     => ['label' => 'Text',     'types' => ['text', 'textarea', 'wysiwyg', 'code']],
			'number'   => ['label' => 'Number',   'types' => ['number', 'slider', 'dimensions']],
			'toggle'   => ['label' => 'Toggle',   'types' => ['switcher', 'choose', 'visual_choice']],
			'select'   => ['label' => 'Select',   'types' => ['select', 'select2']],
			'style'    => ['label' => 'Style',    'types' => ['color', 'typography', 'font', 'background', 'border', 'box_shadow', 'text_shadow']],
			'media'    => ['label' => 'Media',    'types' => ['url', 'media', 'gallery', 'icons']],
			'advanced' => ['label' => 'Advanced', 'types' => ['repeater', 'popover', 'datetime']],
		];

		// Everything the React editor needs is handed over as one JSON payload
		// further down, so there is nothing left to serialize here.
?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>

		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo esc_html($widget_title); ?> — <?php esc_html_e('Widget Builder', 'best-addons'); ?></title>
			<?php
			// ── Assets for the standalone editor page ─────────────────────────────
			// The editor is a React app: it needs dashicons, the media library for
			// image controls, and its own compiled CSS + JS. The legacy jQuery
			// bundle and the WP-bundled CodeMirror are no longer required here.
			wp_enqueue_media();
			wp_enqueue_style('dashicons');

			SpineAssets::enqueue_style( SpineAssets::BUILDER_EDITOR_CSS, ['dashicons'] );

			// react.min.js is the shared React runtime chunk; the editor entry
			// depends on it and cannot boot without it.
			$react  = SpineAssets::enqueue_script( SpineAssets::REACT );
			SpineAssets::enqueue_script(
				SpineAssets::BUILDER_EDITOR,
				null !== $react ? [$react] : []
			);

			// Now print all enqueued styles.
			wp_print_styles();
			?>
		</head>

		<body class="ba-wb-editor-body">
			<?php
			// Print all enqueued scripts (jQuery, CodeMirror init, etc).
			wp_print_scripts();
			?>
			<!-- React root: the editor UI is a React app, PHP only supplies the data. -->
			<div id="ba-editor-root"></div>

			<!-- Bootstrap payload for the React editor. -->
			<script id="ba-editor-data" type="application/json">
				<?php
				echo wp_json_encode([
					'widget_id'      => $widget_id,
					'nonce'          => wp_create_nonce('ba_save_widget'),
					// The export handler verifies a different action, so it needs its own
					// nonce — reusing the save nonce failed the check silently.
					'export_nonce'   => wp_create_nonce('ba_widget_builder'),
					'ajax_url'       => admin_url('admin-ajax.php'),
					'back_url'       => admin_url('admin.php?page=ba-widget-builder'),
					'controls'       => $controls,
					'includes'       => $includes,
					'ctrl_types'     => self::$control_types,
					'palette_groups' => $palette_groups,
					'categories'     => array_map(
						function ($label, $value) {
							return ['value' => $value, 'label' => $label];
						},
						$el_categories,
						array_keys($el_categories)
					),
					'meta'           => [
						'title'    => $widget_title,
						'status'   => $widget_status,
						'icon'     => $icon,
						'category' => $category,
						'html'     => $html_code,
						'css'      => $css_code,
						'js'       => $js_code,
					],
					'l10n'           => [
						'saving'          => esc_html__('Saving…', 'best-addons'),
						'saved'           => esc_html__('Saved!', 'best-addons'),
						'save_error'      => esc_html__('Save failed. Please try again.', 'best-addons'),
						'untitled'        => esc_html__('Untitled Widget', 'best-addons'),
						'ctrl_settings'   => esc_html__('Control Settings', 'best-addons'),
						'remove_ctrl'     => esc_html__('Remove Control', 'best-addons'),
						'drag_hint'       => esc_html__('Drag controls from the left panel.', 'best-addons'),
						'copied'          => esc_html__('Copied!', 'best-addons'),
						'delete_ctrl'     => esc_html__('Delete this control?', 'best-addons'),
						'delete_section'  => esc_html__('Delete this entire section and its controls?', 'best-addons'),
						'no_results'      => esc_html__('No controls match.', 'best-addons'),
						'no_controls'     => esc_html__('Add a control to see its token.', 'best-addons'),
						'no_includes'     => esc_html__('None yet.', 'best-addons'),
						'no_sub_fields'   => esc_html__('No sub-fields defined yet.', 'best-addons'),
						'search_controls' => esc_html__('Search controls…', 'best-addons'),
						'click_to_insert' => esc_html__('Click to insert into editor', 'best-addons'),
						'widget_title'    => esc_html__('Widget title', 'best-addons'),
						'widget_settings' => esc_html__('Widget Settings', 'best-addons'),
						'settings'        => esc_html__('Settings', 'best-addons'),
						'preview'         => esc_html__('Preview', 'best-addons'),
						'export'          => esc_html__('Export', 'best-addons'),
						'save'            => esc_html__('Save', 'best-addons'),
						'back'            => esc_html__('Back', 'best-addons'),
						'tokens'          => esc_html__('Tokens', 'best-addons'),
						'add_include'     => esc_html__('Add include', 'best-addons'),
						'add_option'      => esc_html__('Add Option', 'best-addons'),
						'add_sub_field'   => esc_html__('Add Sub-field', 'best-addons'),
						'remove'          => esc_html__('Remove', 'best-addons'),
						'copy'            => esc_html__('Copy', 'best-addons'),
						'copy_loop'       => esc_html__('Copy loop', 'best-addons'),
						'close'           => esc_html__('Close', 'best-addons'),
						'cancel'          => esc_html__('Cancel', 'best-addons'),
						'control_id'      => esc_html__('Control ID', 'best-addons'),
						'used_as'         => esc_html__('Used as', 'best-addons'),
						'in_code'         => esc_html__('in your HTML/CSS/JS.', 'best-addons'),
						'label'           => esc_html__('Label', 'best-addons'),
						'type'            => esc_html__('Type', 'best-addons'),
						'tab'             => esc_html__('Tab', 'best-addons'),
						'title'           => esc_html__('Title', 'best-addons'),
						'status'          => esc_html__('Status', 'best-addons'),
						'draft'           => esc_html__('Draft', 'best-addons'),
						'publish'         => esc_html__('Publish', 'best-addons'),
						'category'        => esc_html__('Category', 'best-addons'),
						'icon'            => esc_html__('Icon', 'best-addons'),
						'default_value'   => esc_html__('Default Value', 'best-addons'),
						'sub_fields'      => esc_html__('Sub-fields', 'best-addons'),
						'sub_field_label' => esc_html__('Sub-field label', 'best-addons'),
						'sub_field_id'    => esc_html__('Sub-field ID', 'best-addons'),
						'option'          => esc_html__('Option', 'best-addons'),
						'option_label'    => esc_html__('Label', 'best-addons'),
						'option_value'    => esc_html__('value', 'best-addons'),
						'handle'          => esc_html__('handle', 'best-addons'),
						'source'          => esc_html__('source', 'best-addons'),
						'wrapped'         => esc_html__('Wrapped in widget container.', 'best-addons'),
						'export_failed'   => esc_html__('Export failed.', 'best-addons'),
						'save_failed'     => esc_html__('Save failed.', 'best-addons'),
						'autosaved'       => esc_html__('Autosaved', 'best-addons'),
					],
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
				?>
			</script>

			<?php
			// The React bundle is enqueued in the footer, so the mount point and the
			// bootstrap payload above are already in the DOM when it runs.
			wp_print_footer_scripts();
			?>
		</body>

		</html>
<?php
		// The editor outputs a complete standalone HTML page — stop WP here.
		exit;
	}
}
