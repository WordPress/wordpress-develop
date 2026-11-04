<?php

/**
 * Tests for the has_nav_menu() function.
 *
 * @group menu
 *
 * @covers ::has_nav_menu
 */
class Tests_Menu_HasNavMenu extends WP_UnitTestCase {

	/**
	 * Menu ID used by the tests.
	 *
	 * @var int
	 */
	private static $menu_id;

	/**
	 * Menu locations registered before the test.
	 *
	 * @var array
	 */
	private $original_nav_menus;

	/**
	 * Theme features before the test: register_nav_menu() adds menu support.
	 *
	 * @var array
	 */
	private $original_theme_features;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$menu_id = wp_create_nav_menu( 'Primary Menu' );
	}

	public function set_up() {
		global $_wp_registered_nav_menus, $_wp_theme_features;

		parent::set_up();

		$this->original_nav_menus      = $_wp_registered_nav_menus;
		$this->original_theme_features = $_wp_theme_features;

		register_nav_menu( 'primary', 'Primary' );
	}

	public function tear_down() {
		global $_wp_registered_nav_menus, $_wp_theme_features;

		try {
			parent::tear_down();
		} finally {
			// Restore the registrations and theme support exactly as they were,
			// including any this test overwrote or added.
			$_wp_registered_nav_menus = $this->original_nav_menus;
			$_wp_theme_features       = $this->original_theme_features;
		}
	}

	/**
	 * Tests that a registered location with a menu assigned has a menu.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_true_for_a_registered_location_with_a_menu() {
		set_theme_mod( 'nav_menu_locations', array( 'primary' => self::$menu_id ) );

		$this->assertTrue( has_nav_menu( 'primary' ) );
	}

	/**
	 * Tests that a location set to 0, as when its menu is unassigned, has no menu.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_a_location_whose_menu_was_unassigned() {
		set_theme_mod( 'nav_menu_locations', array( 'primary' => 0 ) );

		$this->assertFalse( has_nav_menu( 'primary' ) );
	}

	/**
	 * Tests that an assignment is ignored when its location is not registered,
	 * such as one left behind by a previously active theme.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_an_assigned_location_that_is_not_registered() {
		set_theme_mod( 'nav_menu_locations', array( 'old-theme-location' => self::$menu_id ) );

		$this->assertFalse( has_nav_menu( 'old-theme-location' ) );
	}

	/**
	 * Tests that each location is checked on its own.
	 *
	 * @ticket 65819
	 */
	public function test_should_check_each_location_separately() {
		register_nav_menu( 'footer', 'Footer' );
		set_theme_mod( 'nav_menu_locations', array( 'footer' => self::$menu_id ) );

		$this->assertTrue( has_nav_menu( 'footer' ), 'The footer location should have a menu.' );
		$this->assertFalse( has_nav_menu( 'primary' ), 'The primary location should not have a menu.' );
	}

	/**
	 * Tests that the `has_nav_menu` filter receives the result and the location,
	 * and that its return value is used.
	 *
	 * @ticket 65819
	 */
	public function test_should_apply_the_has_nav_menu_filter() {
		$filter = new MockAction();
		add_filter( 'has_nav_menu', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'has_nav_menu', '__return_true', 11 );

		$this->assertTrue( has_nav_menu( 'primary' ), 'The filtered value should be returned.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The filter should run once.' );
		$this->assertSame( array( false, 'primary' ), $filter->get_args()[0], 'The filter should receive the unfiltered result and the location.' );
	}
}
