<?php
/**
 * The composition root.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons;

use BestAddons\Admin\AdminMenu;
use BestAddons\Admin\Rest\LicenseController;
use BestAddons\Admin\Rest\ModulesController;
use BestAddons\Admin\Rest\SettingsController;
use BestAddons\Assets\AdminApp;
use BestAddons\Assets\AssetPipeline;
use BestAddons\Integrations\Elementor\ElementorBridge;
use BestAddons\Support\Autoloader;
use BestAddons\Support\Manifest;
use BestAddons\WidgetBuilder\AdminUi;
use BestAddons\WidgetBuilder\Cpt;
use BestAddons\WidgetBuilder\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the spine together.
 *
 * Deliberately boring: it constructs the pieces and hands them their hooks. Any
 * behaviour here would belong in one of them, and the file exists so the order of
 * operations is stated exactly once.
 */
final class Plugin {

	/**
	 * Whether the widget builder's server side is booted.
	 *
	 * The builder itself — the React editor, its front-end renderer and the
	 * shared editing engine — now lives in the master-addons plugin, at
	 * dev/js/admin/widget-builder/widget-builder-react/. This plugin no longer
	 * builds those bundles, so booting this half would register a `best_widget`
	 * post type and an Elementor widget whose editor and renderer both 404, plus
	 * four AJAX endpoints that would save markup nothing can display.
	 *
	 * The classes stay on disk deliberately. The CPT, the `_ba_controls` meta
	 * contract and the AJAX surface are the expensive half to rebuild and are
	 * exactly what a port back would need; the JavaScript was the disposable half.
	 *
	 * Flip this back to true only together with restoring the bundles to
	 * build/spine.json. tests/runtime.php asserts the two agree, and fails on
	 * purpose while they don't — that assertion is what caught this.
	 */
	public const BUILDER_AVAILABLE = false;

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		Autoloader::register();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Front-end and editor assets are registered, never enqueued, so a page
		// only downloads what a widget on it actually asked for.
		AssetPipeline::init();

		$elementor = new ElementorBridge();
		$elementor->init();
		$elementor->init_blocks();

		// The visual widget builder is spine chrome rather than a module: it ships
		// with the plugin, is not toggleable, and is not licence-gated — but only
		// while its bundles exist here. See Plugin::BUILDER_AVAILABLE.
		if ( self::BUILDER_AVAILABLE ) {
			Cpt::init();
			Loader::init();
		}

		if ( is_admin() ) {
			( new AdminMenu() )->register();
			AdminApp::init();

			if ( self::BUILDER_AVAILABLE ) {
				( new AdminUi() )->init();
			}

			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		}

		add_action( 'admin_notices', array( $this, 'report_manifest_problem' ) );
	}

	/**
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'best-addons',
			false,
			dirname( plugin_basename( BEST_ADDONS_FILE ) ) . '/languages'
		);
	}

	/**
	 * @return void
	 */
	public function register_rest_routes(): void {
		( new ModulesController() )->register_routes();
		( new SettingsController() )->register_routes();
		( new LicenseController() )->register_routes();
	}

	/**
	 * Tell the site owner when the plugin is inert.
	 *
	 * A missing or stale manifest is silent by nature: nothing throws, the panel
	 * is simply empty. Saying so on the admin screen turns a confusing "where did
	 * my widgets go" into a one-line fix.
	 */
	public function report_manifest_problem(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$error = Manifest::error();

		if ( null === $error ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Best Addons has no modules loaded.', 'best-addons' ),
			esc_html( $error )
		);
	}
}
