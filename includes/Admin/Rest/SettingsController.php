<?php
/**
 * Settings endpoint.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin\Rest;

use BestAddons\Modules\Enablement;
use BestAddons\Modules\Registry;
use BestAddons\Support\Manifest;

defined( 'ABSPATH' ) || exit;

/**
 * `GET|POST /best-addons/v1/settings` — module toggles and general preferences.
 */
final class SettingsController extends Controller {

	/**
	 * Option holding general preferences.
	 */
	private const PREFERENCES = 'best_addons_preferences';

	/**
	 * @inheritDoc
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_write' ),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_write(): bool {
		$this->guard( 'write' );

		return true;
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function show(): \WP_REST_Response {
		$disabled = Enablement::disabled_ids();

		return $this->ok(
			array(
				'disabled'    => array_values( $disabled ),
				'preferences' => $this->preferences(),
				'loaded'      => count( Registry::all() ),
				'total'       => count( Manifest::modules() ),
			)
		);
	}

	/**
	 * Toggle one or more modules.
	 *
	 * @param \WP_REST_Request $request
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$raw = $request->get_param( 'disabled' );

		if ( ! is_array( $raw ) ) {
			return $this->fail( __( 'Expected a list of module ids to disable.', 'best-addons' ) );
		}

		$known = array();
		foreach ( Manifest::modules() as $record ) {
			$known[] = (string) ( $record['id'] ?? '' );
		}

		$requested = array();
		foreach ( $raw as $id ) {
			$id = sanitize_key( (string) $id );

			// Silently ignoring unknown ids would let a client believe it
			// disabled something that does not exist.
			if ( ! in_array( $id, $known, true ) ) {
				return $this->fail(
					sprintf(
						/* translators: %s: module id. */
						__( 'Unknown module "%s".', 'best-addons' ),
						$id
					)
				);
			}

			$requested[] = $id;
		}

		Enablement::replace( array_fill_keys( $requested, false ) );

		// The module set just changed, so anything derived from it is stale.
		Registry::flush();

		$preferences = $request->get_param( 'preferences' );
		if ( is_array( $preferences ) ) {
			update_option( self::PREFERENCES, $this->sanitize_preferences( $preferences ), false );
		}

		return $this->ok(
			array(
				'disabled'    => Enablement::disabled_ids(),
				'preferences' => $this->preferences(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function preferences(): array {
		/** @var mixed $stored */
		$stored = get_option( self::PREFERENCES, array() );

		return array_merge(
			array(
				'clear_assets_on_uninstall' => false,
				'load_assets_in_editor'    => true,
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, bool>
	 */
	private function sanitize_preferences( array $input ): array {
		$clean = array();

		foreach ( array( 'clear_assets_on_uninstall', 'load_assets_in_editor' ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		return $clean;
	}
}
