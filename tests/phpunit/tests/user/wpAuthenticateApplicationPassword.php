<?php

/**
 * @group user
 *
 * @covers ::wp_authenticate_application_password
 */
class Tests_User_WpAuthenticateApplicationPassword extends WP_UnitTestCase {
	protected $admin_user;

	public function set_up() {
		$this->admin_user = $this->factory->user->create_and_get(
			array(
				'role'       => 'administrator',
				'user_login' => 'admin1',
				'user_pass'  => 'password',
			)
		);
	}

	public function tear_down() {
		if ( $this->admin_user instanceof WP_User ) {
			if ( is_multisite() ) {
				wp_delete_user( $this->admin_user->data->ID );
			} else {
				wp_delete_user( $this->admin_user->ID );
			}
		}

		remove_filter(
			'wp_authenticate_user',
			array( $this, 'callback_returns_wp_error' )
		);
	}

	/**
	 * Tests that a WP_User object is returned for a user
	 * that is already logged in.
	 */
	public function test_returns_logged_in_user() {
		$actual = wp_authenticate_application_password( $this->admin_user, 'admin', 'password' );
		$this->assertInstanceOf( 'WP_User', $actual );
	}

	/**
	 * Tests that wp_authenticate_application_password returns a WP_Error object.
	 *
	 * @dataProvider data_returns_wp_error
	 *
	 * @param WP_User|WP_Error|null $user      The user object, a WP Error or null. Default null.
	 * @param string                $username  The username to try to authenticate.
	 * @param string                $password  The password to try to authenticate.
	 * @param array                 $errors    An array of expected error keys.
	 */
	public function test_returns_wp_error( $user, $username, $password, $errors ) {
		update_network_option( null, 'using_application_passwords', true );
		$actual = wp_authenticate_application_password( $user, $username, $password );

		$this->assertInstanceOf(
			'WP_Error',
			$actual,
			'Did not return an error.'
		);

		$this->assertSameSetsWithIndex(
			$errors,
			array_keys( $actual->errors ),
			'Did not return the expected errors.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_returns_wp_error() {
		return array(
			'a WP_Error object' => array(
				'user'     => new WP_Error( 'custom_wp_error' ),
				'username' => 'admin1',
				'password' => 'password',
				'errors'   => array( 'custom_wp_error' ),
			),
		);
	}

}
