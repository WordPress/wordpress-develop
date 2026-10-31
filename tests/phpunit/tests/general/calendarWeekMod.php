<?php

/**
 * Tests for calendar_week_mod().
 *
 * @group general
 *
 * @covers ::calendar_week_mod
 */
class Tests_General_CalendarWeekMod extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_calendar_week_mod
	 *
	 * @param int   $num      Number of day.
	 * @param float $expected Days since the start of the week.
	 */
	public function test_calendar_week_mod( $num, $expected ) {
		$this->assertSame( $expected, calendar_week_mod( $num ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_calendar_week_mod() {
		return array(
			'start of week'          => array( 0, 0.0 ),
			'in-week day'            => array( 6, 6.0 ),
			'exact week boundary'    => array( 7, 0.0 ),
			'day after wrap'         => array( 8, 1.0 ),
			'two weeks'              => array( 14, 0.0 ),
			'negative offset'        => array( -1, 6.0 ),
			'negative week boundary' => array( -7, 0.0 ),
			'negative beyond week'   => array( -8, 6.0 ),
		);
	}
}
