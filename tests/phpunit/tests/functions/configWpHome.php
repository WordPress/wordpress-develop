<?php

/**
 * Tests the _config_wp_home() function.
 *
 * @group functions.php
 *
 * @covers ::_config_wp_home
 */
class Tests_Functions_ConfigWpHome extends WP_UnitTestCase {

	/**
	 * @ticket 57179
	 *
	 * @dataProvider data_config_wp_home
	 *
	 * @param string $url The URL to pass to _config_wp_home().
	 * @param string $expected The expected result.
	 */
	public function test_config_wp_home( $url, $expected ) {
		$this->assertSame( $expected, _config_wp_home( $url ) );
	}

	/**
	 * Data provider for test_config_wp_home().
	 *
	 * @return array[]
	 */
	public function data_config_wp_home() {
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

	//  Can't run these test as they are using the global defined WP_HOME.
	//	/**
	//	 * @ticket 57179
	//	 */
	//	public function test_get_home_url_from_defined() {
	//		if ( ! defined( 'WP_HOME' ) ) {
	//			define( 'WP_HOME', 'defined_home' );
	//		}
	//		$this->assertSame( 'defined_home', _config_wp_home( 'home' ) );
	//	}
	//
	//	/**
	//	 * @ticket 57179
	//	 */
	//	public function test_get_home_url_from_defined_with_training_slash() {
	//		if ( ! defined( 'WP_HOME' ) ) {
	//			define( 'WP_HOME', 'defined_home/' );
	//		}
	//		$this->assertSame( 'defined_home', _config_wp_home( 'home' ) );
	//	}
	//
	//	/**
	//	 * @ticket 57179
	//	 */
	//	public function test_get_home_url_from_options() {
	//		if ( ! defined( 'WP_HOME' ) ) {
	//			define( 'WP_HOME', 'defined_home' );
	//		}
	//		$this->assertSame( 'defined_home', get_option( 'home' ) );
	//	}
}
