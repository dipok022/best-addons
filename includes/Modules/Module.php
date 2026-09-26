<?php
/**
 * The contract every module type implements.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Marker + shared behaviour for anything the registry can boot.
 *
 * A module does not have to implement this — the registry only needs a
 * constructor. It exists so a module can react to being loaded, and so callers
 * have an `id()` they can rely on without knowing the concrete type.
 */
interface Module {

	/**
	 * The module's id, as declared in `module.json`.
	 */
	public function id(): string;
}
