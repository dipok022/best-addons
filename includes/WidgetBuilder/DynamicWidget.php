<?php
/**
 * An Elementor widget generated from `best_widget` data.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\WidgetBuilder;

use BestAddons\Assets\SpineAssets;
use BestAddons\Modules\Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Elementor Widget generated from CPT data.
 * 
 * Improved token system with {{control_id}} parsing in HTML/CSS/JS.
 */
class DynamicWidget extends \Elementor\Widget_Base
{

	private $post_id;
	private $widget_data;

	public function __construct($data = [], $args = null)
	{
		if (! empty($args['post_id'])) {
			$this->post_id     = (int) $args['post_id'];
			$this->widget_data = $this->load_widget_data();
		}
		parent::__construct($data, $args);
	}

	private function load_widget_data()
	{
		$post = get_post($this->post_id);
		if (! $post || 'best_widget' !== $post->post_type) return [];

		return [
			'title'    => $post->post_title,
			'slug'     => sanitize_title($post->post_title),
			'icon'     => get_post_meta($this->post_id, '_ba_icon', true) ?: 'eicon-code',
			'category' => get_post_meta($this->post_id, '_ba_category', true) ?: Categories::DEFAULT,
			'controls' => get_post_meta($this->post_id, '_ba_controls', true) ?: [],
			'html'     => get_post_meta($this->post_id, '_ba_html', true) ?: '',
			'css'      => get_post_meta($this->post_id, '_ba_css', true) ?: '',
			'js'       => get_post_meta($this->post_id, '_ba_js', true) ?: '',
			'includes' => get_post_meta($this->post_id, '_ba_includes', true) ?: ['css' => [], 'js' => []],
		];
	}

	public function get_name()
	{
		return 'best_addons_custom_' . ($this->widget_data['slug'] ?? $this->post_id);
	}

	public function get_title()
	{
		return $this->widget_data['title'] ?? esc_html__('Custom Widget', 'best-addons');
	}

	public function get_icon()
	{
		return $this->widget_data['icon'] ?? 'eicon-code';
	}

	public function get_categories()
	{
		return [$this->widget_data['category'] ?? Categories::DEFAULT];
	}

	// ── Register Controls ─────────────────────────────────────────────────────

	protected function register_controls()
	{
		$controls = $this->widget_data['controls'] ?? [];

		if (empty($controls)) {
			$this->start_controls_section('ba_empty', ['label' => esc_html__('Content', 'best-addons')]);
			$this->add_control('ba_no_controls', [
				'type' => \Elementor\Controls_Manager::NOTICE,
				'notice_type' => 'info',
				'content' => esc_html__('No controls defined. Edit this widget in Widget Builder.', 'best-addons'),
			]);
			$this->end_controls_section();
			return;
		}

		// Check if any control is a section_start/section_end — if so, the
		// user has manually structured their sections. Register controls flat.
		$has_custom_sections = false;
		foreach ($controls as $ctrl) {
			if (in_array($ctrl['type'] ?? '', ['section_start', 'section_end'], true)) {
				$has_custom_sections = true;
				break;
			}
		}

		if ($has_custom_sections) {
			// User-defined sections: register controls flat. Any control that
			// lands OUTSIDE a section_start…section_end pair is auto-wrapped in
			// its own section so Elementor never sees a control outside a section.
			$section_open = false;

			foreach ($controls as $ctrl) {
				$type = $ctrl['type'] ?? '';

				if ($type === 'section_start') {
					// Close any auto-wrapped section still open from before.
					if ($section_open) {
						$this->end_controls_section();
					}
					$this->register_single_control($ctrl);
					$section_open = true;
				} elseif ($type === 'section_end') {
					// Only close when a section is actually open (ignore orphans).
					if ($section_open) {
						$this->register_single_control($ctrl);
						$section_open = false;
					}
				} else {
					// Control outside a section → auto-wrap it so Elementor
					// doesn't throw "Cannot add a control outside of a section".
					if (! $section_open) {
						$tab = strtolower($ctrl['tab'] ?? 'content');
						$this->start_controls_section(
							'ba_auto_' . sanitize_key($ctrl['id']),
							[
								'label' => esc_html__('Settings', 'best-addons'),
								'tab'   => 'style' === $tab
									? \Elementor\Controls_Manager::TAB_STYLE
									: \Elementor\Controls_Manager::TAB_CONTENT,
							]
						);
						$section_open = true;
					}
					$this->register_single_control($ctrl);
				}
			}

			// Close any dangling section still open at the end.
			if ($section_open) {
				$this->end_controls_section();
			}
			return;
		}

		// Auto-group by tab: content / advanced / style.
		$tabs = ['content' => [], 'advanced' => [], 'style' => []];
		foreach ($controls as $ctrl) {
			$tab = strtolower($ctrl['tab'] ?? 'content');
			if (! isset($tabs[$tab])) $tab = 'content';
			$tabs[$tab][] = $ctrl;
		}

		foreach ($tabs as $tab_key => $tab_controls) {
			if (empty($tab_controls)) continue;

			$tab_id    = 'ba_tab_' . $tab_key;
			$tab_label = ucfirst($tab_key);
			$tab_const = $tab_key === 'style'
				? \Elementor\Controls_Manager::TAB_STYLE
				: \Elementor\Controls_Manager::TAB_CONTENT;

			$this->start_controls_section($tab_id, [
				'label' => esc_html($tab_label),
				'tab'   => $tab_const,
			]);

			foreach ($tab_controls as $ctrl) {
				$this->register_single_control($ctrl);
			}

			$this->end_controls_section();
		}
	}

