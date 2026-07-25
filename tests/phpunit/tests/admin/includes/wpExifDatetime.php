<?php

/**
 * Class Tests_Admin_wpExifDatetime
 *
 * Contains unit tests for validating the functionality of the wp_exif_datetime function,
 * which is responsible for formatting datetime strings to the EXIF-compliant format.
 *
 * @group datetime
 * @group image
 *
 * @covers ::wp_exif_datetime
 */
class Tests_Admin_wpExifDatetime extends WP_UnitTestCase {

	/**
	 * @ticket 56887
	 *
	 * Test valid date inputs and their expected formatted outputs.
	 *
	 * @dataProvider provideValidDates
	 *
	 * @param string $input_date The input date string to be formatted.
	 * @param string $expected The expected formatted date string.
	 *
	 * @return void
	 */
	public function test_valid_dates( $input_date, $expected ) {
		$datetime = wp_exif_datetime( $input_date );
		$this->assertEquals( $expected, $datetime->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * @ticket 56887
	 *
	 * Test handling of invalid date inputs.
	 *
	 * @dataProvider provideInvalidDates
	 *
	 * @param string $input_date The date string to be tested for validation.
	 *
	 * @return void
	 */
	public function test_invalid_dates( $input_date ) {
		$this->assertFalse( wp_exif_datetime( $input_date ) );
	}

	/**
	 * Data provider for valid dates
	 */
	public function provideValidDates() {
		return array(
			'unix timestamp'  => array(
				'1710500000', // March 15, 2024 14:30:00
				'0000:06:03 17:10:50',
			),
			'mysql datetime'  => array(
				'2024-03-15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'exif format'     => array(
				'2024:03:15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'mysql date only' => array(
				'2024-03-15',
				'2024:03:15 00:00:00',
			),
			'incomplete date' => array(
				'2024-03',
				'2024:03:01 00:00:00',
			),
		);
	}

	/**
	 * Data provider for invalid dates that should trigger exceptions
	 */
	public function provideInvalidDates() {
		return array(
			'empty string'            => array( '' ),
			'null'                    => array( null ),
			'boolean false'           => array( false ),
			'boolean true'            => array( true ),
			'invalid format'          => array( 'not a date' ),
			'invalid month'           => array( '2024-13-15' ),
			'invalid day'             => array( '2024-03-32' ),
			'invalid time'            => array( '2024-03-15 25:00:00' ),
			'garbage with numbers'    => array( '2024abc15' ),
			'array input'             => array( array() ),
			'object input'            => array( new stdClass() ),
			'out of bounds timestamp' => array( 253402300800 ), // Year 9999
			'negative timestamp'      => array( - 62167219200 ), // Year 0
		);
	}

	/**
	 * @ticket 56887
	 *
	 * Test handling of edge case date and time formats.
	 *
	 * @return void
	 */
	public function test_edge_cases() {

		// Test with a very old date
		$datetime = wp_exif_datetime( '1900-01-01' );
		$this->assertEquals( '1900:01:01 00:00:00', $datetime->format( 'Y:m:d H:i:s' ) );

		// Test with milliseconds
		$datetime = wp_exif_datetime( '2024-03-15 14:30:00.123' );
		$this->assertEquals( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * @ticket 56887
	 *
	 * Tests the functionality of parsing dates with different separators and ensures the output format is consistent.
	 *
	 * @return void
	 */
	public function test_different_separators() {

		$datetime = wp_exif_datetime( '2024/03/15 14:30:00' );
		$this->assertEquals( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );

		$datetime = wp_exif_datetime( '2024/03/15 14:30:00' );
		$this->assertEquals( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );
	}
}
