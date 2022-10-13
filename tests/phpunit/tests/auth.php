<?php

/**
 * @group pluggable
 * @group auth
 */
class Tests_Auth extends WP_UnitTestCase {
	protected $user;

	/**
	 * @var WP_User
	 */
	protected static $_user;
	protected static $user_id;
	protected static $wp_hasher;

	/**
	 * Action hook.
	 */
	protected $nonce_failure_hook = 'wp_verify_nonce_failed';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$_user = $factory->user->create_and_get(
			array(
				'user_login' => 'password-tests',
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
	 * @ticket 23494
	 */
	public function test_password_trimming() {
		$passwords_to_test = array(
			'a password with no trailing or leading spaces',
			'a password with trailing spaces ',
			' a password with leading spaces',
			' a password with trailing and leading spaces ',
		);

		foreach ( $passwords_to_test as $password_to_test ) {
			wp_set_password( $password_to_test, $this->user->ID );
			$authed_user = wp_authenticate( $this->user->user_login, $password_to_test );

			$this->assertInstanceOf( 'WP_User', $authed_user );
			$this->assertSame( $this->user->ID, $authed_user->ID );
		}
	}

	/**
	 * Test wp_hash_password trims whitespace
	 *
	 * This is similar to test_password_trimming but tests the "lower level"
	 * wp_hash_password function
	 *
	 * @ticket 24973
	 */
	public function test_wp_hash_password_trimming() {

		$password = ' pass with leading whitespace';
		$this->assertTrue( wp_check_password( 'pass with leading whitespace', wp_hash_password( $password ) ) );

		$password = 'pass with trailing whitespace ';
		$this->assertTrue( wp_check_password( 'pass with trailing whitespace', wp_hash_password( $password ) ) );

		$password = ' pass with whitespace ';
		$this->assertTrue( wp_check_password( 'pass with whitespace', wp_hash_password( $password ) ) );

		$password = "pass with new line \n";
		$this->assertTrue( wp_check_password( 'pass with new line', wp_hash_password( $password ) ) );

		$password = "pass with vertial tab o_O\x0B";
		$this->assertTrue( wp_check_password( 'pass with vertial tab o_O', wp_hash_password( $password ) ) );
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

	public function test_password_length_limit() {
		$limit = str_repeat( 'a', 4096 );

		wp_set_password( $limit, self::$user_id );
		// phpass hashed password.
		$this->assertStringStartsWith( '$P$', $this->user->data->user_pass );

		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );
		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, $limit );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$user_id, $user->ID );

		// One char too many.
		$user = wp_authenticate( $this->user->user_login, $limit . 'a' );
		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );

		wp_set_password( $limit . 'a', self::$user_id );
		$user = get_user_by( 'id', self::$user_id );
		// Password broken by setting it to be too long.
		$this->assertSame( '*', $user->data->user_pass );

		$user = wp_authenticate( $this->user->user_login, '*' );
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, '*0' );
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, '*1' );
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, 'aaaaaaaa' );
		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, $limit );
		// Wrong password.
		$this->assertInstanceOf( 'WP_Error', $user );

		$user = wp_authenticate( $this->user->user_login, $limit . 'a' );
		// Password broken by setting it to be too long.
		$this->assertInstanceOf( 'WP_Error', $user );
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
				'user_activation_key' => strtotime( '-1 hour' ) . ':' . self::$wp_hasher->HashPassword( $key ),
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
				'user_activation_key' => strtotime( '-48 hours' ) . ':' . self::$wp_hasher->HashPassword( $key ),
			),
			array(
				'ID' => $this->user->ID,
			)
		);
		clean_user_cache( $this->user );

		// An expired but otherwise valid key should be rejected.
		$check = check_password_reset_key( $key, $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
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

		// An empty key with a legacy user_activation_key should be rejected.
		$check = check_password_reset_key( '', $this->user->user_login );
		$this->assertInstanceOf( 'WP_Error', $check );
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
	 * Ensure users can log in using both their username and their email address.
	 *
	 * @ticket 9568
	 */
	public function test_log_in_using_email() {
		$user_args = array(
			'user_login' => 'johndoe',
			'user_email' => 'mail@example.com',
			'user_pass'  => 'password',
		);
		self::factory()->user->create( $user_args );

		$this->assertInstanceOf( 'WP_User', wp_authenticate( $user_args['user_email'], $user_args['user_pass'] ) );
		$this->assertInstanceOf( 'WP_User', wp_authenticate( $user_args['user_login'], $user_args['user_pass'] ) );
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
	 * HTTP Auth headers are used to determine the current user.
	 *
	 * @ticket 42790
	 *
	 * @covers ::wp_validate_application_password
	 */
	public function test_application_password_authentication() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'http_auth_login',
				'user_pass'  => 'http_auth_pass', // Shouldn't be allowed for API login.
			)
		);

		// Create a new app-only password.
		list( $user_app_password, $item ) = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'phpunit' ) );

		// Fake a REST API request.
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		// Fake an HTTP Auth request with the regular account password first.
		$_SERVER['PHP_AUTH_USER'] = 'http_auth_login';
		$_SERVER['PHP_AUTH_PW']   = 'http_auth_pass';

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
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_respects_existing_user() {
		$this->assertSame( self::$_user, wp_authenticate_application_password( self::$_user, self::$_user->user_login, 'password' ) );
	}

	/**
	 * @ticket 42790
	 */
	public function test_authenticate_application_password_is_rejected_if_not_api_request() {
		add_filter( 'application_password_is_api_request', '__return_false' );

		$this->assertNull( wp_authenticate_application_password( null, self::$_user->user_login, 'password' ) );
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
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( self::$user_id, $user->ID );
	}

	/**
	 * @ticket 51939
	 */
	public function test_authenticate_application_password_returns_null_if_not_in_use() {
		delete_site_option( 'using_application_passwords' );

		$authenticated = wp_authenticate_application_password( null, 'idonotexist', 'password' );
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

	public function data_application_passwords_can_use_capability_checks_to_determine_feature_availability() {
		return array(
			'allowed'     => array( 'editor', true ),
			'not allowed' => array( 'subscriber', false ),
		);
	}
}
