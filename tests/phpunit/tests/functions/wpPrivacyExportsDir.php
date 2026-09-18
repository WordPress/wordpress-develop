<?php
/**
 * Tests for the wp_privacy_exports_dir function.
 *
 * @group functions
 *
 * @covers ::wp_privacy_exports_dir
 */
class Tests_Functions_wpPrivacyExportsDir extends WP_UnitTestCase {

	/**
	 * @ticket 59710
	 */
	public function test_wp_privacy_exports_dir() {
		$upload_dir = wp_upload_dir();
		$expected   = trailingslashit( $upload_dir['basedir'] ) . 'wp-personal-data-exports/';
		$this->assertSame( $expected, wp_privacy_exports_dir() );
	}

	/**
	 * @ticket 59710
	 */
	public function test_wp_privacy_exports_dir_filtered() {
		add_filter( 'wp_privacy_exports_dir', array( $this, 'filter_wp_privacy_exports_dir' ) );

		$upload_dir   = wp_upload_dir();
		$expected_dir = trailingslashit( $upload_dir['basedir'] ) . 'filtered-exports/';
		$actual_dir   = wp_privacy_exports_dir();
		$this->assertSame( $expected_dir, $actual_dir );

		remove_filter( 'wp_privacy_exports_dir', array( $this, 'filter_wp_privacy_exports_dir' ) );
	}

	/**
	 * Filters the personal data exports directory for tests.
	 *
	 * @param string $exports_dir Default exports directory.
	 * @return string Filtered exports directory.
	 */
	public function filter_wp_privacy_exports_dir( $exports_dir ) {
		return str_replace( 'wp-personal-data-exports/', 'filtered-exports/', $exports_dir );
	}
}
