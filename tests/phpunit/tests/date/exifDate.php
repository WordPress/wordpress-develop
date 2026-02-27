<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_Exif extends WP_UnitTestCase {

	/**
	 * @ticket 49413
	 *
	 * @dataProvider data_wp_exif_datetime
	 *
	 * @param string      $date_string Date string in EXIF format (Y:m:d H:i:s).
	 * @param string|null $timezone    Optional. Timezone or offset string.
	 * @param string|bool $expected    Expected RFC3339 formatted date string or false for invalid input.
	 */
	public function test_wp_exif_datetime( $date_string, $timezone, $expected ) {
		$datetime = wp_exif_datetime( $date_string, $timezone );
		if ( false === $expected ) {
			$this->assertFalse( $datetime );
		} else {
			$this->assertInstanceOf( 'DateTimeImmutable', $datetime );
			$this->assertSame( $expected, $datetime->format( DATE_RFC3339 ) );
		}
	}

	public function data_wp_exif_datetime() {
		return array(
			'valid date without timezone' => array(
				'2004:07:22 17:14:35',
				null,
				'2004-07-22T17:14:35+00:00',
			),
			'valid date with timezone'    => array(
				'2004:07:22 17:14:35',
				'+05:30',
				'2004-07-22T17:14:35+05:30',
			),
			'invalid date format'         => array(
				'not a date',
				null,
				false,
			),
			'invalid timezone'            => array(
				'2004:07:22 17:14:35',
				'Invalid/Timezone',
				false,
			),
		);
	}

	/**
	 * @ticket 49413
	 *
	 * @dataProvider data_wp_exif_date2ts
	 *
	 * @param string   $date_string Date string in EXIF format (Y:m:d H:i:s).
	 * @param int|bool $expected    Expected Unix timestamp or false for invalid input.
	 */
	public function test_wp_exif_date2ts( $date_string, $expected ) {
		$timestamp = wp_exif_date2ts( $date_string );
		$this->assertSame( $expected, $timestamp );
	}

	public function data_wp_exif_date2ts() {
		return array(
			'valid date'          => array(
				'2004:07:22 17:14:35',
				1090516475,
			),
			'invalid date format' => array(
				'not a date',
				false,
			),
		);
	}
}
