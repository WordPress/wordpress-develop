<?php
/**
 * Tests for the wp_privacy_exports_url function.
 *
 * @group functions.php
 *
 * @covers ::wp_privacy_exports_url
 */
class Tests_functions_test_wpPrivacyExportsUrl extends WP_UnitTestCase {

	public function tear_down(){

		remove_filter( 'wp_privacy_exports_url', array( $this, 'filter_wp_privacy_exports_url' ) );
	}
	
	/**
	 * @ticket 59709
	 */
	public function test_wp_privacy_exports_url() {
		$upload_dir = wp_upload_dir();
		$this->assertEquals( $upload_dir['baseurl'] . '/wp-personal-data-exports/', wp_privacy_exports_url() );
	}

	/**
	 * @ticket 59709
	 */
	public function test_wp_privacy_exports_url_filtered() {

		add_filter( 'wp_privacy_exports_url', array( $this, 'filter_wp_privacy_exports_url' ) );

		$expected_url = 'https://filtered.com/wp-personal-data-exports/';
		$actual_url   = wp_privacy_exports_url();
		$this->assertSame( $expected_url, $actual_url );
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
