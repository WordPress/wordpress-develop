<?php
/**
 * Test cases for the `retrieve_password()` function.
 *
 * @package WordPress
 * @since 6.0.0
 *
 * @group user
 * @covers ::retrieve_password
 */
class Tests_User_RetrievePassword extends WP_UnitTestCase {
	/**
	 * Test user.
	 *
	 * @since 6.0.0
	 *
	 * @var WP_User $user
	 */
	protected $user;

	/**
	 * The message body of the password reset email captured by the test.
	 *
	 * @since 7.2.0
	 *
	 * @var string
	 */
	protected $captured_message = '';

	/**
	 * Create users for tests.
	 *
	 * @since 6.0.0
	 */
	public function set_up() {
		parent::set_up();

		$this->captured_message = '';

		// Create the user.
		$this->user = self::factory()->user->create_and_get(
			array(
				'user_login' => 'jane',
				'user_email' => 'r.jane@example.com',
			)
		);
	}

	/**
	 * Removes the filters and actions added by these tests.
	 *
	 * @since 7.2.0
	 */
	public function tear_down() {
		remove_all_filters( 'reset_password_url' );
		remove_filter( 'retrieve_password_notification_email', array( $this, 'capture_retrieve_password_notification_email' ) );
		remove_all_actions( 'retrieve_password_key' );

		parent::tear_down();
	}

	/**
	 * Captures the message body of the password reset email.
	 *
	 * Used as the callback of the `retrieve_password_notification_email` filter.
	 *
	 * @since 7.2.0
	 *
	 * @param array $defaults The default notification email arguments.
	 * @return array The unmodified notification email arguments.
	 */
	public function capture_retrieve_password_notification_email( $defaults ) {
		$this->captured_message = $defaults['message'];

		return $defaults;
	}

