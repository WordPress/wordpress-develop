<?php

/**
 * Tests the `user_nicename_exists` function.
 *
 * @group user
 *
 * @covers ::user_nicename_exists
 */
class Tests_User_Nicename_Exists extends WP_UnitTestCase {

	/**
	 * Tests that `user_nicename_exists` returns the user ID when the nicename exists.
	 *
	 * @ticket 44921
	 */
	public function test_user_nicename_exists_with_existing_nicename() {
		$user_id = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );

		$this->assertSame( $user_id, user_nicename_exists( 'test-nicename' ) );
	}

	/**
	 * Tests that `user_nicename_exists` returns false when the nicename does not exist.
	 *
	 * @ticket 44921
	 */
	public function test_user_nicename_exists_with_nonexistent_nicename() {
		$this->assertFalse( user_nicename_exists( 'nonexistent-nicename' ) );
	}

	/**
	 * Tests that `user_nicename_exists` returns false when the nicename exists but belongs to a different user.
	 *
	 * @ticket 44921
	 */
	public function test_user_nicename_exists_with_different_user_login() {
		$user_id_1 = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );
		$user_id_2 = $this->factory()->user->create();
		$user_2    = get_user_by( 'id', $user_id_2 );

		$this->assertSame( $user_id_1, user_nicename_exists( 'test-nicename', $user_2->user_login ) );
	}

	/**
	 * Tests that `user_nicename_exists` returns false when the nicename exists but belongs to a different user.
	 *
	 * @ticket 44921
	 */
	public function test_user_nicename_exists_with_same_user_login() {
		$user_id_1 = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );
		$user_1    = get_user_by( 'id', $user_id_1 );

		$this->assertFalse( user_nicename_exists( 'test-nicename', $user_1->user_login ) );
	}
}
