<?php

/**
 * Tests for the `wp_is_rest_endpoint()` function.
 *
 * @group restapi
 * @covers ::rest_user_can_edit_post
 */
class Tests_Rest_User_Can_Edit_Post extends WP_UnitTestCase {

	protected static $editor_id;
	protected static $sub_id;
	protected static $private_role;
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$sub_id    = $factory->user->create( array( 'role' => 'subscriber' ) );

		add_role( 'private_reader', 'Private Reader' );
		$role = get_role( 'private_reader' );
		$role->add_cap( 'edit_post_baz' );
		self::$private_role = $factory->user->create( array( 'role' => 'private_reader' ) );
	}

	public static function wpTearDownAfterClass() {
		remove_role( 'private_reader' );

		self::delete_user( self::$editor_id );
		self::delete_user( self::$sub_id );
		self::delete_user( self::$private_role );
	}

	/**
	 * Tests that `rest_user_can_edit_post()` returns true for a user with edit_posts capability.
	 */
	public function test_rest_user_can_edit_post() {
		wp_set_current_user( self::$editor_id );

		$this->assertTrue( rest_user_can_edit_post() );
	}

	/**
	 * Tests that `rest_user_can_edit_post()` returns false for a user without edit_posts capability.
	 */
	public function test_rest_user_cannot_edit_post() {
		wp_set_current_user( self::$sub_id );

		$this->assertFalse( rest_user_can_edit_post() );
	}

	/**
	 * Tests that `rest_user_can_edit_post()` returns false when no user is set.
	 *
	 * @ticket 42061
	 */
	public function test_rest_user_no_edit_post() {
		$this->assertFalse( rest_user_can_edit_post() );
	}

	public function test_rest_user_can_edit_custom_type() {
		wp_set_current_user( self::$private_role );

		// Create a custom post type and check if the user can edit it.
		register_post_type(
			'custom_type',
			array(
				'show_in_rest' => true,
				'capabilities' => array(
					'edit_posts' => 'edit_post_baz',
				),
			)
		);
		$this->assertTrue( rest_user_can_edit_post() );
	}
}
