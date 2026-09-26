<?php
/**
 * Base class for every Elementor widget module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

use BestAddons\Assets\AssetPipeline;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * An Elementor widget that already knows which module it is.
 *
 * Handles, categories, icons, keywords and cache-busting versions all come from
 * the manifest record for this module. A concrete widget therefore declares only
 * what is genuinely its own: its id, its controls, and its markup.
 *
 * The identity is derived from `get_name()` rather than injected, because
 * Elementor re-instantiates widgets from saved data in several code paths and an
 * injected property would not survive the round trip.
 */
abstract class BaseElementorWidget extends Widget_Base implements Module {

	/**
	 * Every widget module's Elementor name is this plus the module id.
	 */
	public const NAME_PREFIX = 'best_addons_';

	/**
	 * Lazily resolved manifest record.
	 */
	private ?ModuleDefinition $definition = null;

	/**
	 * The module id. Must match the folder name and `module.json`.
	 *
	 * @return string
	 */
	abstract protected function module_id(): string;

	/**
	 * @return string
	 */
	public function id(): string {
		return $this->module_id();
	}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return self::NAME_PREFIX . $this->module_id();
	}

	/**
	 * This module's manifest record, resolved once.
	 *
	 * Falls back to the unfiltered manifest so a widget instantiated outside the
	 * registry — which Elementor does when restoring a document — still knows its
	 * own assets. The gate is not the concern here: the registry already decided
	 * whether this widget may exist on this site.
	 */
	protected function definition(): ModuleDefinition {
		if ( null === $this->definition ) {
			$definition = Registry::get( $this->module_id() );

			if ( null === $definition ) {
				$record = \BestAddons\Support\Manifest::module( $this->module_id() );
				$definition = null !== $record
					? ModuleDefinition::from_array( $record )
					: new ModuleDefinition( array( 'id' => $this->module_id() ) );
			}

			$this->definition = $definition;
		}

		return $this->definition;
	}

	/**
	 * The Elementor category this widget belongs to.
	 *
	 * A widget's `module.json` names the category *module* — `free`, `pro` — not
	 * the slug Elementor registers it under. `Categories::slug()` is the one place
	 * those are joined, so a category folder can be renamed without touching a
	 * widget that points at it.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		$category = $this->definition()->category();

		return array( '' !== $category ? array( Categories::slug( $category ) ) : array() );
	}

	/**
	 * @return string
	 */
	public function get_icon(): string {
		return $this->definition()->icon();
	}

	/**
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		$keywords = $this->definition()->keywords();

		return array_map( 'strval', $keywords );
	}

	/**
	 * Front-end CSS, straight from the manifest.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return $this->handlesFor( 'style' );
	}

	/**
	 * Front-end JS, straight from the manifest.
	 *
	 * The editor bundle is intentionally absent: it is enqueued on the editor
	 * screen by the AssetPipeline, so it cannot leak to a visitor.
	 *
	 * @return array<int, string>
	 */
	public function get_script_depends(): array {
		return $this->handlesFor( 'script' );
	}

	/**
	 * Handles for one asset kind, registering on demand.
	 *
	 * @return array<int, string>
	 */
	protected function handlesFor( string $kind ): array {
		$asset = $this->definition()->asset( $kind );

		if ( null === $asset ) {
			return array();
		}

		if ( 'style' === $kind ) {
			AssetPipeline::register_style( $asset );
		} else {
			AssetPipeline::register_script( $asset );
		}

		return array( $asset['handle'] );
	}
}
