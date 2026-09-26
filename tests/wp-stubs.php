<?php
/**
 * A minimal WordPress stand-in, so the spine can be exercised without a install.
 *
 * Only what the core classes actually call, and each stub behaves the way core
 * does rather than returning something convenient: `apply_filters()` really does
 * run the registered callbacks, `get_option()` really does return the stored
 * value, and the object cache really is a no-op without a persistent backend. A
 * stub that lies would make these tests worse than no tests.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

// ── Constants ────────────────────────────────────────────────────────────────

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

// ── Hooks ────────────────────────────────────────────────────────────────────

$GLOBALS['ba_hooks']    = array();
$GLOBALS['ba_options']    = array();
$GLOBALS['ba_transients'] = array();
$GLOBALS['ba_cache']    = array();
$GLOBALS['ba_actions']  = array();
$GLOBALS['ba_no_cache'] = true;

function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
	$GLOBALS['ba_hooks'][ $hook ][ $priority ][] = $callback;
	return true;
}

function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
	return add_action( $hook, $callback, $priority, $args );
}

function do_action( string $hook, ...$args ): void {
	$GLOBALS['ba_actions'][ $hook ] = ( $GLOBALS['ba_actions'][ $hook ] ?? 0 ) + 1;

	foreach ( $GLOBALS['ba_hooks'][ $hook ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}

function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['ba_hooks'][ $hook ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}
	}

	return $value;
}

function did_action( string $hook ): int {
	return $GLOBALS['ba_actions'][ $hook ] ?? 0;
}

function has_action( string $hook ): bool {
	return ! empty( $GLOBALS['ba_hooks'][ $hook ] );
}

function remove_all_hooks(): void {
	$GLOBALS['ba_hooks']   = array();
	$GLOBALS['ba_actions'] = array();
}

// ── Options ──────────────────────────────────────────────────────────────────

function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['ba_options'] ) ? $GLOBALS['ba_options'][ $name ] : $default;
}

function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['ba_options'][ $name ] = $value;
	return true;
}

function delete_option( string $name ): bool {
	unset( $GLOBALS['ba_options'][ $name ] );
	return true;
}

// ── Transients ───────────────────────────────────────────────────────────────

function get_transient( string $name ) {
	$entry = $GLOBALS['ba_transients'][ $name ] ?? null;

	if ( null === $entry ) {
		return false;
	}

	// An expired transient is a miss, exactly as core behaves — the licence
	// check's cache lifetime is meaningful only if this is honoured.
	if ( $entry['expires'] > 0 && $entry['expires'] < time() ) {
		unset( $GLOBALS['ba_transients'][ $name ] );
		return false;
	}

	return $entry['value'];
}

function set_transient( string $name, $value, int $ttl = 0 ): bool {
	$GLOBALS['ba_transients'][ $name ] = array(
		'value'   => $value,
		'expires' => $ttl > 0 ? time() + $ttl : 0,
	);
	return true;
}

function delete_transient( string $name ): bool {
	unset( $GLOBALS['ba_transients'][ $name ] );
	return true;
}

// ── Object cache ─────────────────────────────────────────────────────────────

function wp_using_ext_object_cache(): bool {
	return (bool) $GLOBALS['ba_no_cache'];
}

function wp_cache_get( string $key, string $group = '' ) {
	return $GLOBALS['ba_cache'][ $group ][ $key ] ?? false;
}

function wp_cache_set( string $key, $value, string $group = '', int $ttl = 0 ): bool {
	$GLOBALS['ba_cache'][ $group ][ $key ] = $value;
	return true;
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	unset( $GLOBALS['ba_cache'][ $group ][ $key ] );
	return true;
}

// ── Paths and URLs ───────────────────────────────────────────────────────────

function plugin_dir_path( string $file ): string {
	return rtrim( dirname( $file ), '/' ) . '/';
}

function plugin_dir_url( string $file ): string {
	return 'https://example.test/wp-content/plugins/best-addons/';
}

function plugins_url( string $path = '', string $file = '' ): string {
	$base = plugin_dir_url( $file );
	return $base . ltrim( $path, '/' );
}

function plugin_basename( string $file ): string {
	return 'best-addons/' . basename( $file );
}

// ── Assets ───────────────────────────────────────────────────────────────────

$GLOBALS['ba_registered'] = array();
$GLOBALS['ba_enqueued']   = array();

function wp_register_style( string $handle, $src, array $deps = array(), $ver = false ): bool {
	$GLOBALS['ba_registered'][ "style:{$handle}" ] = compact( 'src', 'deps', 'ver' );
	return true;
}

function wp_register_script( string $handle, $src, array $deps = array(), $ver = false, $in_footer = false ): bool {
	$GLOBALS['ba_registered'][ "script:{$handle}" ] = compact( 'src', 'deps', 'ver', 'in_footer' );
	return true;
}

function wp_enqueue_style( string $handle ): void {
	$GLOBALS['ba_enqueued'][ "style:{$handle}" ] = true;
}

function wp_enqueue_script( string $handle ): void {
	$GLOBALS['ba_enqueued'][ "script:{$handle}" ] = true;
}

// ── Escaping and i18n ────────────────────────────────────────────────────────

function esc_html( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}

function __( string $text, string $domain = 'default' ) {
	return $text;
}

function esc_url_raw( string $url ): string {
	return $url;
}

function wp_json_encode( $data, int $flags = 0 ) {
	return json_encode( $data, $flags );
}

// ── Misc ─────────────────────────────────────────────────────────────────────

function is_admin(): bool {
	return (bool) ( $GLOBALS['ba_is_admin'] ?? false );
}

function current_user_can( string $capability ): bool {
	return (bool) ( $GLOBALS['ba_can_manage'] ?? true );
}

function _doing_it_wrong( string $function_name, string $message, string $version ): void {
	$GLOBALS['ba_doing_it_wrong'][] = $message;
}

function do_action_ref_array( string $hook, array $args ): void {
	do_action( $hook, ...$args );
}

// ── Admin menus ──────────────────────────────────────────────────────────────

$GLOBALS['ba_menu_pages'] = array();

function add_menu_page( $page_title, $menu_title, $cap, $slug, $callback = '', $icon = '', $position = null ) {
	$GLOBALS['ba_menu_pages'][] = array( 'parent' => $slug, 'slug' => $slug, 'title' => $menu_title );

	return 'toplevel_page_' . $slug;
}

function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $callback = '' ) {
	$GLOBALS['ba_menu_pages'][] = array( 'parent' => $parent, 'slug' => $slug, 'title' => $menu_title );

	return $parent . '_page_' . $slug;
}

function sanitize_key( $key ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}

function wp_unslash( $value ) {
	return $value;
}
