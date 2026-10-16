<?php

/**
 * @group error-protection
 */
class Tests_Error_Protection_wpRecoveryModeCookieService extends WP_UnitTestCase {

	/**
	 * Whether the recovery mode cookie was set before the test.
	 *
	 * @var bool
	 */
	private $had_cookie;

	/**
	 * Original value of the recovery mode cookie.
	 *
	 * @var mixed
	 */
	private $orig_cookie;

	public function set_up() {
		parent::set_up();

		$this->had_cookie  = array_key_exists( RECOVERY_MODE_COOKIE, $_COOKIE );
		$this->orig_cookie = $this->had_cookie ? $_COOKIE[ RECOVERY_MODE_COOKIE ] : null;

		unset( $_COOKIE[ RECOVERY_MODE_COOKIE ] );
	}

	public function tear_down() {
		if ( $this->had_cookie ) {
			$_COOKIE[ RECOVERY_MODE_COOKIE ] = $this->orig_cookie;
		} else {
			unset( $_COOKIE[ RECOVERY_MODE_COOKIE ] );
		}

		parent::tear_down();
	}

	/**
	 * Builds a signed recovery mode cookie value.
	 *
	 * @param int    $created_at Timestamp the cookie was created at.
	 * @param string $random     Random part of the cookie, used to derive the session ID.
	 * @return string Cookie value.
	 */
	private function build_cookie( $created_at, $random ) {
		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$to_sign = sprintf( 'recovery_mode|%s|%s', $created_at, $random );

		return base64_encode( sprintf( '%s|%s', $to_sign, $reflection->invoke( $service, $to_sign ) ) );
	}

