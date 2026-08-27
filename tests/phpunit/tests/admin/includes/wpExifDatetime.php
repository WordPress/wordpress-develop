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
		$this->assertInstanceOf( 'DateTimeImmutable', $datetime );
		$this->assertSame( $expected, $datetime->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * @ticket 56887
	 *
	 * Test handling of invalid date inputs.
	 *
	 * @dataProvider provideInvalidDates
	 *
	 * @param mixed $input_date The value to be tested for validation.
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
			'mysql datetime'  => array(
				'2024-03-15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'exif format'     => array(
				'2024:03:15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'slash separated' => array(
				'2024/03/15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'no separators'   => array(
				'20240315 143000',
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
	 * Data provider for invalid date inputs.
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
			'day rolled into a month' => array( '2024:02:30 00:00:00' ),
			'unset exif field'        => array( '0000:00:00 00:00:00' ),
			'garbage with numbers'    => array( '2024abc15' ),
			'array input'             => array( array() ),
			'object input'            => array( new stdClass() ),
			'out of bounds timestamp' => array( 253402300800 ), // Year 9999
			'negative timestamp'      => array( -62167219200 ), // Year 0
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
	 * Test that an offset from the Exif OffsetTime tags is applied.
	 *
	 * @return void
	 */
	public function test_timezone_argument_is_applied() {
		$datetime = wp_exif_datetime( '2024:03:15 14:30:00', '+09:00' );

		$this->assertSame( '+09:00', $datetime->format( 'P' ) );
		$this->assertSame( '2024:03:15 14:30:00', $datetime->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * @ticket 56887
	 *
	 * Test that an unusable timezone argument falls back to the site timezone instead of
	 * erroring out. Exif data is untrusted, so the tags can hold anything.
	 *
	 * @dataProvider data_unusable_timezones
	 *
	 * @param mixed $timezone The timezone argument to pass.
	 *
	 * @return void
	 */
	public function test_unusable_timezone_falls_back_to_site_timezone( $timezone ) {
		$datetime = wp_exif_datetime( '2024:03:15 14:30:00', $timezone );

		$this->assertInstanceOf( 'DateTimeImmutable', $datetime );
		$this->assertSame( wp_timezone()->getName(), $datetime->getTimezone()->getName() );
	}

	/**
	 * Data provider for timezone arguments that cannot be used.
	 *
	 * @return array[]
	 */
	public function data_unusable_timezones() {
		return array(
			'null'          => array( null ),
			'empty string'  => array( '' ),
			'array'         => array( array() ),
			'object'        => array( new stdClass() ),
			'boolean true'  => array( true ),
			'boolean false' => array( false ),
		);
	}

	/**
	 * @ticket 56887
	 *
	 * Test that an unrecognized timezone string returns false rather than throwing.
	 *
	 * @return void
	 */
	public function test_invalid_timezone_string_returns_false() {
		$this->assertFalse( wp_exif_datetime( '2024:03:15 14:30:00', 'Not/AZone' ) );
	}
}