	private function register_single_control(array $ctrl)
	{
		if (empty($ctrl['id']) || empty($ctrl['type'])) return;

		$id      = sanitize_key($ctrl['id']);
		$label   = $ctrl['label'] ?? ucwords(str_replace('_', ' ', $id));
		$default = $ctrl['default'] ?? '';
		$type    = $ctrl['type'];

		$base = [
			'label'   => esc_html($label),
			'default' => $default,
		];

		switch ($type) {
			case 'text':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
				]));
				break;

			case 'textarea':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::TEXTAREA,
					'label_block' => true,
				]));
				break;

			case 'wysiwyg':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::WYSIWYG,
				]));
				break;

			case 'number':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::NUMBER,
				]));
				break;

			case 'color':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::COLOR,
				]));
				break;

			case 'url':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::URL,
					'label_block' => true,
				]));
				break;

			case 'media':
				$this->add_control($id, [
					'label' => esc_html($label),
					'type'  => \Elementor\Controls_Manager::MEDIA,
				]);
				break;

			case 'icons':
				$this->add_control($id, [
					'label' => esc_html($label),
					'type'  => \Elementor\Controls_Manager::ICONS,
				]);
				break;

			case 'switcher':
				$this->add_control($id, array_merge($base, [
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => esc_html__('Yes', 'best-addons'),
					'label_off'    => esc_html__('No', 'best-addons'),
					'return_value' => 'yes',
				]));
				break;

			case 'select':
				$options = [];
				if (! empty($ctrl['options']) && is_array($ctrl['options'])) {
					foreach ($ctrl['options'] as $opt) {
						$options[$opt['value'] ?? ''] = $opt['label'] ?? $opt['value'] ?? '';
					}
				}
				$this->add_control($id, array_merge($base, [
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => $options,
				]));
				break;

			case 'choose':
				$options = [];
				if (! empty($ctrl['options']) && is_array($ctrl['options'])) {
					foreach ($ctrl['options'] as $opt) {
						$options[$opt['value'] ?? ''] = [
							'title' => $opt['label'] ?? $opt['value'] ?? '',
							'icon'  => $opt['icon']  ?? 'eicon-circle',
						];
					}
				}
				$this->add_control($id, array_merge($base, [
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'options' => $options,
					'toggle'  => false,
				]));
				break;

			case 'slider':
				$this->add_control($id, array_merge($base, [
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => $ctrl['units'] ?? ['px'],
					'range'       => $ctrl['range'] ?? ['px' => ['min' => 0, 'max' => 100]],
				]));
				break;

			case 'dimensions':
				$this->add_control($id, array_merge($base, [
					'type'        => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units'  => $ctrl['units'] ?? ['px', '%', 'em'],
				]));
				break;

			case 'typography':
				$this->add_group_control(
					\Elementor\Group_Control_Typography::get_type(),
					[
						'name'     => $id,
						'label'    => esc_html($label),
						'selector' => $ctrl['selector'] ?? '{{WRAPPER}} .ba-widget-element',
					]
				);
				break;

			case 'border':
				$this->add_group_control(
					\Elementor\Group_Control_Border::get_type(),
					[
						'name'     => $id,
						'label'    => esc_html($label),
						'selector' => $ctrl['selector'] ?? '{{WRAPPER}} .ba-widget-element',
					]
				);
				break;

			case 'box_shadow':
				$this->add_group_control(
					\Elementor\Group_Control_Box_Shadow::get_type(),
					[
						'name'     => $id,
						'label'    => esc_html($label),
						'selector' => $ctrl['selector'] ?? '{{WRAPPER}} .ba-widget-element',
					]
				);
				break;

			// ── Layout controls ────────────────────────────────────────────

			case 'heading':
				$this->add_control($id, [
					'label'     => esc_html($label),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => $ctrl['separator'] ?? 'before',
				]);
				break;

			case 'divider':
				$this->add_control($id, [
					'type' => \Elementor\Controls_Manager::DIVIDER,
				]);
				break;

			case 'hidden':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::HIDDEN,
				]));
				break;

			case 'section_start':
				// Opens a new Elementor controls section (popover-like group inside a tab).
				$this->start_controls_section(
					$id . '_section',
					[
						'label' => esc_html($label),
						'tab'   => $ctrl['tab'] === 'style'
							? \Elementor\Controls_Manager::TAB_STYLE
							: \Elementor\Controls_Manager::TAB_CONTENT,
					]
				);
				break;

			case 'section_end':
				$this->end_controls_section();
				break;

			// ── Code ───────────────────────────────────────────────────────

			case 'code':
				$this->add_control($id, array_merge($base, [
					'type'     => \Elementor\Controls_Manager::CODE,
					'language' => $ctrl['language'] ?? 'html',
					'rows'     => $ctrl['rows'] ?? 10,
				]));
				break;

			// ── Select2 ───────────────────────────────────────────────────

			case 'select2':
				$options = [];
				if (! empty($ctrl['options']) && is_array($ctrl['options'])) {
					foreach ($ctrl['options'] as $opt) {
						$options[$opt['value'] ?? ''] = $opt['label'] ?? $opt['value'] ?? '';
					}
				}
				$this->add_control($id, array_merge($base, [
					'type'     => \Elementor\Controls_Manager::SELECT2,
					'options'  => $options,
					'multiple' => ! empty($ctrl['multiple']),
					'label_block' => true,
				]));
				break;

			// ── Visual Choice ─────────────────────────────────────────────

			case 'visual_choice':
				$options = [];
				if (! empty($ctrl['options']) && is_array($ctrl['options'])) {
					foreach ($ctrl['options'] as $opt) {
						$options[$opt['value'] ?? ''] = [
							'title' => $opt['label'] ?? $opt['value'] ?? '',
							'icon'  => $opt['icon']  ?? 'eicon-circle',
						];
					}
				}
				$this->add_control($id, array_merge($base, [
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'options' => $options,
					'toggle'  => true,
				]));
				break;

			// ── Font ──────────────────────────────────────────────────────

			case 'font':
				$this->add_control($id, array_merge($base, [
					'type' => \Elementor\Controls_Manager::FONT,
				]));
				break;

			// ── Background ───────────────────────────────────────────────

			case 'background':
				$this->add_group_control(
					\Elementor\Group_Control_Background::get_type(),
					[
						'name'     => $id,
						'label'    => esc_html($label),
						'types'    => $ctrl['types'] ?? ['classic', 'gradient'],
						'selector' => $ctrl['selector'] ?? '{{WRAPPER}} .ba-widget-element',
					]
				);
				break;

			// ── Text Shadow ──────────────────────────────────────────────

			case 'text_shadow':
				$this->add_group_control(
					\Elementor\Group_Control_Text_Shadow::get_type(),
					[
						'name'     => $id,
						'label'    => esc_html($label),
						'selector' => $ctrl['selector'] ?? '{{WRAPPER}} .ba-widget-element',
					]
				);
				break;

			// ── Gallery ──────────────────────────────────────────────────

			case 'gallery':
				$this->add_control($id, [
					'label' => esc_html($label),
					'type'  => \Elementor\Controls_Manager::GALLERY,
				]);
				break;

			// ── Repeater ─────────────────────────────────────────────────

			case 'repeater':
				$sub_fields = $ctrl['sub_fields'] ?? [];
				if (empty($sub_fields)) break;

				$repeater = new \Elementor\Repeater();

				foreach ($sub_fields as $sf) {
					if (empty($sf['id']) || empty($sf['type'])) continue;

					$sf_id      = sanitize_key($sf['id']);
					$sf_label   = $sf['label'] ?? ucwords(str_replace('_', ' ', $sf_id));
					$sf_default = $sf['default'] ?? '';
					$sf_type    = $sf['type'];

					switch ($sf_type) {
						case 'text':
							$repeater->add_control($sf_id, [
								'label'       => esc_html($sf_label),
								'type'        => \Elementor\Controls_Manager::TEXT,
								'default'     => $sf_default,
								'label_block' => true,
							]);
							break;

						case 'textarea':
							$repeater->add_control($sf_id, [
								'label'       => esc_html($sf_label),
								'type'        => \Elementor\Controls_Manager::TEXTAREA,
								'default'     => $sf_default,
								'label_block' => true,
							]);
							break;

						case 'wysiwyg':
							$repeater->add_control($sf_id, [
								'label'   => esc_html($sf_label),
								'type'    => \Elementor\Controls_Manager::WYSIWYG,
								'default' => $sf_default,
							]);
							break;

						case 'media':
							$repeater->add_control($sf_id, [
								'label' => esc_html($sf_label),
								'type'  => \Elementor\Controls_Manager::MEDIA,
							]);
							break;

						case 'url':
							$repeater->add_control($sf_id, [
								'label'       => esc_html($sf_label),
								'type'        => \Elementor\Controls_Manager::URL,
								'default'     => ['url' => $sf_default],
								'label_block' => true,
							]);
							break;

						case 'color':
							$repeater->add_control($sf_id, [
								'label'   => esc_html($sf_label),
								'type'    => \Elementor\Controls_Manager::COLOR,
								'default' => $sf_default,
							]);
							break;

						case 'switcher':
							$repeater->add_control($sf_id, [
								'label'        => esc_html($sf_label),
								'type'         => \Elementor\Controls_Manager::SWITCHER,
								'default'      => $sf_default,
								'label_on'     => esc_html__('Yes', 'best-addons'),
								'label_off'    => esc_html__('No', 'best-addons'),
								'return_value' => 'yes',
							]);
							break;

						case 'select':
							$sf_options = [];
							if (! empty($sf['options']) && is_array($sf['options'])) {
								foreach ($sf['options'] as $opt) {
									$sf_options[$opt['value'] ?? ''] = $opt['label'] ?? $opt['value'] ?? '';
								}
							}
							$repeater->add_control($sf_id, [
								'label'   => esc_html($sf_label),
								'type'    => \Elementor\Controls_Manager::SELECT,
								'default' => $sf_default,
								'options' => $sf_options,
							]);
							break;

						case 'icons':
							$repeater->add_control($sf_id, [
								'label' => esc_html($sf_label),
								'type'  => \Elementor\Controls_Manager::ICONS,
							]);
							break;

						case 'number':
							$repeater->add_control($sf_id, [
								'label'   => esc_html($sf_label),
								'type'    => \Elementor\Controls_Manager::NUMBER,
								'default' => $sf_default,
							]);
							break;

						default:
							$repeater->add_control($sf_id, [
								'label'       => esc_html($sf_label),
								'type'        => \Elementor\Controls_Manager::TEXT,
								'default'     => $sf_default,
								'label_block' => true,
							]);
							break;
					}
				}

				$this->add_control($id, [
					'label'       => esc_html($label),
					'type'        => \Elementor\Controls_Manager::REPEATER,
					'fields'      => $repeater->get_controls(),
					'default'     => [],
					'title_field' => ! empty($sub_fields[0]['id'])
						? '{{{ ' . esc_js($sub_fields[0]['id']) . ' }}}'
						: '',
				]);
				break;
		}
	}

	// ── Render ────────────────────────────────────────────────────────────────

	protected function render()
	{
		$settings = $this->get_settings_for_display();
		$html     = $this->widget_data['html'] ?? '';
		$css      = $this->widget_data['css']  ?? '';
		$js       = $this->widget_data['js']   ?? '';
		$includes = $this->widget_data['includes'] ?? ['css' => [], 'js' => []];

		if (empty($html)) {
			echo '<p style="padding:12px;color:#e11d48;">' . esc_html__('No HTML template defined.', 'best-addons') . '</p>';
			return;
		}

		// Enqueue includes.
		$this->enqueue_includes($includes);

		// Parse tokens in HTML, CSS, JS.
		$html = $this->parse_tokens($html, $settings);
		$css  = $this->parse_tokens($css,  $settings);
		$js   = $this->parse_tokens($js,   $settings);

		$widget_id = $this->get_id();

		// Scoped CSS.
		$scoped = '';
		if (! empty($css)) {
			$scoped = $this->scope_css($css, '#ba-custom-widget-' . $widget_id);
		}

		// The React front-end bundle takes over this subtree on hydration. PHP
		// renders the same markup first so the widget is visible without
		// JavaScript and to search engines, and so a script error cannot blank
		// the page. Both paths run through wp_kses_post() and the same token and
		// CSS-scoping rules, so the swap is not visible.
		$payload = [
			'widget_id' => (string) $widget_id,
			'html'      => $html,
			'css'       => $css,
			'js'        => $js,
			'settings'  => $settings,
		];

		echo '<div class="ba-custom-widget-mount" data-ba-widget-id="' . esc_attr($widget_id) . '">';

		if (! empty($scoped)) {
			echo '<style id="ba-widget-style-' . esc_attr($widget_id) . '">' . wp_strip_all_tags($scoped) . '</style>';
		}

		echo '<div id="ba-custom-widget-' . esc_attr($widget_id) . '" class="ba-custom-widget">';
		echo wp_kses_post($html);
		echo '</div>';

		// The widget's own JS is deliberately NOT printed here: React runs it once
		// after it takes over the subtree, and printing it too would execute the
		// author's script twice. Markup and CSS are server-rendered because they
		// are content; behaviour is not.

		// JSON_HEX_TAG / JSON_HEX_AMP keep a value containing </script> or an
		// entity from closing this tag early.
		echo '<script type="application/json" data-ba-widget>' .
			wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) .
			'</script>';

		echo '</div>';

		$this->enqueue_frontend_bundle();
	}

	/**
	 * Registers the React front-end bundle. It is a dependency of the footer
	 * scripts, and it hydrates every `.ba-custom-widget-mount` on the page.
	 */
	private function enqueue_frontend_bundle()
	{
		// One page can hold any number of builder widgets; the bundle is a page-level
		// dependency, not a widget-level one, so it is enqueued exactly once.
		if (did_action('ba_frontend_bundle_enqueued')) {
			return;
		}
		do_action('ba_frontend_bundle_enqueued');

		$react = SpineAssets::enqueue_script( SpineAssets::REACT );

		SpineAssets::enqueue_script(
			SpineAssets::BUILDER_FRONTEND,
			null !== $react ? [$react] : []
		);
	}

	private function parse_tokens(string $template, array $settings): string
	{
		// ── Repeater loop: {% for item in control_id %}...{% endfor %} ────
		$template = preg_replace_callback(
			'/\{%\s*for\s+item\s+in\s+(\w+)\s*%\}(.*?)\{%\s*endfor\s*%\}/s',
			function ($matches) use ($settings) {
				$control_id = $matches[1];
				$loop_body  = $matches[2];
				$items      = $settings[$control_id] ?? [];
				if (empty($items) || ! is_array($items)) return '';

				$output = '';
				foreach ($items as $item) {
					$row = $loop_body;
					// Replace {{item.sub_field_id}} tokens
					foreach ($item as $sf_key => $sf_val) {
						if (is_array($sf_val)) {
							// URL sub-field
							if (isset($sf_val['url'])) {
								$row = str_replace('{{item.' . $sf_key . '}}', esc_url($sf_val['url']), $row);
							}
							// Media sub-field
							elseif (isset($sf_val['id']) && isset($sf_val['url'])) {
								$row = str_replace('{{item.' . $sf_key . '}}', esc_url($sf_val['url']), $row);
							}
							// Icon sub-field
							elseif (isset($sf_val['value'])) {
								ob_start();
								\Elementor\Icons_Manager::render_icon($sf_val, ['aria-hidden' => 'true']);
								$icon_html = ob_get_clean();
								$row = str_replace('{{item.' . $sf_key . '}}', $icon_html, $row);
							}
						} else {
							$row = str_replace('{{item.' . $sf_key . '}}', esc_html((string) $sf_val), $row);
						}
					}
					$output .= $row;
				}
				return $output;
			},
			$template
		);

		// ── Scalar tokens ──────────────────────────────────────────────────
		foreach ($settings as $key => $value) {
			if (is_array($value)) {
				// URL control.
				if (isset($value['url'])) {
					$template = str_replace('{{' . $key . '}}', esc_url($value['url']), $template);
				}
				// Icon control.
				elseif (isset($value['value'])) {
					ob_start();
					\Elementor\Icons_Manager::render_icon($value, ['aria-hidden' => 'true']);
					$icon_html = ob_get_clean();
					$template  = str_replace('{{' . $key . '}}', $icon_html, $template);
				}
			} else {
				$template = str_replace('{{' . $key . '}}', esc_html($value), $template);
			}
		}
		return $template;
	}

	private function scope_css(string $css, string $scope): string
	{
		$css = preg_replace('/\/\*.*?\*\//s', '', $css);
		return preg_replace_callback(
			'/([^{]+)\{([^}]*)\}/s',
			function ($m) use ($scope) {
				$selectors = array_map('trim', explode(',', $m[1]));
				$scoped    = array_map(function ($sel) use ($scope) {
					$sel = trim($sel);
					if (empty($sel) || str_starts_with($sel, '@')) return $sel;
					return $scope . ' ' . $sel;
				}, $selectors);
				return implode(', ', $scoped) . ' {' . $m[2] . '}';
			},
			$css
		) ?: $css;
	}

	private function enqueue_includes(array $includes)
	{
		$css_libs = $includes['css'] ?? [];
		$js_libs  = $includes['js']  ?? [];

		foreach ($css_libs as $lib) {
			if (empty($lib['handle']) || empty($lib['src'])) continue;
			wp_enqueue_style($lib['handle'], esc_url($lib['src']), [], null);
		}

		foreach ($js_libs as $lib) {
			if (empty($lib['handle']) || empty($lib['src'])) continue;
			wp_enqueue_script($lib['handle'], esc_url($lib['src']), [], null, true);
		}
	}
}
