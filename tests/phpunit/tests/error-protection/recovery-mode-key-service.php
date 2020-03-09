<?php

/**
 * @group error-protection
 */
class Tests_Recovery_Mode_Key_Service extends WP_UnitTestCase {

	/**
	 * @ticket 46130
	 */
	public function test_generate_and_store_recovery_mode_key_returns_recovery_key() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$key     = $service->generate_and_store_recovery_mode_key( $token );

		$this->assertNotWPError( $key );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_no_key_set() {
		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( '', 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'token_not_found', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_data_missing() {
		update_option( 'recovery_keys', 'gibberish' );

		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( '', 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'token_not_found', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_bad() {
		update_option( 'recovery_keys', array( 'token' => 'gibberish' ) );

		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( 'token', 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_recovery_key_format', $error->get_error_code() );
	}


	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_stored_format_is_invalid() {

		$token = wp_generate_password( 22, false );
		update_option( 'recovery_keys', array( $token => 'gibberish' ) );

		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( $token, 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_recovery_key_format', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_empty_key() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$service->generate_and_store_recovery_mode_key( $token );
		$error = $service->validate_recovery_mode_key( $token, '', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_hash_mismatch() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$service->generate_and_store_recovery_mode_key( $token );
		$error = $service->validate_recovery_mode_key( $token, 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_expired() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$key     = $service->generate_and_store_recovery_mode_key( $token );

		$records                         = get_option( 'recovery_keys' );
		$records[ $token ]['created_at'] = time() - HOUR_IN_SECONDS - 30;
		update_option( 'recovery_keys', $records );

		$error = $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'key_expired', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_true_for_valid_key() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$key     = $service->generate_and_store_recovery_mode_key( $token );
		$this->assertTrue( $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS ) );
	}

	/**
	 * @ticket 46595
	 */
	public function test_validate_recovery_mode_key_returns_error_if_token_used_more_than_once() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$key     = $service->generate_and_store_recovery_mode_key( $token );

		$this->assertTrue( $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS ) );

		// Data should be remove by first call.
		$error = $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'token_not_found', $error->get_error_code() );
	}

	/**
	 * @ticket 46595
	 */
	public function test_validate_recovery_mode_key_returns_error_if_token_used_more_than_once_more_than_key_stored() {
		$service = new WP_Recovery_Mode_Key_Service();

		// Create an extra key.
		$token = $service->generate_recovery_mode_token();
		$service->generate_and_store_recovery_mode_key( $token );

		$token = $service->generate_recovery_mode_token();
		$key   = $service->generate_and_store_recovery_mode_key( $token );

		$this->assertTrue( $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS ) );

		// Data should be remove by first call.
		$error = $service->validate_recovery_mode_key( $token, $key, HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'token_not_found', $error->get_error_code() );
	}

	/**
	 * @ticket 46595
	 */
	public function test_clean_expired_keys() {
		$service = new WP_Recovery_Mode_Key_Service();
		$token   = $service->generate_recovery_mode_token();
		$service->generate_and_store_recovery_mode_key( $token );

		$records = get_option( 'recovery_keys' );

		$records[ $token ]['created_at'] = time() - HOUR_IN_SECONDS - 30;

		update_option( 'recovery_keys', $records );

		$service->clean_expired_keys( HOUR_IN_SECONDS );

		$this->assertEmpty( get_option( 'recovery_keys' ) );
	}
}