	/**
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_returns_wp_error_if_invalid_format() {

		$service = new WP_Recovery_Mode_Cookie_Service();

		$error = $service->validate_cookie( 'gibbersih' );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_format', $error->get_error_code() );

		$error = $service->validate_cookie( base64_encode( 'test|data|format' ) );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_format', $error->get_error_code() );

		$error = $service->validate_cookie( base64_encode( 'test|data|format|to|long' ) );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_format', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_returns_wp_error_if_expired() {
		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$to_sign = sprintf( 'recovery_mode|%s|%s', time() - WEEK_IN_SECONDS - 30, wp_generate_password( 20, false ) );
		$signed  = $reflection->invoke( $service, $to_sign );
		$cookie  = base64_encode( sprintf( '%s|%s', $to_sign, $signed ) );

		$error = $service->validate_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertSame( 'expired', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_returns_wp_error_if_signature_mismatch() {
		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'generate_cookie' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$cookie  = $reflection->invoke( $service );
		$cookie .= 'gibbersih';

		$error = $service->validate_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertSame( 'signature_mismatch', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_returns_wp_error_if_created_at_is_invalid_format() {
		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$to_sign = sprintf( 'recovery_mode|%s|%s', 'month', wp_generate_password( 20, false ) );
		$signed  = $reflection->invoke( $service, $to_sign );
		$cookie  = base64_encode( sprintf( '%s|%s', $to_sign, $signed ) );

		$error = $service->validate_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_created_at', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_returns_true_for_valid_cookie() {

		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'generate_cookie' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertTrue( $service->validate_cookie( $reflection->invoke( $service ) ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::is_cookie_set
	 *
	 * @dataProvider data_is_cookie_set
	 *
	 * @param mixed $cookie   Cookie value, or null to leave the cookie unset.
	 * @param bool  $expected Whether the cookie counts as set.
	 */
	public function test_is_cookie_set( $cookie, $expected ) {
		if ( null !== $cookie ) {
			$_COOKIE[ RECOVERY_MODE_COOKIE ] = $cookie;
		}

		$service = new WP_Recovery_Mode_Cookie_Service();

		$this->assertSame( $expected, $service->is_cookie_set() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_is_cookie_set() {
		return array(
			'cookie not set' => array( null, false ),
			'empty string'   => array( '', false ),
			'zero string'    => array( '0', false ),
			'cookie value'   => array( 'cmVjb3ZlcnlfbW9kZQ==', true ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 *
	 * @dataProvider data_missing_cookies
	 *
	 * @param mixed $cookie Cookie value, or null to leave the cookie unset.
	 */
	public function test_validate_cookie_returns_wp_error_if_no_cookie_is_present( $cookie ) {
		if ( null !== $cookie ) {
			$_COOKIE[ RECOVERY_MODE_COOKIE ] = $cookie;
		}

		$service = new WP_Recovery_Mode_Cookie_Service();

		$error = $service->validate_cookie();
		$this->assertWPError( $error );
		$this->assertSame( 'no_cookie', $error->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_missing_cookies() {
		return array(
			'cookie not set' => array( null ),
			'empty string'   => array( '' ),
			'zero string'    => array( '0' ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_reads_cookie_from_request() {
		$_COOKIE[ RECOVERY_MODE_COOKIE ] = $this->build_cookie( time(), 'abcdefghij0123456789' );

		$service = new WP_Recovery_Mode_Cookie_Service();

		$this->assertTrue( $service->validate_cookie() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 */
	public function test_validate_cookie_prefers_cookie_argument_over_request() {
		$_COOKIE[ RECOVERY_MODE_COOKIE ] = $this->build_cookie( time(), 'abcdefghij0123456789' );

		$service = new WP_Recovery_Mode_Cookie_Service();

		$error = $service->validate_cookie( 'gibberish' );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_format', $error->get_error_code() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::validate_cookie
	 *
	 * @dataProvider data_filtered_cookie_lengths
	 *
	 * @param int         $age           Age of the cookie in seconds.
	 * @param int         $cookie_length Filtered cookie length in seconds.
	 * @param string|true $expected      Expected error code, or true if the cookie is valid.
	 */
	public function test_validate_cookie_uses_filtered_cookie_length( $age, $cookie_length, $expected ) {
		$filter = new MockAction();
		add_filter( 'recovery_mode_cookie_length', array( $filter, 'filter' ) );
		add_filter(
			'recovery_mode_cookie_length',
			static function () use ( $cookie_length ) {
				return $cookie_length;
			},
			20
		);

		$service = new WP_Recovery_Mode_Cookie_Service();
		$result  = $service->validate_cookie( $this->build_cookie( time() - $age, 'abcdefghij0123456789' ) );

		if ( true === $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
			$this->assertSame( $expected, $result->get_error_code() );
		}

		$this->assertSame( array( array( 604800 ) ), $filter->get_args(), 'The filter should receive the default length of one week.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filtered_cookie_lengths() {
		return array(
			'shorter length expires a recent cookie' => array( 2 * HOUR_IN_SECONDS, HOUR_IN_SECONDS, 'expired' ),
			'zero length expires any older cookie'   => array( MINUTE_IN_SECONDS, 0, 'expired' ),
			'longer length keeps an old cookie'      => array( 2 * WEEK_IN_SECONDS, 3 * WEEK_IN_SECONDS, true ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::get_session_id_from_cookie
	 */
	public function test_get_session_id_from_cookie_returns_hash_of_random_part() {
		$service = new WP_Recovery_Mode_Cookie_Service();
		$cookie  = base64_encode( 'recovery_mode|1234567890|abcdefghij0123456789|signature' );

		$this->assertSame( '6256c24ff916241741c75edc27548d4ba4124a29', $service->get_session_id_from_cookie( $cookie ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::get_session_id_from_cookie
	 */
	public function test_get_session_id_from_cookie_reads_cookie_from_request() {
		$_COOKIE[ RECOVERY_MODE_COOKIE ] = base64_encode( 'recovery_mode|1234567890|abcdefghij0123456789|signature' );

		$service = new WP_Recovery_Mode_Cookie_Service();

		$this->assertSame( '6256c24ff916241741c75edc27548d4ba4124a29', $service->get_session_id_from_cookie() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::get_session_id_from_cookie
	 *
	 * @dataProvider data_missing_cookies
	 *
	 * @param mixed $cookie Cookie value, or null to leave the cookie unset.
	 */
	public function test_get_session_id_from_cookie_returns_wp_error_if_no_cookie_is_present( $cookie ) {
		if ( null !== $cookie ) {
			$_COOKIE[ RECOVERY_MODE_COOKIE ] = $cookie;
		}

		$service = new WP_Recovery_Mode_Cookie_Service();

		$error = $service->get_session_id_from_cookie();
		$this->assertWPError( $error );
		$this->assertSame( 'no_cookie', $error->get_error_code() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::get_session_id_from_cookie
	 *
	 * @dataProvider data_cookies_with_invalid_format
	 *
	 * @param string $cookie Cookie value.
	 */
	public function test_get_session_id_from_cookie_returns_wp_error_if_invalid_format( $cookie ) {
		$service = new WP_Recovery_Mode_Cookie_Service();

		$error = $service->get_session_id_from_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_format', $error->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cookies_with_invalid_format() {
		return array(
			'not base64 encoded' => array( 'gibberish' ),
			'too few parts'      => array( base64_encode( 'recovery_mode|1234567890|abcdefghij0123456789' ) ),
			'too many parts'     => array( base64_encode( 'recovery_mode|1234567890|abcdefghij0123456789|signature|extra' ) ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::recovery_mode_hash
	 */
	public function test_recovery_mode_hash_uses_stored_key_and_salt_with_default_secret_keys() {
		if ( 'put your unique phrase here' !== AUTH_KEY || 'put your unique phrase here' !== AUTH_SALT ) {
			$this->markTestSkipped( 'This test requires the default AUTH_KEY and AUTH_SALT values.' );
		}

		update_site_option( 'recovery_mode_auth_key', 'test-auth-key' );
		update_site_option( 'recovery_mode_auth_salt', 'test-auth-salt' );

		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertSame(
			'3b83c8f3e016b4ed284de9e6300874a516e896a3',
			$reflection->invoke( $service, 'recovery_mode|1234567890|abcdefghij0123456789' )
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers WP_Recovery_Mode_Cookie_Service::recovery_mode_hash
	 */
	public function test_recovery_mode_hash_generates_and_reuses_key_and_salt_with_default_secret_keys() {
		if ( 'put your unique phrase here' !== AUTH_KEY || 'put your unique phrase here' !== AUTH_SALT ) {
			$this->markTestSkipped( 'This test requires the default AUTH_KEY and AUTH_SALT values.' );
		}

		delete_site_option( 'recovery_mode_auth_key' );
		delete_site_option( 'recovery_mode_auth_salt' );

		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$hash = $reflection->invoke( $service, 'recovery_mode|1234567890|abcdefghij0123456789' );
		$key  = get_site_option( 'recovery_mode_auth_key' );
		$salt = get_site_option( 'recovery_mode_auth_salt' );

		$this->assertIsString( $key, 'A key should be generated and stored.' );
		$this->assertSame( 64, strlen( $key ), 'The stored key should be 64 characters long.' );
		$this->assertIsString( $salt, 'A salt should be generated and stored.' );
		$this->assertSame( 64, strlen( $salt ), 'The stored salt should be 64 characters long.' );
		$this->assertNotSame( $key, $salt, 'The key and salt should differ.' );

		$this->assertSame( $hash, $reflection->invoke( $service, 'recovery_mode|1234567890|abcdefghij0123456789' ), 'The same data should produce the same hash.' );
		$this->assertSame( $key, get_site_option( 'recovery_mode_auth_key' ), 'The stored key should be reused.' );
		$this->assertSame( $salt, get_site_option( 'recovery_mode_auth_salt' ), 'The stored salt should be reused.' );
	}
}
