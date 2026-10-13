<?php

/**
 * @group option
 * @group transient
 *
 * @covers ::is_valid_site_transient
 */
class Tests_Option_ValidSiteTransient extends WP_UnitTestCase {

	/**
	 * @ticket 37040
	 */
	public function test_valid_site_transient_with_expired_timeout() {
		$transient_name  = 'valid_site_transient_with_expired_timeout';
		$transient_value = 'transient_value';

		set_site_transient( $transient_name, $transient_value, 10 );

		$this->assertTrue( is_valid_site_transient( $transient_name ) );

		// Force the transient to expire.
		$past_time = time() - 1000;
		update_site_option( '_site_transient_timeout_' . $transient_name, $past_time );

		wp_cache_flush();

		$this->assertFalse( is_valid_site_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_site_transient_with_no_timeout() {
		$transient_name  = 'valid_site_transient_with_no_timeout';
		$transient_value = 'transient_value';

		set_site_transient( $transient_name, $transient_value );

		$this->assertTrue( is_valid_site_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_site_transient_with_no_transient() {
		$transient_name = 'valid_site_transient_with_no_transient';

		$this->assertFalse( is_valid_site_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_site_transient_with_no_site_transient() {
		$transient_name  = 'valid_site_transient_with_no_site_transient';
		$transient_value = 'transient_value';

		set_transient( $transient_name, $transient_value );

		$this->assertFalse( is_valid_site_transient( $transient_name ) );
	}

	/**
	 * @ticket 37040
	 */
	public function test_valid_site_transient_with_object_cache() {
		if ( ! wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'Object cache not available.' );
		}

		$transient_name  = 'valid_site_transient_with_object_cache';
		$transient_value = 'transient_value';

		set_site_transient( $transient_name, $transient_value, 60 );
		$this->assertTrue( is_valid_site_transient( $transient_name ) );

		wp_cache_delete( $transient_name, 'site-transient' );
		$this->assertFalse( is_valid_site_transient( $transient_name ) );
	}
}
