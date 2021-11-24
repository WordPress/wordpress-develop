<?php
/**
 * IndexNow: Tests_Indexnow_Indexnow class
 *
 * Main test class.
 *
 * @package   IndexNow
 */

/**
 * Core indexnow test cases.
 *
 * @group indexnow
 */
class Tests_Indexnow_Indexnow extends WP_UnitTestCase {

	public function set_up() {
		if ( ! defined( 'WP_INDEXNOW' ) ) {
			define( 'WP_INDEXNOW', true );
		}
		global $wp_indexnow;
		$wp_indexnow = '';
	}

	public function test_wp_indexnow_get_api_key() {
		$api_key = wp_indexnow_get_api_key();
		$this->assertNotEmpty( $api_key );
	}

	public function test_wp_indexnow_regenerate_api_key() {
		$api_key     = wp_indexnow_get_api_key();
		$new_api_key = wp_indexnow_regenerate_key();
		$this->assertFalse( $api_key === $new_api_key );
	}

	public function test_wp_indexnow_ignore_path() {
		$this->assertTrue( wp_indexnow_ignore_path( '/^\/test\//' ) );
	}

}
