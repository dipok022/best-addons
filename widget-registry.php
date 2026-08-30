<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Best_Addons_Widget_Registry {

	public static function register_all_widgets( $widgets_manager ) {
		$widgets_dir = plugin_dir_path( __FILE__ ) . 'widgets/';

		require_once( __DIR__ . '/admin-settings.php' );
		$all_allowed_widgets = Best_Addons_Admin_Settings::get_all_widgets_map();
		$active_widgets      = get_option( 'best_addons_active_widgets', array_keys( $all_allowed_widgets ) );
		
		if ( ! is_array( $active_widgets ) ) {
			$active_widgets = array_keys( $all_allowed_widgets );
		}

		$files = glob( $widgets_dir . '*.php' );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file_path ) {
				$filename = basename( $file_path, '.php' );

				if ( ! in_array( $filename, $active_widgets ) ) {
					continue; 
				}

				require_once( $file_path );

				// Converts "sample-widget" into array ['sample', 'widget']
				$words = explode( '-', $filename );
				$capitalized_words = array_map( 'ucfirst', $words );
				
				// SMART CHECK: Appends _Widget only if the filename doesn't already end with "widget"
				$class_suffix = ( end($words) === 'widget' ) ? '' : '_Widget';
				$class_name = 'Best_Addons_' . implode( '_', $capitalized_words ) . $class_suffix;

				if ( class_exists( $class_name ) ) {
					$widgets_manager->register( new $class_name() );
				}
			}
		}
	}
}
