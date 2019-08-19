<?php

/**
 * @group date
 * @group datetime
 */
class Tests_WP_Timezone extends WP_UnitTestCase {

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

		$this->assertEquals( $tz_name, wp_timezone_string() );

		$timezone = wp_timezone();

		$this->assertEquals( $tz_name, $timezone->getName() );
	}

	/**
	 * @ticket 24730
	 */
	public function test_should_return_timezone_string() {
		update_option( 'timezone_string', 'Europe/Kiev' );

		$this->assertEquals( 'Europe/Kiev', wp_timezone_string() );

		$timezone = wp_timezone();

		$this->assertEquals( 'Europe/Kiev', $timezone->getName() );
	}

	/**
	 * Data provider to test numeric offset conversion.
	 *
	 * @return array
	 */
	public function timezone_offset_provider() {

		return [
			[ - 12, '-12:00' ],
			[ - 11.5, '-11:30' ],
			[ - 11, '-11:00' ],
			[ - 10.5, '-10:30' ],
			[ - 10, '-10:00' ],
			[ - 9.5, '-09:30' ],
			[ - 9, '-09:00' ],
			[ - 8.5, '-08:30' ],
			[ - 8, '-08:00' ],
			[ - 7.5, '-07:30' ],
			[ - 7, '-07:00' ],
			[ - 6.5, '-06:30' ],
			[ - 6, '-06:00' ],
			[ - 5.5, '-05:30' ],
			[ - 5, '-05:00' ],
			[ - 4.5, '-04:30' ],
			[ - 4, '-04:00' ],
			[ - 3.5, '-03:30' ],
			[ - 3, '-03:00' ],
			[ - 2.5, '-02:30' ],
			[ - 2, '-02:00' ],
			[ '-1.5', '-01:30' ],
			[ - 1.5, '-01:30' ],
			[ - 1, '-01:00' ],
			[ - 0.5, '-00:30' ],
			[ 0, '+00:00' ],
			[ '0', '+00:00' ],
			[ 0.5, '+00:30' ],
			[ 1, '+01:00' ],
			[ 1.5, '+01:30' ],
			[ '1.5', '+01:30' ],
			[ 2, '+02:00' ],
			[ 2.5, '+02:30' ],
			[ 3, '+03:00' ],
			[ 3.5, '+03:30' ],
			[ 4, '+04:00' ],
			[ 4.5, '+04:30' ],
			[ 5, '+05:00' ],
			[ 5.5, '+05:30' ],
			[ 5.75, '+05:45' ],
			[ 6, '+06:00' ],
			[ 6.5, '+06:30' ],
			[ 7, '+07:00' ],
			[ 7.5, '+07:30' ],
			[ 8, '+08:00' ],
			[ 8.5, '+08:30' ],
			[ 8.75, '+08:45' ],
			[ 9, '+09:00' ],
			[ 9.5, '+09:30' ],
			[ 10, '+10:00' ],
			[ 10.5, '+10:30' ],
			[ 11, '+11:00' ],
			[ 11.5, '+11:30' ],
			[ 12, '+12:00' ],
			[ 12.75, '+12:45' ],
			[ 13, '+13:00' ],
			[ 13.75, '+13:45' ],
			[ 14, '+14:00' ],
		];
	}
}


