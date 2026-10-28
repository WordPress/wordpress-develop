<?php

/**
 * Tests for get_month_link().
 *
 * @group link
 *
 * @covers ::get_month_link
 */
class Tests_Link_GetMonthLink extends WP_UnitTestCase {

	/**
	 * Tests that get_month_link() returns the plain query string format when permalinks are disabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_year_and_month
	 *
	 * @param int|string $year            Year input.
	 * @param int|string $month           Month input.
	 * @param string     $expected_padded Expected two-digit padded month.
	 */
	public function test_get_month_link_plain_permalinks( $year, $month, $expected_padded ) {
		$this->set_permalink_structure( '' );

		$expected = home_url( '?m=' . $year . $expected_padded );
		$this->assertSame( $expected, get_month_link( $year, $month ) );
	}

	/**
	 * Tests that get_month_link() falls back to current year and/or month when given empty inputs with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_falsy_inputs
	 *
	 * @param mixed $year  Year input.
	 * @param mixed $month Month input.
	 */
	public function test_get_month_link_plain_permalinks_fallback( $year, $month ) {
		$this->set_permalink_structure( '' );

		$expected_year  = $year ? $year : current_time( 'Y' );
		$expected_month = $month ? zeroise( $month, 2 ) : current_time( 'm' );

		$expected = home_url( '?m=' . $expected_year . $expected_month );
		$this->assertSame( $expected, get_month_link( $year, $month ) );
	}

	/**
	 * Tests that get_month_link() returns pretty permalink structure when enabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_year_and_month
	 *
	 * @param int|string $year            Year input.
	 * @param int|string $month           Month input.
	 * @param string     $expected_padded Expected two-digit padded month.
	 */
	public function test_get_month_link_pretty_permalinks( $year, $month, $expected_padded ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected = home_url( '/' . $year . '/' . $expected_padded . '/' );
		$this->assertSame( $expected, get_month_link( $year, $month ) );
	}

	/**
	 * Tests that get_month_link() falls back to current year and/or month when given empty inputs with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_falsy_inputs
	 *
	 * @param mixed $year  Year input.
	 * @param mixed $month Month input.
	 */
	public function test_get_month_link_pretty_permalinks_fallback( $year, $month ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected_year  = $year ? $year : current_time( 'Y' );
		$expected_month = $month ? zeroise( (int) $month, 2 ) : current_time( 'm' );

		$expected = home_url( '/' . $expected_year . '/' . $expected_month . '/' );
		$this->assertSame( $expected, get_month_link( $year, $month ) );
	}

	/**
	 * Tests that the 'month_link' filter modifies the return value and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_month_link_filter() {
		$this->set_permalink_structure( '' );

		$filter = new MockAction();
		add_filter( 'month_link', array( $filter, 'filter' ), 10, 3 );

		$year   = 2024;
		$month  = 6;
		$actual = get_month_link( $year, $month );

		$this->assertSame( home_url( '?m=202406' ), $actual );
		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( home_url( '?m=202406' ), $filter_args[0][0] );
		$this->assertSame( 2024, $filter_args[0][1] );
		$this->assertSame( 6, $filter_args[0][2] );
	}

	/**
	 * Tests that the 'month_link' filter can customize the returned URL.
	 *
	 * @ticket 65819
	 */
	public function test_month_link_filter_custom_url() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$custom_callback = static function ( $monthlink, $year, $month ) {
			return 'https://example.org/archives/' . $year . '-' . $month;
		};

		add_filter( 'month_link', $custom_callback, 10, 3 );
		$actual = get_month_link( 2025, 11 );
		remove_filter( 'month_link', $custom_callback, 10 );

		$this->assertSame( 'https://example.org/archives/2025-11', $actual );
	}

	/**
	 * Data provider of valid year and month pairs.
	 *
	 * @return array[]
	 */
	public static function data_valid_year_and_month() {
		return array(
			'single digit month as integer' => array( 2024, 4, '04' ),
			'two digit month as integer'    => array( 2024, 11, '11' ),
		);
	}

	/**
	 * Data provider of falsy inputs that trigger current year/month fallback.
	 *
	 * @return array[]
	 */
	public static function data_falsy_inputs() {
		return array(
			'false year and valid month' => array( false, 5 ),
			'valid year and false month' => array( 2024, false ),
			'both false'                 => array( false, false ),
		);
	}
}
