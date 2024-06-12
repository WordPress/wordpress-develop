<?php

/**
 * @group date
 * @group datetime
 * @covers ::wp_timezone_string
 * @covers ::wp_timezone
 */
class Tests_Date_wpTimezone extends WP_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset changed options to their default value.
		update_option( 'gmt_offset', 0 );
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}

	/**
	 * @ticket 24730
	 *
	 * @dataProvider timezone_offset_provider
	 *
	 * @param float  $gmt_offset Numeric offset from UTC.
	 * @param string $tz_name    Expected timezone name.
	 */
	public function test_should_convert_gmt_offset( $gmt_offset, $tz_name ) {
		delete_option( 'timezone_string' );
		update_option( 'gmt_offset', $gmt_offset );

		$this->assertSame( $tz_name, wp_timezone_string() );

		$timezone = wp_timezone();

		$this->assertSame( $tz_name, $timezone->getName() );
	}

	/**
	 * @ticket 24730
	 */
	public function test_should_return_timezone_string() {
		update_option( 'timezone_string', 'Europe/Helsinki' );

		$this->assertSame( 'Europe/Helsinki', wp_timezone_string() );

		$timezone = wp_timezone();

		$this->assertSame( 'Europe/Helsinki', $timezone->getName() );
	}

	/**
	 * Ensures that deprecated timezone strings are handled correctly.
	 *
	 * @ticket 56468
	 */
	public function test_should_return_deprecated_timezone_string() {
		$tz_string = 'America/Buenos_Aires'; // This timezone was deprecated pre-PHP 5.6.
		update_option( 'timezone_string', $tz_string );

		$this->assertSame( $tz_string, wp_timezone_string() );

		$timezone = wp_timezone();

		$this->assertSame( $tz_string, $timezone->getName() );
	}

	/**
	 * Data provider to test numeric offset conversion.
	 *
	 * @return array
	 */
	public function timezone_offset_provider() {
		return array(
			array( -12, '-12:00' ),
			array( -11.5, '-11:30' ),
			array( -11, '-11:00' ),
			array( -10.5, '-10:30' ),
			array( -10, '-10:00' ),
			array( -9.5, '-09:30' ),
			array( -9, '-09:00' ),
			array( -8.5, '-08:30' ),
			array( -8, '-08:00' ),
			array( -7.5, '-07:30' ),
			array( -7, '-07:00' ),
			array( -6.5, '-06:30' ),
			array( -6, '-06:00' ),
			array( -5.5, '-05:30' ),
			array( -5, '-05:00' ),
			array( -4.5, '-04:30' ),
			array( -4, '-04:00' ),
			array( -3.5, '-03:30' ),
			array( -3, '-03:00' ),
			array( -2.5, '-02:30' ),
			array( -2, '-02:00' ),
			array( '-1.5', '-01:30' ),
			array( -1.5, '-01:30' ),
			array( -1, '-01:00' ),
			array( -0.5, '-00:30' ),
			array( 0, '+00:00' ),
			array( '0', '+00:00' ),
			array( 0.5, '+00:30' ),
			array( 1, '+01:00' ),
			array( 1.5, '+01:30' ),
			array( '1.5', '+01:30' ),
			array( 2, '+02:00' ),
			array( 2.5, '+02:30' ),
			array( 3, '+03:00' ),
			array( 3.5, '+03:30' ),
			array( 4, '+04:00' ),
			array( 4.5, '+04:30' ),
			array( 5, '+05:00' ),
			array( 5.5, '+05:30' ),
			array( 5.75, '+05:45' ),
			array( 6, '+06:00' ),
			array( 6.5, '+06:30' ),
			array( 7, '+07:00' ),
			array( 7.5, '+07:30' ),
			array( 8, '+08:00' ),
			array( 8.5, '+08:30' ),
			array( 8.75, '+08:45' ),
			array( 9, '+09:00' ),
			array( 9.5, '+09:30' ),
			array( 10, '+10:00' ),
			array( 10.5, '+10:30' ),
			array( 11, '+11:00' ),
			array( 11.5, '+11:30' ),
			array( 12, '+12:00' ),
			array( 12.75, '+12:45' ),
			array( 13, '+13:00' ),
			array( 13.75, '+13:45' ),
			array( 14, '+14:00' ),
		);
	}
}
