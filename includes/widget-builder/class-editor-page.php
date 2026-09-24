<?php
if (! defined('ABSPATH')) exit;

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
class Best_Addons_Widget_Builder_Editor_Page
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
		$category  = $widget_id ? (get_post_meta($widget_id, '_ba_category', true) ?: 'best-addons-category') : 'best-addons-category';
		$controls  = $widget_id ? (get_post_meta($widget_id, '_ba_controls', true) ?: []) : [];
		$html_code = $widget_id ? (get_post_meta($widget_id, '_ba_html',     true) ?: '') : '';
		$css_code  = $widget_id ? (get_post_meta($widget_id, '_ba_css',      true) ?: '') : '';
		$js_code   = $widget_id ? (get_post_meta($widget_id, '_ba_js',       true) ?: '') : '';
		$includes  = $widget_id ? (get_post_meta($widget_id, '_ba_includes', true) ?: ['css' => [], 'js' => []]) : ['css' => [], 'js' => []];

		if (! is_array($controls)) $controls = [];
		if (! is_array($includes)) $includes = ['css' => [], 'js' => []];

		// Available Elementor widget categories (from options or defaults).
		$el_categories = apply_filters('ba_widget_builder_categories', [
			'best-addons-category' => esc_html__('Best Addons', 'best-addons'),
			'general'              => esc_html__('General', 'best-addons'),
			'basic'                => esc_html__('Basic', 'best-addons'),
		]);

		// Control type groups for the palette.
		$palette_groups = [
			'layout'   => ['label' => 'Layout',   'types' => ['heading', 'divider', 'hidden', 'tabs', 'section_start', 'section_end']],
			'text'     => ['label' => 'Text',     'types' => ['text', 'textarea', 'wysiwyg', 'code']],
			'number'   => ['label' => 'Number',   'types' => ['number', 'slider', 'dimensions']],
			'toggle'   => ['label' => 'Toggle',   'types' => ['switcher', 'choose', 'visual_choice']],
			'select'   => ['label' => 'Select',   'types' => ['select', 'select2']],
			'style'    => ['label' => 'Style',    'types' => ['color', 'typography', 'font', 'background', 'border', 'box_shadow', 'text_shadow']],
			'media'    => ['label' => 'Media',    'types' => ['url', 'media', 'gallery', 'icons']],
			'advanced' => ['label' => 'Advanced', 'types' => ['repeater', 'popover', 'datetime']],
		];

		// Serialize for JS.
		$js_controls = esc_attr(wp_json_encode($controls));
		$js_includes = esc_attr(wp_json_encode($includes));
		$js_types    = esc_attr(wp_json_encode(self::$control_types));

		// Categories dropdown.
		ob_start();
		foreach ($el_categories as $val => $label) {
			echo '<option value="' . esc_attr($val) . '"' . selected($category, $val, false) . '>' . esc_html($label) . '</option>';
		}
		$categories_options = ob_get_clean();
