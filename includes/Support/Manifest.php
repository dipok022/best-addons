<?php
/**
 * The runtime view of `manifest.json`.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the build-time manifest exactly once per request.
 *
 * This class is the reason the plugin does no directory scanning at runtime. The
 * extractor has already answered every question the runtime could ask — what
 * modules exist, which class holds each one, where its assets live, what version
 * to bust cache with — and this is the only place that answers them.
 */
final class Manifest {

	/**
	 * The shape this build of the plugin understands. A manifest written by a
	 * newer major is refused rather than half-interpreted.
	 */
	private const SUPPORTED_MAJOR = 2;

	/**
	 * Object-cache group for the decoded manifest, so a persistent object cache
	 * (Redis, Memcached) stops the JSON decode on every request.
	 */
	private const CACHE_GROUP = 'best-addons';

	/**
	 * Decoded manifest. Populated on first access and never invalidated within a
	 * request — the manifest cannot change while PHP is running.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $data = null;

	/**
	 * Set when the manifest is missing or unreadable.
	 *
	 * @var string|null
	 */
	private static $error = null;

	/**
	 * Load the manifest. Subsequent calls are free.
	 *
	 * @return array<string, mixed> Empty array when unavailable.
	 */
	public static function all(): array {
		if ( null !== self::$data ) {
			return self::$data;
		}

		$cached = self::from_cache();
		if ( null !== $cached ) {
			self::$data = $cached;

			return self::$data;
		}

		$file = Paths::manifest();

		if ( ! is_readable( $file ) ) {
			self::$error = 'manifest.json is missing — run `npm run extract`.';

			return self::$data = [];
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			self::$error = 'manifest.json could not be read.';

			return self::$data = [];
		}

		/** @var mixed $decoded */
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['version'] ) ) {
			self::$error = 'manifest.json is not valid extractor output.';

			return self::$data = [];
		}

		$major = (int) $decoded['version'];

		if ( $major !== self::SUPPORTED_MAJOR ) {
			self::$error = sprintf(
				'manifest.json is version %1$d; this build of the plugin expects %2$d. Run `npm run build`.',
				$major,
				self::SUPPORTED_MAJOR
			);

			return self::$data = [];
		}

		$decoded['modules']   = is_array( $decoded['modules'] ?? null ) ? $decoded['modules'] : array();
		$decoded['classmap']  = is_array( $decoded['classmap'] ?? null ) ? $decoded['classmap'] : array();
		$decoded['categories'] = is_array( $decoded['categories'] ?? null ) ? $decoded['categories'] : array();
		$decoded['stats']     = is_array( $decoded['stats'] ?? null ) ? $decoded['stats'] : array();

		self::to_cache( $decoded );

		return self::$data = $decoded;
	}

	/**
	 * Every module record, unfiltered.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function modules(): array {
		/** @var array<int, array<string, mixed>> $modules */
		$modules = self::all()['modules'];

		return $modules;
	}

	/**
	 * A single module record by id.
	 *
	 * @param string $id Kebab-case module id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function module( string $id ): ?array {
		foreach ( self::modules() as $module ) {
			if ( ( $module['id'] ?? '' ) === $id ) {
				/** @var array<string, mixed> $module */
				return $module;
			}
		}

		return null;
	}

	/**
	 * Class-to-file map, as produced by the extractor.
	 *
	 * @return array<string, string>
	 */
	public static function classmap(): array {
		/** @var array<string, string> $classmap */
		$classmap = self::all()['classmap'];

		return $classmap;
	}

	/**
	 * Category slugs declared by `type: category` modules.
	 *
	 * @return string[]
	 */
	public static function categories(): array {
		/** @var string[] $categories */
		$categories = self::all()['categories'];

		return $categories;
	}

	/**
	 * Extractor-reported counts, for diagnostics and the admin UI.
	 *
	 * @return array<string, int>
	 */
	public static function stats(): array {
		/** @var array<string, int> $stats */
		$stats = self::all()['stats'];

		return $stats;
	}

	/**
	 * Why the manifest is unavailable, if it is. Surfaced on the admin screen
	 * rather than swallowed, because an empty plugin with no explanation is the
	 * hardest kind of bug to diagnose.
	 */
	public static function error(): ?string {
		self::all();

		return self::$error;
	}

	/**
	 * Drop the memoised copy. Used by tests and by the extractor check.
	 */
	public static function flush(): void {
		self::$data  = null;
		self::$error = null;

		wp_cache_delete( 'manifest', self::CACHE_GROUP );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function from_cache(): ?array {
		// Without a persistent object cache this would be a pointless round trip
		// to the in-request array that the property already covers.
		if ( ! wp_using_ext_object_cache() ) {
			return null;
		}

		/** @var mixed $cached */
		$cached = wp_cache_get( 'manifest', self::CACHE_GROUP );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function to_cache( array $data ): void {
		if ( ! wp_using_ext_object_cache() ) {
			return;
		}

		wp_cache_set( 'manifest', $data, self::CACHE_GROUP, HOUR_IN_SECONDS );
	}
}
