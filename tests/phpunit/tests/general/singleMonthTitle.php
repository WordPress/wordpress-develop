<?php

/**
 * Tests for the `single_month_title()` function.
 *
 * @group general
 * @group template
 */
class Tests_General_SingleMonthTitle extends WP_UnitTestCase {

	/**
	 * Tests the title built from the date query variables.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_month_title
	 *
	 * @dataProvider data_month_titles
	 *
	 * @param string $query    Query string of the request.
	 * @param string $prefix   Prefix passed to the function.
	 * @param string $expected Expected title.
	 */
	public function test_returns_month_title( $query, $prefix, $expected ) {
		$this->go_to( home_url( '/?' . $query ) );

		$this->assertSame( $expected, single_month_title( $prefix, false ) );
	}

	/**
	 * Data provider for test_returns_month_title().
	 *
	 * @return array[]
	 */
	public static function data_month_titles() {
		return array(
			'year and monthnum, no prefix'          => array( 'year=2024&monthnum=3', '', 'March2024' ),
			'year and monthnum, custom prefix'      => array( 'year=2024&monthnum=3', ' / ', ' / March / 2024' ),
			'm with full date'                      => array( 'm=20241105', ' ', ' November 2024' ),
			'year and monthnum take precedence'     => array( 'm=202411&year=2024&monthnum=3', ' ', ' March 2024' ),
			'monthnum without year falls back to m' => array( 'm=202411&monthnum=3', ' ', ' November 2024' ),
		);
	}

	/**
	 * Tests that false is returned without a valid month.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_month_title
	 *
	 * @dataProvider data_no_month
	 *
	 * @param string $query Query string of the request.
	 */
	public function test_returns_false_without_month( $query ) {
		$this->go_to( home_url( '/?' . $query ) );

		$this->assertFalse( single_month_title( ' ', false ) );
	}

	/**
	 * Data provider for test_returns_false_without_month().
	 *
	 * @return array[]
	 */
	public static function data_no_month() {
		return array(
			'no date query variables' => array( '' ),
			'm with year only'        => array( 'm=2024' ),
			'm with invalid month'    => array( 'm=202413' ),
		);
	}

	/**
	 * Tests that the title is echoed by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_month_title
	 */
	public function test_echoes_title_by_default() {
		$this->go_to( home_url( '/?year=2024&monthnum=3' ) );

		$this->expectOutputString( ' March 2024' );

		$this->assertNull( single_month_title( ' ' ) );
	}

	/**
	 * Tests that nothing is echoed and false is returned without a valid month.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_month_title
	 */
	public function test_echoes_nothing_without_month() {
		$this->go_to( home_url( '/?year=2024' ) );

		$this->expectOutputString( '' );

		$this->assertFalse( single_month_title( ' ' ) );
	}

	/**
	 * Tests that the month name comes from the locale.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_month_title
	 */
	public function test_uses_locale_month_name() {
		global $wp_locale;

		$original = $wp_locale->month['03'];

		$wp_locale->month['03'] = 'Marzo';

		try {
			$this->go_to( home_url( '/?year=2024&monthnum=3' ) );

			$this->assertSame( ' Marzo 2024', single_month_title( ' ', false ) );
		} finally {
			$wp_locale->month['03'] = $original;
		}
	}
}
