<?php
/**
 * The React admin app.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Assets;

use BestAddons\Licensing\LicenseManager;
use BestAddons\Support\Paths;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the admin SPA on the screens that need it, and nowhere else.
 *
 * The SPA is not a module — it is spine chrome, so its entry is fixed in
 * `vite.config.ts` rather than derived from the manifest. It is loaded only on
 * hooks the plugin itself registered, which keeps React and the admin bundle off
 * every other screen in wp-admin, including the Elementor editor.
 */
final class AdminApp {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'ba-admin';

	/**
	 * Style handle.
	 */
	public const STYLE_HANDLE = 'ba-admin';

	/**
	 * Id of the element the app mounts into.
	 */
	public const ROOT_ID = 'ba-admin-root';

	/**
	 * Id of the element carrying the bootstrap payload.
	 */
	public const DATA_ID = 'ba-admin-data';

	/**
	 * Register the admin-side hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Load the bundle, but only on our own screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix = '' ): void {
		if ( ! self::is_our_screen( $hook_suffix ) ) {
			return;
		}

		$dist = Paths::admin_dist();

		$script = Paths::asset( 'js/ba-admin.min.js' );
		$style  = Paths::asset( 'css/ba-admin.min.css' );

		// A missing build should produce a readable notice rather than a blank
		// page and a 404 in the console.
		if ( ! file_exists( $dist . '/js/ba-admin.min.js' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'Best Addons admin assets are not built.', 'best-addons' ),
						esc_html__( 'Run `npm run build` inside the plugin folder.', 'best-addons' )
					);
				}
			);

			return;
		}

		wp_enqueue_script( self::HANDLE, $script, array(), self::version( $dist . '/js/ba-admin.min.js' ), true );
		wp_enqueue_style( self::STYLE_HANDLE, $style, array( 'dashicons' ), self::version( $dist . '/css/ba-admin.min.css' ) );
	}

	/**
	 * @param string $hook_suffix
	 */
	public static function is_our_screen( string $hook_suffix ): bool {
		return str_starts_with( $hook_suffix, 'best-addons_page_' )
			|| str_starts_with( $hook_suffix, 'toplevel_page_best-addons' );
	}

	/**
	 * Tag the body so the SPA can take over the full width of the screen.
	 *
	 * @param string $classes
	 */
	public static function body_class( $classes ): string {
		return trim( (string) $classes . ' ba-admin-page' );
	}

	/**
	 * The bootstrap payload.
	 *
	 * Returned as an array so OptionsPage can encode it with the flags that stop
	 * a value containing `</script>` from closing the tag early.
	 *
	 * @return array<string, mixed>
	 */
	public static function bootstrap_data(): array {
		return array(
			'restUrl'  => esc_url_raw( rest_url( \BestAddons\Admin\Rest\Controller::NAMESPACE . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'homeUrl'  => esc_url_raw( admin_url() ),
			'license'  => array(
				'active' => LicenseManager::is_active(),
			),
			'links'    => array(
				'widgetBuilder' => admin_url( 'admin.php?page=ba-widget-builder' ),
			),
			'l10n'     => array(
				'title'          => __( 'Best Addons', 'best-addons' ),
				'modules'        => __( 'Modules', 'best-addons' ),
				'license'        => __( 'Licence', 'best-addons' ),
				'settings'       => __( 'Settings', 'best-addons' ),
				'saving'         => __( 'Saving…', 'best-addons' ),
				'saved'          => __( 'Saved', 'best-addons' ),
				'saveFailed'     => __( 'Could not save. Try again.', 'best-addons' ),
				'loadFailed'     => __( 'Could not load. Is the manifest built?', 'best-addons' ),
				'free'           => __( 'Free', 'best-addons' ),
				'pro'            => __( 'Pro', 'best-addons' ),
				'locked'         => __( 'Locked', 'best-addons' ),
				'enabled'        => __( 'Enabled', 'best-addons' ),
				'disabled'       => __( 'Disabled', 'best-addons' ),
				'searchModules'  => __( 'Search modules…', 'best-addons' ),
				'noMatches'      => __( 'No modules match your search.', 'best-addons' ),
				'reset'          => __( 'Enable all modules', 'best-addons' ),
				'manifestError'  => __( 'The module manifest is not built yet.', 'best-addons' ),
				'activate'       => __( 'Activate', 'best-addons' ),
				'deactivate'     => __( 'Deactivate', 'best-addons' ),
				'licenceKey'     => __( 'Licence key', 'best-addons' ),
				'licenceActive'  => __( 'Pro licence active', 'best-addons' ),
				'licenceNone'    => __( 'No active licence. Pro modules are locked.', 'best-addons' ),
				'expiresIn'      => __( 'Expires in %d days', 'best-addons' ),
				'perpetual'      => __( 'Perpetual licence', 'best-addons' ),
				'dismiss'        => __( 'Dismiss', 'best-addons' ),
			),
		);
	}

	/**
	 * `filemtime` here is a build-tool concern, not a request-path one: the admin
	 * bundle is loaded on a handful of screens by a logged-in user, and a
	 * content-hash lookup would mean reading the manifest for no benefit.
	 */
	private static function version( string $file ): string {
		return file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0';
	}
}
