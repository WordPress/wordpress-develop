<?php

/**
 * @group formatting
 * @ticket 38773
 *
 * @covers ::human_time_diff
 */
class Tests_Formatting_HumanTimeDiff extends WP_UnitTestCase {

	/**
	 * @group formatting
	 * @ticket 38773
	 * @dataProvider data_human_time_diff
	 */
	public function test_human_time_diff( $expected, $stopdate, $message ) {
		$startdate = new DateTime( '2016-01-01 12:00:00' );
		$this->assertSame( $expected, human_time_diff( $startdate->format( 'U' ), $stopdate->format( 'U' ) ), $message );
	}

	// Data for test_human_time_diff.
	public function data_human_time_diff() {
		return array(
			array(
				'37 seconds',
				new DateTime( '2016-01-01 12:00:37' ),
				'Test a difference of 37 seconds.',
			),
			array(
				'5 minutes',
				new DateTime( '2016-01-01 12:05:00' ),
				'Test a difference of 5 minutes.',
			),
			array(
				'1 hour',
				new DateTime( '2016-01-01 13:00:00' ),
				'Test a difference of 1 hour.',
			),
			array(
				'2 days',
				new DateTime( '2016-01-03 12:00:00' ),
				'Test a difference of 2 days.',
			),
			array(
				'2 hours',
				new DateTime( '2016-01-01 14:29:59' ),
				'Test a difference of 2 hours, 29 minutes and 59 seconds  - should round down to 2 hours.',
			),
			array(
				'3 hours',
				new DateTime( '2016-01-01 14:30:00' ),
				'Test a difference of 2 hours and 30 minutes - should round up to 3 hours.',
			),
			array(
				'2 months',
				new DateTime( '2016-02-15 12:00:00' ),
				'Test a difference of 1 month and 15 days - should round up to 2 months.',
			),
			array(
				'1 month',
				new DateTime( '2016-02-14 12:00:00' ),
				'Test a difference of 1 month and 14 days - should round down to 1 month.',
			),
			array(
				'3 years',
				new DateTime( '2018-07-02 12:00:00' ),
				'Test a difference of 2 years 6 months and 1 day, should round up to 3 years.',
			),
			array(
				'2 years',
				new DateTime( '2018-07-01 12:00:00' ),
				'Test a difference of 2 years 6 months, should round down to 2 years.',
			),
		);
	}

	/**
	 * Tests the output at, and one second below, each unit threshold.
	 *
	 * @dataProvider data_human_time_diff_unit_boundaries
	 *
	 * @param non-negative-int $diff     Difference in seconds.
	 * @param string           $expected Expected human-readable difference.
	 */
	public function test_human_time_diff_unit_boundaries( int $diff, string $expected ): void {
		$from = 1451649600; // 2016-01-01 12:00:00 UTC.

		$this->assertSame( $expected, human_time_diff( $from, $from + $diff ), 'Expected forward difference to match.' );
		$this->assertSame( $expected, human_time_diff( $from + $diff, $from ), 'Expected backward difference to match.' );
	}

	/**
	 * Data provider for {@see self::test_human_time_diff_unit_boundaries()}.
	 *
	 * @return array<non-falsy-string, array{ 0: non-negative-int, 1: non-falsy-string }>
	 */
	public function data_human_time_diff_unit_boundaries(): array {
		return array(
			'no difference'             => array( 0, '1 second' ),
			'1 second'                  => array( 1, '1 second' ),
			'one second below a minute' => array( MINUTE_IN_SECONDS - 1, '59 seconds' ),
			'exactly a minute'          => array( MINUTE_IN_SECONDS, '1 minute' ),
			'one second below an hour'  => array( HOUR_IN_SECONDS - 1, '60 minutes' ),
			'exactly an hour'           => array( HOUR_IN_SECONDS, '1 hour' ),
			'one second below a day'    => array( DAY_IN_SECONDS - 1, '24 hours' ),
			'exactly a day'             => array( DAY_IN_SECONDS, '1 day' ),
			'one second below a week'   => array( WEEK_IN_SECONDS - 1, '7 days' ),
			'exactly a week'            => array( WEEK_IN_SECONDS, '1 week' ),
			'one second below a month'  => array( MONTH_IN_SECONDS - 1, '4 weeks' ),
			'exactly a month'           => array( MONTH_IN_SECONDS, '1 month' ),
			'one second below a year'   => array( YEAR_IN_SECONDS - 1, '12 months' ),
			'exactly a year'            => array( YEAR_IN_SECONDS, '1 year' ),
		);
	}
}
