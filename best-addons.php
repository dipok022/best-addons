<?php
/**
 * Plugin Name:       Best Addons
 * Description:       High-performance folder-extractor architecture for Elementor and Gutenberg, with Free and Pro module tiers and per-widget asset splitting.
 * Version:           1.0.0
 * Author:            Dipok Roy
 * Text Domain:       best-addons
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BestAddons
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BEST_ADDONS_VERSION' ) ) {
	// A second copy loaded — a symlinked checkout, or the same plugin installed
	// twice. Booting it would register every hook twice, which shows up as
	// duplicated widgets and double-enqueued assets rather than an error.
	return;
}

define( 'BEST_ADDONS_VERSION', '1.0.0' );
define( 'BEST_ADDONS_FILE', __FILE__ );
define( 'BEST_ADDONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BEST_ADDONS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Boot the plugin once WordPress and the other plugins are loaded.
 *
 * `plugins_loaded` rather than `init` because the spine registers Elementor
 * categories and widgets, and Elementor's own classes are only guaranteed to
 * exist by then. Priority 20 gives a plugin that registers Elementor
 * integrations on `plugins_loaded` at the default 10 the chance to finish
 * first, so this plugin's `elementor/elements/categories_registered` listener is
 * attached before the categories it adds are needed.
 */
add_action( 'plugins_loaded', 'best_addons_boot', 20 );

/**
 * Start the plugin.
 *
 * @return void
 */
function best_addons_boot(): void {
	// The autoloader has to be registered before Plugin is touched, and Plugin
	// is the only thing that should need loading by name.
	require_once BEST_ADDONS_PATH . 'includes/Support/Autoloader.php';
	\BestAddons\Support\Autoloader::register();

	\BestAddons\Plugin::instance()->boot();
}
