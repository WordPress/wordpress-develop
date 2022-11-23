<?php

/**
 * Tests the _config_wp_home() function.
 *
 * @group functions.php
 *
 * @covers ::_config_wp_home
 */
class Test_for_config_wp_home extends WP_UnitTestCase {

	/**
	 * @ticket 57179
	 * @dataProvider _config_wp_home_dataset
	 */
	function test_config_wp_home( $url, $expected ) {
		$this->assertSame( $expected, _config_wp_home( $url ) );
	}

	function _config_wp_home_dataset() {
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
	 * @ticket 57179
	 */
	public function test_get_home_url_from_defined() {
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', 'defined_home' );
		}
		$this->assertSame( 'defined_home', _config_wp_home( 'home' ) );
	}

	/**
	 * @ticket 57179
	 */
	public function test_get_home_url_from_defined_with_training_slash() {
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', 'defined_home/' );
		}
		$this->assertSame( 'defined_home', _config_wp_home( 'home' ) );
	}

	/**
	 * @ticket 57179
	 */
	public function test_get_home_url_from_options() {
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', 'defined_home' );
		}
		$this->assertSame( 'defined_home', get_option( 'home' ) );
	}
}
