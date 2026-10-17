<?php

/**
 * Tests for user_can_access_admin_page().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::user_can_access_admin_page
 */
class Tests_Admin_Includes_Plugin_UserCanAccessAdminPage extends WP_UnitTestCase {

	/**
	 * Names of the globals used by the function under test.
	 *
	 * @var string[]
	 */
	const GLOBALS_USED = array(
		'parent_file',
		'menu',
		'submenu',
		'pagenow',
		'typenow',
		'plugin_page',
		'admin_page_hooks',
		'_registered_pages',
		'_wp_real_parent_file',
		'_wp_menu_nopriv',
		'_wp_submenu_nopriv',
	);

	/**
	 * Original values of the globals that were set before the test, keyed by name.
	 *
	 * @var array
	 */
	private $orig_globals = array();

	/**
	 * IDs of the users for each role, keyed by role.
	 *
	 * @var int[]
	 */
	private static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		foreach ( array( 'administrator', 'editor', 'subscriber' ) as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->orig_globals = array();

		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->orig_globals[ $name ] = $GLOBALS[ $name ];
			}

			unset( $GLOBALS[ $name ] );
		}

		$GLOBALS['menu']               = array(
			2  => array( 'Dashboard', 'read', 'index.php' ),
			25 => array( 'My Plugin', 'manage_options', 'my-plugin' ),
			80 => array( 'Settings', 'manage_options', 'options-general.php' ),
		);
		$GLOBALS['submenu']            = array(
			'index.php'           => array(
				0 => array( 'Home', 'read', 'index.php' ),
			),
			'my-plugin'           => array(
				0 => array( 'My Plugin', 'manage_options', 'my-plugin' ),
				1 => array( 'Sub Page', 'edit_posts', 'my-plugin-sub' ),
			),
			'options-general.php' => array(
				10 => array( 'General', 'manage_options', 'options-general.php' ),
				50 => array( 'My Settings', 'manage_options', 'my-settings' ),
			),
		);
		$GLOBALS['admin_page_hooks']   = array(
			'my-plugin'           => 'my-plugin',
			'options-general.php' => 'settings',
		);
		$GLOBALS['_registered_pages']  = array();
		$GLOBALS['_wp_menu_nopriv']    = array();
		$GLOBALS['_wp_submenu_nopriv'] = array();
	}

	public function tear_down() {
		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $this->orig_globals ) ) {
				$GLOBALS[ $name ] = $this->orig_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests whether the current user can access the current admin page.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_user_can_access_admin_page
	 *
	 * @param array  $globals  Globals describing the current request, merged into the defaults set up for each test.
	 * @param string $role     Role of the current user.
	 * @param bool   $expected Whether the user should be able to access the page.
	 */
	public function test_user_can_access_admin_page( $globals, $role, $expected ) {
		foreach ( $globals as $name => $value ) {
			if ( is_array( $value ) && isset( $GLOBALS[ $name ] ) && is_array( $GLOBALS[ $name ] ) && in_array( $name, array( 'menu', 'submenu' ), true ) ) {
				$GLOBALS[ $name ] = array_merge( $GLOBALS[ $name ], $value );
			} else {
				$GLOBALS[ $name ] = $value;
			}
		}

		wp_set_current_user( self::$user_ids[ $role ] );

		$this->assertSame( $expected, user_can_access_admin_page() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_user_can_access_admin_page() {
		$book_menu = array(
			'menu'    => array( 26 => array( 'Books', 'edit_posts', 'edit.php?post_type=book' ) ),
			'submenu' => array(
				'edit.php?post_type=book' => array(
					5 => array( 'All Books', 'manage_options', 'edit.php?post_type=book' ),
				),
			),
			'pagenow' => 'edit.php',
			'typenow' => 'book',
		);

		return array(
			// Core pages in a submenu use the capability of the submenu item.
			'submenu page, user has the capability'   => array(
				array( 'pagenow' => 'options-general.php' ),
				'administrator',
				true,
			),
			'submenu page, user lacks the capability' => array(
				array( 'pagenow' => 'options-general.php' ),
				'editor',
				false,
			),
			'submenu page marked as not accessible'   => array(
				array(
					'pagenow'            => 'options-general.php',
					'_wp_submenu_nopriv' => array( 'options-general.php' => array( 'options-general.php' => true ) ),
				),
				'administrator',
				false,
			),

			// Plugin pages must be registered, and use the capability of their menu item.
			'plugin subpage, registered, user has the capability' => array(
				array(
					'pagenow'           => 'options-general.php',
					'plugin_page'       => 'my-settings',
					'_registered_pages' => array( 'settings_page_my-settings' => true ),
				),
				'administrator',
				true,
			),
			'plugin subpage, registered, user lacks the capability' => array(
				array(
					'pagenow'           => 'options-general.php',
					'plugin_page'       => 'my-settings',
					'_registered_pages' => array( 'settings_page_my-settings' => true ),
				),
				'editor',
				false,
			),
			'plugin subpage that is not registered'   => array(
				array(
					'pagenow'     => 'options-general.php',
					'plugin_page' => 'my-settings',
				),
				'administrator',
				false,
			),
			'plugin subpage marked as not accessible' => array(
				array(
					'pagenow'            => 'options-general.php',
					'plugin_page'        => 'my-settings',
					'_registered_pages'  => array( 'settings_page_my-settings' => true ),
					'_wp_submenu_nopriv' => array( 'options-general.php' => array( 'my-settings' => true ) ),
				),
				'administrator',
				false,
			),
			'top-level plugin page, user has the capability' => array(
				array(
					'pagenow'           => 'admin.php',
					'plugin_page'       => 'my-plugin',
					'_registered_pages' => array( 'toplevel_page_my-plugin' => true ),
				),
				'administrator',
				true,
			),
			'top-level plugin page, user lacks the capability' => array(
				array(
					'pagenow'           => 'admin.php',
					'plugin_page'       => 'my-plugin',
					'_registered_pages' => array( 'toplevel_page_my-plugin' => true ),
				),
				'editor',
				false,
			),
			'plugin page under a top-level plugin menu uses its own capability' => array(
				array(
					'pagenow'           => 'admin.php',
					'plugin_page'       => 'my-plugin-sub',
					'_registered_pages' => array( 'my-plugin_page_my-plugin-sub' => true ),
				),
				'editor',
				true,
			),
			'top-level plugin page marked as not accessible' => array(
				array(
					'pagenow'           => 'admin.php',
					'plugin_page'       => 'hidden-plugin',
					'_registered_pages' => array( 'admin_page_hidden-plugin' => true ),
					'_wp_menu_nopriv'   => array( 'hidden-plugin' => true ),
				),
				'administrator',
				false,
			),

			// Pages without a parent are accessible unless a menu marks them as not accessible.
			'page without a parent'                   => array(
				array( 'pagenow' => 'profile.php' ),
				'subscriber',
				true,
			),
			'page without a parent, not accessible as a menu' => array(
				array(
					'pagenow'         => 'profile.php',
					'_wp_menu_nopriv' => array( 'profile.php' => true ),
				),
				'subscriber',
				false,
			),
			'page without a parent, not accessible as its own submenu' => array(
				array(
					'pagenow'            => 'profile.php',
					'_wp_submenu_nopriv' => array( 'profile.php' => array( 'profile.php' => true ) ),
				),
				'subscriber',
				false,
			),
			'page without a parent, not accessible in another submenu' => array(
				array(
					'pagenow'            => 'profile.php',
					'_wp_submenu_nopriv' => array( 'users.php' => array( 'profile.php' => true ) ),
				),
				'subscriber',
				false,
			),
			'plugin page without a parent'            => array(
				array(
					'pagenow'           => 'profile.php',
					'plugin_page'       => 'orphan',
					'_registered_pages' => array( 'admin_page_orphan' => true ),
				),
				'subscriber',
				true,
			),
			'plugin page without a parent, not accessible as a menu' => array(
				array(
					'pagenow'           => 'profile.php',
					'plugin_page'       => 'orphan',
					'_registered_pages' => array( 'admin_page_orphan' => true ),
					'_wp_menu_nopriv'   => array( 'orphan' => true ),
				),
				'subscriber',
				false,
			),
			'plugin page without a parent, not accessible in another submenu' => array(
				array(
					'pagenow'            => 'profile.php',
					'plugin_page'        => 'orphan',
					'_registered_pages'  => array( 'admin_page_orphan' => true ),
					'_wp_submenu_nopriv' => array( 'users.php' => array( 'orphan' => true ) ),
				),
				'subscriber',
				false,
			),

			// When no submenu item matches the page, the capability of the parent menu item is used.
			'parent menu capability, user has it'     => array( $book_menu, 'editor', true ),
			'parent menu capability, user lacks it'   => array( $book_menu, 'subscriber', false ),
			'parent that is not in the menu'          => array(
				array_merge( $book_menu, array( 'menu' => array() ) ),
				'subscriber',
				true,
			),
		);
	}
}
