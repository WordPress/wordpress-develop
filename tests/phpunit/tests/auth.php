<?php

/**
 * @group pluggable
 * @group auth
 */
class Tests_Auth extends WP_UnitTestCase {
	// Class User values assigned to constants.
	const USER_EMAIL = 'test@password.com';
	const USER_LOGIN = 'password-user';
	const USER_PASS  = 'password';

	/**
	 * @var WP_User
	 */
	protected $user;

	/**
	 * @var WP_User
	 */
	protected static $_user;

	/**
	 * @var int
	 */
	protected static $user_id;

	/**
	 * @var PasswordHash
	 */
	protected static $wp_hasher;

	protected static $bcrypt_length_limit = 72;

	protected static $phpass_length_limit = 4096;

	protected static $password_length_limit = 4096;

	/**
	 * Action hook.
	 */
	protected $nonce_failure_hook = 'wp_verify_nonce_failed';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$_user = $factory->user->create_and_get(
			array(
				'user_login' => self::USER_LOGIN,
				'user_email' => self::USER_EMAIL,
				'user_pass'  => self::USER_PASS,
			)
		);

		self::$user_id = self::$_user->ID;

		require_once ABSPATH . WPINC . '/class-phpass.php';
		self::$wp_hasher = new PasswordHash( 8, true );
	}

	public function set_up() {
		parent::set_up();

		$this->user = clone self::$_user;
		wp_set_current_user( self::$user_id );
		update_site_option( 'using_application_passwords', 1 );

		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $GLOBALS['wp_rest_application_password_status'], $GLOBALS['wp_rest_application_password_uuid'] );
	}

	public function tear_down() {
		// Cleanup all the global state.
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $GLOBALS['wp_rest_application_password_status'], $GLOBALS['wp_rest_application_password_uuid'] );

		// Cleanup manual auth cookie test.
		unset( $_COOKIE[ AUTH_COOKIE ] );
		unset( $_COOKIE[ SECURE_AUTH_COOKIE ] );

		parent::tear_down();
	}

	public function test_auth_cookie_valid() {
		$cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'auth' );
		$this->assertSame( self::$user_id, wp_validate_auth_cookie( $cookie, 'auth' ) );
	}

	public function test_auth_cookie_invalid() {
		// 3600 or less and +3600 may occur in wp_validate_auth_cookie(),
		// as an ajax test may have defined DOING_AJAX, failing the test.

		$cookie = wp_generate_auth_cookie( self::$user_id, time() - 7200, 'auth' );
		$this->assertFalse( wp_validate_auth_cookie( $cookie, 'auth' ), 'expired cookie' );

		$cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'auth' );
		$this->assertFalse( wp_validate_auth_cookie( $cookie, 'logged_in' ), 'wrong auth scheme' );

		$cookie          = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'auth' );
		list($a, $b, $c) = explode( '|', $cookie );
		$cookie          = $a . '|' . ( $b + 1 ) . '|' . $c;
		$this->assertFalse( wp_validate_auth_cookie( self::$user_id, 'auth' ), 'altered cookie' );
	}

	public function test_auth_cookie_scheme() {
		// Arbitrary scheme name.
		$cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'foo' );
		$this->assertSame( self::$user_id, wp_validate_auth_cookie( $cookie, 'foo' ) );

		// Wrong scheme name - should fail.
		$cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'foo' );
		$this->assertFalse( wp_validate_auth_cookie( $cookie, 'bar' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_auth_cookie_generated_with_phpass_hash_remains_valid() {
		self::set_user_password_with_phpass( 'password', self::$user_id );

		$auth_cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'auth' );

		$this->assertSame( self::$user_id, wp_validate_auth_cookie( $auth_cookie, 'auth' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_auth_cookie_generated_with_plain_bcrypt_hash_remains_valid() {
		self::set_user_password_with_plain_bcrypt( 'password', self::$user_id );

		$auth_cookie = wp_generate_auth_cookie( self::$user_id, time() + 3600, 'auth' );

		$this->assertSame( self::$user_id, wp_validate_auth_cookie( $auth_cookie, 'auth' ) );
	}

	/**
	 * @ticket 23494
	 * @dataProvider data_passwords_for_trimming
	 */
	public function test_password_trimming( $password_to_test ) {
		wp_set_password( $password_to_test, $this->user->ID );
		$authed_user = wp_authenticate( $this->user->user_login, $password_to_test );

		$this->assertNotWPError( $authed_user );
		$this->assertInstanceOf( 'WP_User', $authed_user );
		$this->assertSame( $this->user->ID, $authed_user->ID );
	}

	public function data_passwords_for_trimming() {
		return array(
			'no spaces'                => array( 'a password with no trailing or leading spaces' ),
			'trailing space'           => array( 'a password with trailing spaces ' ),
			'leading space'            => array( ' a password with leading spaces' ),
			'leading and trailing'     => array( ' a password with trailing and leading spaces ' ),
			'multiple leading spaces'  => array( '    a password with multiple leading spaces' ),
			'multiple trailing spaces' => array( 'a password with multiple trailing spaces    ' ),
		);
	}

	/**
	 * Tests hooking into wp_set_password().
	 *
	 * @ticket 57436
	 * @ticket 61541
	 *
	 * @covers ::wp_set_password
	 */
	public function test_wp_set_password_action() {
		$action = new MockAction();

		$previous_user_pass = get_user_by( 'id', $this->user->ID )->user_pass;

		add_action( 'wp_set_password', array( $action, 'action' ), 10, 3 );
		wp_set_password( 'A simple password', $this->user->ID );

		$this->assertSame( 1, $action->get_call_count() );

		// Check that the old data passed through the hook is correct.
		$this->assertSame( $previous_user_pass, $action->get_args()[0][2]->user_pass );
	}

	/**
	 * Test wp_hash_password trims whitespace
	 *
	 * This is similar to test_password_trimming but tests the "lower level"
	 * wp_hash_password function
	 *
	 * @ticket 24973
	 * @dataProvider data_passwords_with_whitespace
	 */
	public function test_wp_hash_password_trimming( $password_with_whitespace, $expected_password ) {
		$this->assertTrue( wp_check_password( $expected_password, wp_hash_password( $password_with_whitespace ) ) );
	}

	public function data_passwords_with_whitespace() {
		return array(
			'leading whitespace'  => array( ' pass with leading whitespace', 'pass with leading whitespace' ),
			'trailing whitespace' => array( 'pass with trailing whitespace ', 'pass with trailing whitespace' ),
			'both whitespace'     => array( ' pass with whitespace ', 'pass with whitespace' ),
			'new line'            => array( "pass with new line \n", 'pass with new line' ),
			'vertical tab'        => array( "pass with vertical tab o_O\x0B", 'pass with vertical tab o_O' ),
		);
	}

	/**
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_phpass_hash() {
		$password = 'password';
		$hash     = self::$wp_hasher->HashPassword( $password );
		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * Ensure wp_check_password() remains compatible with an increase to the default bcrypt cost.
	 *
	 * The test verifies this by reducing the cost used to generate the hash, therefore mimicing a hash
	 * which was generated prior to the default cost being increased.
	 *
	 * Notably the bcrypt cost was increased in PHP 8.4: https://wiki.php.net/rfc/bcrypt_cost_2023 .
	 *
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_hash_with_increased_bcrypt_cost() {
		$password = 'password';

		// Reducing the cost mimics an increase to the default cost.
		add_filter( 'wp_hash_password_options', array( $this, 'reduce_hash_cost' ) );
		$hash = wp_hash_password( $password, PASSWORD_BCRYPT );
		remove_filter( 'wp_hash_password_options', array( $this, 'reduce_hash_cost' ) );

		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );
	}

	/**
	 * Ensure wp_check_password() remains compatible with a reduction of the default bcrypt cost.
	 *
	 * The test verifies this by increasing the cost used to generate the hash, therefore mimicing a hash
	 * which was generated prior to the default cost being reduced.
	 *
	 * A reduction of the cost is unlikely to occur but is fully supported.
	 *
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_hash_with_reduced_bcrypt_cost() {
		$password = 'password';

		// Increasing the cost mimics a reduction of the default cost.
		add_filter( 'wp_hash_password_options', array( $this, 'increase_hash_cost' ) );
		$hash = wp_hash_password( $password, PASSWORD_BCRYPT );
		remove_filter( 'wp_hash_password_options', array( $this, 'increase_hash_cost' ) );

		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_wp_hash_with_default_bcrypt_cost() {
		$password = 'password';

		$hash = wp_hash_password( $password, PASSWORD_BCRYPT );

		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
		$this->assertFalse( wp_password_needs_rehash( $hash ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_plain_bcrypt_hash_with_default_bcrypt_cost() {
		$password = 'password';

		$hash = password_hash( $password, PASSWORD_BCRYPT );

		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );
	}

	/**
	 * Ensure wp_check_password() is compatible with Argon2i hashes.
	 *
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_argon2i_hash() {
		if ( ! defined( 'PASSWORD_ARGON2I' ) ) {
			$this->markTestSkipped( 'Argon2i is not supported.' );
		}

		$password = 'password';
		$hash     = password_hash( trim( $password ), PASSWORD_ARGON2I );
		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * Ensure wp_check_password() is compatible with Argon2id hashes.
	 *
	 * @requires PHP >= 7.3
	 *
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_argon2id_hash() {
		if ( ! defined( 'PASSWORD_ARGON2ID' ) ) {
			$this->markTestSkipped( 'Argon2id is not supported.' );
		}

		$password = 'password';
		$hash     = password_hash( trim( $password ), PASSWORD_ARGON2ID );
		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_wp_check_password_supports_md5_hash() {
		$password = 'password';
		$hash     = md5( $password );
		$this->assertTrue( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_wp_check_password_does_not_support_plain_text() {
		$password = 'password';
		$hash     = $password;
		$this->assertFalse( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * @ticket 21022
	 *
	 * @dataProvider data_empty_values
	 * @param mixed $value
	 */
	public function test_wp_check_password_does_not_support_empty_hash( $value ) {
		$password = 'password';
		$hash     = $value;
		$this->assertFalse( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	/**
	 * @ticket 21022
	 *
	 * @dataProvider data_empty_values
	 * @param mixed $value
	 */
	public function test_wp_check_password_does_not_support_empty_password( $value ) {
		$password = $value;
		$hash     = $value;
		$this->assertFalse( wp_check_password( $password, $hash ) );
		$this->assertSame( 1, did_filter( 'check_password' ) );
	}

	public function data_empty_values() {
		return array(
			// String zero:
			array( '0' ),
			// Zero-length string:
			array( '' ),
			// Null byte character:
			array( "\0" ),
			// Asterisk values:
			array( '*' ),
			array( '*0' ),
			array( '*1' ),
		);
	}

	/**
	 * @ticket 29217
	 */
	public function test_wp_verify_nonce_with_empty_arg() {
		$this->assertFalse( wp_verify_nonce( '' ) );
		$this->assertFalse( wp_verify_nonce( null ) );
	}

	/**
	 * @ticket 29542
	 */
	public function test_wp_verify_nonce_with_integer_arg() {
		$this->assertFalse( wp_verify_nonce( 1 ) );
	}

	/**
	 * @ticket 24030
	 */
	public function test_wp_nonce_verify_failed() {
		$nonce = substr( md5( uniqid() ), 0, 10 );
		$count = did_action( $this->nonce_failure_hook );

		wp_verify_nonce( $nonce, 'nonce_test_action' );

		$this->assertSame( ( $count + 1 ), did_action( $this->nonce_failure_hook ) );
	}

	/**
	 * @ticket 24030
	 */
	public function test_wp_nonce_verify_success() {
		$nonce = wp_create_nonce( 'nonce_test_action' );
		$count = did_action( $this->nonce_failure_hook );

		wp_verify_nonce( $nonce, 'nonce_test_action' );

		$this->assertSame( $count, did_action( $this->nonce_failure_hook ) );
	}

	/**
	 * @ticket 36361
	 */
	public function test_check_admin_referer_with_no_action_triggers_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'check_admin_referer' );

		// A valid nonce needs to be set so the check doesn't die().
		$_REQUEST['_wpnonce'] = wp_create_nonce( -1 );
		$result               = check_admin_referer();
		$this->assertSame( 1, $result );

		unset( $_REQUEST['_wpnonce'] );
	}

	public function test_check_admin_referer_with_default_action_as_string_not_doing_it_wrong() {
		// A valid nonce needs to be set so the check doesn't die().
		$_REQUEST['_wpnonce'] = wp_create_nonce( '-1' );
		$result               = check_admin_referer( '-1' );
		$this->assertSame( 1, $result );

		unset( $_REQUEST['_wpnonce'] );
	}

	/**
	 * @ticket 36361
	 */
	public function test_check_ajax_referer_with_no_action_triggers_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'check_ajax_referer' );

		// A valid nonce needs to be set so the check doesn't die().
		$_REQUEST['_wpnonce'] = wp_create_nonce( -1 );
		$result               = check_ajax_referer();
		$this->assertSame( 1, $result );

		unset( $_REQUEST['_wpnonce'] );
	}

	/**
	 * @ticket 21022
	 */
	public function test_password_is_hashed_with_bcrypt() {
		$password = 'password';

		// Set the user password.
		wp_set_password( $password, self::$user_id );

		// Ensure the password is hashed with bcrypt.
		$this->assertStringStartsWith( '$wp$2y$', get_userdata( self::$user_id )->user_pass );

		// Authenticate.
		$user = wp_authenticate( $this->user->user_login, $password );

		// Verify correct password.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	public function data_passwords(): array {
		return array(
			array( 'a' ),
			array( 'password' ),
			array( str_repeat( 'a', self::$password_length_limit ) ),
		);
	}

	/**
	 * Ensure the hash of the user password remains less than 64 characters in length to account for the old users table schema.
	 *
	 * @ticket 21022
	 * @dataProvider data_passwords
	 */
	public function test_user_password_against_old_users_table_schema( string $password ) {
		// Mimic the schema of the users table prior to WordPress 4.4.
		add_filter( 'wp_pre_insert_user_data', array( $this, 'mimic_users_schema_prior_to_44' ) );

		$username = 'old-schema-user';

		// Create a user.
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => $username,
				'user_email' => 'old-schema-user@example.com',
				'user_pass'  => $password,
			)
		);

		// Check the user can authenticate.
		$user = wp_authenticate( $username, $password );

		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( $user_id, $user->ID, 'User should be able to authenticate' );
		$this->assertNotSame( self::$user_id, $user->ID, 'A unique user must be created for this test, the shared fixture must not be used' );
	}

	/**
	 * Ensure the hash of the user activation key remains less than 60 characters in length to account for the old users table schema.
	 *
	 * @ticket 21022
	 */
	public function test_user_activation_key_against_old_users_table_schema() {
		// Mimic the schema of the users table prior to WordPress 4.4.
		add_filter( 'wp_pre_insert_user_data', array( $this, 'mimic_users_schema_prior_to_44' ) );

		$username = 'old-schema-user';

		// Create a user.
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => $username,
				'user_email' => 'old-schema-user@example.com',
			)
		);

		$user = get_userdata( $user_id );
		$key  = get_password_reset_key( $user );

		// A correctly saved key should be accepted.
		$check = check_password_reset_key( $key, $user->user_login );

		$this->assertNotWPError( $check );
		$this->assertInstanceOf( 'WP_User', $check );
		$this->assertSame( $user->ID, $check->ID );
		$this->assertNotSame( self::$user_id, $user->ID, 'A unique user must be created for this test, the shared fixture must not be used' );
	}

	/*
	 * Fake the schema of the users table prior to WordPress 4.4 to mimic sites that are using the
	 * `DO_NOT_UPGRADE_GLOBAL_TABLES` constant and have not updated the users table schema.
	 *
	 * The schema of the wp_users table on wordpress.org has not been updated since the schema was changed in [35638]
	 * for WordPress 4.4, which means the `user_activation_key` field remains at 60 characters length and the `user_pass`
	 * field remains at 64 characters length instead of the expected 255. Although this is unlikely to affect other
	 * sites, this can be accommodated for in the codebase.
	 *
	 * Actually altering the database schema during tests will commit the transaction and break subsequent tests, hence
	 * the use of this filter.
	 */
	public function mimic_users_schema_prior_to_44( array $data ): array {
		if ( isset( $data['user_pass'] ) ) {
			$this->assertLessThanOrEqual( 64, strlen( $data['user_pass'] ) );
		}

		if ( isset( $data['user_activation_key'] ) ) {
			$this->assertLessThanOrEqual( 60, strlen( $data['user_activation_key'] ) );
		}

		return $data;
	}

	/**
	 * @ticket 21022
	 */
	public function test_invalid_password_at_bcrypt_length_limit_is_rejected() {
		$limit = str_repeat( 'a', self::$bcrypt_length_limit );

		// Set the user password to the bcrypt limit.
		wp_set_password( $limit, self::$user_id );

		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );
		// Wrong password.
		$this->assertWPError( $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_invalid_password_beyond_bcrypt_length_limit_is_rejected() {
		$limit = str_repeat( 'a', self::$bcrypt_length_limit + 1 );

		// Set the user password beyond the bcrypt limit.
		wp_set_password( $limit, self::$user_id );

		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );
		// Wrong password.
		$this->assertWPError( $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_valid_password_at_bcrypt_length_limit_is_accepted() {
		$limit = str_repeat( 'a', self::$bcrypt_length_limit );

		// Set the user password to the bcrypt limit.
		wp_set_password( $limit, self::$user_id );

		// Authenticate.
		$user = wp_authenticate( $this->user->user_login, $limit );

		// Correct password.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 21022
	 */
	public function test_valid_password_beyond_bcrypt_length_limit_is_accepted() {
		$limit = str_repeat( 'a', self::$bcrypt_length_limit + 1 );

		// Set the user password beyond the bcrypt limit.
		wp_set_password( $limit, self::$user_id );

		// Authenticate.
		$user = wp_authenticate( $this->user->user_login, $limit );

		// Correct password depite its length.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * A password beyond 72 bytes will be truncated by bcrypt by default and still be accepted.
	 *
	 * This ensures that a truncated password is not accepted by WordPress.
	 *
	 * @ticket 21022
	 */
	public function test_long_truncated_password_is_rejected() {
		$at_limit     = str_repeat( 'a', self::$bcrypt_length_limit );
		$beyond_limit = str_repeat( 'a', self::$bcrypt_length_limit + 1 );

		// Set the user password beyond the bcrypt limit.
		wp_set_password( $beyond_limit, self::$user_id );

		// Authenticate using a truncated password.
		$user = wp_authenticate( $this->user->user_login, $at_limit );

		// Incorrect password.
		$this->assertWPError( $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_setting_password_beyond_bcrypt_length_limit_is_rejected() {
		$beyond_limit = str_repeat( 'a', self::$password_length_limit + 1 );

		// Set the user password beyond the limit.
		wp_set_password( $beyond_limit, self::$user_id );

		// Password broken by setting it to be too long.
		$user = get_user_by( 'id', self::$user_id );
		$this->assertSame( '*', $user->data->user_pass );

		// Password is not accepted.
		$user = wp_authenticate( $this->user->user_login, $beyond_limit );
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );

		// Placeholder is not accepted.
		$user = wp_authenticate( $this->user->user_login, '*' );
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @see https://core.trac.wordpress.org/changeset/30466
	 */
	public function test_invalid_password_at_phpass_length_limit_is_rejected() {
		$limit = str_repeat( 'a', self::$phpass_length_limit );

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( $limit, self::$user_id );

		// Authenticate.
		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );

		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	public function test_valid_password_at_phpass_length_limit_is_accepted() {
		$limit = str_repeat( 'a', self::$phpass_length_limit );

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( $limit, self::$user_id );

		// Authenticate.
		$user = wp_authenticate( $this->user->user_login, $limit );

		// Correct password.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	public function test_too_long_password_at_phpass_length_limit_is_rejected() {
		$limit = str_repeat( 'a', self::$phpass_length_limit );

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( $limit, self::$user_id );

		// Authenticate with a password that is one character too long.
		$user = wp_authenticate( $this->user->user_login, $limit . 'a' );

		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	public function test_too_long_password_beyond_phpass_length_limit_is_rejected() {
		// One char too many.
		$too_long = str_repeat( 'a', self::$phpass_length_limit + 1 );

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( $too_long, self::$user_id );

		$user = get_user_by( 'id', self::$user_id );
		// Password broken by setting it to be too long.
		$this->assertSame( '*', $user->data->user_pass );

		// Password is not accepted.
		$user = wp_authenticate( $this->user->user_login, '*' );
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @dataProvider data_empty_values
	 * @param mixed $value
	 */
	public function test_empty_password_is_rejected_by_bcrypt( $value ) {
		// Set the user password.
		wp_set_password( 'password', self::$user_id );

		$user = wp_authenticate( $this->user->user_login, $value );
		$this->assertInstanceOf( 'WP_Error', $user );
	}

	/**
	 * @dataProvider data_empty_values
	 * @param mixed $value
	 */
	public function test_empty_password_is_rejected_by_phpass( $value ) {
		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( 'password', self::$user_id );

		$user = wp_authenticate( $this->user->user_login, $value );
		$this->assertInstanceOf( 'WP_Error', $user );
	}

	public function test_incorrect_password_is_rejected_by_phpass() {
		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( 'password', self::$user_id );

		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );

		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	public function test_too_long_password_is_rejected_by_phpass() {
		$limit = str_repeat( 'a', self::$phpass_length_limit );

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( 'password', self::$user_id );

		$user = wp_authenticate( $this->user->user_login, $limit . 'a' );

		// Password broken by setting it to be too long.
		$this->assertInstanceOf( 'WP_Error', $user );
		$this->assertSame( 'incorrect_password', $user->get_error_code() );
	}

	/**
	 * @ticket 45746
	 */
	public function test_user_activation_key_is_saved() {
		$user = get_userdata( $this->user->ID );
		$key  = get_password_reset_key( $user );

		// A correctly saved key should be accepted.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertNotWPError( $check );
		$this->assertInstanceOf( 'WP_User', $check );
		$this->assertSame( $this->user->ID, $check->ID );
	}

	/**
	 * @ticket 32429
	 */
	public function test_user_activation_key_is_checked() {
		global $wpdb;

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => strtotime( '-1 hour' ) . ':' . wp_fast_hash( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// A valid key should be accepted.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertNotWPError( $check );
		$this->assertInstanceOf( 'WP_User', $check );
		$this->assertSame( $this->user->ID, $check->ID );

		// An invalid key should be rejected.
		$check = check_password_reset_key( 'key', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );

		// An empty key should be rejected.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );

		// A truncated key should be rejected.
		$partial = substr( $key, 0, 10 );
		$check   = check_password_reset_key( $partial, $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
	}

	/**
	 * @ticket 32429
	 */
	public function test_expired_user_activation_key_is_rejected() {
		global $wpdb;

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => strtotime( '-48 hours' ) . ':' . wp_fast_hash( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// An expired but otherwise valid key should be rejected.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
		$this->assertSame( 'expired_key', $check->get_error_code() );
	}

	/**
	 * @ticket 32429
	 */
	public function test_empty_user_activation_key_fails_key_check() {
		// An empty user_activation_key should not allow any key to be accepted.
		$check = check_password_reset_key( 'key', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );

		// An empty user_activation_key should not allow an empty key to be accepted.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
	}

	/**
	 * @ticket 32429
	 */
	public function test_legacy_user_activation_key_is_rejected() {
		global $wpdb;

		// A legacy user_activation_key is one without the `time()` prefix introduced in WordPress 4.3.

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => self::$wp_hasher->HashPassword( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// A legacy user_activation_key should not be accepted.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
		$this->assertSame( 'expired_key', $check->get_error_code() );

		// An empty key with a legacy user_activation_key should be rejected.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
		$this->assertSame( 'invalid_key', $check->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_phpass_user_activation_key_is_allowed() {
		global $wpdb;

		// A legacy user_activation_key is one hashed using phpass between WordPress 4.3 and 6.8.0.

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => strtotime( '-1 hour' ) . ':' . self::$wp_hasher->HashPassword( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// A legacy phpass user_activation_key should remain valid.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertNotWPError( $check );
		$this->assertInstanceOf( 'WP_User', $check );
		$this->assertSame( $this->user->ID, $check->ID );

		// An empty key with a legacy user_activation_key should be rejected.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertWPError( $check );
		$this->assertSame( 'invalid_key', $check->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_expired_phpass_user_activation_key_is_rejected() {
		global $wpdb;

		// A legacy user_activation_key is one hashed using phpass between WordPress 4.3 and 6.8.0.

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => strtotime( '-48 hours' ) . ':' . self::$wp_hasher->HashPassword( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// A legacy phpass user_activation_key should still be subject to an expiry check.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertWPError( $check );
		$this->assertSame( 'expired_key', $check->get_error_code() );

		// An empty key with a legacy user_activation_key should be rejected.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertWPError( $check );
		$this->assertSame( 'invalid_key', $check->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_user_request_key_handling() {
		$request_id = wp_create_user_request( 'test@example.com', 'remove_personal_data' );
		$key        = wp_generate_user_request_key( $request_id );

		// A valid key should be accepted.
		$check = wp_validate_user_request_key( $request_id, $key );
		$this->assertNotWPError( $check );
		$this->assertTrue( $check );

		// An invalid key should rejected.
		$check = wp_validate_user_request_key( $request_id, 'invalid' );
		$this->assertWPError( $check );
		$this->assertSame( 'invalid_key', $check->get_error_code() );

		// An empty key should be rejected.
		$check = wp_validate_user_request_key( $request_id, '' );
		$this->assertWPError( $check );
		$this->assertSame( 'missing_key', $check->get_error_code() );
	}

	/**
	 * @ticket 21022
	 */
	public function test_phpass_user_request_key_is_allowed() {
		// A legacy user request key is one hashed using phpass between WordPress 4.3 and 6.8.0.

		$request_id = wp_create_user_request( 'test@example.com', 'remove_personal_data' );
		$key        = wp_generate_password( 20, false );

		wp_update_post(
			array(
				'ID'            => $request_id,
				'post_password' => self::$wp_hasher->HashPassword( $key ),
			)
		);

		// A legacy phpass key should remain valid.
		$check = wp_validate_user_request_key( $request_id, $key );
		$this->assertNotWPError( $check );
		$this->assertTrue( $check );

		// An empty key with a legacy key should be rejected.
		$check = wp_validate_user_request_key( $request_id, '' );
		$this->assertWPError( $check );
		$this->assertSame( 'missing_key', $check->get_error_code() );
	}

	/**
	 * The `wp_password_needs_rehash()` function is just a wrapper around `password_needs_rehash()`, but this ensures
	 * that it works as expected.
	 *
	 * Notably the bcrypt cost was increased in PHP 8.4: https://wiki.php.net/rfc/bcrypt_cost_2023 .
	 *
	 * @ticket 21022
	 */
	public function check_password_needs_rehashing() {
		$password = 'password';

		// Current password hashing algorithm.
		$hash = wp_hash_password( $password );
		$this->assertFalse( wp_password_needs_rehash( $hash ) );

		// A future upgrade from a previously lower cost.
		$default = self::get_default_bcrypt_cost();
		$opts    = array(
			// Reducing the cost mimics an increase in the default cost.
			'cost' => $default - 1,
		);
		$hash    = password_hash( $password, PASSWORD_BCRYPT, $opts );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );

		// Previous phpass algorithm.
		$hash = self::$wp_hasher->HashPassword( $password );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );

		// o_O md5.
		$hash = md5( $password );
		$this->assertTrue( wp_password_needs_rehash( $hash ) );
	}

	/**
	 * @ticket 32429
	 * @ticket 24783
	 */
	public function test_plaintext_user_activation_key_is_rejected() {
		global $wpdb;

		// A plaintext user_activation_key is one stored before hashing was introduced in WordPress 3.7.

		$key = wp_generate_password( 20, false );
		$wpdb->update(
			$wpdb->users,
			array(
				'user_activation_key' => $key,
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// A plaintext user_activation_key should not allow an otherwise valid key to be accepted.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );

		// A plaintext user_activation_key should not allow an empty key to be accepted.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
	}

	/**
	 * Ensure that `user_activation_key` is cleared after a successful login.
	 *
	 * @ticket 58901
	 *
	 * @covers ::wp_signon
	 */
	public function test_user_activation_key_after_successful_login() {
		global $wpdb;

		$password_reset_key = get_password_reset_key( $this->user );
		$user               = wp_signon(
			array(
				'user_login'    => self::USER_LOGIN,
				'user_password' => self::USER_PASS,
			)
		);

		$activation_key_from_database = $wpdb->get_var(
			$wpdb->prepare( "SELECT user_activation_key FROM $wpdb->users WHERE ID = %d", $this->user->ID )
		);

		$this->assertNotWPError( $password_reset_key, 'The password reset key was not created.' );
		$this->assertNotWPError( $user, 'The user was not authenticated.' );
		$this->assertEmpty( $user->user_activation_key, 'The `user_activation_key` was not empty on the user object returned by `wp_signon()` function.' );
		$this->assertEmpty( $activation_key_from_database, 'The `user_activation_key` was not empty in the database.' );
	}

	/**
	 * @ticket 21022
	 */
	public function test_phpass_application_password_is_accepted() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		$password = 'password';

		// Set an application password with the old phpass algorithm.
		$uuid = self::set_application_password_with_phpass( $password, self::$user_id );

		// Authenticate.
		$user = wp_authenticate_application_password( null, self::USER_LOGIN, $password );

		// Verify that the phpass hash for the application password was valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 21022
	 * @ticket 63203
	 */
	public function test_plain_bcrypt_application_password_is_accepted() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		$password = 'password';

		// Set an application password with plain bcrypt, which mimics a password that was hashed with
		// a custom `wp_hash_password()` in use.
		$uuid = self::set_application_password_with_plain_bcrypt( $password, self::$user_id );

		// Authenticate.
		$user = wp_authenticate_application_password( null, self::USER_LOGIN, $password );

		// Verify that the plain bcrypt hash for the application password was valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @dataProvider data_usernames
	 *
	 * @ticket 21022
	 */
	public function test_phpass_password_is_rehashed_after_successful_user_password_authentication( $username_or_email ) {
		$password = 'password';

		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( $password, self::$user_id );

		// Verify that the password needs rehashing.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertTrue( wp_password_needs_rehash( $hash, self::$user_id ) );

		// Authenticate.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the phpass password hash was valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );

		// Verify that the password no longer needs rehashing.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertFalse( wp_password_needs_rehash( $hash, self::$user_id ) );

		// Authenticate a second time to ensure the new hash is valid.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the bcrypt password hash is valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @dataProvider data_usernames
	 *
	 * @ticket 21022
	 */
	public function test_md5_password_is_rehashed_after_successful_user_password_authentication( $username_or_email ) {
		$password = 'password';

		// Set the user password with the old md5 algorithm.
		self::set_user_password_with_md5( $password, self::$user_id );

		// Verify that the password needs rehashing.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertTrue( wp_password_needs_rehash( $hash, self::$user_id ) );

		// Authenticate.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the md5 password hash was valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );

		// Verify that the password no longer needs rehashing.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertFalse( wp_password_needs_rehash( $hash, self::$user_id ) );

		// Authenticate a second time to ensure the new hash is valid.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the bcrypt password hash is valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @dataProvider data_usernames
	 *
	 * @ticket 21022
	 */
	public function test_bcrypt_password_is_rehashed_with_new_cost_after_successful_user_password_authentication( $username_or_email ) {
		$password = 'password';

		// Hash the user password with a lower cost than default to mimic a cost upgrade.
		add_filter( 'wp_hash_password_options', array( $this, 'reduce_hash_cost' ) );
		wp_set_password( $password, self::$user_id );
		remove_filter( 'wp_hash_password_options', array( $this, 'reduce_hash_cost' ) );

		// Verify that the password needs rehashing.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertTrue( wp_password_needs_rehash( $hash, self::$user_id ) );

		// Authenticate.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the reduced cost password hash was valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );

		// Verify that the password has been rehashed with the increased cost.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertFalse( wp_password_needs_rehash( $hash, self::$user_id ) );
		$this->assertSame( self::get_default_bcrypt_cost(), password_get_info( substr( $hash, 3 ) )['options']['cost'] );

		// Authenticate a second time to ensure the new hash is valid.
		$user = wp_authenticate( $username_or_email, $password );

		// Verify that the password hash is valid.
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	public function reduce_hash_cost( array $options ): array {
		$options['cost'] = self::get_default_bcrypt_cost() - 1;
		return $options;
	}

	public function increase_hash_cost( array $options ): array {
		$options['cost'] = self::get_default_bcrypt_cost() + 1;
		return $options;
	}

	public function data_usernames() {
		return array(
			array(
				self::USER_LOGIN,
			),
			array(
				self::USER_EMAIL,
			),
		);
	}

	/**
	 * @ticket 21022
	 */
	public function test_password_rehashing_requirement_can_be_filtered() {
		$filter_count_before = did_filter( 'password_needs_rehash' );

		wp_password_needs_rehash( '$hash' );

		$this->assertSame( $filter_count_before + 1, did_filter( 'password_needs_rehash' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_password_hashing_algorithm_can_be_filtered() {
		$password = 'password';

		$filter_count_before = did_filter( 'wp_hash_password_algorithm' );

		$wp_hash = wp_hash_password( $password );

		wp_check_password( $password, $wp_hash );
		wp_password_needs_rehash( $wp_hash );

		$this->assertSame( $filter_count_before + 2, did_filter( 'wp_hash_password_algorithm' ) );
	}

	/**
	 * @ticket 21022
	 */
	public function test_password_hashing_options_can_be_filtered() {
		$password = 'password';

		add_filter(
			'wp_hash_password_options',
			static function ( $options ) {
				$options['cost'] = 5;
				return $options;
			}
		);

		$filter_count_before = did_filter( 'wp_hash_password_options' );

		$wp_hash      = wp_hash_password( $password );
		$valid        = wp_check_password( $password, $wp_hash );
		$needs_rehash = wp_password_needs_rehash( $wp_hash );
		$info         = password_get_info( substr( $wp_hash, 3 ) );
		$cost         = $info['options']['cost'];

		$this->assertTrue( $valid );
		$this->assertFalse( $needs_rehash );
		$this->assertSame( $filter_count_before + 2, did_filter( 'wp_hash_password_options' ) );
		$this->assertSame( 5, $cost );
	}

	/**
	 * @ticket 21022
	 */
	public function test_password_checks_support_wp_hasher_fallback() {
		global $wp_hasher;

		$filter_count_before = did_filter( 'wp_hash_password_options' );

		$password = 'password';

		// Ensure the global $wp_hasher is set.
		$wp_hasher = new WP_Fake_Hasher();

		$hasher_hash  = $wp_hasher->HashPassword( $password );
		$wp_hash      = wp_hash_password( $password );
		$valid        = wp_check_password( $password, $wp_hash );
		$needs_rehash = wp_password_needs_rehash( $wp_hash );

		// Reset the global $wp_hasher.
		$wp_hasher = null;

		$this->assertSame( $hasher_hash, $wp_hash );
		$this->assertTrue( $valid );
		$this->assertFalse( $needs_rehash );
		$this->assertSame( 1, did_filter( 'check_password' ) );
		$this->assertSame( $filter_count_before, did_filter( 'wp_hash_password_options' ) );
	}

	/**
	 * Ensure users can log in using both their username and their email address.
	 *
	 * @ticket 9568
	 */
	public function test_log_in_using_email() {
		$this->assertInstanceOf( 'WP_User', wp_authenticate( self::USER_EMAIL, self::USER_PASS ) );
		$this->assertInstanceOf( 'WP_User', wp_authenticate( self::USER_LOGIN, self::USER_PASS ) );
	}

	/**
	 * @ticket 60700
	 */
	public function test_authenticate_filter() {
		add_filter( 'authenticate', '__return_null', 20 );
		$this->assertInstanceOf( 'WP_Error', wp_authenticate( self::USER_LOGIN, self::USER_PASS ) );
		add_filter( 'authenticate', '__return_false', 20 );
		$this->assertInstanceOf( 'WP_Error', wp_authenticate( self::USER_LOGIN, self::USER_PASS ) );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_wp_user_object() {
		$result = wp_authenticate_username_password( self::$_user, '', '' );
		$this->assertSame( $result->ID, self::$user_id );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_login_and_password() {
		$result = wp_authenticate_username_password( null, self::USER_LOGIN, self::USER_PASS );
		$this->assertSame( self::$user_id, $result->ID );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_null_password() {
		$result = wp_authenticate_username_password( null, self::USER_LOGIN, null );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_null_login() {
		$result = wp_authenticate_username_password( null, null, self::USER_PASS );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_invalid_login() {
		$result = wp_authenticate_username_password( null, 'invalidlogin', self::USER_PASS );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_username_password_with_invalid_password() {
		$result = wp_authenticate_username_password( null, self::USER_LOGIN, 'invalidpassword' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_wp_user_object() {
		$result = wp_authenticate_email_password( self::$_user, '', '' );
		$this->assertSame( self::$user_id, $result->ID );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_login_and_password() {
		$result = wp_authenticate_email_password( null, self::USER_EMAIL, self::USER_PASS );
		$this->assertSame( self::$user_id, $result->ID );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_null_password() {
		$result = wp_authenticate_email_password( null, self::USER_EMAIL, null );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_null_email() {
		$result = wp_authenticate_email_password( null, null, self::USER_PASS );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_invalid_email() {
		$result = wp_authenticate_email_password( null, 'invalid@example.com', self::USER_PASS );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_email_password_with_invalid_password() {
		$result = wp_authenticate_email_password( null, self::USER_EMAIL, 'invalidpassword' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_cookie_with_wp_user_object() {
		$result = wp_authenticate_cookie( $this->user, null, null );
		$this->assertSame( self::$user_id, $result->ID );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_cookie_with_null_params() {
		$result = wp_authenticate_cookie( null, null, null );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 36476
	 */
	public function test_wp_authenticate_cookie_with_invalid_cookie() {
		$_COOKIE[ AUTH_COOKIE ]        = 'invalid_cookie';
		$_COOKIE[ SECURE_AUTH_COOKIE ] = 'secure_invalid_cookie';

		$result = wp_authenticate_cookie( null, null, null );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @ticket 38744
	 */
	public function test_wp_signon_using_email_with_an_apostrophe() {
		$user_args = array(
			'user_email' => "mail\'@example.com",
			'user_pass'  => 'password',
		);
		self::factory()->user->create( $user_args );

		$_POST['log'] = $user_args['user_email'];
		$_POST['pwd'] = $user_args['user_pass'];
		$this->assertInstanceOf( 'WP_User', wp_signon() );
	}

	/**
	 * Tests that PHP 8.1 "passing null to non-nullable" deprecation notices
	 * are not thrown when `user_login` and `user_password` parameters are empty.
	 *
	 * The notices that we should not see:
	 * `Deprecated: preg_replace(): Passing null to parameter #3 ($subject) of type array|string is deprecated`.
	 * `Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated`.
	 *
	 * @ticket 56850
	 */
	public function test_wp_signon_does_not_throw_deprecation_notices_with_default_parameters() {
		$error = wp_signon();
		$this->assertWPError( $error, 'The result should be an instance of WP_Error.' );

		$error_codes = $error->get_error_codes();
		$this->assertContains( 'empty_username', $error_codes, 'The "empty_username" error code should be present.' );
		$this->assertContains( 'empty_password', $error_codes, 'The "empty_password" error code should be present.' );
	}

	/**
	 * Tests that a warning or a fatal error is not thrown when the login or password
	 * passed via `$_POST` is an array instead of a string.
	 *
	 * The messages that we should not see:
	 * `Warning: wp_strip_all_tags() expects parameter #1 ($text) to be a string, array given`.
	 * `TypeError: trim(): Argument #1 ($string) must be of type string, array given`.
	 *
	 * @ticket 62794
	 */
	public function test_wp_signon_does_not_throw_fatal_errors_with_array_parameters() {
		$_POST['log'] = array( 'example' );
		$_POST['pwd'] = array( 'example' );

		$error = wp_signon();
		$this->assertWPError( $error, 'The result should be an instance of WP_Error.' );

		$error_codes = $error->get_error_codes();
		$this->assertContains( 'empty_username', $error_codes, 'The "empty_username" error code should be present.' );
		$this->assertContains( 'empty_password', $error_codes, 'The "empty_password" error code should be present.' );
	}

	/**
	 * HTTP Auth headers are used to determine the current user.
	 *
	 * @ticket 42790
	 *
	 * @covers ::wp_validate_application_password
	 */
	public function test_application_password_authentication() {
		$user_id = self::$_user->ID;

		// Create a new app-only password.
		list( $user_app_password, $item ) = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'phpunit' ) );

		// Fake a REST API request.
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		// Fake an HTTP Auth request with the regular account password first.
		$_SERVER['PHP_AUTH_USER'] = self::USER_LOGIN;
		$_SERVER['PHP_AUTH_PW']   = self::USER_PASS;

		$this->assertNull(
			wp_validate_application_password( null ),
			'Regular user account password should not be allowed for API authentication'
		);
		$this->assertNull( rest_get_authenticated_app_password() );

		// Not try with an App password instead.
		$_SERVER['PHP_AUTH_PW'] = $user_app_password;

		$this->assertSame(
			$user_id,
			wp_validate_application_password( null ),
			'Application passwords should be allowed for API authentication'
		);
		$this->assertSame( $item['uuid'], rest_get_authenticated_app_password() );
	}

	/**
	 * @ticket 21022
	 * @ticket 63203
	 *
	 * @covers WP_Application_Passwords::create_new_application_password
	 */
	public function test_application_password_is_hashed_with_fast_hash() {
		// Create a new app-only password.
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		$this->assertStringStartsWith( '$generic$', $item['password'] );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_respects_existing_user() {
		$user = wp_authenticate_application_password( self::$_user, self::$_user->user_login, 'password' );
		$this->assertNotWPError( $user );
		$this->assertSame( self::$_user, $user );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_is_rejected_if_not_api_request() {
		add_filter( 'application_password_is_api_request', '__return_false' );

		$user = wp_authenticate_application_password( null, self::$_user->user_login, 'password' );
		$this->assertNotWPError( $user );
		$this->assertNull( $user );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_invalid_username() {
		add_filter( 'application_password_is_api_request', '__return_true' );

		$error = wp_authenticate_application_password( null, 'idonotexist', 'password' );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_username', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_invalid_email() {
		add_filter( 'application_password_is_api_request', '__return_true' );

		$error = wp_authenticate_application_password( null, 'idonotexist@example.org', 'password' );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_email', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_not_allowed() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_false' );

		$error = wp_authenticate_application_password( null, self::$_user->user_login, 'password' );
		$this->assertWPError( $error );
		$this->assertSame( 'application_passwords_disabled', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_not_allowed_for_user() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		add_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );

		$error = wp_authenticate_application_password( null, self::$_user->user_login, 'password' );
		$this->assertWPError( $error );
		$this->assertSame( 'application_passwords_disabled_for_user', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_incorrect_password() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		$error = wp_authenticate_application_password( null, self::$_user->user_login, 'password' );
		$this->assertWPError( $error );
		$this->assertSame( 'incorrect_password', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_custom_errors() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		add_action(
			'wp_authenticate_application_password_errors',
			static function ( WP_Error $error ) {
				$error->add( 'my_code', 'My Error' );
			}
		);

		list( $password ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		$error = wp_authenticate_application_password( null, self::$_user->user_login, $password );
		$this->assertWPError( $error );
		$this->assertSame( 'my_code', $error->get_error_code() );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_by_username() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		list( $password ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		$user = wp_authenticate_application_password( null, self::$_user->user_login, $password );
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_by_email() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		list( $password ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		$user = wp_authenticate_application_password( null, self::$_user->user_email, $password );
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_chunked() {
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		list( $password ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		$user = wp_authenticate_application_password( null, self::$_user->user_email, WP_Application_Passwords::chunk_password( $password ) );
		$this->assertNotWPError( $user );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 51939
	 */
	public function test_authenticate_application_password_returns_null_if_not_in_use() {
		delete_site_option( 'using_application_passwords' );

		$authenticated = wp_authenticate_application_password( null, 'idonotexist', 'password' );
		$this->assertNotWPError( $authenticated );
		$this->assertNull( $authenticated );
	}

	/**
	 * @ticket 52003
	 *
	 * @covers ::wp_validate_application_password
	 */
	public function test_application_passwords_does_not_attempt_auth_if_missing_password() {
		WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'phpunit' ) );

		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		$_SERVER['PHP_AUTH_USER'] = self::$_user->user_login;
		unset( $_SERVER['PHP_AUTH_PW'] );

		$this->assertNull( wp_validate_application_password( null ) );
	}

	/**
	 * @ticket 53386
	 * @dataProvider data_application_passwords_can_use_capability_checks_to_determine_feature_availability
	 */
	public function test_application_passwords_can_use_capability_checks_to_determine_feature_availability( $role, $authenticated ) {
		$user = self::factory()->user->create_and_get( array( 'role' => $role ) );

		list( $password ) = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => 'phpunit' ) );

		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		add_filter(
			'wp_is_application_passwords_available_for_user',
			static function ( $available, WP_User $user ) {
				return user_can( $user, 'edit_posts' );
			},
			10,
			2
		);

		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW']   = $password;

		unset( $GLOBALS['current_user'] );
		$current = get_current_user_id();

		if ( $authenticated ) {
			$this->assertSame( $user->ID, $current );
		} else {
			$this->assertSame( 0, $current );
		}
	}

	/**
	 * @ticket 52529
	 */
	public function test_reset_password_with_apostrophe_in_email() {
		$user_args = array(
			'user_email' => "jo'hn@example.com",
			'user_pass'  => 'password',
		);

		$user_id = self::factory()->user->create( $user_args );

		$user = get_userdata( $user_id );
		$key  = get_password_reset_key( $user );

		// A correctly saved key should be accepted.
		$check = check_password_reset_key( $key, $user->user_login );

		$this->assertNotWPError( $check );
		$this->assertInstanceOf( 'WP_User', $check );
		$this->assertSame( $user_id, $check->ID );
	}

	public function data_application_passwords_can_use_capability_checks_to_determine_feature_availability() {
		return array(
			'allowed'     => array( 'editor', true ),
			'not allowed' => array( 'subscriber', false ),
		);
	}

	/*
	 * @ticket 57512
	 * @covers ::wp_populate_basic_auth_from_authorization_header
	 */
	public function tests_basic_http_authentication_with_username_and_password() {
		// Header passed as "username:password".
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=';

		wp_populate_basic_auth_from_authorization_header();

		$this->assertSame( $_SERVER['PHP_AUTH_USER'], 'username' );
		$this->assertSame( $_SERVER['PHP_AUTH_PW'], 'password' );
	}

	/*
	 * @ticket 57512
	 * @covers ::wp_populate_basic_auth_from_authorization_header
	 */
	public function tests_basic_http_authentication_with_username_only() {
		// Malformed header passed as "username" with no password.
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcm5hbWU=';

		wp_populate_basic_auth_from_authorization_header();

		$this->assertArrayNotHasKey( 'PHP_AUTH_USER', $_SERVER );
		$this->assertArrayNotHasKey( 'PHP_AUTH_PW', $_SERVER );
	}

	/*
	 * @ticket 57512
	 * @covers ::wp_populate_basic_auth_from_authorization_header
	 */
	public function tests_basic_http_authentication_with_colon_in_password() {
		// Header passed as "username:pass:word" where password contains colon.
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcm5hbWU6cGFzczp3b3Jk';

		wp_populate_basic_auth_from_authorization_header();

		$this->assertSame( $_SERVER['PHP_AUTH_USER'], 'username' );
		$this->assertSame( $_SERVER['PHP_AUTH_PW'], 'pass:word' );
	}

	/**
	 * Test the tests
	 *
	 * @covers Tests_Auth::set_user_password_with_phpass
	 *
	 * @ticket 21022
	 */
	public function test_set_user_password_with_phpass() {
		// Set the user password with the old phpass algorithm.
		self::set_user_password_with_phpass( 'password', self::$user_id );

		// Ensure the password is hashed with phpass.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertStringStartsWith( '$P$', $hash );
	}

	private static function set_user_password_with_phpass( string $password, int $user_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->users,
			array(
				'user_pass' => self::$wp_hasher->HashPassword( $password ),
			),
			array(
				'ID' => $user_id,
			)
		);
		clean_user_cache( $user_id );
	}

	/**
	 * Test the tests
	 *
	 * @covers Tests_Auth::set_user_password_with_md5
	 *
	 * @ticket 21022
	 */
	public function test_set_user_password_with_md5() {
		$password = 'password';

		// Set the user password with the old md5 algorithm.
		self::set_user_password_with_md5( $password, self::$user_id );

		// Ensure the password is hashed with md5.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertSame( md5( $password ), $hash );
	}

	private static function set_user_password_with_md5( string $password, int $user_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->users,
			array(
				'user_pass' => md5( $password ),
			),
			array(
				'ID' => $user_id,
			)
		);
		clean_user_cache( $user_id );
	}

	/**
	 * Test the tests
	 *
	 * @covers Tests_Auth::set_user_password_with_plain_bcrypt
	 *
	 * @ticket 21022
	 */
	public function test_set_user_password_with_plain_bcrypt() {
		// Set the user password with plain bcrypt.
		self::set_user_password_with_plain_bcrypt( 'password', self::$user_id );

		// Ensure the password is hashed with bcrypt.
		$hash = get_userdata( self::$user_id )->user_pass;
		$this->assertStringStartsWith( '$2y$', $hash );
	}

	private static function set_user_password_with_plain_bcrypt( string $password, int $user_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->users,
			array(
				'user_pass' => password_hash( 'password', PASSWORD_BCRYPT ),
			),
			array(
				'ID' => $user_id,
			)
		);
		clean_user_cache( $user_id );
	}

	/**
	 * Test the tests
	 *
	 * @covers Tests_Auth::set_application_password_with_plain_bcrypt
	 *
	 * @ticket 21022
	 * @ticket 63203
	 */
	public function test_set_application_password_with_plain_bcrypt() {
		// Set an application password with the plain_bcrypt algorithm.
		$uuid = self::set_application_password_with_plain_bcrypt( 'password', self::$user_id );

		// Ensure the password is hashed with plain_bcrypt.
		$hash = WP_Application_Passwords::get_user_application_password( self::$user_id, $uuid )['password'];
		$this->assertStringStartsWith( '$2y$', $hash );
	}

	/**
	 * Creates an application password that is hashed using bcrypt instead of the generic algorithm.
	 *
	 * This is ultimately used to mimic a plugged version of `wp_hash_password()` that uses bcrypt and
	 * facilitate backwards compatibility testing.
	 *
	 * @param string $password The password to hash.
	 * @param int    $user_id  The user ID to associate the password with.
	 * @return string The UUID of the application password.
	 */
	private static function set_application_password_with_plain_bcrypt( string $password, int $user_id ) {
		return self::set_application_password( password_hash( $password, PASSWORD_BCRYPT ), $user_id );
	}

	/**
	 * Test the tests
	 *
	 * @covers Tests_Auth::set_application_password_with_phpass
	 *
	 * @ticket 21022
	 */
	public function test_set_application_password_with_phpass() {
		// Set an application password with the old phpass algorithm.
		$uuid = self::set_application_password_with_phpass( 'password', self::$user_id );

		// Ensure the password is hashed with phpass.
		$hash = WP_Application_Passwords::get_user_application_password( self::$user_id, $uuid )['password'];
		$this->assertStringStartsWith( '$P$', $hash );
	}

	/**
	 * Creates an application password that is hashed using a phpass portable hash instead of the generic algorithm.
	 *
	 * This facilitate backwards compatibility testing.
	 *
	 * @param string $password The password to hash.
	 * @param int    $user_id  The user ID to associate the password with.
	 * @return string The UUID of the application password.
	 */
	private static function set_application_password_with_phpass( string $password, int $user_id ) {
		return self::set_application_password( self::$wp_hasher->HashPassword( $password ), $user_id );
	}

	/**
	 * Creates an application password using the given password hash.
	 *
	 * @param string $hash    The password hash.
	 * @param int    $user_id The user ID to associate the password with.
	 * @return string The UUID of the application password.
	 */
	private static function set_application_password( string $hash, int $user_id ) {
		$uuid = wp_generate_uuid4();
		$item = array(
			'uuid'      => $uuid,
			'app_id'    => '',
			'name'      => 'Test',
			'password'  => $hash,
			'created'   => time(),
			'last_used' => null,
			'last_ip'   => null,
		);

		$saved = update_user_meta(
			$user_id,
			WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS,
			array( $item )
		);

		if ( ! $saved ) {
			throw new Exception( 'Could not save application password.' );
		}

		update_network_option( get_main_network_id(), WP_Application_Passwords::OPTION_KEY_IN_USE, true );

		return $uuid;
	}

	private static function get_default_bcrypt_cost(): int {
		return 5;
	}
}
