<?php

/**
 * Tests for the wp_checkdate() function.
 *
 * @group date
 * @group datetime
 * @group functions
 *
 * @covers ::wp_checkdate
 */
class Tests_Date_wpCheckdate extends WP_UnitTestCase {

	/**
	 * @ticket 59825
	 *
	 * @dataProvider data_wp_checkdate
	 *
	 * @param int|string $month       The month to check.
	 * @param int|string $day         The day to check.
	 * @param int|string $year        The year to check.
	 * @param string     $source_date The date to pass to the wp_checkdate filter.
	 * @param bool       $expected    The expected result.
	 */
	public function test_wp_checkdate( $month, $day, $year, $source_date, $expected ) {
		$this->assertSame( $expected, wp_checkdate( $month, $day, $year, $source_date ) );
	}

	/**
	 * Data provider for test_wp_checkdate().
	 *
	 * @return array
	 */
	public function data_wp_checkdate() {
		return array(
			'integers'              => array( 1, 1, 1, '1-1-1', true ),
			'strings'               => array( '1', '1', '1', '1-1-1', true ),
			'arbitrary source_date' => array( 1, 1, 1, 'arbitrary source_date', true ), // source_date is only used by the filter.
			'valid day'             => array( 2, 29, 2024, '2/29/2024', true ),         // 2024 is a leap year.
			'invalid day'           => array( 2, 29, 2023, '2/29/2023', false ),        // 2023 is not a leap year.
			'invalid month'         => array( 99, 1, 1, '1-1-1', false ),               // Month must be between 1 and 12.
			'invalid year'          => array( 1, 1, 0, '1-1-0', false ),                // Year must be between 1 and 32767.
		);
	}

	/**
	 * Checks that the filter overrides the return value.
	 */
	public function test_wp_checkdate_filter() {
		add_filter(
			'wp_checkdate',
			static function ( $is_valid_date, $source_date ) {
				if ( '2/29/2023' === $source_date ) {
					// Date is invalid, but return true anyway.
					return true;
				}

				return $is_valid_date;
			},
			10,
			2
		);

		// Test with an invalid date that the filter will return as valid.
		$this->assertTrue( wp_checkdate( '2', '29', '2023', '2/29/2023' ) );
	}
}
