<?php

/**
 * @group user
 */
class Tests_User_WpAuthenticateSpamCheck extends WP_UnitTestCase {

	/**
	 * @group ms-excluded
	 */
	function test_wp_authenticate_spam_check_returns_user_when_single_site() {
		$user_id     = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$user        = new WP_User( $user_id );
		$actual_user = wp_authenticate_spam_check( $user );
		wp_delete_user( $user_id );

		$this->assertSame( $user->user_login, $actual_user->user_login );
	}

	/**
	 * @group ms-required
	 */
	function test_wp_authenticate_spam_check_returns_user_when_not_flagged() {
		$user_id     = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$user        = new WP_User( $user_id );
		$actual_user = wp_authenticate_spam_check( $user );
		wpmu_delete_user( $user_id );

		$this->assertSame( $user->user_login, $actual_user->user_login );
	}

	/**
	 * @group ms-required
	 */
	function test_wp_authenticate_spam_check_returns_wp_error_when_flagged() {
		$user_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_update_user(
			array(
				'ID'   => $user_id,
				'spam' => '1',
			)
		);

		$user        = new WP_User( $user_id );
		$actual_user = wp_authenticate_spam_check( $user );
		wpmu_delete_user( $user_id );

		$this->assertInstanceOf( 'WP_Error', $actual_user );
	}
}
