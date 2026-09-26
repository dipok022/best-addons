<?php
/**
 * The options screen.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin;

use BestAddons\Assets\AdminApp;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the React mount point. No interface markup of its own.
 *
 * The panel used to be a hand-written `settings_fields()` form. Everything it did
 * is now a REST call, which is what lets the module grid populate itself from the
 * manifest: a module added in a folder appears in the UI without a line of PHP
 * changing.
 */
final class OptionsPage {

	/**
	 * Menu slug for the main screen.
	 */
	public const SLUG = 'best-addons';

	/**
	 * Render the mount point.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'best-addons' ) );
		}

		?>
		<div class="ba-admin-shell">
			<div id="<?php echo esc_attr( AdminApp::ROOT_ID ); ?>"></div>
		</div>
		<script
			type="application/json"
			id="<?php echo esc_attr( AdminApp::DATA_ID ); ?>">
			<?php
			// JSON_HEX_TAG and JSON_HEX_AMP stop a translated string containing
			// "</script>" from closing this tag and turning the payload into
			// markup. wp_localize_script does not do this.
			echo wp_json_encode(
				AdminApp::bootstrap_data(),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);
			?>
		</script>
		<?php
	}
}
