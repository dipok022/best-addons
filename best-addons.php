<?php
/**
 * Plugin Name: Best Addons
 * Description: High-performance optimized conditional addon infrastructure framework with Asset Splitting.
 * Version:     1.0.0
 * Author:      Dipok Roy
 * Text Domain: best-addons
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Best_Addons_Optimizer {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', [ $this, 'i18n' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		
		if ( is_admin() ) {
			require_once( __DIR__ . '/admin-settings.php' );
			\Best_Addons_Admin_Settings::init();
		}
	}

	public function i18n() {
		load_plugin_textdomain( 'best-addons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function init() {
		if ( ! did_action( 'elementor/loaded' ) ) return;

		add_action( 'wp_enqueue_scripts', [ $this, 'register_production_assets' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
		add_action( 'elementor/widgets/register', [ $this, 'init_widgets' ] );
	}

	public function register_production_assets() {
		// Matches your explicit asset keys directly with the filenames output by Vite
		$widgets = [ 'sample-widget', 'advanced-accordion' ];

		foreach ( $widgets as $widget_key ) {
			$css_file = plugin_dir_path( __FILE__ ) . "assets/dist/css/{$widget_key}.min.css";
			$version = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';

			$css_url = plugins_url( "/assets/dist/css/{$widget_key}.min.css", __FILE__ );
			$js_url  = plugins_url( "/assets/dist/js/{$widget_key}.min.js", __FILE__ );

			wp_register_style( "best-addons-style-{$widget_key}", $css_url, [], $version );
			wp_register_script( "best-addons-script-{$widget_key}", $js_url, [ 'jquery' ], $version, true );
		}
	}

public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'best-addons-category',
			[
				'title' => esc_html__( 'Best Addons', 'best-addons' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}


	// FIXED: Removed the accidental parentheses inside the argument definition
	public function init_widgets( $widgets_manager ) {
		require_once( __DIR__ . '/widget-registry.php' );
		\Best_Addons_Widget_Registry::register_all_widgets( $widgets_manager );
	}
}

Best_Addons_Optimizer::instance();
