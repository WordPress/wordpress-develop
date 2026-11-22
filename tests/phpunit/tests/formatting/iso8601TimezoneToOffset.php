<?php

/**
 * Tests for iso8601_timezone_to_offset().
 *
 * @group formatting
 *
 * @covers ::iso8601_timezone_to_offset
 */
class Tests_Formatting_Iso8601TimezoneToOffset extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_iso8601_timezone_to_offset
	 *
	 * @param string    $timezone ISO 8601 timezone string.
	 * @param int|float $expected Offset in seconds.
	 */
	public function test_iso8601_timezone_to_offset( $timezone, $expected ) {
		// Function is documented to return int|float; compare numerically.
		$this->assertEquals( $expected, iso8601_timezone_to_offset( $timezone ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_iso8601_timezone_to_offset() {
		return array(
			'Zulu / UTC'           => array( 'Z', 0 ),
			'zero offset'          => array( '+0000', 0 ),
			'positive half hour'   => array( '+0030', 0.5 * HOUR_IN_SECONDS ),
			'positive hours mins'  => array( '+0530', 5.5 * HOUR_IN_SECONDS ),
			'negative whole hours' => array( '-0800', -8 * HOUR_IN_SECONDS ),
			'negative hours mins'  => array( '-0530', -5.5 * HOUR_IN_SECONDS ),
		);
	}
}
