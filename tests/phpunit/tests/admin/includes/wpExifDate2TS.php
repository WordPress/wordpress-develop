<?php

/**
 * @group admin
 * @group image
 */
class Tests_Admin_wpExifDate2ts extends WP_UnitTestCase {

	/**
	 * Test conversion of various date formats to EXIF format
	 *
	 * @dataProvider provideValidDates
	 */
	public function test_valid_dates( $input_date, $expected ) {
		$result = wp_exif_datetime( $input_date );
		$this->assertSame( $expected, $result->format( 'Y:m:d H:i:s' ) );
	}

	/**
	 * Test that invalid inputs return false
	 *
	 * @dataProvider provideInvalidDates
	 */
	public function test_returns_false_for_invalid_input( $input ) {
		$result = wp_exif_datetime( $input );
		$this->assertFalse( $result );
	}

	/**
	 * Data provider for valid dates
	 */
	public function provideValidDates() {
		return array(
			'mysql format'              => array(
				'2024-03-15 14:30:00',
				'2024:03:15 14:30:00',
			),
			'mysql format with seconds' => array(
				'2024-03-15 14:30:45',
				'2024:03:15 14:30:45',
			),
			'date only'                 => array(
				'2024-03-15',
				'2024:03:15 00:00:00',
			),
			'incomplete date'          => array(
				'2024-03',
				'2024:03:01 00:00:00',
			),
		);
	}

	/**
	 * Data provider for invalid dates
	 */
	public function provideInvalidDates() {
		return array(
			'empty string'        => array( '' ),
			'null'                => array( null ),
			'invalid date string' => array( 'not a date' ),
			'malformed date'      => array( '2024-13-45' ),
			'boolean true'        => array( true ),
			'boolean false'       => array( false ),
			'array'               => array( array() ),
			'object'              => array( new stdClass() ),
			'invalid month'       => array( '2024-13-15' ),
			'invalid day'         => array( '2024-03-32' ),
			'invalid hour'        => array( '2024-03-15 25:00:00' ),
		);
	}
}
