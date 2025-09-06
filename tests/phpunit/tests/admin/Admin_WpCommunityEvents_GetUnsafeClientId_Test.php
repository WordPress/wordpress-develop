<?php

require_once __DIR__ . '/Admin_WpCommunityEvents_TestCase.php';

/**
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.8.0
 *
 * @group admin
 * @group community-events
 *
 * @covers WP_Community_Events::get_unsafe_client_ip
 */
class Admin_WpCommunityEvents_GetUnsafeClientId_Test extends Admin_WpCommunityEvents_TestCase {
	/**
	 * Test that get_unsafe_client_ip() properly anonymizes all possible address formats
	 *
	 * @dataProvider data_get_unsafe_client_ip
	 *
	 * @ticket 41083
	 */
	public function test_get_unsafe_client_ip( $raw_ip, $expected_result ) {
		$_SERVER['REMOTE_ADDR']    = 'this should not be used';
		$_SERVER['HTTP_CLIENT_IP'] = $raw_ip;
		$actual_result             = WP_Community_Events::get_unsafe_client_ip();

		$this->assertSame( $expected_result, $actual_result );
	}

	/**
	 * Provide test cases for `test_get_unsafe_client_ip()`.
	 *
	 * @return array
	 */
	public function data_get_unsafe_client_ip() {
		return array(
			// Handle '::' returned from `wp_privacy_anonymize_ip()`.
			array(
				'or=\"[1000:0000:0000:0000:0000:0000:0000:0001',
				false,
			),

			// Handle '0.0.0.0' returned from `wp_privacy_anonymize_ip()`.
			array(
				'unknown',
				false,
			),

			// Valid IPv4.
			array(
				'198.143.164.252',
				'198.143.164.0',
			),

			// Valid IPv6.
			array(
				'2a03:2880:2110:df07:face:b00c::1',
				'2a03:2880:2110:df07::',
			),
		);
	}
}
