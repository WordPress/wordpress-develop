<?php

/**
 * Tests for get_day_link().
 *
 * @group link
 *
 * @covers ::get_day_link
 */
class Tests_Link_GetDayLink extends WP_UnitTestCase {

	/**
	 * Tests that get_day_link() returns the plain query string format when permalinks are disabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_dates
	 *
	 * @param int|string $year                  Year input.
	 * @param int|string $month                 Month input.
	 * @param int|string $day                   Day input.
	 * @param string     $expected_month_padded Expected two-digit padded month.
	 * @param string     $expected_day_padded   Expected two-digit padded day.
	 */
	public function test_get_day_link_plain_permalinks( $year, $month, $day, $expected_month_padded, $expected_day_padded ) {
		$this->set_permalink_structure( '' );

		$expected = home_url( '?m=' . $year . $expected_month_padded . $expected_day_padded );
		$this->assertSame( $expected, get_day_link( $year, $month, $day ) );
	}

	/**
	 * Tests that get_day_link() falls back to current year, month, or day when given empty inputs with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_falsy_inputs
	 *
	 * @param mixed $year  Year input.
	 * @param mixed $month Month input.
	 * @param mixed $day   Day input.
	 */
	public function test_get_day_link_plain_permalinks_fallback( $year, $month, $day ) {
		$this->set_permalink_structure( '' );

		$expected_year  = $year ? $year : current_time( 'Y' );
		$expected_month = $month ? zeroise( $month, 2 ) : current_time( 'm' );
		$expected_day   = $day ? zeroise( $day, 2 ) : zeroise( current_time( 'j' ), 2 );

		$expected = home_url( '?m=' . $expected_year . $expected_month . $expected_day );
		$this->assertSame( $expected, get_day_link( $year, $month, $day ) );
	}

	/**
	 * Tests that get_day_link() returns pretty permalink structure when enabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_dates
	 *
	 * @param int|string $year                  Year input.
	 * @param int|string $month                 Month input.
	 * @param int|string $day                   Day input.
	 * @param string     $expected_month_padded Expected two-digit padded month.
	 * @param string     $expected_day_padded   Expected two-digit padded day.
	 */
	public function test_get_day_link_pretty_permalinks( $year, $month, $day, $expected_month_padded, $expected_day_padded ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected = home_url( '/' . $year . '/' . $expected_month_padded . '/' . $expected_day_padded . '/' );
		$this->assertSame( $expected, get_day_link( $year, $month, $day ) );
	}

	/**
	 * Tests that get_day_link() falls back to current year, month, or day when given empty inputs with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_falsy_inputs
	 *
	 * @param mixed $year  Year input.
	 * @param mixed $month Month input.
	 * @param mixed $day   Day input.
	 */
	public function test_get_day_link_pretty_permalinks_fallback( $year, $month, $day ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected_year  = $year ? $year : current_time( 'Y' );
		$expected_month = $month ? zeroise( (int) $month, 2 ) : current_time( 'm' );
		$expected_day   = $day ? zeroise( (int) $day, 2 ) : zeroise( current_time( 'j' ), 2 );

		$expected = home_url( '/' . $expected_year . '/' . $expected_month . '/' . $expected_day . '/' );
		$this->assertSame( $expected, get_day_link( $year, $month, $day ) );
	}

	/**
	 * Tests that the 'day_link' filter modifies the return value and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_day_link_filter() {
		$this->set_permalink_structure( '' );

		$filter = new MockAction();
		add_filter( 'day_link', array( $filter, 'filter' ), 10, 4 );

		$year   = 2024;
		$month  = 6;
		$day    = 15;
		$actual = get_day_link( $year, $month, $day );

		$this->assertSame( home_url( '?m=20240615' ), $actual );
		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( home_url( '?m=20240615' ), $filter_args[0][0] );
		$this->assertSame( 2024, $filter_args[0][1] );
		$this->assertSame( 6, $filter_args[0][2] );
		$this->assertSame( 15, $filter_args[0][3] );
	}

	/**
	 * Tests that the 'day_link' filter can customize the returned URL.
	 *
	 * @ticket 65819
	 */
	public function test_day_link_filter_custom_url() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$custom_callback = static function ( $daylink, $year, $month, $day ) {
			return 'https://example.org/daily/' . $year . '/' . $month . '/' . $day;
		};

		add_filter( 'day_link', $custom_callback, 10, 4 );
		$actual = get_day_link( 2025, 11, 23 );
		remove_filter( 'day_link', $custom_callback, 10 );

		$this->assertSame( 'https://example.org/daily/2025/11/23', $actual );
	}

	/**
	 * Data provider of valid dates (year, month, day).
	 *
	 * @return array[]
	 */
	public static function data_valid_dates() {
		return array(
			'single digit month and day as integers' => array( 2024, 4, 5, '04', '05' ),
			'single digit month and day as strings'  => array( '2024', '4', '5', '04', '05' ),
			'two digit month and day as integers'    => array( 2024, 11, 25, '11', '25' ),
			'two digit month and day as strings'     => array( '2024', '11', '25', '11', '25' ),
			'first day of year'                      => array( 2023, 1, 1, '01', '01' ),
			'last day of year'                       => array( 2023, 12, 31, '12', '31' ),
			'past year date'                         => array( 1999, 9, 9, '09', '09' ),
			'future year date'                       => array( 2030, 2, 28, '02', '28' ),
		);
	}

	/**
	 * Data provider of falsy inputs that trigger fallbacks.
	 *
	 * @return array[]
	 */
	public static function data_falsy_inputs() {
		return array(
			'false year, valid month and day' => array( false, 5, 10 ),
			'valid year, false month and day' => array( 2024, false, false ),
			'valid year and month, false day' => array( 2024, 5, false ),
			'all false'                       => array( false, false, false ),
			'empty string year'               => array( '', 5, 10 ),
			'empty string month'              => array( 2024, '', 10 ),
			'empty string day'                => array( 2024, 5, '' ),
			'all null'                        => array( null, null, null ),
			'all zero'                        => array( 0, 0, 0 ),
		);
	}
}
