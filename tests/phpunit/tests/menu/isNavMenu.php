<?php

/**
 * Tests for the is_nav_menu() function.
 *
 * @group menu
 *
 * @covers ::is_nav_menu
 */
class Tests_Menu_IsNavMenu extends WP_UnitTestCase {

	/**
	 * Menu term used by the tests.
	 *
	 * @var WP_Term
	 */
	private static $menu;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$menu_id    = wp_create_nav_menu( 'Primary Menu' );
		self::$menu = get_term( $menu_id, 'nav_menu' );
	}

	/**
	 * Tests that a menu is found by its ID, slug, name, or term object.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_ways_to_identify_a_menu
	 *
	 * @param string $field The menu field to identify the menu by.
	 */
	public function test_should_return_true_for_an_existing_menu( $field ) {
		$menu = 'object' === $field ? self::$menu : self::$menu->$field;

		$this->assertTrue( is_nav_menu( $menu ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ways_to_identify_a_menu() {
		return array(
			'ID'          => array( 'term_id' ),
			'slug'        => array( 'slug' ),
			'name'        => array( 'name' ),
			'term object' => array( 'object' ),
		);
	}

	/**
	 * Tests that a menu ID passed as a numeric string is found.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_true_for_a_numeric_string_id() {
		$this->assertTrue( is_nav_menu( (string) self::$menu->term_id ) );
	}

	/**
	 * Tests that empty values return false.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_values
	 *
	 * @param mixed $menu An empty value.
	 */
	public function test_should_return_false_for_an_empty_value( $menu ) {
		$this->assertFalse( is_nav_menu( $menu ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_values() {
		return array(
			'zero'         => array( 0 ),
			'string zero'  => array( '0' ),
			'empty string' => array( '' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}

	/**
	 * Tests that values that do not match a menu return false.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unknown_menus
	 *
	 * @param int|string $menu A value that does not identify a menu.
	 */
	public function test_should_return_false_for_an_unknown_menu( $menu ) {
		$this->assertFalse( is_nav_menu( $menu ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unknown_menus() {
		return array(
			'ID'   => array( PHP_INT_MAX ),
			'slug' => array( 'no-such-menu' ),
			'name' => array( 'No Such Menu' ),
		);
	}

	/**
	 * Tests that a term from another taxonomy is not a menu, whether it is
	 * passed by ID, slug, or as a term object.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_a_term_from_another_taxonomy() {
		$category = self::factory()->category->create_and_get( array( 'slug' => 'not-a-menu' ) );

		$this->assertFalse( is_nav_menu( $category->term_id ), 'A category ID should not be a menu.' );
		$this->assertFalse( is_nav_menu( $category->slug ), 'A category slug should not be a menu.' );
		$this->assertFalse( is_nav_menu( $category ), 'A category term object should not be a menu.' );
	}

	/**
	 * Tests that a menu is no longer found once it is deleted.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_a_deleted_menu() {
		$menu_id = wp_create_nav_menu( 'Temporary Menu' );
		wp_delete_nav_menu( $menu_id );

		$this->assertFalse( is_nav_menu( $menu_id ) );
	}

	/**
	 * Tests that the result follows the `wp_get_nav_menu_object` filter.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_when_the_menu_object_is_filtered_out() {
		add_filter( 'wp_get_nav_menu_object', '__return_false' );

		$this->assertFalse( is_nav_menu( self::$menu->term_id ) );
	}
}
