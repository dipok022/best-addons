<?php
/**
 * Shared REST plumbing.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Base for every `best-addons/v1` route.
 *
 * Centralises the two checks that must not be forgotten on a route that changes
 * state: the capability, and the nonce. Everything the admin SPA sends is
 * cookie-authenticated, so without both a crafted request from another origin
 * could flip a module off.
 */
abstract class Controller {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'best-addons/v1';

	/**
	 * The capability required to read and write.
	 */
	protected const CAPABILITY = 'manage_options';

	/**
	 * Register this controller's routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Check capability and nonce, ending the request if either fails.
	 */
	protected function guard( string $action = 'read' ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do that.', 'best-addons' ) ),
				rest_authorization_required_code()
			);
		}

		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Your session expired. Reload the page.', 'best-addons' ) ),
				403
			);
		}

		unset( $action );
	}

	/**
	 * Success envelope.
	 *
	 * @param mixed $data
	 */
	protected function ok( $data = null ): \WP_REST_Response {
		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Error envelope.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $extra
	 */
	protected function fail( string $message, array $extra = array(), int $status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array_merge( array( 'message' => $message ), $extra ),
			$status
		);
	}
}
