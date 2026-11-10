<?php

/**
 * Tests for rest_is_ip_address().
 *
 * @group restapi
 *
 * @covers ::rest_is_ip_address
 */
class Tests_REST_API_RestIsIpAddress extends WP_UnitTestCase {

	/**
	 * Tests that valid IP addresses are returned unchanged.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_ip_addresses
	 *
	 * @param string $ip Valid IP address.
	 */
	public function test_returns_valid_ip_address( $ip ) {
		$this->assertSame( $ip, rest_is_ip_address( $ip ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_valid_ip_addresses() {
		return array(
			'ipv4' => array( '192.0.2.10' ),
			'ipv6' => array( '2001:db8::1' ),
		);
	}

	/**
	 * Tests that invalid IP values return false.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_ip_addresses
	 *
	 * @param mixed $ip Invalid IP value.
	 */
	public function test_returns_false_for_invalid_ip_address( $ip ) {
		$this->assertFalse( rest_is_ip_address( $ip ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_ip_addresses() {
		return array(
			'empty string'      => array( '' ),
			'out of range ipv4' => array( '256.0.0.1' ),
			'hostname'          => array( 'example.com' ),
		);
	}
}
