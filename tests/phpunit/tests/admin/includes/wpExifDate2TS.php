<?php

class Test_WP_Exif_Date2TS extends WP_UnitTestCase {

	/**
	 * Test conversion of valid EXIF date formats to timestamp
	 *
	 * @dataProvider provideValidExifDates
	 */
	public function test_valid_exif_dates( $date, $expected ) {
		$this->assertEquals( $expected, wp_exif_date2ts( $date ) );
	}

	/**
	 * Test handling of invalid EXIF dates
	 *
	 * @dataProvider provideInvalidExifDates
	 */
	public function test_invalid_exif_dates( $date ) {
		$this->assertFalse( wp_exif_date2ts( $date ) );
	}

	/**
	 * Data provider for valid EXIF dates
	 */
	public function provideValidExifDates() {
		return [
			'standard format'         => [
				'2024:03:15 14:30:45',
				strtotime( '2024:03:15 14:30:45' )
			],
			'slash format'            => [
				'2024/03/15 14:30:45',
				strtotime( '2024:03:15 14:30:45' )
			],
			'invalid format'          => [
				'2024-03-15',
				strtotime( '2024:03:15 00:00:00' )
			],
		];
	}

	/**
	 * Data provider for invalid EXIF dates
	 */
	public function provideInvalidExifDates() {
		return [
			'empty string'            => [ '' ],
			'null'                    => [ null ],
			'date only'               => [ '2024:03:15' ],
			'incomplete date'         => [ '2024:03' ],
			'garbage data'            => [ 'not a date' ],
			'wrong order'             => [ '15:03:2024 14:30:45' ],
			'with fractional seconds' => [ '2024:03:15 14:30:45.123' ]
		];
	}

	/**
	 * Test timezone handling
	 */
	public function test_timezone_handling() {
		$original_timezone = date_default_timezone_get();

		// Test with different timezone
		date_default_timezone_set( 'UTC' );
		$utc_timestamp = wp_exif_date2ts( '2024:03:15 14:30:45' );

		date_default_timezone_set( 'America/New_York' );
		$ny_timestamp = wp_exif_date2ts( '2024:03:15 14:30:45' );

		// The timestamps should be equal regardless of timezone
		$this->assertEquals( $utc_timestamp, $ny_timestamp );

		// Restore original timezone
		date_default_timezone_set( $original_timezone );
	}
}
