<?php
/**
 * Absolute paths and URLs, resolved once.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for every filesystem path and plugin URL.
 *
 * `plugin_dir_url()` and friends each do their own work; calling them from
 * dozens of modules means dozens of near-identical string builds. Centralised
 * here, and memoised, so the cost is paid once per request no matter how many
 * modules load.
 */
final class Paths {

	/**
	 * Plugin root, with a trailing slash.
	 *
	 * @var string|null
	 */
	private static $root = null;

	/**
	 * Plugin URL, with a trailing slash.
	 *
	 * @var string|null
	 */
	private static $url = null;

	/**
	 * Absolute path to a plugin-relative file or folder.
	 *
	 * @param string $relative Forward-slashed path relative to the plugin root.
	 */
	public static function path( string $relative = '' ): string {
		if ( null === self::$root ) {
			self::$root = rtrim( __DIR__ . '/../..', '/\\' ) . '/';
		}

		return self::$root . ltrim( $relative, '/' );
	}

	/**
	 * Public URL to a plugin-relative file or folder.
	 *
	 * @param string $relative Forward-slashed path relative to the plugin root.
	 */
	public static function url( string $relative = '' ): string {
		if ( null === self::$url ) {
			// This file lives at includes/Support/Paths.php, so the plugin root is
			// two levels up. `plugins_url()` needs a real file to resolve against,
			// which is why this cannot be built from the directory alone.
			self::$url = rtrim( plugins_url( '/', __DIR__ . '/../../best-addons.php' ), '/' ) . '/';
		}

		return self::$url . ltrim( $relative, '/' );
	}

	/**
	 * Absolute path to the generated manifest.
	 */
	public static function manifest(): string {
		return self::path( 'manifest.json' );
	}

	/**
	 * URL of a module asset inside the built `dist` folder.
	 *
	 * @param string $dist_file Path relative to the plugin root, e.g. `css/free-x-style.min.css`.
	 */
	public static function asset( string $dist_file ): string {
		return self::url( 'assets/dist/' . ltrim( $dist_file, '/' ) );
	}

	/**
	 * The folder that holds the React admin app's build output.
	 */
	public static function admin_dist(): string {
		return self::path( 'assets/dist/admin' );
	}
}
