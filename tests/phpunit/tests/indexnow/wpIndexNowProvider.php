<?php
/**
 * IndexNow: Tests_Indexnow_WP_Indexnow_Provider class
 *
 * WP_IndexNow_Provider test class.
 *
 * @package   IndexNow
 */

/**
 * Core indexnow test cases.
 *
 * @group indexnow
 */
class Tests_Indexnow_WP_Indexnow_Provider extends WP_UnitTestCase {

	public function set_up() {
		if ( ! defined( 'WP_INDEXNOW' ) ) {
			define( 'WP_INDEXNOW', true );
		}
		global $wp_indexnow;
		$wp_indexnow = '';
	}

	public function test_wp_indexnow_submit_url() {
		$api_key   = wp_indexnow_get_api_key();
		$provider  = new WP_IndexNow_Provider( 'https://www.bing.com' );
		$test_post = self::factory()->post->create_and_get(
			array(
				'taxonomy' => 'post_tag',
			)
		);
		$result    = $provider->submit_url( get_home_url(), get_permalink( $test_post->ID ), $api_key );
		$this->assertTrue( 'success' === $result, $result );
	}
}
