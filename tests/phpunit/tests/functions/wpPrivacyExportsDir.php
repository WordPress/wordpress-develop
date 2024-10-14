<?php
/**
 * Tests for the wp_privacy_exports_dir function.
 *
 * @group functions.php
 *
 * @covers ::wp_privacy_exports_dir
 */#
class Tests_functions_wp_privacy_exports_dir extends WP_UnitTestCase {

	/**
	 * @ticket 59710
	 */
	public function test_wp_privacy_exports_dir() {

		$this->assertEquals( '/var/www/src/wp-content/uploads/wp-personal-data-exports/', wp_privacy_exports_dir() );
	}

	/**
	 * @ticket 59710
	 */
	public function test_wp_privacy_exports_dir_filtered() {

		add_filter( 'wp_privacy_exports_dir', array( $this, 'filter_wp_privacy_exports_dir' ) );

		$expected_url = '/wp-personal-data-exports-url/';
		$actual_url   = wp_privacy_exports_dir();
		$this->assertEquals( $expected_url, $actual_url );

		remove_filter( 'wp_privacy_exports_dir', array( $this, 'filter_wp_privacy_exports_dir' ) );
	}

	/**
	 * Filter for test
	 *
	 * @param string $dir
	 */
	public function filter_wp_privacy_exports_dir( $dir ) {

		return '/wp-personal-data-exports-url/';
	}
}
