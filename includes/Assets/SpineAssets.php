<?php
/**
 * Assets that belong to the spine rather than to a module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Assets;

use BestAddons\Support\Paths;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the plugin's own fixed bundles — the admin SPA, the widget builder's
 * three entries, the shared React runtime.
 *
 * These are the assets a module contract cannot describe, because they are not
 * features: the admin panel, and the widget builder that ships with the plugin.
 * Funnelling them through one helper means there is a single place that knows
 * where `assets/dist` is and how to version a file, instead of the same four
 * lines of `file_exists()` + `filemtime()` copy-pasted into every screen.
 */
final class SpineAssets {

	/**
	 * Shared React runtime. Both React entries depend on it, so it is a manual
	 * chunk rather than a duplicated copy.
	 */
	public const REACT = 'js/react.min.js';

	/**
	 * The admin options SPA.
	 */
	public const ADMIN = 'js/ba-admin.min.js';

	/**
	 * The admin options SPA's stylesheet.
	 */
	public const ADMIN_CSS = 'css/ba-admin.min.css';

	/**
	 * The widget builder's list page (legacy jQuery, being retired).
	 */
	public const BUILDER_LIST = 'js/ba-builder-list.min.js';

	/**
	 * The widget builder's full-screen React editor.
	 */
	public const BUILDER_EDITOR = 'js/ba-builder-editor.min.js';

	/**
	 * The widget builder editor's stylesheet.
	 */
	public const BUILDER_EDITOR_CSS = 'css/ba-builder-editor.min.css';

	/**
	 * The widget builder's front-end React renderer.
	 */
	public const BUILDER_FRONTEND = 'js/ba-builder-frontend.min.js';

	/**
	 * Enqueue a script if it exists.
	 *
	 * @param string        $relative Path under `assets/dist`.
	 * @param array<int,string> $dependencies Handle dependencies.
	 * @param bool          $in_footer
	 *
	 * @return string|null The handle, or null when the file is not built.
	 */
	public static function enqueue_script( string $relative, array $dependencies = array(), bool $in_footer = true ): ?string {
		$handle = self::handle( $relative );

		if ( ! self::exists( $relative ) ) {
			return null;
		}

		wp_enqueue_script(
			$handle,
			Paths::asset( $relative ),
			$dependencies,
			self::version( $relative ),
			$in_footer
		);

		return $handle;
	}

	/**
	 * Enqueue a stylesheet if it exists.
	 *
	 * @param string           $relative    Path under `assets/dist`.
	 * @param array<int,string> $dependencies
	 *
	 * @return string|null The handle, or null when the file is not built.
	 */
	public static function enqueue_style( string $relative, array $dependencies = array() ): ?string {
		$handle = self::handle( $relative );

		if ( ! self::exists( $relative ) ) {
			return null;
		}

		wp_enqueue_style( $handle, Paths::asset( $relative ), $dependencies, self::version( $relative ) );

		return $handle;
	}

	/**
	 * Register a script if it exists, without enqueueing it.
	 *
	 * @param string           $relative
	 * @param array<int,string> $dependencies
	 *
	 * @return string|null
	 */
	public static function register_script( string $relative, array $dependencies = array(), bool $in_footer = true ): ?string {
		$handle = self::handle( $relative );

		if ( ! self::exists( $relative ) ) {
			return null;
		}

		wp_register_script( $handle, Paths::asset( $relative ), $dependencies, self::version( $relative ), $in_footer );

		return $handle;
	}

	/**
	 * @param string $relative
	 */
	public static function exists( string $relative ): bool {
		return is_readable( Paths::path( 'assets/dist/' . ltrim( $relative, '/' ) ) );
	}

	/**
	 * @param string $relative
	 */
	public static function version( string $relative ): string {
		$file = Paths::path( 'assets/dist/' . ltrim( $relative, '/' ) );

		return is_readable( $file ) ? (string) filemtime( $file ) : '1.0.0';
	}

	/**
	 * `js/ba-builder-list.min.js` → `ba-spine-ba-builder-list-min-js`.
	 *
	 * @param string $relative
	 */
	public static function handle( string $relative ): string {
		return 'ba-spine-' . str_replace( array( '/', '.', '_' ), '-', ltrim( $relative, '/' ) );
	}
}
