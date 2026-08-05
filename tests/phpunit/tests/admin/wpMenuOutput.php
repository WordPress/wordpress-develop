<?php

/**
 * @group admin
 * @group menu
 *
 * @covers ::_wp_menu_output
 */
class Tests_Admin_WpMenuOutput extends WP_UnitTestCase {
	protected static $admin_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		/*
		 * Including menu-header.php defines _wp_menu_output(), but the file also
		 * renders the admin menu at include time. Seed empty menu globals and
		 * discard that output so including it here is side-effect free.
		 */
		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();

		ob_start();
		require_once ABSPATH . 'wp-admin/menu-header.php';
		ob_end_clean();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * A hidden count description on a top-level menu title is associated with the
	 * link via aria-describedby, keeping the count out of the accessible name.
	 *
	 * @ticket 65793
	 */
	public function test_top_level_count_description_is_associated_via_aria_describedby() {
		$menu = array(
			array(
				'Plugins <span id="wp-menu-plugins-count-description" class="wp-menu-count-description screen-reader-text" aria-hidden="true">2 plugin updates available</span>',
				'read',
				'plugins.php',
				'',
				'menu-top menu-icon-plugins',
				'menu-plugins',
				'dashicons-admin-plugins',
			),
		);

		$output = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertStringContainsString( 'aria-describedby="wp-menu-plugins-count-description"', $output );
		$this->assertStringContainsString( 'id="wp-menu-plugins-count-description"', $output );
	}

	/**
	 * A hidden count description on a submenu title is associated with the submenu link.
	 *
	 * @ticket 65793
	 */
	public function test_submenu_count_description_is_associated_via_aria_describedby() {
		$menu = array(
			array( 'Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
		);

		$submenu = array(
			'index.php' => array(
				10 => array(
					'Updates <span id="wp-menu-updates-count-description" class="wp-menu-count-description screen-reader-text" aria-hidden="true">2 updates available</span>',
					'read',
					'update-core.php',
				),
			),
		);

		$output = get_echo( '_wp_menu_output', array( $menu, $submenu ) );

		$this->assertStringContainsString( 'aria-describedby="wp-menu-updates-count-description"', $output );
	}

	/**
	 * The count description id must appear once even when the top-level item has a
	 * submenu, since the submenu head repeats the top-level title.
	 *
	 * @ticket 65793
	 */
	public function test_count_description_id_is_not_duplicated_by_submenu_head() {
		$menu = array(
			array(
				'Plugins <span id="wp-menu-plugins-count-description" class="wp-menu-count-description screen-reader-text" aria-hidden="true">2 plugin updates available</span>',
				'read',
				'plugins.php',
				'',
				'menu-top menu-icon-plugins',
				'menu-plugins',
				'dashicons-admin-plugins',
			),
		);

		$submenu = array(
			'plugins.php' => array(
				5  => array( 'Installed Plugins', 'activate_plugins', 'plugins.php' ),
				10 => array( 'Add New Plugin', 'install_plugins', 'plugin-install.php' ),
			),
		);

		$output = get_echo( '_wp_menu_output', array( $menu, $submenu ) );

		$this->assertSame( 1, substr_count( $output, 'id="wp-menu-plugins-count-description"' ) );
		$this->assertStringContainsString( 'aria-describedby="wp-menu-plugins-count-description"', $output );
	}

	/**
	 * Menu items without a count description do not receive an aria-describedby attribute.
	 *
	 * @ticket 65793
	 */
	public function test_menu_without_count_description_has_no_aria_describedby() {
		$menu = array(
			array( 'Media', 'upload_files', 'upload.php', '', 'menu-top menu-icon-media', 'menu-media', 'dashicons-admin-media' ),
		);

		$output = get_echo( '_wp_menu_output', array( $menu, array() ) );

		$this->assertStringNotContainsString( 'aria-describedby', $output );
	}
}
