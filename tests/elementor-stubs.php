<?php
/**
 * The slice of Elementor the module classes extend.
 *
 * Deliberately not a mock: these are real base classes with the same names, so
 * loading a module here exercises the same inheritance chain it will in Elementor
 * and a signature mismatch fails here rather than in the editor. Only the members
 * a module actually calls are present, so a module reaching for something else
 * shows up as a test error.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace Elementor;

abstract class Widget_Base {

	public $id;

	public function __construct( $data = array(), $args = null ) {
		$this->id = 'a1b2c3d';
	}

	public function get_name() {
		return static::class;
	}

	public function get_title() {
		return '';
	}

	public function get_icon() {
		return '';
	}

	public function get_categories() {
		return array();
	}

	public function get_keywords() {
		return array();
	}

	public function get_style_depends() {
		return array();
	}

	public function get_script_depends() {
		return array();
	}

	public function get_id() {
		return $this->id;
	}

	protected function start_controls_section( $id, $args = array() ): void {}

	protected function end_controls_section(): void {}

	protected function add_control( $id, $args = array() ): void {}

	protected function add_notice( $control, $id, $content ): void {}

	protected function get_settings_for_display( $key = null ) {
		return array();
	}

	protected function render() {}
}

class Controls_Manager {

	const TEXT            = 'text';
	const TEXTAREA        = 'textarea';
	const WYSIWYG         = 'wysiwyg';
	const NUMBER          = 'number';
	const SELECT          = 'select';
	const SWITCHER        = 'switcher';
	const ICONS           = 'icons';
	const MEDIA           = 'media';
	const URL             = 'url';
	const REPEATER        = 'repeater';
	const CHOOSE          = 'choose';
	const NOTICE          = 'notice';
	const HEADING         = 'heading';

	const TAB_CONTENT     = 'content';
	const TAB_STYLE       = 'style';
	const TAB_ADVANCED    = 'advanced';
}

class Repeater {

	/** @var array<string, array> */
	public $fields = array();

	public function add_control( $id, $args = array() ): void {
		$this->fields[ $id ] = $args;
	}

	public function start_controls_tabs( $id ): void {}

	public function end_controls_tabs(): void {}

	public function start_controls_tab( $id, $args = array() ): void {}

	public function end_controls_tab(): void {}

	public function get_fields(): array {
		return $this->fields;
	}
}

class Icons_Manager {

	public static function render_icon( $icon, $args = array() ): void {
		echo '<span class="ba-icon"></span>';
	}
}

class Utils {

	public static function validate_html_tag( $tag ) {
		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
		return in_array( $tag, $allowed, true ) ? $tag : 'div';
	}
}
