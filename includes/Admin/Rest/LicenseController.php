<?php
/**
 * Licence endpoint.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin\Rest;

use BestAddons\Licensing\LicenseManager;
use BestAddons\Modules\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * `GET|POST /best-addons/v1/license` — read status, activate, deactivate.
 */
final class LicenseController extends Controller {

	/**
	 * Hook that receives the licence key. Wire your store's API here:
	 *
	 *   add_action( 'best_addons_activate_license', function ( string $key ) { … }, 10, 1 );
	 *
	 * Returning a WP_Error fails the activation; returning true, or nothing,
	 * accepts the key locally.
	 */
	public const ACTIVATION_HOOK = 'best_addons_activate_license';

	/**
	 * @inheritDoc
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/license',
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
		$state = LicenseManager::state();
		$check = LicenseManager::last_check();

		return $this->ok(
			array(
				'active'        => LicenseManager::is_active(),
				'status'        => $state['status'],
				'customer'      => $state['customer'],
				'expires'       => $state['expires'],
				'days_remaining' => LicenseManager::days_remaining(),
				'last_check'    => $check,
				// The key is echoed masked, never in full: this response is visible
				// to anyone who can read the options panel.
				'key_masked'    => $this->mask( $state['key'] ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$action = (string) $request->get_param( 'action' );

		if ( 'deactivate' === $action ) {
			LicenseManager::deactivate();
			Registry::flush();

			return $this->ok(
				array(
					'active' => false,
					'message' => __( 'Licence deactivated.', 'best-addons' ),
				)
			);
		}

		if ( 'activate' !== $action ) {
			return $this->fail( __( 'Unknown licence action.', 'best-addons' ) );
		}

		$key = trim( (string) $request->get_param( 'key' ) );

		if ( '' === $key ) {
			return $this->fail( __( 'Enter a licence key.', 'best-addons' ) );
		}

		/**
		 * Validates a licence key against your store.
		 *
		 * Return a WP_Error to reject. The hook's return value is discarded when
		 * it is not a WP_Error, so a void handler means "accept locally".
		 *
		 * @param string $key
		 */
		$result = apply_filters( self::ACTIVATION_HOOK, $key );

		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();

			LicenseManager::record_failure( $message );

			return $this->fail( $message, array( 'active' => false ), 400 );
		}

		$expires  = is_array( $result ) && isset( $result['expires'] ) ? (int) $result['expires'] : 0;
		$customer = is_array( $result ) && isset( $result['customer'] ) ? (string) $result['customer'] : '';

		LicenseManager::activate( $key, $expires, $customer );
		LicenseManager::record_success( $expires );

		// The module set just widened from Free-only to whatever Pro contains.
		Registry::flush();

		return $this->ok(
			array(
				'active'  => true,
				'message' => __( 'Licence activated. Pro modules are unlocked.', 'best-addons' ),
			)
		);
	}

	/**
	 * `ABCD-1234-…-WXYZ`
	 */
	private function mask( string $key ): string {
		if ( '' === $key ) {
			return '';
		}

		$length = strlen( $key );

		if ( $length <= 8 ) {
			return str_repeat( '*', $length );
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $key, -4 );
	}
}
