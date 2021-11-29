<?php


/**
 * @group functions.php
 * @covers ::wp_get_direct_update_https_url
 */
class Tests_wp_get_direct_update_https_url  extends WP_UnitTestCase {
	function set_up() {
		parent::set_up();

	}

	function tear_down() {
		parent::tear_down();
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=null' );
	}
	public function test_wp_get_direct_update_https_url() {
		$this->assertEmpty( wp_get_direct_update_https_url() );
	}

	public function test_wp_get_direct_update_https_url_when_env_set() {
		if ( ! defined( 'WP_DIRECT_UPDATE_HTTPS_URL' ) ) {
			putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=https://example.com/' );
		}
		$this->assertEquals( 'https://example.com/', wp_get_direct_update_https_url() );
	}

	public function test_wp_get_direct_update_https_url_with_filter() {
		add_filter(
			'wp_direct_update_https_url',
			static function( $url ) {
				return 'https://filtered.com/';
			}
		);
		$this->assertEquals( 'https://filtered.com/', wp_get_direct_update_https_url() );
	}
}
