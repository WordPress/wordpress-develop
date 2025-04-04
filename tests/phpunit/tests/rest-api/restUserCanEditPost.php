<?php

/**
 * Tests for the `wp_is_rest_endpoint()` function.
 *
 * @group restapi
 * @covers ::rest_user_can_edit_post
 */
class Tests_Rest_User_Can_Edit_Post extends WP_UnitTestCase {

	/**
	 * Tests that `rest_user_can_edit_post()` returns true for a user with edit_posts capability.
	 */
	public function test_rest_user_can_edit_post() {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( rest_user_can_edit_post() );
	}

	/**
	 * Tests that `rest_user_can_edit_post()` returns false for a user without edit_posts capability.
	 */
	public function test_rest_user_cannot_edit_post() {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

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
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$sub_role = get_role( 'subscriber' );
		$sub_role->add_cap( 'edit_post_baz' );
		wp_set_current_user( $user_id );

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
