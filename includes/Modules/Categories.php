<?php
/**
 * Category slugs.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * The slugs of the categories this plugin registers.
 *
 * The slugs are constants because code stores them — a builder widget's
 * `_ba_category` meta, a widget's `module.json`. Deriving them from the manifest
 * at those call sites would mean storing a value that could change under a saved
 * document. The *set* of categories is still manifest-driven; see `all()`.
 */
final class Categories {

	/**
	 * Free modules.
	 */
	public const FREE = 'best-addons-free';

	/**
	 * Pro modules. Hidden from the panel while the licence is locked, because the
	 * Pro modules that live in it are dropped before they can be registered.
	 */
	public const PRO = 'best-addons-pro';

	/**
	 * Widgets made with the visual widget builder.
	 */
	public const BUILDER = 'best-addons-builder';

	/**
	 * Where a builder widget lands when the author has not chosen.
	 */
	public const DEFAULT = self::BUILDER;

	/**
	 * Every category the plugin should register right now, as slug => title.
	 *
	 * Reads the *registry*, not the manifest, so a category is subject to the same
	 * two filters as everything else: a Pro category is absent while the licence is
	 * locked, and a category the owner has disabled does not register. Reading the
	 * manifest directly — as an earlier version did — would have put the Pro
	 * category in the panel on exactly the sites it exists to tease.
	 *
	 * Keyed by slug, not by module id, because this array feeds
	 * `add_category()` and a widget's `get_categories()`, both of which speak slug.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$categories = array();

		foreach ( Registry::of_type( 'category' ) as $module ) {
			$slug = $module->slug();

			// A category module with no slug cannot be registered, and registering
			// it under its module id would produce a category no widget points at.
			if ( '' === $slug ) {
				continue;
			}

			$categories[ $slug ] = $module->title();
		}

		return $categories;
	}

	/**
	 * Translate a category module id into the slug widgets should point at.
	 *
	 * A widget's `module.json` names the category *module* (`free`, `pro`) rather
	 * than the slug, because that reference is what the extractor can validate
	 * against the folder on disk. This is the one place the two are joined up.
	 *
	 * Falls back to the id itself, so a module pointing at a category this plugin
	 * does not ship still returns something usable instead of an empty string.
	 *
	 * @param string $id Category module id.
	 */
	public static function slug( string $id ): string {
		$definition = Registry::get( $id );

		if ( null !== $definition && '' !== $definition->slug() ) {
			return $definition->slug();
		}

		return $id;
	}

	/**
	 * A category's title, falling back to a readable version of the slug.
	 */
	public static function title( string $slug ): string {
		$all = self::all();

		if ( isset( $all[ $slug ] ) ) {
			return $all[ $slug ];
		}

		return ucwords( str_replace( array( 'best-addons-', '-' ), array( '', ' ' ), $slug ) );
	}
}
