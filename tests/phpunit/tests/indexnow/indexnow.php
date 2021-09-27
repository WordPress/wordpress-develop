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

    public function test_wp_indexnow_get_api_key() {
        $api_key = wp_indexnow_get_api_key();
        $this->assertNotEmpty( $api_key );
    }

    public function test_wp_indexnow_regenerate_api_key() {
        $api_key = wp_indexnow_get_api_key();
        $new_api_key = wp_indexnow_regenerate_key();
        error_log( $api_key . ' ' . $new_api_key );
        $this->assertFalse( $api_key === $new_api_key );
    }

    public function test_wp_indexnow_ignore_path() {
        $this->assertTrue( wp_indexnow_ignore_path( 'test' ) );
    }

}