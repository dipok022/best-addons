<?php
/**
 * Pro licensing gate.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * The one question the module registry asks about a Pro module: may it load?
 *
 * A locked Pro module is dropped before its PHP is parsed, before its class is
 * autoloaded, and before its assets are registered. That is stronger than hiding
 * the UI, and it is the reason the gate lives in the registry rather than in the
 * widget.
 */
final class LicenseManager {

	/**
	 * Option holding the licence state.
	 */
	private const OPTION = 'best_addons_license';

	/**
	 * Transient key for the last remote validation result.
	 */
	private const CHECK_TRANSIENT = 'best_addons_license_check';

	/**
	 * In-request memo, so a page with 14 modules asks the database once.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $state = null;

	/**
	 * Read the stored licence state, merged over its defaults.
	 *
	 * @return array{key: string, status: string, expires: int, customer: string}
	 */
	public static function state(): array {
		if ( null !== self::$state ) {
			/** @var array{key: string, status: string, expires: int, customer: string} */
			return self::$state;
		}

		/** @var mixed $stored */
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return self::$state = array(
			'key'      => isset( $stored['key'] ) ? (string) $stored['key'] : '',
			'status'   => isset( $stored['status'] ) ? (string) $stored['status'] : 'inactive',
			'expires'  => isset( $stored['expires'] ) ? (int) $stored['expires'] : 0,
			'customer' => isset( $stored['customer'] ) ? (string) $stored['customer'] : '',
		);
	}

	/**
	 * Is a valid, unexpired Pro licence active?
	 */
	public static function is_active(): bool {
		$state = self::state();

		if ( 'valid' !== $state['status'] || '' === $state['key'] ) {
			return false;
		}

		// Expiry is optional: a perpetual licence stores 0.
		return 0 === $state['expires'] || $state['expires'] > time();
	}

	/**
	 * Days until expiry, or null when perpetual or inactive.
	 */
	public static function days_remaining(): ?int {
		$state = self::state();

		if ( ! self::is_active() || 0 === $state['expires'] ) {
			return null;
		}

		return max( 0, (int) ceil( ( $state['expires'] - time() ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Whether the last remote check succeeded, and when it ran.
	 *
	 * @return array{ok: bool, checked: int, message: string}
	 */
	public static function last_check(): array {
		/** @var mixed $stored */
		$stored = get_transient( self::CHECK_TRANSIENT );

		if ( ! is_array( $stored ) ) {
			return array(
				'ok'      => false,
				'checked' => 0,
				'message' => '',
			);
		}

		return array(
			'ok'      => ! empty( $stored['ok'] ),
			'checked' => isset( $stored['checked'] ) ? (int) $stored['checked'] : 0,
			'message' => isset( $stored['message'] ) ? (string) $stored['message'] : '',
		);
	}

	/**
	 * Store a successful activation.
	 *
	 * @param string               $key      Licence key.
	 * @param int                  $expires  Unix timestamp, or 0 for perpetual.
	 * @param string               $customer Licensed-to name.
	 */
	public static function activate( string $key, int $expires = 0, string $customer = '' ): void {
		self::write(
			array(
				'key'      => $key,
				'status'   => 'valid',
				'expires'  => $expires,
				'customer' => $customer,
			)
		);

		update_option( 'best_addons_license_status', 'valid', false );
	}

	/**
	 * Store a failed activation without discarding an existing valid key.
	 */
	public static function record_failure( string $message ): void {
		set_transient(
			self::CHECK_TRANSIENT,
			array(
				'ok'      => false,
				'checked' => time(),
				'message' => $message,
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Record a successful remote check.
	 */
	public static function record_success( int $expires = 0 ): void {
		set_transient(
			self::CHECK_TRANSIENT,
			array(
				'ok'      => true,
				'checked' => time(),
				'message' => '',
			),
			DAY_IN_SECONDS
		);

		if ( $expires > 0 ) {
			$state         = self::state();
			$state['expires'] = $expires;
			self::write( $state );
		}
	}

	/**
	 * Drop the licence. Modules fall back to Free on the next request.
	 */
	public static function deactivate(): void {
		delete_option( self::OPTION );
		update_option( 'best_addons_license_status', 'inactive', false );
		delete_transient( self::CHECK_TRANSIENT );

		self::$state = null;
	}

	/**
	 * Clear the in-request memo. Tests only.
	 */
	public static function flush(): void {
		self::$state = null;
	}

	/**
	 * @param array{key: string, status: string, expires: int, customer: string} $state
	 */
	private static function write( array $state ): void {
		update_option( self::OPTION, $state, false );

		// A licence change can flip which modules exist, so anything derived from
		// the module set has to be rebuilt rather than reused.
		delete_transient( 'best_addons_modules_index' );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'best-addons' );
		}

		self::$state = $state;
	}
}
