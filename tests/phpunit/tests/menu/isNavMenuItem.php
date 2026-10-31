<?php

/**
 * Tests for the is_nav_menu_item() function.
 *
 * @group menu
 *
 * @covers ::is_nav_menu_item
 */
class Tests_Menu_IsNavMenuItem extends WP_UnitTestCase {

	/**
	 * Menu item ID used by the tests.
	 *
	 * @var int
	 */
	private static $menu_item_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$menu_id            = wp_create_nav_menu( 'Primary Menu' );
		self::$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-type'   => 'custom',
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);
	}

	/**
	 * Tests that a menu item is recognized by its ID.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_true_for_a_menu_item_id() {
		$this->assertTrue( is_nav_menu_item( self::$menu_item_id ) );
	}

	/**
	 * Tests that a menu item is recognized by its post object.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_true_for_a_menu_item_post_object() {
		$this->assertTrue( is_nav_menu_item( get_post( self::$menu_item_id ) ) );
	}

	/**
	 * Tests that posts of other types are not menu items.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_other_post_types
	 *
	 * @param string $post_type Post type.
	 */
	public function test_should_return_false_for_another_post_type( $post_type ) {
		$post_id = self::factory()->post->create( array( 'post_type' => $post_type ) );

		$this->assertFalse( is_nav_menu_item( $post_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_other_post_types() {
		return array(
			'post' => array( 'post' ),
			'page' => array( 'page' ),
		);
	}

	/**
	 * Tests that an ID with no post is not a menu item.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_a_nonexistent_id() {
		$this->assertFalse( is_nav_menu_item( PHP_INT_MAX ) );
	}

	/**
	 * Tests that a WP_Error, such as one returned by wp_update_nav_menu_item(),
	 * is not a menu item.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_for_a_wp_error() {
		$this->assertFalse( is_nav_menu_item( new WP_Error( 'update_nav_menu_item_failed' ) ) );
	}

	/**
	 * Tests that the default value checks the global post.
	 *
	 * @ticket 65819
	 */
	public function test_should_check_the_global_post_by_default() {
		$this->assertFalse( is_nav_menu_item(), 'Without a global post, there should be no menu item.' );

		$GLOBALS['post'] = get_post( self::$menu_item_id );

		$this->assertTrue( is_nav_menu_item(), 'A menu item as the global post should be a menu item.' );
	}

	/**
	 * Tests that a menu item stays a menu item while it is in the trash.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_true_for_a_trashed_menu_item() {
		$menu_item_id = wp_update_nav_menu_item(
			wp_create_nav_menu( 'Trash Menu' ),
			0,
			array(
				'menu-item-type'   => 'custom',
				'menu-item-title'  => 'About',
				'menu-item-url'    => home_url( '/about/' ),
				'menu-item-status' => 'publish',
			)
		);
		wp_trash_post( $menu_item_id );

		$this->assertTrue( is_nav_menu_item( $menu_item_id ) );
	}
}
