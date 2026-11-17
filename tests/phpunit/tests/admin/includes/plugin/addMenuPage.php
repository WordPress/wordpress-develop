<?php

/**
 * Tests for add_menu_page() and add_submenu_page().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_AddMenuPage extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The names of the globals that the menu functions change.
	 *
	 * @var string[]
	 */
	private static $menu_globals = array(
		'menu',
		'submenu',
		'admin_page_hooks',
		'_registered_pages',
		'_parent_pages',
		'_wp_menu_nopriv',
		'_wp_submenu_nopriv',
		'_wp_nopriv_pages',
		'_wp_real_parent_file',
	);

	/**
	 * The original values of the menu globals.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin fixture.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Starts with empty menus.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$menu_globals as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
			$GLOBALS[ $name ]                = array();
		}

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the menus.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $name ] );
			} else {
				$GLOBALS[ $name ] = $value;
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests the menu that is added, with the default icon.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_add_a_menu_with_the_default_icon() {
		global $menu;

		$hookname = add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug' );

		$this->assertSame( 'toplevel_page_the-slug', $hookname, 'The hook name should be returned.' );
		$this->assertSame(
			array(
				array( 'The Menu', 'manage_options', 'the-slug', 'The Page', 'menu-top menu-icon-generic toplevel_page_the-slug', 'toplevel_page_the-slug', 'dashicons-admin-generic' ),
			),
			$menu
		);
	}

	/**
	 * Tests the menu that is added, with an icon.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 *
	 * @dataProvider data_icons
	 *
	 * @param string $icon     The icon.
	 * @param string $expected The expected icon.
	 */
	public function test_add_menu_page_should_use_the_icon_without_the_generic_class( $icon, $expected ) {
		global $menu;

		add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug', '', $icon );

		$this->assertSame( $expected, $menu[0][6], 'The icon should be used.' );
		$this->assertSame( 'menu-top toplevel_page_the-slug', $menu[0][4], 'The generic class should not be used.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_icons() {
		return array(
			'a dashicon' => array( 'dashicons-admin-site', 'dashicons-admin-site' ),
			'a data URI' => array( 'data:image/svg+xml;base64,PHN2Zy8+', 'data:image/svg+xml;base64,PHN2Zy8+' ),
			'a URL'      => array( 'https://example.org/icon.png', 'http://example.org/icon.png' ),
			'none'       => array( 'none', 'none' ),
		);
	}

	/**
	 * Tests that the slug is made relative to the plugins directory.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_make_the_slug_relative_to_the_plugins_directory() {
		global $menu;

		add_menu_page( 'The Page', 'The Menu', 'manage_options', WP_PLUGIN_DIR . '/my-plugin/page.php' );

		$this->assertSame( 'my-plugin/page.php', $menu[0][2] );
	}

	/**
	 * Tests the global lists of pages that the menu is added to.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_register_the_page() {
		global $admin_page_hooks, $_registered_pages, $_parent_pages;

		$hookname = add_menu_page( 'The Page', 'The <b>Menu</b> Name', 'manage_options', 'the-slug' );

		$this->assertSame( 'the-menu-name', $admin_page_hooks['the-slug'], 'The title of the menu should be sanitized for the hook.' );
		$this->assertSame( array( $hookname => true ), $_registered_pages, 'The page should be registered.' );
		$this->assertSame( array( 'the-slug' => false ), $_parent_pages, 'The page should have no parent.' );
	}

	/**
	 * Tests that the callback is hooked for a user who can access the page only.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_hook_the_callback_for_a_user_who_can_access_the_page() {
		$hookname = add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug', '__return_true' );

		$this->assertSame( 10, has_action( $hookname, '__return_true' ), 'The callback should be hooked.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$hookname = add_menu_page( 'Other Page', 'Other Menu', 'manage_options', 'other-slug', '__return_false' );

		$this->assertFalse( has_action( $hookname, '__return_false' ), 'The callback should not be hooked for a user who cannot access the page.' );
	}

	/**
	 * Tests that no callback is hooked when there is none.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_not_hook_an_empty_callback() {
		$hookname = add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug' );

		$this->assertFalse( has_action( $hookname ) );
	}

	/**
	 * Tests that a menu without a position is added last.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_add_the_menu_last_without_a_position() {
		global $menu;

		add_menu_page( 'One', 'One', 'manage_options', 'one', '', '', 5 );
		add_menu_page( 'Two', 'Two', 'manage_options', 'two' );

		$this->assertSame( array( 5, 6 ), array_keys( $menu ), 'The menu should be added after the last one.' );
		$this->assertSame( 'two', $menu[6][2] );
	}

	/**
	 * Tests that a menu is added at its position.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_add_the_menu_at_its_position() {
		global $menu;

		add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug', '', '', 42 );

		$this->assertSame( array( 42 ), array_keys( $menu ) );
		$this->assertSame( 'the-slug', $menu[42][2] );
	}

	/**
	 * Tests that a menu does not replace the menu that has its position.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_not_replace_the_menu_at_its_position() {
		global $menu;

		add_menu_page( 'One', 'One', 'manage_options', 'one', '', '', 42 );
		add_menu_page( 'Two', 'Two', 'manage_options', 'two', '', '', 42 );

		$this->assertCount( 2, $menu, 'Both menus should be kept.' );
		$this->assertSame( 'one', $menu[42][2], 'The first menu should keep its position.' );

		$collision_avoider = (int) base_convert( substr( md5( 'twoTwo' ), -4 ), 16, 10 ) * 0.00001;
		$position          = (string) ( 42 + $collision_avoider );

		$this->assertArrayHasKey( $position, $menu, 'The second menu should be next to the first one.' );
		$this->assertSame( 'two', $menu[ $position ][2] );
	}

	/**
	 * Tests that a position that is not a number is reported and ignored.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_menu_page
	 */
	public function test_add_menu_page_should_report_a_position_that_is_not_a_number() {
		global $menu;

		$this->setExpectedIncorrectUsage( 'add_menu_page' );

		add_menu_page( 'The Page', 'The Menu', 'manage_options', 'the-slug', '', '', 'first' );

		$this->assertSame( array( 0 ), array_keys( $menu ), 'The menu should be added without a position.' );
	}

	/**
	 * Tests that a submenu of a menu that has none starts with the menu itself.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_start_with_the_parent_menu() {
		global $submenu;

		add_menu_page( 'Parent Page', 'Parent Menu', 'manage_options', 'parent-slug' );

		$this->assertSame( 'parent-menu_page_child-slug', add_submenu_page( 'parent-slug', 'Child Page', 'Child Menu', 'manage_options', 'child-slug' ), 'The hook name should be returned.' );
		$this->assertSame(
			array(
				array( 'Parent Menu', 'manage_options', 'parent-slug', 'Parent Page' ),
				array( 'Child Menu', 'manage_options', 'child-slug', 'Child Page' ),
			),
			$submenu['parent-slug']
		);
	}

	/**
	 * Tests that the menu is not added to the submenu of itself.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_not_repeat_the_parent_menu_for_its_own_slug() {
		global $submenu;

		add_menu_page( 'Parent Page', 'Parent Menu', 'manage_options', 'parent-slug' );
		add_submenu_page( 'parent-slug', 'Same Page', 'Same Menu', 'manage_options', 'parent-slug' );

		$this->assertSame( array( array( 'Same Menu', 'manage_options', 'parent-slug', 'Same Page' ) ), $submenu['parent-slug'] );
	}

	/**
	 * Tests that the slugs are made relative to the plugins directory.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_make_the_slugs_relative_to_the_plugins_directory() {
		global $submenu;

		add_submenu_page( WP_PLUGIN_DIR . '/my-plugin/parent.php', 'Child Page', 'Child Menu', 'manage_options', WP_PLUGIN_DIR . '/my-plugin/child.php' );

		$this->assertArrayHasKey( 'my-plugin/parent.php', $submenu );
		$this->assertSame( 'my-plugin/child.php', $submenu['my-plugin/parent.php'][0][2] );
	}

	/**
	 * Tests that the real file of a parent is used.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_use_the_real_file_of_the_parent() {
		global $submenu, $_wp_real_parent_file;

		$_wp_real_parent_file['alias.php'] = 'real.php';

		add_submenu_page( 'alias.php', 'Child Page', 'Child Menu', 'manage_options', 'child-slug' );

		$this->assertArrayHasKey( 'real.php', $submenu );
		$this->assertArrayNotHasKey( 'alias.php', $submenu );
	}

	/**
	 * Tests that a page is not added for a user who cannot access it, and that it is recorded.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_record_a_page_that_the_user_cannot_access() {
		global $submenu, $_wp_submenu_nopriv, $_wp_nopriv_pages;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( add_submenu_page( 'parent-slug', 'Child Page', 'Child Menu', 'manage_options', 'child-slug' ) );
		$this->assertSame( array(), $submenu, 'The submenu should not change.' );
		$this->assertSame( array( 'parent-slug' => array( 'child-slug' => true ) ), $_wp_submenu_nopriv );
		$this->assertSame( array( 'child-slug' => true ), $_wp_nopriv_pages );
	}

	/**
	 * Tests that the parent menu is not copied when the user cannot access it.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_not_copy_a_parent_menu_that_the_user_cannot_access() {
		global $submenu, $menu;

		$menu[] = array( 'Parent Menu', 'manage_network', 'parent-slug', 'Parent Page' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		get_userdata( get_current_user_id() )->add_cap( 'read_private_posts' );

		add_submenu_page( 'parent-slug', 'Child Page', 'Child Menu', 'read', 'child-slug' );

		$this->assertSame( array( array( 'Child Menu', 'read', 'child-slug', 'Child Page' ) ), $submenu['parent-slug'] );
	}

	/**
	 * Tests that a position that is not a number is reported and ignored.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_report_a_position_that_is_not_a_number() {
		global $submenu;

		$this->setExpectedIncorrectUsage( 'add_submenu_page' );

		add_submenu_page( 'parent-slug', 'One', 'One', 'manage_options', 'one' );
		add_submenu_page( 'parent-slug', 'Two', 'Two', 'manage_options', 'two', '', 'first' );

		$this->assertSame( array( 'one', 'two' ), wp_list_pluck( $submenu['parent-slug'], 2 ), 'The submenu should be added last.' );
	}

	/**
	 * Tests that a position at the start or below it adds the submenu first.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 *
	 * @dataProvider data_first_positions
	 *
	 * @param int $position The position.
	 */
	public function test_add_submenu_page_should_add_the_submenu_first_for_a_position_at_the_start( $position ) {
		global $submenu;

		add_submenu_page( 'parent-slug', 'One', 'One', 'manage_options', 'one' );
		add_submenu_page( 'parent-slug', 'Two', 'Two', 'manage_options', 'two' );
		add_submenu_page( 'parent-slug', 'First', 'First', 'manage_options', 'first', '', $position );

		$this->assertSame( array( 'first', 'one', 'two' ), wp_list_pluck( $submenu['parent-slug'], 2 ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_first_positions() {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -5 ),
		);
	}

	/**
	 * Tests that a position after the last submenu adds the submenu last.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_add_the_submenu_last_for_a_position_after_the_end() {
		global $submenu;

		add_submenu_page( 'parent-slug', 'One', 'One', 'manage_options', 'one' );
		add_submenu_page( 'parent-slug', 'Two', 'Two', 'manage_options', 'two', '', 99 );

		$this->assertSame( array( 'one', 'two' ), wp_list_pluck( $submenu['parent-slug'], 2 ) );
	}

	/**
	 * Tests the global lists of pages that the submenu is added to.
	 *
	 * @ticket 66557
	 *
	 * @covers ::add_submenu_page
	 */
	public function test_add_submenu_page_should_register_the_page() {
		global $_registered_pages, $_parent_pages;

		add_menu_page( 'Parent Page', 'Parent Menu', 'manage_options', 'parent-slug' );
		add_submenu_page( 'parent-slug', 'Child Page', 'Child Menu', 'manage_options', 'child-slug', '__return_true' );

		$this->assertSame( 'parent-slug', $_parent_pages['child-slug'], 'The parent of the page should be recorded.' );
		$this->assertArrayHasKey( 'toplevel_page_parent-slug', $_registered_pages );
		$this->assertArrayHasKey( 'parent-menu_page_child-slug', $_registered_pages, 'The page should be registered.' );
		$this->assertSame( 10, has_action( 'parent-menu_page_child-slug', '__return_true' ), 'The callback should be hooked.' );
	}
}
