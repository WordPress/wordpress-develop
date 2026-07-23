<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_activate_signup
 */
class Tests_Multisite_wpmuActivateSignup extends WP_UnitTestCase {

	/**
	 * @ticket 42389
	 */
	public function test_should_not_return_freshly_generated_password_for_existing_user_activating_additional_site() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'existinguser42389' ) );

		$result = $this->signup_and_activate_blog_for( 'existinguser42389', '/existingusersite42389/' );

		$this->assertNotWPError( $result );
		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertTrue( $result['user_already_exists'] );
		$this->assertSame( '', $result['password'] );
	}

	/**
	 * @ticket 42389
	 */
	public function test_should_return_generated_password_for_new_user_activating_a_site() {
		$result = $this->signup_and_activate_blog_for( 'newuser42389', '/newusersite42389/' );

		$this->assertNotWPError( $result );
		$this->assertFalse( $result['user_already_exists'] );
		$this->assertNotEmpty( $result['password'] );
	}

	/**
	 * Signs up and activates a new site for the given user login, returning the
	 * activation result.
	 *
	 * @param string $user_login User login to sign up the site for.
	 * @param string $path       Site path, used to keep signups from different
	 *                           tests from colliding.
	 * @return array|WP_Error The return value of wpmu_activate_signup().
	 */
	private function signup_and_activate_blog_for( $user_login, $path ) {
		global $wpdb;

		add_filter( 'wpmu_signup_blog_notification', '__return_false' );

		wpmu_signup_blog( WP_TESTS_DOMAIN, $path, 'A Test Site', $user_login, "{$user_login}@example.com" );

		$key = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT activation_key FROM $wpdb->signups WHERE user_login = %s AND path = %s",
				$user_login,
				$path
			)
		);

		$result = wpmu_activate_signup( $key );

		remove_filter( 'wpmu_signup_blog_notification', '__return_false' );

		return $result;
	}
}
