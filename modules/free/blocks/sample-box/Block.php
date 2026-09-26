<?php
/**
 * Sample Box block module.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Feature\Free\Blocks\SampleBox;

use BestAddons\Modules\BaseBlock;

defined( 'ABSPATH' ) || exit;

/**
 * The reference block module.
 *
 * Blocks follow the same contract as widgets — same manifest, same folder shape,
 * same gate — but register with `register_block_type()` instead of Elementor.
 * That difference is the only thing this file has to get right, which is the
 * point of including it: it proves the extractor does not assume Elementor.
 *
 * Note what is *not* here: no name, no title, no category, no asset handles.
 * BaseBlock::register() reads all of that from the manifest, so `module.json`
 * stays the one place a module describes itself.
 */
final class Block extends BaseBlock {

	/**
	 * @inheritDoc
	 */
	protected function module_id(): string {
		return 'sample-box';
	}

	/**
	 * @inheritDoc
	 */
	protected function attributes(): array {
		return array(
			'title' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function render( array $attributes, string $content ): string {
		$title = (string) ( $attributes['title'] ?? '' );

		// An empty title is the block's "deleted" state, not a blank box. Rendering
		// nothing is also what keeps an accidentally-emptied block from becoming a
		// visible gap in someone's post.
		if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
			return '';
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes()
			: '';

		return sprintf(
			'<div %1$s><h3 class="best-addons-sample-box__title">%2$s</h3></div>',
			$wrapper,
			esc_html( $title )
		);
	}
}
