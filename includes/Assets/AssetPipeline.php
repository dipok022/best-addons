<?php
/**
 * Asset registration from the manifest.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Assets;

use BestAddons\Modules\ModuleDefinition;
use BestAddons\Modules\Registry;
use BestAddons\Support\Paths;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every module's built asset. Never enqueues.
 *
 * The separation is the whole performance story: WordPress prints a handle only
 * if some widget asked for it through `get_style_depends()` / `get_script_depends()`,
 * so a page using one accordion downloads one accordion's CSS and nothing else —
 * even though every other module's asset is registered.
 *
 * Versions come from the manifest's content hash. The previous implementation
 * called `filemtime()` per widget per request purely to build a cache-busting
 * string; that is now a build-time number and this method performs no I/O.
 */
final class AssetPipeline {

	/**
	 * Guards against registering the same handle twice.
	 *
	 * @var array<string, true>
	 */
	private static array $registered = array();

	/**
	 * Editor assets are enqueued per-screen, not per-widget, so they are held
	 * back here and released on the editor screen only.
	 *
	 * @var array<string, array{handle: string, file: string, ver: string}>
	 */
	private static array $editor_assets = array();

	/**
	 * Whether the held-back editor bundles have been enqueued.
	 *
	 * This is deliberately separate from `$editor_assets`. That array fills up
	 * during `register()`, which runs on `wp_enqueue_scripts` — and the Elementor
	 * editor is an admin request, so `wp_enqueue_scripts` fires there too, before
	 * the editor hook. Guarding on the array's own contents would therefore make
	 * this method return before it had enqueued anything, on exactly the screen
	 * that needs them.
	 *
	 * @var bool
	 */
	private static bool $editor_enqueued = false;

	/**
	 * Hook the pipeline up.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register' ), 5 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( self::class, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register every loaded module's style and script.
	 *
	 * Front-end hooks only. The editor screen calls it too, because a widget needs
	 * its style registered there as well.
	 */
	public static function register(): void {
		foreach ( Registry::all() as $module ) {
			self::register_module( $module );
		}
	}

	/**
	 * Register one module's assets.
	 */
	public static function register_module( ModuleDefinition $module ): void {
		$style = $module->asset( 'style' );
		if ( null !== $style ) {
			self::register_style( $style );
		}

		$script = $module->asset( 'script' );
		if ( null !== $script ) {
			self::register_script( $script );
		}

		$editor = $module->asset( 'editor' );
		if ( null !== $editor ) {
			// Held back: the editor bundle must never reach a visitor.
			self::$editor_assets[ $editor['handle'] ] = array(
				'handle' => $editor['handle'],
				'file'   => $editor['file'],
				'ver'    => $editor['ver'],
			);
		}
	}

	/**
	 * Register one style. Exposed so a module can register a shared one.
	 *
	 * @param array{handle: string, file: string, ver: string} $asset
	 */
	public static function register_style( array $asset ): void {
		if ( isset( self::$registered[ $asset['handle'] ] ) ) {
			return;
		}

		self::$registered[ $asset['handle'] ] = true;

		wp_register_style(
			$asset['handle'],
			Paths::asset( $asset['file'] ),
			array(),
			$asset['ver']
		);
	}

	/**
	 * Register one script.
	 *
	 * @param array{handle: string, file: string, ver: string} $asset
	 */
	public static function register_script( array $asset, string $in_footer = 'true' ): void {
		if ( isset( self::$registered[ $asset['handle'] ] ) ) {
			return;
		}

		self::$registered[ $asset['handle'] ] = true;

		wp_register_script(
			$asset['handle'],
			Paths::asset( $asset['file'] ),
			array(),
			$asset['ver'],
			'true' === $in_footer
		);
	}

	/**
	 * Release the held-back editor bundles.
	 *
	 * Enqueued on the Elementor editor screen only, so a Pro module's editor
	 * behaviour costs a visitor nothing. The trade-off is that it loads for every
	 * widget in the panel rather than only the ones on the canvas — the editor
	 * screen already loads Elementor, React and the media library, and scoping
	 * this per-widget would mean reaching into Elementor's asset internals, which
	 * is where addons break on updates.
	 */
	public static function enqueue_editor_assets(): void {
		if ( self::$editor_enqueued ) {
			return;
		}

		// Register first, in case nothing else has: on a screen where
		// `wp_enqueue_scripts` did not run, the discovery list is still empty.
		self::register();

		self::$editor_enqueued = true;

		foreach ( self::$editor_assets as $asset ) {
			self::register_script( $asset );
			wp_enqueue_script( $asset['handle'] );
		}
	}

	/**
	 * Editor assets discovered so far. Used by the tests.
	 *
	 * @return array<string, array{handle: string, file: string, ver: string}>
	 */
	public static function editor_assets(): array {
		return self::$editor_assets;
	}

	/**
	 * Forget what has been registered. Tests only.
	 */
	public static function flush(): void {
		self::$registered      = array();
		self::$editor_assets   = array();
		self::$editor_enqueued = false;
	}
}