?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>

		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo esc_html($widget_title); ?> — <?php esc_html_e('Widget Builder', 'best-addons'); ?></title>
			<?php
			// ── Load all necessary WP assets for standalone page ─────────────────────
			// Enqueue everything needed BEFORE printing styles/scripts.
			wp_enqueue_media();

			// Dashicons
			wp_enqueue_style('dashicons');

			// CodeMirror (WP bundled since 4.9)
			$cm_settings = wp_enqueue_code_editor(['type' => 'text/html']);

			// jQuery (needed before our JS)
			wp_enqueue_script('jquery');

			// WP AJAX
			wp_enqueue_script('wp-ajax-response');

			// Our editor CSS (dist first, else skip — inline fallback below)
			$plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
			$plugin_url = plugins_url('', dirname(dirname(__FILE__)));
			$dist_css   = $plugin_dir . 'assets/dist/css/widget-builder-admin.min.css';
			if (file_exists($dist_css)) {
				wp_enqueue_style(
					'ba-widget-builder-admin',
					$plugin_url . '/assets/dist/css/widget-builder-admin.min.css',
					['dashicons', 'wp-codemirror'],
					filemtime($dist_css)
				);
			}

			// Our editor JS (dist first, else src)
			$dist_js = $plugin_dir . 'assets/dist/js/widget-builder-admin.min.js';
			$src_js  = $plugin_dir . 'assets/src/js/widget-builder-admin.js';
			if (file_exists($dist_js)) {
				$editor_js_url = $plugin_url . '/assets/dist/js/widget-builder-admin.min.js';
				$editor_js_ver = filemtime($dist_js);
			} elseif (file_exists($src_js)) {
				$editor_js_url = $plugin_url . '/assets/src/js/widget-builder-admin.js';
				$editor_js_ver = filemtime($src_js);
			} else {
				$editor_js_url = false;
				$editor_js_ver = '1.0.0';
			}

			// Now print all enqueued styles.
			wp_print_styles();
			?>
		</head>

		<body class="ba-wb-editor-body">
			<?php
			// Print all enqueued scripts (jQuery, CodeMirror init, etc).
			wp_print_scripts();
			?>

			<!-- ══════════════════════════════════════════════════════════════════════ -->
			<!--  TOP BAR                                                               -->
			<!-- ══════════════════════════════════════════════════════════════════════ -->
			<div class="ba-editor-topbar">
				<div class="ba-topbar-left">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ba-widget-builder')); ?>" class="ba-topbar-back" title="<?php esc_attr_e('Back to widget list', 'best-addons'); ?>">
						<span class="dashicons dashicons-arrow-left-alt"></span>
					</a>
					<div class="ba-topbar-widget-name-wrap">
						<input
							type="text"
							id="ba-widget-title-input"
							class="ba-topbar-widget-name"
							value="<?php echo esc_attr($widget_title); ?>"
							placeholder="<?php esc_attr_e('Widget Name…', 'best-addons'); ?>"
							autocomplete="off">
					</div>
					<button type="button" id="ba-widget-settings-btn" class="ba-topbar-settings-btn" title="<?php esc_attr_e('Widget Settings', 'best-addons'); ?>">
						<span class="dashicons dashicons-admin-generic"></span>
						<span class="ba-topbar-settings-label"><?php echo esc_html($widget_title); ?></span>
					</button>
					<span class="ba-autosave-indicator" id="ba-autosave-indicator"></span>
				</div>
				<div class="ba-topbar-right">
					<span class="ba-status-dot <?php echo $widget_status === 'publish' ? 'ba-status-live' : 'ba-status-draft'; ?>" id="ba-status-dot"></span>
					<span class="ba-status-label" id="ba-status-label">
						<?php echo $widget_status === 'publish' ? esc_html__('Active', 'best-addons') : esc_html__('Draft', 'best-addons'); ?>
					</span>
					<button type="button" id="ba-preview-btn" class="button ba-btn-preview">
						<?php esc_html_e('Preview', 'best-addons'); ?>
					</button>
					<button type="button" id="ba-save-btn" class="button ba-btn-save" data-widget-id="<?php echo esc_attr($widget_id); ?>">
						<?php esc_html_e('Save Settings', 'best-addons'); ?>
					</button>
				</div>
			</div>

			<!-- ══════════════════════════════════════════════════════════════════════ -->
			<!--  EDITOR BODY: 4-column layout                                          -->
			<!-- ══════════════════════════════════════════════════════════════════════ -->
			<div class="ba-editor-body" id="ba-editor-body">

				<!-- ── COL 1: Widget Settings Panel (slides in from left via gear btn) -->
				<div class="ba-settings-panel" id="ba-settings-panel">
					<div class="ba-settings-panel-header">
						<h3><?php esc_html_e('Widget Settings', 'best-addons'); ?></h3>
						<button type="button" id="ba-settings-panel-close" class="ba-panel-close">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="ba-settings-panel-body">
						<div class="ba-settings-field">
							<label><?php esc_html_e('Widget Title', 'best-addons'); ?></label>
							<input type="text" id="ba-settings-title" value="<?php echo esc_attr($widget_title); ?>" class="widefat">
						</div>
						<div class="ba-settings-field">
							<label><?php esc_html_e('Widget Icon', 'best-addons'); ?></label>
							<div class="ba-icon-picker-wrap">
								<div class="ba-selected-icon-preview" id="ba-selected-icon-preview">
									<span class="dashicons <?php echo esc_attr($icon); ?>" id="ba-icon-preview-span"></span>
								</div>
								<button type="button" id="ba-change-icon-btn" class="button">
									<?php esc_html_e('Change Icon', 'best-addons'); ?>
								</button>
								<input type="hidden" id="ba-widget-icon" value="<?php echo esc_attr($icon); ?>">
							</div>
						</div>
						<div class="ba-settings-field">
							<label><?php esc_html_e('Widget Category', 'best-addons'); ?></label>
							<select id="ba-widget-category" class="widefat">
								<?php echo $categories_options; ?>
							</select>
						</div>
						<hr class="ba-settings-divider">
						<div class="ba-settings-field">
							<label><?php esc_html_e('Status', 'best-addons'); ?></label>
							<select id="ba-widget-status" class="widefat">
								<option value="publish" <?php selected($widget_status, 'publish'); ?>>
									<?php esc_html_e('Active (Published)', 'best-addons'); ?>
								</option>
								<option value="draft" <?php selected($widget_status, 'draft'); ?>>
									<?php esc_html_e('Draft (Inactive)', 'best-addons'); ?>
								</option>
							</select>
						</div>
						<hr class="ba-settings-divider">
						<?php if ($widget_id) : ?>
							<div class="ba-settings-field">
								<label><?php esc_html_e('Export Widget', 'best-addons'); ?></label>
								<p class="ba-field-desc"><?php esc_html_e('Export the full widget configuration as a JSON file you can back up, share, or import on another site.', 'best-addons'); ?></p>
								<button type="button" id="ba-export-widget-btn" class="button ba-btn-export" data-id="<?php echo esc_attr($widget_id); ?>">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e('Export Widget', 'best-addons'); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- ── COL 2: Control Palette ──────────────────────────────────────── -->
				<div class="ba-editor-col ba-palette-col" id="ba-palette-col">
					<div class="ba-palette-search-wrap" style="position:relative;padding:12px;border-bottom:1px solid rgba(255,255,255,0.08);box-sizing:border-box;width:100%;">
						<span class="dashicons dashicons-search ba-palette-search-icon" style="position:absolute;left:22px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:15px;color:#64748b;"></span>
						<input
							type="text"
							id="ba-palette-search"
							class="ba-palette-search"
							placeholder="<?php esc_attr_e('Search controls…', 'best-addons'); ?>"
							autocomplete="off"
							style="display:block;width:100%;box-sizing:border-box;padding:8px 12px 8px 34px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:#1e293b;color:#f1f5f9;font-size:12px;">
					</div>
					<div class="ba-palette-scroll" id="ba-palette-scroll">
						<?php foreach ($palette_groups as $group_key => $group) : ?>
							<div class="ba-palette-group" data-group="<?php echo esc_attr($group_key); ?>">
								<div class="ba-palette-group-title"><?php echo esc_html($group['label']); ?></div>
								<div class="ba-palette-grid">
									<?php foreach ($group['types'] as $type_key) :
										$ctrl_def = self::$control_types[$type_key] ?? [];
										$icon_cls = $ctrl_def['icon']  ?? 'dashicons-layout';
										$label    = $ctrl_def['label'] ?? $type_key;
									?>
										<div class="ba-palette-item"
											data-type="<?php echo esc_attr($type_key); ?>"
											data-label="<?php echo esc_attr($label); ?>"
											draggable="true"
											title="<?php echo esc_attr($label); ?>">
											<span class="dashicons <?php echo esc_attr($icon_cls); ?>"></span>
											<span class="ba-palette-item-label"><?php echo esc_html($label); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- ── COL 3: Settings Panel ───────────────────────────────────────── -->
				<div class="ba-editor-col ba-controls-col" id="ba-controls-col">
					<!-- Panel Tabs: CONTENT / ADVANCED / STYLE -->
					<div class="ba-panel-tabs">
						<button class="ba-panel-tab active" data-tab="content">
							<span class="dashicons dashicons-screenoptions"></span>
							<?php esc_html_e('CONTENT', 'best-addons'); ?>
						</button>
						<button class="ba-panel-tab" data-tab="advanced">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e('ADVANCED', 'best-addons'); ?>
						</button>
						<button class="ba-panel-tab" data-tab="style">
							<span class="dashicons dashicons-art"></span>
							<?php esc_html_e('STYLE', 'best-addons'); ?>
						</button>
					</div>

					<!-- Tab Content Panels -->
					<div class="ba-panel-tab-content active" id="ba-tab-content">
						<div class="ba-controls-drop-area" id="ba-controls-content" data-tab="content">
							<div class="ba-drop-hint" id="ba-drop-hint-content">
								<button type="button" class="ba-add-section-btn" data-tab="content">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e('Add Section', 'best-addons'); ?>
								</button>
								<span class="dashicons dashicons-arrow-left-alt2"></span>
								<p><?php esc_html_e('Drag controls from the left panel to build your widget\'s Content tab.', 'best-addons'); ?></p>
							</div>
						</div>
					</div>
					<div class="ba-panel-tab-content" id="ba-tab-advanced">
						<div class="ba-controls-drop-area" id="ba-controls-advanced" data-tab="advanced">
							<div class="ba-drop-hint" id="ba-drop-hint-advanced">
								<button type="button" class="ba-add-section-btn" data-tab="advanced">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e('Add Section', 'best-addons'); ?>
								</button>
								<span class="dashicons dashicons-arrow-left-alt2"></span>
								<p><?php esc_html_e('Drag controls here for the Advanced tab.', 'best-addons'); ?></p>
							</div>
						</div>
					</div>
					<div class="ba-panel-tab-content" id="ba-tab-style">
						<div class="ba-controls-drop-area" id="ba-controls-style" data-tab="style">
							<div class="ba-drop-hint" id="ba-drop-hint-style">
								<button type="button" class="ba-add-section-btn" data-tab="style">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e('Add Section', 'best-addons'); ?>
								</button>
								<span class="dashicons dashicons-arrow-left-alt2"></span>
								<p><?php esc_html_e('Drag style controls here for the Style tab.', 'best-addons'); ?></p>
							</div>
						</div>
					</div>

					<!-- Control Settings Drawer (slides up when a control row is clicked) -->
					<div class="ba-ctrl-settings-drawer" id="ba-ctrl-settings-drawer" style="display:none;">
						<div class="ba-ctrl-settings-header">
							<span id="ba-ctrl-settings-title"><?php esc_html_e('Control Settings', 'best-addons'); ?></span>
							<button type="button" id="ba-ctrl-settings-close">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
						<div class="ba-ctrl-settings-body" id="ba-ctrl-settings-body">
							<!-- Dynamically populated by JS -->
						</div>
					</div>
				</div>

				<!-- ── COL 4: Code Editor ──────────────────────────────────────────── -->
				<div class="ba-editor-col ba-code-col" id="ba-code-col">
					<div class="ba-code-tabs">
						<button class="ba-code-tab active" data-code-tab="html">HTML</button>
						<button class="ba-code-tab" data-code-tab="css">CSS</button>
						<button class="ba-code-tab" data-code-tab="js">JS</button>
						<button class="ba-code-tab" data-code-tab="includes">INCLUDES</button>
					</div>

					<!-- HTML -->
					<div class="ba-code-panel active" id="ba-code-panel-html">
						<textarea id="ba-html-editor" class="ba-codemirror-target" data-mode="htmlmixed"><?php echo esc_textarea($html_code); ?></textarea>
					</div>
					<!-- CSS -->
					<div class="ba-code-panel" id="ba-code-panel-css">
						<textarea id="ba-css-editor" class="ba-codemirror-target" data-mode="css"><?php echo esc_textarea($css_code); ?></textarea>
					</div>
					<!-- JS -->
					<div class="ba-code-panel" id="ba-code-panel-js">
						<textarea id="ba-js-editor" class="ba-codemirror-target" data-mode="javascript"><?php echo esc_textarea($js_code); ?></textarea>
					</div>
					<!-- INCLUDES -->
					<div class="ba-code-panel" id="ba-code-panel-includes">
						<div class="ba-includes-section">
							<h4><?php esc_html_e('CSS Libraries', 'best-addons'); ?></h4>
							<p class="ba-field-desc"><?php esc_html_e('Load external CSS files (e.g. Font Awesome CDN). They are only enqueued on pages using this widget.', 'best-addons'); ?></p>
							<div class="ba-includes-list" id="ba-includes-css-list">
								<?php
								$css_libs = $includes['css'] ?? [];
								foreach ($css_libs as $lib) : ?>
									<div class="ba-include-row">
										<input type="text" class="ba-include-handle" placeholder="<?php esc_attr_e('Handle (unique name)', 'best-addons'); ?>" value="<?php echo esc_attr($lib['handle'] ?? ''); ?>">
										<input type="url" class="ba-include-src" placeholder="<?php esc_attr_e('https://cdn.example.com/style.css', 'best-addons'); ?>" value="<?php echo esc_attr($lib['src'] ?? ''); ?>">
										<button type="button" class="ba-remove-include button-link-delete">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button ba-btn-add-include" data-target="css">
								<?php esc_html_e('+ Add CSS', 'best-addons'); ?>
							</button>
						</div>

						<div class="ba-includes-section">
							<h4><?php esc_html_e('JS Libraries', 'best-addons'); ?></h4>
							<p class="ba-field-desc"><?php esc_html_e('Load external JS files (e.g. jQuery, GSAP). They are only enqueued on pages using this widget.', 'best-addons'); ?></p>
							<div class="ba-includes-list" id="ba-includes-js-list">
								<?php
								$js_libs = $includes['js'] ?? [];
								foreach ($js_libs as $lib) : ?>
									<div class="ba-include-row">
										<input type="text" class="ba-include-handle" placeholder="<?php esc_attr_e('Handle (unique name)', 'best-addons'); ?>" value="<?php echo esc_attr($lib['handle'] ?? ''); ?>">
										<input type="url" class="ba-include-src" placeholder="<?php esc_attr_e('https://cdn.example.com/library.js', 'best-addons'); ?>" value="<?php echo esc_attr($lib['src'] ?? ''); ?>">
										<button type="button" class="ba-remove-include button-link-delete">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button ba-btn-add-include" data-target="js">
								<?php esc_html_e('+ Add JS', 'best-addons'); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- ── COL 5: Documentation / Token Sidebar ────────────────────────── -->
				<div class="ba-editor-col ba-docs-col" id="ba-docs-col">
					<div class="ba-docs-header">
						<?php esc_html_e('Documentation', 'best-addons'); ?>
					</div>
					<div class="ba-docs-body" id="ba-docs-body">
						<div class="ba-docs-empty" id="ba-docs-empty">
							<p><?php esc_html_e('Tokens appear here as you add controls. Click any token to copy it.', 'best-addons'); ?></p>
						</div>
						<!-- Tokens are dynamically injected by JS when controls are added. -->
						<div class="ba-docs-tokens" id="ba-docs-tokens"></div>
					</div>
				</div>

			</div><!-- /.ba-editor-body -->

			<!-- Hidden data for JS -->
			<script id="ba-editor-data" type="application/json">
				<?php echo wp_json_encode([
					'widget_id'   => $widget_id,
					'nonce'       => wp_create_nonce('ba_save_widget'),
					'ajax_url'    => admin_url('admin-ajax.php'),
					'controls'    => $controls,
					'includes'    => $includes,
					'ctrl_types'  => self::$control_types,
					'back_url'    => admin_url('admin.php?page=ba-widget-builder'),
					'l10n'        => [
						'saving'        => esc_html__('Saving…', 'best-addons'),
						'saved'         => esc_html__('Saved!', 'best-addons'),
						'save_error'    => esc_html__('Save failed. Please try again.', 'best-addons'),
						'untitled'      => esc_html__('Untitled Widget', 'best-addons'),
						'ctrl_settings' => esc_html__('Control Settings', 'best-addons'),
						'remove_ctrl'   => esc_html__('Remove Control', 'best-addons'),
						'drag_hint'     => esc_html__('Drag controls from the left panel.', 'best-addons'),
						'copied'        => esc_html__('Copied!', 'best-addons'),
						'delete_ctrl'   => esc_html__('Delete this control?', 'best-addons'),
					],
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
			</script>

			<?php
			// $editor_js_url and $editor_js_ver were resolved in the <head> section above.
			wp_print_footer_scripts();
			if (! empty($editor_js_url)) : ?>
				<script src="<?php echo esc_url($editor_js_url); ?>?ver=<?php echo esc_attr($editor_js_ver); ?>"></script>
			<?php endif; ?>
		</body>

		</html>
<?php
		// The editor outputs a complete standalone HTML page — stop WP here.
		exit;
	}
}
