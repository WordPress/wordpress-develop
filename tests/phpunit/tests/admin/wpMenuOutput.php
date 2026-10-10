<?php
/**
 * Unit tests covering the icons of the admin menu.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.2.0
 *
 * @group admin
 * @group menu
 */
class Tests_Admin_WpMenuOutput extends WP_UnitTestCase {

	/**
	 * Defines the admin menu functions.
	 *
	 * The file that defines them also prints the admin menu, so it is included
	 * once with an empty menu and its output is discarded.
	 */
	public static function set_up_before_class() {
		global $menu, $submenu;

		parent::set_up_before_class();

		if ( function_exists( '_wp_menu_output' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$original_menu    = $menu;
		$original_submenu = $submenu;
		$menu             = array();
		$submenu          = array();

		ob_start();
		require ABSPATH . 'wp-admin/menu-header.php';
		ob_end_clean();

		$menu    = $original_menu;
		$submenu = $original_submenu;
	}

	public function set_up() {
		parent::set_up();

		// Menu items are only linked, and given their icon, for users who can access them.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Dashicons back-compat: the Dashicons of the core menu items are kept in the menu data and rendered as SVG icons.
	 *
	 * @ticket 65089
	 *
	 * @covers ::_wp_menu_output
	 * @covers ::_wp_replace_menu_dashicon
	 *
	 * @dataProvider data_replaced_dashicons
	 *
	 * @param string $dashicon Dashicon class name.
	 */
	public function test_core_dashicon_is_rendered_as_svg_icon( $dashicon ): void {
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
		$menu = array(
			array( 'Item', 'read', 'my-plugin', '', 'menu-top', 'toplevel_page_my-plugin', $dashicon ),
		);
		$html = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertMatchesRegularExpression( "#<div class='wp-menu-image dashicons-before svg-icon' aria-hidden='true'><svg\\b.*?</svg>\\s*</div>#s", $html );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string }>
	 */
	public static function data_replaced_dashicons(): array {
		return array(
			'dashboard'  => array( 'dashicons-dashboard' ),
			'posts'      => array( 'dashicons-admin-post' ),
			'media'      => array( 'dashicons-admin-media' ),
			'links'      => array( 'dashicons-admin-links' ),
			'pages'      => array( 'dashicons-admin-page' ),
			'comments'   => array( 'dashicons-admin-comments' ),
			'appearance' => array( 'dashicons-admin-appearance' ),
			'plugins'    => array( 'dashicons-admin-plugins' ),
			'users'      => array( 'dashicons-admin-users' ),
			'tools'      => array( 'dashicons-admin-tools' ),
			'settings'   => array( 'dashicons-admin-settings' ),
			'sites'      => array( 'dashicons-admin-multisite' ),
		);
	}

	/**
	 * Dashicons back-compat: any other icon value is rendered as before.
	 *
	 * @ticket 65089
	 *
	 * @covers ::_wp_menu_output
	 * @covers ::_wp_replace_menu_dashicon
	 *
	 * @dataProvider data_unchanged_icons
	 *
	 * @param string $icon     The icon of the menu item.
	 * @param string $expected The expected HTML of the icon element.
	 */
	public function test_other_icon_is_rendered_as_before( $icon, $expected ): void {
		$this->assertSame( $icon, _wp_replace_menu_dashicon( $icon ) );

		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
		$menu = array(
			array( 'Item', 'read', 'my-plugin', '', 'menu-top', 'toplevel_page_my-plugin', $icon ),
		);
		$html = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertStringContainsString( $expected, $html );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string, 1: non-falsy-string }>
	 */
	public static function data_unchanged_icons(): array {
		return array(
			'other Dashicon' => array(
				'dashicons-heart',
				"<div class='wp-menu-image dashicons-before dashicons-heart' aria-hidden='true'><br /></div>",
			),
			'image URL'      => array(
				'https://example.org/icon.png',
				"<div class='wp-menu-image dashicons-before' aria-hidden='true'><img src=\"https://example.org/icon.png\" alt=\"\" /></div>",
			),
			'none'           => array(
				'none',
				"<div class='wp-menu-image dashicons-before' aria-hidden='true'><br /></div>",
			),
			'div'            => array(
				'div',
				"<div class='wp-menu-image dashicons-before' aria-hidden='true'><br /></div>",
			),
			'base64 SVG'     => array(
				'data:image/svg+xml;base64,PHN2Zy8+',
				"<div class='wp-menu-image svg' style=\"background-image:url('data:image/svg+xml;base64,PHN2Zy8+')\" aria-hidden='true'><br /></div>",
			),
		);
	}

	/**
	 * @ticket 65089
	 *
	 * @covers ::_wp_menu_output
	 */
	public function test_icon_name_is_rendered_as_svg_icon(): void {
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
		$menu = array(
			array( 'Item', 'read', 'my-plugin', '', 'menu-top', 'toplevel_page_my-plugin', 'core/chart-bar' ),
		);
		$html = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertStringContainsString( "<div class='wp-menu-image dashicons-before svg-icon' aria-hidden='true'>" . wp_get_icon( 'core/chart-bar' ) . '</div>', $html );
	}

	/**
	 * @ticket 65089
	 *
	 * @covers ::_wp_menu_output
	 *
	 * @expectedIncorrectUsage _wp_menu_output
	 */
	public function test_unregistered_icon_name_leaves_icon_element_empty(): void {
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
		$menu = array(
			array( 'Item', 'read', 'my-plugin', '', 'menu-top', 'toplevel_page_my-plugin', 'my-plugin/not-registered' ),
		);
		$html = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertStringContainsString( "<div class='wp-menu-image dashicons-before' aria-hidden='true'><br /></div>", $html );
	}
}
