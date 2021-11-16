<?php

/**
 * Tests for wp_url_encode_ending_period()
 *
 * @group  functions.php
 * @covers ::wp_url_encode_ending_period
 */
class Tests_Functions_WpUrlEncodeEndingPeriod extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_url_encode_ending_period
	 *
	 * @ticket 42957
	 *
	 * @param string $url      URL to test.
	 * @param string $expected Expected result.
	 */
	public function test_wp_url_encode_ending_period( $url, $expected ) {
		$this->assertSame( $expected, wp_url_encode_ending_period( $url ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_url_encode_ending_period() {
		return array(
			'empty string'            => array(
				'input'    => '',
				'expected' => '',
			),
			'.'                       => array(
				'input'    => '.',
				'expected' => '%2E',
			),
			'. with space after'      => array(
				'input'    => '. ',
				'expected' => '%2E',
			),
			'..'                      => array(
				'input'    => '..',
				'expected' => '.%2E',
			),
			'. with space after'      => array(
				'input'    => '..  ',
				'expected' => '.%2E',
			),
			'...'                     => array(
				'input'    => '...',
				'expected' => '..%2E',
			),
			'starts with .'           => array(
				'input'    => '.username',
				'expected' => '.username',
			),
			'ends with .'             => array(
				'input'    => 'username.',
				'expected' => 'username%2E',
			),
			'ends with spaces and .'  => array(
				'input'    => 'username .',
				'expected' => 'username%20%2E',
			),
			'. in the middle'         => array(
				'input'    => 'user.name',
				'expected' => 'user.name',
			),
			'. in middle with spaces' => array(
				'input'    => 'user . name',
				'expected' => 'user%20.%20name',
			),
			'no . with a space'       => array(
				'input'    => 'user name',
				'expected' => 'user%20name',
			),
			'. in middle and end'     => array(
				'input'    => 'user.name.',
				'expected' => 'user.name%2E',
			),
		);
	}
}
