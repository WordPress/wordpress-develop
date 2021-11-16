<?php

/**
 * @group user
 *
 * @covers ::wp_authenticate_username_password
 */
class Tests_User_WpAuthenticateUsernamePassword extends WP_UnitTestCase {
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

	public function test_returns_logged_in_user() {
		$actual = wp_authenticate_username_password( $this->admin_user, 'admin', 'password' );
		$this->assertInstanceOf( 'WP_User', $actual );
	}

	/**
	 * @dataProvider data_returns_wp_error
	 *
	 * @param WP_User|WP_Error|null $user      The user object, a WP Error or null. Default null.
	 * @param string                $username  The username to try to authenticate.
	 * @param string                $password  The password to try to authenticate.
	 * @param array                 $errors    An array of expected error keys.
	 */
	public function test_returns_wp_error( $user, $username, $password, $errors ) {
		$actual = wp_authenticate_username_password( $user, $username, $password );

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
			'a WP_Error object'              => array(
				'user'     => new WP_Error( 'custom_wp_error' ),
				'username' => 'admin1',
				'password' => 'password',
				'errors'   => array( 'custom_wp_error' ),
			),
			'no username'                    => array(
				'user'     => null,
				'username' => '',
				'password' => 'password',
				'errors'   => array( 'empty_username' ),
			),
			'no password'                    => array(
				'user'     => null,
				'username' => 'admin1',
				'password' => '',
				'errors'   => array( 'empty_password' ),
			),
			'no username or password'        => array(
				'user'     => null,
				'username' => '',
				'password' => '',
				'errors'   => array(
					'empty_username',
					'empty_password',
				),
			),
			'a username that does not exist' => array(
				'user'     => null,
				'username' => '1nimda',
				'password' => 'password',
				'errors'   => array( 'invalid_username' ),
			),
			'incorrect password'             => array(
				'user'     => null,
				'username' => 'admin1',
				'password' => 'password1',
				'errors'   => array( 'incorrect_password' ),
			),
		);
	}

	/**
	 * @ticket 46748
	 */
	public function test_filter_with_less_than_20_prohibits_logging_in() {
		$callback = static function() {
			return new WP_Error();
		};
		add_filter( 'wp_authenticate_user', $callback );

		$actual = wp_authenticate_username_password( null, 'admin1', 'password' );

		remove_filter( 'wp_authenticate_user', $callback );

		$this->assertInstanceOf( 'WP_Error', $actual );
	}

	public function test_returns_user_with_correct_username_and_password() {
		$actual = wp_authenticate_username_password( null, 'admin1', 'password' );
		$this->assertInstanceOf( 'WP_User', $actual );
	}

}
