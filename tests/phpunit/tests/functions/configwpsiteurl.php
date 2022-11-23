<?php

/**
 * Test the _config_wp_siteurl function
 *
 * @group functions.php
 *
 * @covers ::_config_wp_siteurl
 */
class Test_Functions_ConfigWpSiteurl extends WP_UnitTestCase {

	/**
	 * @ticket 57180
	 *
	 * @dataProvider data_config_wp_siteurl
	 *
	 * @param string $url The URL to pass to _config_wp_siteurl().
	 * @param string $expected The expected result.
	 */
	function test_config_wp_siteurl( $url, $expected ) {
		$this->assertSame( $expected, _config_wp_siteurl( $url ) );
	}

	/**
	 * Data provider for test_config_wp_home().
	 *
	 * @return array[]
	 */
	function data_config_wp_siteurl() {
		return array(
			'only a forward slash' => array(
				'url'      => '/',
				'expected' => '/',
			),
			'https'                => array(
				'url'      => 'https://www.example.com/',
				'expected' => 'https://www.example.com/',
			),
			'URL as empty string'  => array(
				'url'      => '',
				'expected' => '',
			),
		);
	}

	//	Can't run these test as they are using the global defined  WP_SITEURL
	//	/**
	//	 * @ticket 57180
	//	 */
	//	public function test_get_siteurl_url_from_defined() {
	//		if ( ! defined( 'WP_SITEURL' ) ) {
	//			define( 'WP_SITEURL', 'defined_siteurl' );
	//		}
	//		$this->assertSame( 'defined_siteurl', _config_wp_siteurl( 'home' ) );
	//	}
	//
	//	/**
	//	 * @ticket 57180
	//	 */
	//	public function test_get_siteurl_url_from_defined_with_training_slash() {
	//		if ( ! defined( 'WP_SITEURL' ) ) {
	//			define( 'WP_SITEURL', 'defined_siteurl/' );
	//		}
	//		$this->assertSame( 'defined_siteurl', _config_wp_siteurl( 'home' ) );
	//	}
	//
	//	/**
	//	 * @ticket 57180
	//	 */
	//	public function test_get_siteurl_url_from_options() {
	//		if ( ! defined( 'WP_SITEURL' ) ) {
	//			define( 'WP_SITEURL', 'defined_siteurl' );
	//		}
	//		$this->assertSame( 'defined_siteurl', get_option( 'siteurl' ) );
	//	}
}
