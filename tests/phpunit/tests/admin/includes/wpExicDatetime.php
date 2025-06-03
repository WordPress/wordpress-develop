<?php

/**
 * @group admin
 * @group image
 */
class Tests_Admin_wpExifDatetime extends WP_UnitTestCase {

	/**
	 * Test conversion of various date formats to EXIF format
	 *
	 * @dataProvider provideValidDates
	 */
	public function test_valid_dates( $input_date, $expected ) {
		$datetime = wp_exif_datetime( $input_date );
		$this->assertEquals( $expected, $datetime->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * Test handling of invalid dates
	 *
	 * @dataProvider provideInvalidDates
	 */
	public function test_invalid_dates( $input_date ) {
		$this->assertFalse( wp_exif_datetime( $input_date ) );
	}

	/**
	 * Data provider for valid dates
	 */
	public function provideValidDates() {
		return [
			// Unix timestamps
			'unix timestamp'        => [
				'1710500000',
				'0000:06:02 17:10:50'
			],
			// MySQL format
			'mysql datetime'        => [
				'2024-03-15 14:30:00',
				'2024:03:15 14:30:00'
			],
			// Already in EXIF format
			'exif format'           => [
				'2024:03:15 14:30:00',
				'2024:03:15 14:30:00'
			],
			// Date only
			'mysql date only'       => [
				'2024-03-15',
				'2024:03:15 00:00:00'
			],
			// Different time formats
			'with seconds'          => [
				'2024-03-15 14:30:45',
				'2024:03:15 14:30:45'
			],
			'time with T separator' => [
				'2024-03-15T14:30:00',
				'2024:03:15 14:30:00'
			],
			'incomplete date'       => [
				'2024-03',
				'2024:03:01 00:00:00'
			],
			'future year'           => [
				'2525-03-15 14:30:00',
				'2525:03:15 14:30:00'
			],
		];
	}

	/**
	 * Data provider for invalid dates
	 */
	public function provideInvalidDates() {
		return [
			'empty string'         => [ '' ],
			'0'                    => [ '0' ],
			'null'                 => [ null ],
			'boolean false'        => [ false ],
			'boolean true'         => [ true ],
			'unix timestamp'       => [ 1710500000 ],
			'invalid format'       => [ 'not a date' ],
			'invalid month'        => [ '2024-13-15' ],
			'invalid day'          => [ '2024-03-32' ],
			'invalid time'         => [ '2024-03-15 25:00:00' ],
			'garbage with numbers' => [ '2024abc15' ],
		];
	}

	/**
	 * Test timezone handling
	 */
	public function test_timezone_handling() {
		$original_timezone = date_default_timezone_get();

		// Test with different timezones
		date_default_timezone_set( 'UTC' );
		$utc_result = wp_exif_datetime( '2024-03-15 14:30:00' );

		date_default_timezone_set( 'America/New_York' );
		$ny_result = wp_exif_datetime( '2024-03-15 14:30:00' );

		// Results should be consistent regardless of timezone
		$this->assertEquals( $utc_result, $ny_result );

		// Restore original timezone
		date_default_timezone_set( $original_timezone );
	}

	/**
	 * Test handling of edge cases
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
	 * Test input with different separators
	 */
	public function test_different_separators() {

		$datetime = wp_exif_datetime( '2024/03/15 14:30:00' );
		$this->assertEquals( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );

		$datetime = wp_exif_datetime( '2024/03/15 14:30:00' );
		$this->assertEquals( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );
	}
}
