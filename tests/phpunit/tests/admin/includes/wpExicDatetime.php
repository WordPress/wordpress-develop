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
		$this->assertEquals( $expected, wp_exif_datetime( $input_date ) );
	}

	/**
	 * Test handling of invalid dates and exceptions
	 *
	 * @dataProvider provideInvalidDates
	 */
	public function test_invalid_dates( $input ) {
		$this->assertFalse( wp_exif_datetime( $input ) );
	}

	/**
	 * Data provider for valid dates
	 */
	public function provideValidDates() {
		return array(
			'unix timestamp'  => array(
				1710500000, // March 15, 2024 14:30:00
				'2024:03:15 14:30:00'
			),
			'mysql datetime'  => array(
				'2024-03-15 14:30:00',
				'2024:03:15 14:30:00'
			),
			'exif format'     => array(
				'2024:03:15 14:30:00',
				'2024:03:15 14:30:00'
			),
			'mysql date only' => array(
				'2024-03-15',
				'2024:03:15 00:00:00'
			)
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
			'incomplete date'         => array( '2024-03' ),
			'invalid month'           => array( '2024-13-15' ),
			'invalid day'             => array( '2024-03-32' ),
			'invalid time'            => array( '2024-03-15 25:00:00' ),
			'garbage with numbers'    => array( '2024abc15' ),
			'array input'             => array( array() ),
			'object input'            => array( new stdClass() ),
			'malformed timestamp'     => array( '@12345abc' ),
			'out of bounds timestamp' => array( 253402300800 ), // Year 9999
			'negative timestamp'      => array( - 62167219200 ) // Year 0
		);
	}

	/**
	 * Test that extreme edge cases return false instead of throwing exceptions
	 */
	public function test_edge_cases_return_false() {
		// Test extremely large numbers that might cause integer overflow
		$this->assertFalse( wp_exif_datetime( PHP_INT_MAX ) );
		$this->assertFalse( wp_exif_datetime( PHP_INT_MIN ) );

		// Test malformed date strings that might cause DateTime exceptions
		$this->assertFalse( wp_exif_datetime( '0000-00-00' ) );
		$this->assertFalse( wp_exif_datetime( '2024-02-31' ) ); // Invalid day in February

		// Test with resource type
		$fp = fopen( 'php://memory', 'r' );
		$this->assertFalse( wp_exif_datetime( $fp ) );
		fclose( $fp );
	}

	/**
	 * Test timezone handling with invalid timezone that might cause exceptions
	 */
	public function test_timezone_exceptions() {
		$original_timezone = date_default_timezone_get();

		// Test with invalid timezone
		$this->assertFalse( @date_default_timezone_set( 'Invalid/Timezone' ) );
		$result = wp_exif_datetime( '2024-03-15 14:30:00' );
		$this->assertFalse( $result );

		// Restore original timezone
		date_default_timezone_set( $original_timezone );
	}

	/**
	 * Test with potentially problematic character encodings
	 */
	public function test_encoding_issues() {
		// Test with UTF-8 BOM
		$this->assertFalse( wp_exif_datetime( "\xEF\xBB\xBF2024-03-15" ) );

		// Test with null bytes
		$this->assertFalse( wp_exif_datetime( "2024-03-15\0" ) );

		// Test with non-printable characters
		$this->assertFalse( wp_exif_datetime( "2024-03-15\n14:30:00" ) );
	}
}
