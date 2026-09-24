<?php
if (! defined('ABSPATH')) exit;

/**
 * Dynamic Elementor Widget generated from CPT data.
 * 
 * Improved token system with {{control_id}} parsing in HTML/CSS/JS.
 */
class Best_Addons_Dynamic_Widget extends \Elementor\Widget_Base
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
			'category' => get_post_meta($this->post_id, '_ba_category', true) ?: 'best-addons-category',
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
		return [$this->widget_data['category'] ?? 'best-addons-category'];
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

		// Group by tab: content / advanced / style.
		$tabs = ['content' => [], 'advanced' => [], 'style' => []];
		foreach ($controls as $ctrl) {
			$tab = strtolower($ctrl['tab'] ?? 'content');
			if (! isset($tabs[$tab])) $tab = 'content';
			$tabs[$tab][] = $ctrl;
		}

		foreach ($tabs as $tab_key => $tab_controls) {
			if (empty($tab_controls)) continue;

			$tab_id = 'ba_tab_' . $tab_key;
			$tab_label = ucfirst($tab_key);
			$tab_const = $tab_key === 'style' ? \Elementor\Controls_Manager::TAB_STYLE : \Elementor\Controls_Manager::TAB_CONTENT;

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
		if (! empty($css)) {
			$scoped = $this->scope_css($css, '#ba-custom-widget-' . $widget_id);
			echo '<style id="ba-widget-style-' . esc_attr($widget_id) . '">' . wp_strip_all_tags($scoped) . '</style>';
		}

		// Widget HTML.
		echo '<div id="ba-custom-widget-' . esc_attr($widget_id) . '" class="ba-custom-widget">';
		echo wp_kses_post($html);
		echo '</div>';

		// Scoped JS.
		if (! empty($js)) {
			echo '<script>(function(){var widgetEl=document.getElementById("ba-custom-widget-' . esc_js($widget_id) . '");if(!widgetEl)return;' . $js . '})();</script>';
		}
	}

	private function parse_tokens(string $template, array $settings): string
	{
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
