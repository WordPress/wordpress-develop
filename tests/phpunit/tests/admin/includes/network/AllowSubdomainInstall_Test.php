<?php

/**
 * @group admin
 * @group multisite
 *
 * @covers ::allow_subdomain_install
 */
class Tests_Admin_Includes_Network_AllowSubdomainInstall_Test extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/network.php';
	}

	/**
	 * @ticket 35274
	 *
	 * @dataProvider data_allow_subdomain_install
	 *
	 * @param string $home     Value to set the 'home' option to.
	 * @param bool   $expected Expected return value of allow_subdomain_install().
	 */
	public function test_allow_subdomain_install( $home, $expected ) {
		update_option( 'home', $home );

		$this->assertSame( $expected, allow_subdomain_install() );
	}

	/**
	 * Data provider for test_allow_subdomain_install().
	 *
	 * @return array<string, array{home: string, expected: bool}>
	 */
	public function data_allow_subdomain_install() {
		return array(
			'a plain domain'              => array(
				'home'     => 'https://example.com',
				'expected' => true,
			),
			'a domain with a subdomain'   => array(
				'home'     => 'https://www.example.com',
				'expected' => true,
			),
			'localhost'                   => array(
				'home'     => 'https://localhost',
				'expected' => false,
			),
			'a domain with a path'        => array(
				'home'     => 'https://example.com/wordpress',
				'expected' => false,
			),
			'an IPv4 address'             => array(
				'home'     => 'http://127.0.0.1',
				'expected' => false,
			),
			'an IPv4 address with a port' => array(
				'home'     => 'http://192.168.1.1:8080',
				'expected' => false,
			),
		);
	}
}
