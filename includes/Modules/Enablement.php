<?php
/**
 * Per-site module on/off state.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Which modules the site owner has switched on.
 *
 * The default for a module the site has never heard of is *enabled*. That single
 * decision removes an entire class of upgrade bug: shipping a plugin update that
 * adds three widgets must not require a settings migration, and a new install must
 * not ship with everything switched off because no row exists yet.
 *
 * A module is therefore stored only when the owner deviates from the default.
 */
final class Enablement {

	/**
	 * Option holding the explicit deviations.
	 */
	private const OPTION = 'best_addons_module_state';

	/**
	 * In-request memo.
	 *
	 * @var array<string, bool>|null
	 */
	private static $state = null;

	/**
	 * Every explicit decision, keyed by module id.
	 *
	 * @return array<string, bool>
	 */
	public static function all(): array {
		if ( null !== self::$state ) {
			return self::$state;
		}

		/** @var mixed $stored */
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/** @var array<string, bool> $state */
		$state = array();
		foreach ( $stored as $id => $enabled ) {
			$state[ sanitize_key( (string) $id ) ] = (bool) $enabled;
		}

		return self::$state = $state;
	}

	/**
	 * Is this module switched on? Unknown modules default to on.
	 */
	public static function is_enabled( string $id ): bool {
		$state = self::all();

		return $state[ $id ] ?? true;
	}

	/**
	 * Record the owner's decision for a module.
	 */
	public static function set( string $id, bool $enabled ): void {
		$state = self::all();

		// Storing the default would be noise: "enabled" is what an absent row
		// already means, so a row is only written when it disagrees.
		if ( $enabled ) {
			unset( $state[ $id ] );
		} else {
			$state[ $id ] = false;
		}

		self::persist( $state );
	}

	/**
	 * Replace the whole set, used by the options panel's bulk save.
	 *
	 * @param array<string, bool> $states
	 */
	public static function replace( array $states ): void {
		$clean = array();
		foreach ( $states as $id => $enabled ) {
			$clean[ sanitize_key( (string) $id ) ] = (bool) $enabled;
		}

		self::persist( $clean );
	}

	/**
	 * The ids the owner has explicitly switched off.
	 *
	 * @return string[]
	 */
	public static function disabled_ids(): array {
		$ids = array();

		foreach ( self::all() as $id => $enabled ) {
			if ( ! $enabled ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Forget every decision, so all modules return to enabled.
	 */
	public static function reset(): void {
		self::persist( array() );
	}

	/**
	 * Clear the memo. Tests only.
	 */
	public static function flush(): void {
		self::$state = null;
	}

	/**
	 * @param array<string, bool> $state
	 */
	private static function persist( array $state ): void {
		if ( array() === $state ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $state, false );
		}

		self::$state = $state;
	}
}
