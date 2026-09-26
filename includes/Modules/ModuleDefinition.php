<?php
/**
 * One module, as described by the manifest.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object over one manifest record.
 *
 * Every accessor tolerates a missing key rather than assuming the manifest is
 * complete, because a record that reaches this class has already been validated at
 * build time — but a stale manifest from a previous plugin version should degrade
 * to "this module does nothing" rather than to a fatal error on a live site.
 */
final class ModuleDefinition {

	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct( private array $data ) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * @return string
	 */
	public function id(): string {
		return (string) ( $this->data['id'] ?? '' );
	}

	/**
	 * @return string `widget`, `block` or `category`.
	 */
	public function type(): string {
		return (string) ( $this->data['type'] ?? 'widget' );
	}

	/**
	 * @return string `free` or `pro`.
	 */
	public function tier(): string {
		return (string) ( $this->data['tier'] ?? 'free' );
	}

	/**
	 * The category slug to register under.
	 *
	 * Only meaningful for a `type: category` module. Empty for everything else.
	 *
	 * @return string
	 */
	public function slug(): string {
		return (string) ( $this->data['slug'] ?? '' );
	}

	/**
	 * @return string
	 */
	public function title(): string {
		return (string) ( $this->data['title'] ?? $this->id() );
	}

	/**
	 * @return string
	 */
	public function description(): string {
		return (string) ( $this->data['description'] ?? '' );
	}

	/**
	 * @return string[]
	 */
	public function keywords(): array {
		/** @var string[] $keywords */
		$keywords = $this->data['keywords'] ?? array();

		return $keywords;
	}

	/**
	 * Fully-qualified class name of the module's PHP entry point.
	 *
	 * @return string Empty for a module that has no PHP, such as a category.
	 */
	public function class_name(): string {
		return (string) ( $this->data['class'] ?? '' );
	}

	/**
	 * Plugin-relative path to the entry class file.
	 *
	 * @return string
	 */
	public function file(): string {
		return (string) ( $this->data['file'] ?? '' );
	}
	/**
	 * @return string
	 */
	public function category(): string {
		return (string) ( $this->data['category'] ?? '' );
	}

	/**
	 * @return string
	 */
	public function icon(): string {
		return (string) ( $this->data['icon'] ?? 'eicon-default' );
	}

	/**
	 * @return int
	 */
	public function priority(): int {
		return (int) ( $this->data['priority'] ?? 100 );
	}

	/**
	 * Content hash, used as the `ver` argument on every enqueue.
	 *
	 * @return string
	 */
	public function hash(): string {
		return (string) ( $this->data['hash'] ?? '1.0.0' );
	}

	/**
	 * @return string
	 */
	public function version(): string {
		return (string) ( $this->data['version'] ?? '1.0.0' );
	}

	/**
	 * Asset descriptors keyed by kind (`style`, `script`, `editor`).
	 *
	 * @return array<string, array{handle: string, file: string, ver: string, kind: string, source: string}>
	 */
	public function assets(): array {
		/** @var array<string, array{handle: string, file: string, ver: string, kind: string, source: string}> $assets */
		$assets = $this->data['assets'] ?? array();

		return $assets;
	}

	/**
	 * One asset descriptor.
	 *
	 * @param string $kind `style`, `script` or `editor`.
	 *
	 * @return array{handle: string, file: string, ver: string, kind: string, source: string}|null
	 */
	public function asset( string $kind ): ?array {
		return $this->assets()[ $kind ] ?? null;
	}

	/**
	 * The `type` value this module handles.
	 */
	public function is_widget(): bool {
		return 'widget' === $this->type();
	}

	/**
	 * @return bool
	 */
	public function is_block(): bool {
		return 'block' === $this->type();
	}

	/**
	 * @return bool
	 */
	public function is_category(): bool {
		return 'category' === $this->type();
	}

	/**
	 * @return bool
	 */
	public function is_pro(): bool {
		return 'pro' === $this->tier();
	}
}
