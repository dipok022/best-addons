<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Best_Addons_Admin_Settings {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_plugin_settings' ] );
	}

	public static function add_settings_menu() {
		add_menu_page(
			esc_html__( 'Best Addons Settings', 'best-addons' ),
			esc_html__( 'Best Addons', 'best-addons' ),
			'manage_options',
			'best-addons-settings',
			[ __CLASS__, 'render_settings_page' ],
			'dashicons-admin-plugins',
			90
		);
	}

	public static function register_plugin_settings() {
		register_setting( 'best_addons_settings_group', 'best_addons_active_widgets' );
	}

	public static function get_all_widgets_map() {
		return [
			'sample-widget'      => esc_html__( 'Sample Content Box', 'best-addons' ),
			'advanced-accordion' => esc_html__( 'Advanced Accordion Grid', 'best-addons' ),
			'accordion' => esc_html__( 'Accordion', 'best-addons' ),
		];
	}

	public static function render_settings_page() {
		$all_widgets    = self::get_all_widgets_map();
		$active_widgets = get_option( 'best_addons_active_widgets', array_keys( $all_widgets ) );
		if ( ! is_array( $active_widgets ) ) {
			$active_widgets = array_keys( $all_widgets );
		}
		?>
		<div class="wrap" style="background:#fff; padding:30px; border-radius:12px; margin-top:20px; max-width:800px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
			<h1 style="color:#222; font-weight:700; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:25px;">⚡ Best Addons Optimization Panel</h1>
			<p style="color:#64748b; font-size:14px; margin-bottom:25px;">Toggle off the widgets you aren't using. Deactivated widgets will have their PHP classes and CSS/JS code completely removed from your visitors' download stream.</p>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'best_addons_settings_group' ); ?>
				
				<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:20px; margin-bottom:30px;">
					<?php foreach ( $all_widgets as $key => $name ) : 
						$is_checked = in_array( $key, $active_widgets ) ? 'checked' : '';
						?>
						<label style="display:flex; align-items:center; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; cursor:pointer; transition:all 0.2s ease;">
							<input type="checkbox" name="best_addons_active_widgets[]" value="<?php echo esc_attr( $key ); ?>" <?php echo $is_checked; ?> style="margin-right:12px; width:18px; height:18px; accent-color:#e11d48;">
							<span style="font-weight:600; color:#334155; font-size:14px;"><?php echo esc_html( $name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

				<?php submit_button( esc_html__( 'Save Active Configurations', 'best-addons' ), 'primary', 'submit', true, [ 'style' => 'background:#e11d48; border:none; padding:10px 24px; font-weight:600; box-shadow:0 4px 10px rgba(225,29,72,0.25);' ] ); ?>
			</form>
		</div>
		<?php
	}
}
