<?php

/**
 * Tests for the determine_current_user filter.
 *
 * @group user
 * @group auth
 *
 * @covers ::wp_validate_auth_cookie
 */
class Tests_User_WpDetermineCurrentUser extends WP_UnitTestCase {

	/**
	 * Tests that determine_current_user filter callbacks with priority < 10 return their user ID
	 * without being overridden by wp_validate_auth_cookie.
	 *
	 * @ticket 28212
	 */
	public function test_determine_current_user_early_filter_priority_less_than_10() {
		$user_id = self::factory()->user->create();

		$callback = function ( $current_user_id ) use ( $user_id ) {
			return $user_id;
		};

		add_filter( 'determine_current_user', $callback, 5 );

		$determined_user_id = apply_filters( 'determine_current_user', false );

		remove_filter( 'determine_current_user', $callback, 5 );

		$this->assertSame( $user_id, $determined_user_id );
	}
}
