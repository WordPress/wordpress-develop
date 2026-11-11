<?php

/**
 * Tests for the WP_Http::is_ip_address() method.
 *
 * @group http
 *
 * @covers WP_Http::is_ip_address
 */
class Tests_HTTP_WpHttpIsIpAddress extends WP_UnitTestCase {

	/**
	 * Tests that IPv4 addresses are recognized.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_ipv4_addresses
	 *
	 * @param string $address IPv4 address.
	 */
	public function test_should_return_4_for_an_ipv4_address( $address ) {
		$this->assertSame( 4, WP_Http::is_ip_address( $address ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ipv4_addresses() {
		return array(
			'private address' => array( '192.168.0.1' ),
			'loopback'        => array( '127.0.0.1' ),
			'zeros'           => array( '0.0.0.0' ),
			'broadcast'       => array( '255.255.255.255' ),
			'single digits'   => array( '1.2.3.4' ),
			'leading zeros'   => array( '010.001.000.001' ),
		);
	}

	/**
	 * Tests that IPv6 addresses are recognized.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_ipv6_addresses
	 *
	 * @param string $address IPv6 address.
	 */
	public function test_should_return_6_for_an_ipv6_address( $address ) {
		$this->assertSame( 6, WP_Http::is_ip_address( $address ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ipv6_addresses() {
		return array(
			'full address'          => array( '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ),
			'without leading zeros' => array( '2001:db8:85a3:0:0:8a2e:370:7334' ),
			'uppercase'             => array( '2001:DB8:85A3::8A2E:370:7334' ),
			'short form'            => array( '2001:db8::1' ),
			'loopback'              => array( '::1' ),
			'unspecified'           => array( '::' ),
			'in brackets'           => array( '[2001:db8::1]' ),
			'loopback in brackets'  => array( '[::1]' ),
			'with an ipv4 suffix'   => array( '::ffff:192.168.0.1' ),
			'link local'            => array( 'fe80::1' ),
		);
	}

	/**
	 * Tests that strings that are not IP addresses are not recognized.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_values_that_are_not_ip_addresses
	 *
	 * @param string $value Value that is not an IP address.
	 */
	public function test_should_return_false_for_a_value_that_is_not_an_ip_address( $value ) {
		$this->assertFalse( WP_Http::is_ip_address( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_ip_addresses() {
		return array(
			'empty string'              => array( '' ),
			'domain name'               => array( 'example.org' ),
			'localhost'                 => array( 'localhost' ),
			'three octets'              => array( '192.168.0' ),
			'five octets'               => array( '1.2.3.4.5' ),
			'octet with four digits'    => array( '1234.1.1.1' ),
			'letters in an octet'       => array( '1.2.3.a' ),
			'trailing dot'              => array( '1.2.3.4.' ),
			'surrounding spaces'        => array( ' 1.2.3.4 ' ),
			'ipv4 with a port'          => array( '1.2.3.4:80' ),
			'ipv4 in brackets'          => array( '[1.2.3.4]' ),
			'too many groups'           => array( '1:2:3:4:5:6:7:8:9' ),
			'two double colons'         => array( '1::2::3' ),
			'group with five digits'    => array( '2001:db8::12345' ),
			'invalid characters'        => array( '2001:db8::xyz' ),
			'a single colon'            => array( ':' ),
			'a colon and a domain name' => array( 'example.org:80' ),
		);
	}

	/**
	 * Tests that an octet greater than 255 is accepted, because only the format is checked.
	 *
	 * @ticket 66507
	 */
	public function test_should_only_check_the_format_of_an_ipv4_address() {
		$this->assertSame( 4, WP_Http::is_ip_address( '999.999.999.999' ) );
	}
}
