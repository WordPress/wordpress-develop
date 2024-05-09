<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesNetwork extends WP_UnitTestCase {

	/**
	 * Set up test assets before the class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/network.php';
	}

	/**
	 * @dataProvider data_allow_subdomain_install
	 *
	 * @covers ::allow_subdomain_install
	 */
	public function test_allow_subdomain_install( $expected, $home_value ) {
		add_filter(
			'pre_option_home',
			function() use ( $home_value ) {
				return $home_value;
			}
		);

		$this->assertSame( $expected, allow_subdomain_install() );
	}

	/**
	 * Data provider.
	 */
	public function data_allow_subdomain_install() {
		return array(
			'empty string'                      => array(
				'expected'   => false,
				'home_value' => '',
			),
			'single slash'                      => array(
				'expected'   => false,
				'home_value' => '/',
			),
			'null'                              => array(
				'expected'   => false,
				'home_value' => null,
			),
			'number'                            => array(
				'expected'   => false,
				'home_value' => 123,
			),
			'localhost'                         => array(
				'expected'   => false,
				'home_value' => 'localhost',
			),
			'localhost with https protocol'     => array(
				'expected'   => false,
				'home_value' => 'https://localhost',
			),
			'localhost with ftp protocol'       => array(
				'expected'   => false,
				'home_value' => 'ftp://localhost',
			),
			'localhost with subdirectory'       => array(
				'expected'   => false,
				'home_value' => 'https://localhost/wp',
			),
			'valid domain'                      => array(
				'expected'   => true,
				'home_value' => 'https://example.com',
			),
			'valid domain with http'            => array(
				'expected'   => true,
				'home_value' => 'http://example.com',
			),
			'valid domain with a slash as path' => array(
				'expected'   => true,
				'home_value' => 'https://example.com/',
			),
			'valid domain with subdirectory'    => array(
				'expected'   => false,
				'home_value' => 'https://example.com/wp',
			),
			'ip address'                        => array(
				'expected'   => false,
				'home_value' => 'https://1.1.1.1',
			),
			'ip address with path'              => array(
				'expected'   => false,
				'home_value' => 'https://1.1.1.1/wp',
			),
		);
	}

	public function test_allow_subdomain_install_returns_false_when_home_value_is_false() {
		add_filter( 'option_home', '__return_false' );

		$this->assertFalse( allow_subdomain_install() );
	}

}
