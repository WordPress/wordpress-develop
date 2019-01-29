<?php

class Tests_Recovery_Mode extends WP_UnitTestCase {

	private static $subscriber;
	private static $administrator;

	public static function setUpBeforeClass() {
		self::$subscriber    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );

		return parent::setUpBeforeClass();
	}

	public static function tearDownAfterClass() {
		wp_delete_user( self::$subscriber );
		wp_delete_user( self::$administrator );

		return parent::tearDownAfterClass();
	}

	public function test_generate_and_store_returns_recovery_key() {
		$key = generate_and_store_recovery_mode_key();

		$this->assertNotWPError( $key );
	}

	public function test_verify_recovery_mode_key_returns_wp_error_if_no_key_set() {
		$error = validate_recovery_mode_key( 'abcd' );

		$this->assertWPError( $error );
		$this->assertEquals( 'no_recovery_key_set', $error->get_error_code() );
	}

	public function test_verify_recovery_mode_key_returns_wp_error_if_stored_format_is_invalid() {
		update_site_option( 'recovery_key', 'gibberish' );
		$error = validate_recovery_mode_key( 'abcd' );

		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_recovery_key_format', $error->get_error_code() );
	}

	public function test_verify_recovery_mode_key_returns_wp_error_if_empty_key() {
		generate_and_store_recovery_mode_key();
		$error = validate_recovery_mode_key( '' );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	public function test_verify_recovery_mode_key_returns_wp_error_if_hash_mismatch() {
		generate_and_store_recovery_mode_key();
		$error = validate_recovery_mode_key( 'abcd' );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	public function test_verify_recovery_mode_key_returns_wp_error_if_expired() {
		$key = generate_and_store_recovery_mode_key();

		$record               = get_site_option( 'recovery_key' );
		$record['created_at'] = time() - HOUR_IN_SECONDS - 30;
		update_site_option( 'recovery_key', $record );

		$error = validate_recovery_mode_key( $key );

		$this->assertWPError( $error );
		$this->assertEquals( 'key_expired', $error->get_error_code() );
	}

	public function test_verify_recovery_mode_key_returns_true_for_valid_key() {

		$key = generate_and_store_recovery_mode_key();
		$this->assertTrue( validate_recovery_mode_key( $key ) );
	}

	public function test_validate_recovery_mode_cookie_returns_wp_error_if_invalid_format() {

		$error = validate_recovery_mode_cookie( 'gibbersih' );
		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_format', $error->get_error_code() );

		$error = validate_recovery_mode_cookie( base64_encode( 'test|data|format' ) );
		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_format', $error->get_error_code() );

		$error = validate_recovery_mode_cookie( base64_encode( 'test|data|format|to|long' ) );
		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_format', $error->get_error_code() );
	}

	public function test_validate_recovery_mode_cookie_returns_wp_error_if_expired() {

		$to_sign = sprintf( 'recovery_mode|%s|%s', time() - WEEK_IN_SECONDS - 30, wp_generate_password( 20, false ) );
		$signed  = recovery_mode_hash( $to_sign );
		$cookie  = base64_encode( sprintf( '%s|%s', $to_sign, $signed ) );

		$error = validate_recovery_mode_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertEquals( 'expired', $error->get_error_code() );
	}

	public function test_validate_recovery_mode_cookie_returns_wp_error_if_signature_mismatch() {

		$cookie = generate_recovery_mode_cookie();
		$cookie .= 'gibbersih';

		$error = validate_recovery_mode_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertEquals( 'signature_mismatch', $error->get_error_code() );
	}

	public function test_validate_recovery_mode_cookie_returns_wp_error_if_created_at_is_invalid_format() {

		$to_sign = sprintf( 'recovery_mode|%s|%s', 'month', wp_generate_password( 20, false ) );
		$signed  = recovery_mode_hash( $to_sign );
		$cookie  = base64_encode( sprintf( '%s|%s', $to_sign, $signed ) );

		$error = validate_recovery_mode_cookie( $cookie );
		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_created_at', $error->get_error_code() );
	}

	public function test_generate_and_validate_recovery_mode_cookie_returns_true_for_valid_cookie() {

		$cookie = generate_recovery_mode_cookie();
		$this->assertTrue( validate_recovery_mode_cookie( $cookie ) );
	}
}
