<?php
/**
 * Tests for the wp_privacy_exports_url function.
 *
 * @group functions.php
 *
 * @covers ::wp_privacy_exports_url
 */#
class Tests_functions_test_wp_privacy_exports_url extends WP_UnitTestCase {

	/**
	 * @ticket 59709
	 */
	public function test_wp_privacy_exports_url() {

		$this->assertEquals( 'http://example.org/wp-content/uploads/wp-personal-data-exports/', wp_privacy_exports_url() );
	}

	/**
	 * @ticket 59709
	 */
	public function test_wp_privacy_exports_url_filtered() {

		add_filter( 'wp_privacy_exports_url', array( $this, 'filter_wp_privacy_exports_url' ) );

		$expected_url = 'https://filtered.com/wp-personal-data-exports/';
		$actual_url   = wp_privacy_exports_url();
		$this->assertEquals( $expected_url, $actual_url );

		remove_filter( 'wp_privacy_exports_url', array( $this, 'filter_wp_privacy_exports_url' ) );
	}

	/**
	 * Filter for test
	 *
	 * @param string $url
	 */
	public function filter_wp_privacy_exports_url( $url ) {

		return 'https://filtered.com/wp-personal-data-exports/';
	}
}
