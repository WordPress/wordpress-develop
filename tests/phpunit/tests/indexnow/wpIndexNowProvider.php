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
    
    public function test_wp_indexnow_submit_url() {
        $api_key = wp_indexnow_get_api_key();
        $provider = new WP_IndexNow_Provider( 'https://www.bing.com' );
        $test_post = self::factory()->post->create_and_get( array(
            'taxonomy' => 'post_tag',
        ) );
        $result = $provider->submit_url( 'www.example.org' , get_permalink( $test_post->ID ) , $api_key );
        $this->assertTrue( $result === 'success' , $result );
    }
}