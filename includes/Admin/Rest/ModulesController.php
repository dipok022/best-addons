<?php
/**
 * Modules endpoint.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin\Rest;

use BestAddons\Licensing\LicenseManager;
use BestAddons\Licensing\ProGate;
use BestAddons\Modules\Enablement;
use BestAddons\Modules\ModuleDefinition;
use BestAddons\Modules\Registry;
use BestAddons\Support\Manifest;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /best-addons/v1/modules` — the options panel's data source.
 *
 * Returns every module in the manifest, *including* locked and disabled ones.
 * The panel has to show them: an owner cannot buy a module they cannot see, and
 * a disabled module still needs a switch to turn back on. Whether a module may
 * run is decided by the registry, not by this response.
 */
final class ModulesController extends Controller {

	/**
	 * @inheritDoc
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/modules',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function index(): \WP_REST_Response {
		$pro_active = LicenseManager::is_active();
		$items      = array();

		foreach ( Manifest::modules() as $record ) {
			$module = ModuleDefinition::from_array( $record );
			$locked = $module->is_pro() && ! $pro_active;
			$active = ! $locked && Enablement::is_enabled( $module->id() );

			$items[] = array(
				'id'          => $module->id(),
				'type'        => $module->type(),
				'tier'        => $module->tier(),
				'title'       => $module->title(),
				'description' => $module->description(),
				'keywords'    => $module->keywords(),
				'icon'        => $module->icon(),
				'version'     => $module->version(),
				'category'    => $module->category(),
				'hash'        => $module->hash(),
				'assets'      => array_map(
					static fn ( array $asset ): string => $asset['file'],
					$module->assets()
				),
				'active'      => $active,
				'locked'      => $locked,
				'loaded'      => null !== Registry::get( $module->id() ),
				'lock_reason' => $locked ? ProGate::lock_reason() : '',
			);
		}

		return $this->ok(
			array(
				'modules'    => $items,
				'stats'      => Manifest::stats(),
				'categories' => Manifest::categories(),
				'license'    => array(
					'active' => $pro_active,
					'reason' => $pro_active ? '' : ProGate::lock_reason(),
				),
				'meta'       => array(
					'plugin'    => defined( 'BEST_ADDONS_VERSION' ) ? BEST_ADDONS_VERSION : '',
					'generated' => Manifest::all()['generated'] ?? 0,
					'error'     => Manifest::error(),
				),
			)
		);
	}
}
