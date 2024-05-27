<?php

/**
 * @group option
 * @group transient
 *
 * @covers ::is_valid_transient
 */
class Tests_Option_ValidTransient extends WP_UnitTestCase {

	/**
	 * @ticket 37040
	 */
	public function test_valid_transient_with_expired_timeout() {
		$transient_name  = 'valid_transient_with_expired_timeout';
		$transient_value = 'transient_value';

		set_transient( $transient_name, $transient_value, 10 );

		$this->assertTrue( is_valid_transient( $transient_name ) );

		update_option( '_transient_timeout_' . $transient_name, time() - 10 );

		$this->assertFalse( is_valid_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_transient_with_no_timeout() {
		$transient_name  = 'valid_transient_with_no_timeout';
		$transient_value = 'transient_value';

		set_transient( $transient_name, $transient_value );
		$this->assertTrue( is_valid_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_transient_with_no_transient() {
		$transient_name = 'valid_transient_with_no_transient';

		$this->assertFalse( is_valid_transient( $transient_name ) );
	}
}
