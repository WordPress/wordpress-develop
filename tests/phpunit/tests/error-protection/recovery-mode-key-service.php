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
		$key     = $service->generate_and_store_recovery_mode_key();

		$this->assertNotWPError( $key );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_no_key_set() {
		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'no_recovery_key_set', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_stored_format_is_invalid() {
		update_option( 'recovery_key', 'gibberish' );

		$service = new WP_Recovery_Mode_Key_Service();
		$error   = $service->validate_recovery_mode_key( 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'invalid_recovery_key_format', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_empty_key() {
		$service = new WP_Recovery_Mode_Key_Service();
		$service->generate_and_store_recovery_mode_key();
		$error = $service->validate_recovery_mode_key( '', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_hash_mismatch() {
		$service = new WP_Recovery_Mode_Key_Service();
		$service->generate_and_store_recovery_mode_key();
		$error = $service->validate_recovery_mode_key( 'abcd', HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'hash_mismatch', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_wp_error_if_expired() {
		$service = new WP_Recovery_Mode_Key_Service();
		$key     = $service->generate_and_store_recovery_mode_key();

		$record               = get_option( 'recovery_key' );
		$record['created_at'] = time() - HOUR_IN_SECONDS - 30;
		update_option( 'recovery_key', $record );

		$error = $service->validate_recovery_mode_key( $key, HOUR_IN_SECONDS );

		$this->assertWPError( $error );
		$this->assertEquals( 'key_expired', $error->get_error_code() );
	}

	/**
	 * @ticket 46130
	 */
	public function test_validate_recovery_mode_key_returns_true_for_valid_key() {
		$service = new WP_Recovery_Mode_Key_Service();
		$key     = $service->generate_and_store_recovery_mode_key();
		$this->assertTrue( $service->validate_recovery_mode_key( $key, HOUR_IN_SECONDS ) );
	}
}
