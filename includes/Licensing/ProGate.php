<?php
/**
 * Tier gating.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a module's tier may load on this site.
 *
 * Deliberately the narrowest possible gate: it answers one question, has no
 * knowledge of modules, and is filterable. That keeps every licensing decision in
 * one auditable place instead of scattered through the registrars.
 */
final class ProGate {

	/**
	 * Name of the filter that can override the gate.
	 *
	 * Exists for two honest reasons: staging sites where you want to see Pro
	 * without a licence, and automated tests.
	 */
	public const FILTER = 'best_addons_allow_pro';

	/**
	 * May modules of this tier load?
	 *
	 * @param string $tier `free` or `pro`.
	 */
	public static function allows( string $tier ): bool {
		if ( 'pro' !== $tier ) {
			return true;
		}

		/**
		 * Filters whether Pro modules may load.
		 *
		 * @param bool   $allowed Defaults to the licence state.
		 * @param string $tier    The tier being checked.
		 */
		$allowed = apply_filters( self::FILTER, LicenseManager::is_active(), $tier );

		return (bool) $allowed;
	}

	/**
	 * A short human reason a Pro module is unavailable, for the options panel.
	 */
	public static function lock_reason(): string {
		return LicenseManager::is_active()
			? ''
			: __( 'Activate a Pro licence to unlock this module.', 'best-addons' );
	}
}