	/**
	 * The function should not error when the email was sent.
	 *
	 * @ticket 54690
	 */
	public function test_retrieve_password_reset_notification_email() {
		$this->assertNotWPError( retrieve_password( $this->user->user_login ), 'Sending password reset notification email failed.' );
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @ticket 54690
	 */
	public function test_retrieve_password_should_return_wp_error_on_failed_email() {
		add_filter(
			'retrieve_password_notification_email',
			static function () {
				return array( 'message' => '' );
			}
		);

		$this->assertWPError( retrieve_password( $this->user->user_login ), 'Sending password reset notification email succeeded.' );
	}

	/**
	 * @ticket 53634
	 */
	public function test_retrieve_password_should_fetch_user_by_login_if_not_found_by_email() {
		self::factory()->user->create(
			array(
				'user_login' => 'foo@example.com',
				'user_email' => 'bar@example.com',
			)
		);

		$this->assertTrue( retrieve_password( 'foo@example.com' ), 'Fetching user by login failed.' );
		$this->assertTrue( retrieve_password( 'bar@example.com' ), 'Fetching user by email failed.' );
	}

	/**
	 * Tests that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when the `$user_login` parameter is empty.
	 *
	 * The notice that we should not see:
	 * `Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated`.
	 *
	 * @ticket 62298
	 */
	public function test_retrieve_password_does_not_throw_deprecation_notice_with_default_parameters() {
		$this->assertWPError( retrieve_password() );
	}

	/**
	 * Tests that the `reset_password_url` filter replaces the reset URL in the email.
	 *
	 * @ticket 34712
	 */
	public function test_retrieve_password_should_apply_reset_password_url_filter() {
		$custom_url = 'https://example.org/custom-reset-page/';

		add_filter(
			'reset_password_url',
			static function () use ( $custom_url ) {
				return $custom_url;
			}
		);
		add_filter( 'retrieve_password_notification_email', array( $this, 'capture_retrieve_password_notification_email' ) );

		$this->assertTrue( retrieve_password( $this->user->user_login ), 'Sending the password reset notification email failed.' );
		$this->assertStringContainsString(
			$custom_url,
			$this->captured_message,
			'The custom password reset URL was not used in the email message.'
		);
		$this->assertStringNotContainsString(
			'wp-login.php?login=',
			$this->captured_message,
			'The default password reset URL was still used in the email message.'
		);
	}

	/**
	 * Tests that the `reset_password_url` filter receives the expected arguments.
	 *
	 * @ticket 34712
	 */
	public function test_retrieve_password_should_pass_expected_arguments_to_reset_password_url_filter() {
		$filter_args         = array();
		$generated_key       = '';
		$expected_user_id    = $this->user->ID;
		$expected_user_login = $this->user->user_login;

		add_action(
			'retrieve_password_key',
			static function ( $user_login, $key ) use ( &$generated_key ) {
				$generated_key = $key;
			},
			10,
			2
		);
		add_filter(
			'reset_password_url',
			static function ( $reset_url, $user_login, $key, $user_data ) use ( &$filter_args ) {
				$filter_args = array(
					'reset_url'  => $reset_url,
					'user_login' => $user_login,
					'key'        => $key,
					'user_data'  => $user_data,
				);

				return $reset_url;
			},
			10,
			4
		);

		$this->assertTrue( retrieve_password( $this->user->user_login ), 'Sending the password reset notification email failed.' );

		$this->assertNotEmpty( $generated_key, 'The password reset key was not generated.' );
		$this->assertSame( $expected_user_login, $filter_args['user_login'], 'The user login passed to the filter is incorrect.' );
		$this->assertIsString( $filter_args['key'], 'The activation key passed to the filter is not a string.' );
		$this->assertNotEmpty( $filter_args['key'], 'The activation key passed to the filter is empty.' );
		$this->assertSame( $generated_key, $filter_args['key'], 'The activation key passed to the filter is incorrect.' );
		$this->assertInstanceOf( WP_User::class, $filter_args['user_data'], 'The user data passed to the filter is not a WP_User object.' );
		$this->assertSame( $expected_user_id, $filter_args['user_data']->ID, 'The user ID passed to the filter is incorrect.' );

		$expected_default_url = network_site_url( 'wp-login.php?login=' . rawurlencode( $expected_user_login ) . "&key=$generated_key&action=rp", 'login' );

		$this->assertSame( $expected_default_url, $filter_args['reset_url'], 'The URL passed to the filter is not the default password reset URL.' );
	}

	/**
	 * Tests that the default reset URL, including the `wp_lang` query arg, is unchanged
	 * when the `reset_password_url` filter is not registered.
	 *
	 * @ticket 34712
	 */
	public function test_retrieve_password_should_keep_default_reset_url_when_unfiltered() {
		$generated_key = '';

		add_action(
			'retrieve_password_key',
			static function ( $user_login, $key ) use ( &$generated_key ) {
				$generated_key = $key;
			},
			10,
			2
		);
		add_filter( 'retrieve_password_notification_email', array( $this, 'capture_retrieve_password_notification_email' ) );

		$this->assertTrue( retrieve_password( $this->user->user_login ), 'Sending the password reset notification email failed.' );

		$this->assertNotEmpty( $generated_key, 'The password reset key was not generated.' );

		$default_url = network_site_url( 'wp-login.php?login=' . rawurlencode( $this->user->user_login ) . "&key=$generated_key&action=rp", 'login' );
		$locale      = get_user_locale( $this->user );

		$this->assertStringContainsString( 'wp-login.php?login=', $this->captured_message, 'The default password reset URL is missing from the email message.' );
		$this->assertStringContainsString( 'action=rp', $this->captured_message, 'The default password reset URL is missing the action argument.' );
		$this->assertStringContainsString(
			$default_url . '&wp_lang=' . $locale . "\r\n\r\n",
			$this->captured_message,
			'The default password reset URL and locale are not intact in the email message.'
		);
	}

	/**
	 * Tests that a fatal error is not thrown when the login passed via `$_POST`
	 * is an array instead of a string.
	 *
	 * The message that we should not see:
	 * `TypeError: trim(): Argument #1 ($string) must be of type string, array given`.
	 *
	 * @ticket 62794
	 */
	public function test_retrieve_password_does_not_throw_fatal_error_with_array_parameters() {
		$_POST['user_login'] = array( 'example' );

		$error = retrieve_password();
		$this->assertWPError( $error, 'The result should be an instance of WP_Error.' );

		$error_codes = $error->get_error_codes();
		$this->assertContains( 'empty_username', $error_codes, 'The "empty_username" error code should be present.' );
	}
}
