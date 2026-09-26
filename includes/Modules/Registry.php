<?php
/**
 * The runtime module registry.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

use BestAddons\Licensing\ProGate;
use BestAddons\Support\Manifest;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the manifest into the set of modules that may run on this request.
 *
 * Three filters are applied, in this order, and a module must survive all of them
 * before a single line of its PHP is parsed:
 *
 *   1. **Tier** — `tier: pro` is dropped unless `ProGate::allows()`.
 *   2. **Enablement** — dropped when the site owner switched it off.
 *   3. **Type** — a module is only handed to the registrar that can handle it.
 *
 * The result is memoised for the request. A page with forty modules asks the
 * options table once, not forty times.
 */
final class Registry {

	/**
	 * Filter applied to the manifest before the filters run. The seam where a
	 * site adds a module that is not in a folder.
	 *
	 * @var array<string, ModuleDefinition>|null
	 */
	private static $index = null;

	/**
	 * Every module that may run, indexed by id.
	 *
	 * @return array<string, ModuleDefinition>
	 */
	public static function all(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		/** @var array<string, ModuleDefinition> $indexed */
		$indexed = array();

		foreach ( Manifest::modules() as $record ) {
			$definition = ModuleDefinition::from_array( $record );

			$id = $definition->id();
			if ( '' === $id ) {
				continue;
			}

			// Filter 1 — licence.
			if ( ! ProGate::allows( $definition->tier() ) ) {
				continue;
			}

			// Filter 2 — site owner's toggle.
			if ( ! Enablement::is_enabled( $id ) ) {
				continue;
			}

			$indexed[ $id ] = $definition;
		}

		/**
		 * Filters the modules that will load this request.
		 *
		 * @param array<string, ModuleDefinition> $indexed
		 */
		$filtered = apply_filters( 'best_addons_modules', $indexed );

		$index = array();
		if ( is_array( $filtered ) ) {
			foreach ( $filtered as $id => $definition ) {
				if ( $definition instanceof ModuleDefinition ) {
					$index[ (string) $id ] = $definition;
				}
			}
		}

		return self::$index = $index;
	}

	/**
	 * Modules of one type, in panel order.
	 *
	 * @param string $type `widget`, `block` or `category`.
	 *
	 * @return ModuleDefinition[]
	 */
	public static function of_type( string $type ): array {
		$matches = array();

		foreach ( self::all() as $definition ) {
			if ( $definition->type() === $type ) {
				$matches[] = $definition;
			}
		}

		usort(
			$matches,
			static fn ( ModuleDefinition $a, ModuleDefinition $b ): int =>
				$a->priority() <=> $b->priority() ?: strcmp( $a->id(), $b->id() )
		);

		return $matches;
	}

	/**
	 * One module by id, or null when filtered out or absent.
	 */
	public static function get( string $id ): ?ModuleDefinition {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Does this id exist in the manifest at all, regardless of filtering?
	 *
	 * The options panel needs this: it lists locked and disabled modules too, so
	 * the owner can see what a licence would unlock.
	 */
	public static function exists( string $id ): bool {
		return null !== Manifest::module( $id );
	}

	/**
	 * Instantiate a module's entry class.
	 *
	 * This is the only place a module's PHP is loaded, and it happens after the
	 * filters — so a locked or disabled module is never parsed.
	 *
	 * @return object|null Null when the class is missing or the constructor refuses.
	 */
	/**
	 * Build one module's object, reporting a broken module rather than fataling.
	 *
	 * A single malformed module must not take the whole panel down with it, so a
	 * missing class, a constructor that throws, or a class of the wrong ancestry
	 * is reported and skipped.
	 *
	 * @param ModuleDefinition $definition  The module to build.
	 * @param string           $must_extend Optional ancestor the result must have,
	 *                                      e.g. `\Elementor\Widget_Base`. Empty to
	 *                                      accept any object.
	 */
	public static function instantiate( ModuleDefinition $definition, string $must_extend = '' ): ?object {
		$class = $definition->class_name();

		if ( '' === $class || ! class_exists( $class ) ) {
			self::report( $definition, sprintf( 'class %s does not exist', '' === $class ? '(none declared)' : $class ) );

			return null;
		}

		try {
			$instance = new $class();
		} catch ( \Throwable $error ) {
			self::report( $definition, $error->getMessage() );

			return null;
		}

		if ( '' !== $must_extend && ! is_a( $instance, $must_extend ) ) {
			self::report( $definition, sprintf( '%s does not extend %s', $class, $must_extend ) );

			return null;
		}

		return $instance;
	}

	/**
	 * @param string $reason
	 */
	private static function report( ModuleDefinition $definition, string $reason ): void {
		_doing_it_wrong(
			__METHOD__,
			esc_html( sprintf( 'Module "%s" was skipped: %s', $definition->id(), $reason ) ),
			'1.0.0'
		);
	}

	/**
	 * Clear the memo. Called on licence and settings changes, and by tests.
	 */
	public static function flush(): void {
		self::$index = null;
	}
}
