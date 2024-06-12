<?php

/**
 * @group pluggable
 * @covers ::get_user_by
 */
class Tests_Pluggable_GetUserBy extends WP_UnitTestCase {

	/**
	 * @ticket 28020
	 */
	public function test_get_user_by_should_return_same_instance_as_wp_get_current_user() {
		// Create a test user.
		$new_user = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Set the test user as the current user.
		$current_user = wp_set_current_user( $new_user );

		// Get the test user using get_user_by().
		$from_get_user_by = get_user_by( 'id', $new_user );

		$this->assertSame( $current_user, $from_get_user_by );
	}
}
