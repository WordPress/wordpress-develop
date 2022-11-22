<?php

/**
 * Test the _config_wp_siteurl function
 *
 * @group Functions
 * @covers ::_config_wp_siteurl
 */
class Test_for_config_wp_siteurl extends WP_UnitTestCase {

	/**
	 * @ticket 57180
	 * @dataProvider _config_wp_siteurl_dataset
	 */
	function test_config_wp_siteurl( $url, $expected ) {
		$this->assertSame( $expected, _config_wp_siteurl( $url ) );
	}

	function _config_wp_siteurl_dataset() {
		return array(
			'default'   => array(
				'url'      => '/',
				'expected' => '/',
			),
			'htpps'     => array(
				'url'      => 'https://www.example.com/',
				'expected' => 'https://www.example.com/',
			),
			'blank_url' => array(
				'url'      => '',
				'expected' => '',
			),
		);
	}

	/**
	 * @ticket 57180
	 */
	public function test_get_siteurl_url_from_defined() {
		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', 'defined_siteurl' );
		}
		$this->assertSame( 'defined_siteurl', _config_wp_siteurl( 'home' ) );
	}

	/**
	 * @ticket 57180
	 */
	public function test_get_siteurl_url_from_defined_with_training_slash() {
		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', 'defined_siteurl/' );
		}
		$this->assertSame( 'defined_siteurl', _config_wp_siteurl( 'home' ) );
	}

	/**
	 * @ticket 57180
	 */
	public function test_get_siteurl_url_from_options() {
		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', 'defined_siteurl' );
		}
		$this->assertSame( 'defined_siteurl', get_option( 'siteurl' ) );
	}
}
