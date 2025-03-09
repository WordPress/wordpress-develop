<?php

/**
 * Tests for the wp_timezone_override_offset() function.
 *
 * @group functions
 *
 * @covers ::wp_timezone_override_offset
 */
class Tests_Functions_wpTimezoneOverrideOffset extends WP_UnitTestCase {

	/**
	 * @ticket 59980
	 *
	 * @dataProvider data_wp_timezone_override_offset
	 */
	public function test_wp_timezone_override_offset( $timezone_string, $expected ) {
		update_option( 'timezone_string', $timezone_string );
		$this->assertSame( $expected, wp_timezone_override_offset() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string $timezone_string Test value.
	 *     @type string $expected        Expected return value.
	 * }
	 */
	public function data_wp_timezone_override_offset() {
		return array(
			'no timezone string option set' => array( '', false ),
			'bad option set'                => array( 'BAD_TIME_ZONE', false ),
			'UTC option set'                => array( 'UTC', 0.0 ),
			'EST option set'                => array( 'EST', -5.0 ),
			'NST option set'                => array( 'America/St_Johns', $this->get_expected_offset( 'America/St_Johns' ) ),
		);
	}

	/**
	 * Gets the expected timezone offset in hours for a given timezone.
	 *
	 * This function determines the current offset (including daylight saving time adjustments)
	 * for the provided timezone string.
	 *
	 * @param string $timezone_string The timezone identifier (e.g., 'America/St_Johns').
	 * @return float The current timezone offset in hours.
	 */
	private function get_expected_offset( $timezone_string ) {
		$timezone = new DateTimeZone( $timezone_string );
		$datetime = new DateTime( 'now', $timezone );
		return $timezone->getOffset( $datetime ) / 3600;
	}
}
