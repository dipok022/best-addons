<?php
/**
 * Base class for every block module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

use BestAddons\Assets\AssetPipeline;
use BestAddons\Support\Manifest;

defined( 'ABSPATH' ) || exit;

/**
 * A Gutenberg block module.
 *
 * The block counterpart to `BaseElementorWidget`: the module id, the asset
 * handles and the block name all come from the manifest, so a block module
 * declares only its attribute schema and its markup.
 */
abstract class BaseBlock implements Module {

	/**
	 * Prefix for every block this plugin registers.
	 */
	public const BLOCK_PREFIX = 'best-addons/';

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
	 * The block's attribute schema, in `register_block_type` shape.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function attributes(): array;

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Values straight from `get_attributes()`.
	 * @param string               $content    Inner blocks' rendered content.
	 */
	abstract protected function render( array $attributes, string $content ): string;

	/**
	 * @return string
	 */
	public function id(): string {
		return $this->module_id();
	}

	/**
	 * The registered block name, e.g. `best-addons/advanced-tabs`.
	 *
	 * @return string
	 */
	public function block_name(): string {
		return self::BLOCK_PREFIX . $this->module_id();
	}

	/**
	 * This module's manifest record, resolved once.
	 */
	protected function definition(): ModuleDefinition {
		if ( null === $this->definition ) {
			$definition = Registry::get( $this->module_id() );

			if ( null === $definition ) {
				$record = Manifest::module( $this->module_id() );
				$definition = null !== $record
					? ModuleDefinition::from_array( $record )
					: new ModuleDefinition( array( 'id' => $this->module_id() ) );
			}

			$this->definition = $definition;
		}

		return $this->definition;
	}

	/**
	 * Register this block with WordPress.
	 *
	 * Called by the BlockRegistrar once the module has cleared the registry's
	 * filters. Registering assets here rather than waiting for
	 * `wp_enqueue_scripts` matters: `register_block_type()` runs on `init`, long
	 * before any enqueue hook, and it needs the handles to exist.
	 */
	public function register(): void {
		$definition = $this->definition();

		AssetPipeline::register_module( $definition );

		$args = array(
			'api_version'     => 3,
			'title'           => $definition->title(),
			'description'     => $definition->description(),
			'category'        => 'widgets',
			'attributes'      => $this->attributes(),
			'supports'        => array(
				'html'    => false,
				'align'   => array( 'wide', 'full' ),
				'spacing' => array( 'margin', 'padding' ),
			),
			'render_callback' => array( $this, 'render_callback' ),
		);

		if ( null !== $definition->asset( 'style' ) ) {
			$args['style_handles'] = array( $definition->asset( 'style' )['handle'] );
		}

		if ( null !== $definition->asset( 'script' ) ) {
			$args['script_handles'] = array( $definition->asset( 'script' )['handle'] );
		}

		if ( null !== $definition->asset( 'editor' ) ) {
			$args['editor_script_handles'] = array( $definition->asset( 'editor' )['handle'] );
		}

		if ( ! register_block_type( $this->block_name(), $args ) ) {
			// A duplicate registration means another module claimed this name.
			// The extractor's id-uniqueness rule makes that impossible for
			// folders, but a filter could inject one.
			_doing_it_wrong(
				__METHOD__,
				esc_html( sprintf( 'Block "%s" could not be registered.', $this->block_name() ) ),
				'1.0.0'
			);
		}
	}

	/**
	 * Adapter between WordPress's render callback signature and the module's.
	 *
	 * @param array<string, mixed> $attributes
	 * @param string               $content
	 */
	public function render_callback( $attributes, $content = '' ): string {
		/** @var array<string, mixed> $attributes */
		$attributes = is_array( $attributes ) ? $attributes : array();

		return $this->render( $attributes, (string) $content );
	}
}
