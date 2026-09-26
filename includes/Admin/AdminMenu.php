<?php
/**
 * Admin menus.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

namespace BestAddons\Admin;

use BestAddons\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's admin screens.
 *
 * The widget builder keeps its own slugs, so its existing links and bookmarks
 * survive the migration to the React panel.
 */
final class AdminMenu {

	/**
	 * Submenu slug the widget builder's own screens hang off.
	 *
	 * This is the main plugin menu, declared on `OptionsPage` because that is the
	 * page it renders. Referencing it through `self::` here would be a fatal —
	 * `self` means *this* class, and there is no `AdminMenu::SLUG`.
	 */
	public const BUILDER_PARENT = OptionsPage::SLUG;

	/**
	 * Submenu slug for the widget builder list.
	 */
	public const BUILDER_SLUG = 'ba-widget-builder';

	/**
	 * Submenu slug for the widget builder editor.
	 */
	public const BUILDER_EDIT_SLUG = 'ba-widget-builder-edit';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_main_menu' ) );
	}

	/**
	 * @return void
	 */
	public function add_main_menu(): void {
		$page = new OptionsPage();

		add_menu_page(
			__( 'Best Addons', 'best-addons' ),
			__( 'Best Addons', 'best-addons' ),
			'manage_options',
			OptionsPage::SLUG,
			array( $page, 'render' ),
			'dashicons-admin-plugins',
			90
		);

		add_submenu_page(
			OptionsPage::SLUG,
			__( 'Modules', 'best-addons' ),
			__( 'Modules', 'best-addons' ),
			'manage_options',
			OptionsPage::SLUG,
			array( $page, 'render' )
		);

		// The builder's screens are registered by the builder itself; the link is
		// added here so it appears under this menu in the right order. While the
		// builder is disabled the link is dropped too, or it would point at a page
		// nobody registers. See Plugin::BUILDER_AVAILABLE.
		if ( ! Plugin::BUILDER_AVAILABLE ) {
			return;
		}

		add_submenu_page(
			OptionsPage::SLUG,
			__( 'Widget Builder', 'best-addons' ),
			__( 'Widget Builder', 'best-addons' ),
			'manage_options',
			self::BUILDER_SLUG
		);
	}
}
