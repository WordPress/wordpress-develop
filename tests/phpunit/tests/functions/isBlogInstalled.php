<?php

/**
 * Tests for the is_blog_installed function.
 *
 * @group functions
 *
 * @covers ::is_blog_installed
 */

class Test_Functions_isBlogInstalled extends WP_UnitTestCase {

	private $original_wpdb;


	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->original_wpdb = $wpdb;
		// Create a mock for the wpdb object
		$wpdb = $this->createMock( 'wpdb' );

		// Set the global $wpdb to the mock
		$this->setUpMockedWpdb( $wpdb );
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;

		wp_cache_set( 'is_blog_installed', false );

		parent::tear_down();
	}

	public function test_should_return_true_by_default() {
		$this->assertTrue( is_blog_installed() );
	}

	// Test case 1: Returns true when siteurl exists in the database
	public function test_should_return_true_when_siteurl_exists() {
		global $wpdb;

		// Mock the get_var to return 'http://example.com' for siteurl
		$wpdb->method( 'get_var' )->willReturn( 'http://example.com' );

		$result = is_blog_installed();

		$this->assertTrue( $result );
	}

	// Test case 2: Returns false when siteurl is empty
	public function test_should_return_false_when_siteurl_is_empty() {
		global $wpdb;

		$filter_callback = function ( $alloptions ) {
			$alloptions['siteurl'] = '';
			return $alloptions;
		};

		add_filter(
			'pre_wp_load_alloptions',
			$filter_callback,
			10,
			1
		);

		$result = is_blog_installed();

		remove_filter( 'pre_wp_load_alloptions', $filter_callback );

		$this->assertFalse( $result );
	}

	// Test case 3: Returns true when WP_REPAIRING constant is defined
	public function test_should_return_true_when_wp_repairing_is_defined() {

		if ( ! defined( 'WP_REPAIRING' ) ) {
			define( 'WP_REPAIRING', true );
		}

		$result = is_blog_installed();

		$this->assertTrue( $result );
	}

	// Test case 4: Cache behavior - Returns cached result
	public function test_should_use_cache_once_installed_status_is_checked() {
		global $wpdb;

		wp_cache_set( 'is_blog_installed', true );

		$result = is_blog_installed();

		$this->assertTrue( $result );

		$result = is_blog_installed();
		$this->assertTrue( $result );
	}

	// Helper to mock the global wpdb instance
	private function setUpMockedWpdb( $wpdb_mock ) {
		global $wpdb;
		$wpdb = $wpdb_mock;
	}
}
