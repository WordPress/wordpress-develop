<?php

/**
 * Class Tests_Admin_wpExifDate2ts
 *
 * Contains unit tests for wp_exif_date2ts(), which converts an Exif date
 * string to a unix timestamp. Coverage for the underlying parsing lives in
 * Tests_Admin_wpExifDatetime.
 *
 * @group admin
 * @group image
 *
 * @covers ::wp_exif_date2ts
 */
class Tests_Admin_wpExifDate2ts extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Pin the site timezone so the expected timestamps are deterministic.
		update_option( 'timezone_string', 'UTC' );
	}

	/**
	 * @ticket 56887
	 *
	 * Test that valid date strings are converted to the expected timestamp.
	 *
	 * @dataProvider data_valid_dates
	 *
	 * @param string $input_date The date string to convert.
	 * @param int    $expected   The expected unix timestamp.
	 *
	 * @return void
	 */
	public function test_valid_dates( $input_date, $expected ) {
		$this->assertSame( $expected, wp_exif_date2ts( $input_date ) );
	}

	/**
	 * @ticket 56887
	 *
	 * Test that invalid input returns false rather than throwing.
	 *
	 * @dataProvider data_invalid_dates
	 *
	 * @param mixed $input_date The value to convert.
	 *
	 * @return void
	 */
	public function test_returns_false_for_invalid_input( $input_date ) {
		$this->assertFalse( wp_exif_date2ts( $input_date ) );
	}

	/**
	 * Data provider for valid dates.
	 *
	 * @return array[]
	 */
	public function data_valid_dates() {
		return array(
			'exif format'               => array(
				'2024:03:15 14:30:00',
				1710513000,
			),
			'mysql format'              => array(
				'2024-03-15 14:30:00',
				1710513000,
			),
			'mysql format with seconds' => array(
				'2024-03-15 14:30:45',
				1710513045,
			),
			'date only'                 => array(
				'2024-03-15',
				1710460800,
			),
			'incomplete date'           => array(
				'2024-03',
				1709251200,
			),
		);
	}

	/**
	 * Data provider for invalid dates.
	 *
	 * @return array[]
	 */
	public function data_invalid_dates() {
		return array(
			'empty string'        => array( '' ),
			'null'                => array( null ),
			'boolean true'        => array( true ),
			'boolean false'       => array( false ),
			'array'               => array( array() ),
			'object'              => array( new stdClass() ),
			'invalid date string' => array( 'not a date' ),
			'invalid month'       => array( '2024-13-15' ),
			'invalid day'         => array( '2024-03-32' ),
			'invalid hour'        => array( '2024-03-15 25:00:00' ),
		);
	}
}
