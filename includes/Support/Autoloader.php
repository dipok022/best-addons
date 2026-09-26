<?php
/**
 * Class loading.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloads module classes from the manifest classmap, and spine classes by path.
 *
 * Two tiers, chosen by namespace prefix rather than tried in order:
 *
 * 1. **PSR-4 (spine classes).** `BestAddons\Support\Manifest` ⇒
 *    `includes/Support/Manifest.php`. One `is_readable()` per class, once per
 *    request, and only for classes actually reached.
 * 2. **Classmap (module classes).** Everything under `BestAddons\Feature\` is
 *    module code, which lives in `modules/` and so can never be found by the
 *    path rule. The extractor already resolved each one to a file, so loading it
 *    is a single array lookup and a single `require_once`. This is what replaced
 *    the old pattern of `require_once`-ing every widget file just to test
 *    `class_exists()`.
 *
 * The split matters for more than speed. An earlier version consulted the
 * classmap first, for every class — which meant resolving `Manifest` required
 * the classmap, which required resolving `Manifest`. PHP's guard against
 * re-entrant autoloading then silently gives up and the class never exists, so
 * the plugin fatals on the first class it loads. Dispatching on the prefix means
 * the classmap is only ever read for a module class, and `Manifest` is only ever
 * reached from there.
 */
final class Autoloader {

	/**
	 * Namespace prefix owned by the whole plugin.
	 */
	private const PREFIX = 'BestAddons\\';

	/**
	 * Length of the prefix, precomputed so the hot path does not call strlen().
	 */
	private const PREFIX_LENGTH = 11;

	/**
	 * Namespace prefix owned by module code, resolved through the classmap.
	 */
	private const FEATURE_PREFIX = 'BestAddons\\Feature\\';

	/**
	 * Length of the feature prefix, precomputed for the same reason.
	 */
	private const FEATURE_PREFIX_LENGTH = 19;

	/**
	 * Guard against registering twice.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register with the SPL stack.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Plugin root, with a trailing slash.
	 *
	 * Resolved from `__DIR__` rather than through `Paths`, because `Paths` is
	 * itself a spine class: reaching for it here would re-enter this autoloader
	 * for the class currently being loaded, and PHP answers that with a
	 * "class not found".
	 */
	private static function root(): string {
		return rtrim( __DIR__ . '/../..', '/\\' ) . '/';
	}

	/**
	 * @param string $class_name Fully-qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strncmp( $class_name, self::PREFIX, self::PREFIX_LENGTH ) ) {
			return;
		}

		// Module code: the manifest is the only thing that knows where it is.
		if ( 0 === strncmp( $class_name, self::FEATURE_PREFIX, self::FEATURE_PREFIX_LENGTH ) ) {
			$relative = Manifest::classmap()[ $class_name ] ?? '';

			if ( is_string( $relative ) && '' !== $relative ) {
				self::require( $relative );
			}

			return;
		}

		// Spine code: includes/{Rest\Of\The\Namespace}.php
		self::require(
			'includes/' . str_replace( '\\', '/', substr( $class_name, self::PREFIX_LENGTH ) ) . '.php'
		);
	}

	/**
	 * @param string $relative Plugin-relative path, forward-slashed.
	 */
	private static function require( string $relative ): void {
		$file = self::root() . $relative;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
