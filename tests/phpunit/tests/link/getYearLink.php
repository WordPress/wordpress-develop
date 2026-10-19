<?php

/**
 * Tests for get_year_link().
 *
 * @group link
 *
 * @covers ::get_year_link
 */
class Tests_Link_GetYearLink extends WP_UnitTestCase {

	/**
	 * Tests that get_year_link() returns the plain query string format when permalinks are disabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_years
	 *
	 * @param int|string $year Year input.
	 */
	public function test_get_year_link_plain_permalinks( $year ) {
		$this->set_permalink_structure( '' );

		$expected = home_url( '?m=' . $year );
		$this->assertSame( $expected, get_year_link( $year ) );
	}

	/**
	 * Tests that get_year_link() falls back to the current year when given false or empty input with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_years
	 *
	 * @param mixed $empty_year Empty year input.
	 */
	public function test_get_year_link_plain_permalinks_fallback_to_current_year( $empty_year ) {
		$this->set_permalink_structure( '' );

		$expected = home_url( '?m=' . current_time( 'Y' ) );
		$this->assertSame( $expected, get_year_link( $empty_year ) );
	}

	/**
	 * Tests that get_year_link() returns pretty permalink structure when enabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_years
	 *
	 * @param int|string $year Year input.
	 */
	public function test_get_year_link_pretty_permalinks( $year ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected = home_url( '/' . $year . '/' );
		$this->assertSame( $expected, get_year_link( $year ) );
	}

	/**
	 * Tests that get_year_link() falls back to the current year when given false or empty input with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_years
	 *
	 * @param mixed $empty_year Empty year input.
	 */
	public function test_get_year_link_pretty_permalinks_fallback_to_current_year( $empty_year ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected = home_url( '/' . current_time( 'Y' ) . '/' );
		$this->assertSame( $expected, get_year_link( $empty_year ) );
	}

	/**
	 * Tests that get_year_link() respects permastructs without a trailing slash.
	 *
	 * @ticket 65819
	 */
	public function test_get_year_link_pretty_permalinks_without_trailing_slash() {
		$this->set_permalink_structure( '/%year%/%postname%' );

		$this->assertSame( home_url( '/2024' ), get_year_link( 2024 ) );
	}

	/**
	 * Tests that the 'year_link' filter modifies the return value and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_year_link_filter() {
		$this->set_permalink_structure( '' );

		$filter = new MockAction();
		add_filter( 'year_link', array( $filter, 'filter' ), 10, 2 );

		$year   = 2024;
		$actual = get_year_link( $year );

		$this->assertSame( home_url( '?m=2024' ), $actual );
		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( home_url( '?m=2024' ), $filter_args[0][0] );
		$this->assertSame( 2024, $filter_args[0][1] );

		// Verify filter receives fallback year when given empty or falsy input.
		get_year_link( false );
		$filter_args = $filter->get_args();
		$this->assertSame( current_time( 'Y' ), $filter_args[1][1] );
	}

	/**
	 * Tests that the 'year_link' filter can customize the returned URL.
	 *
	 * @ticket 65819
	 */
	public function test_year_link_filter_custom_url() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$custom_callback = static function ( $yearlink, $year ) {
			return 'https://example.org/custom-archive/' . $year;
		};

		add_filter( 'year_link', $custom_callback, 10, 2 );
		$actual = get_year_link( 2026 );

		$this->assertSame( 'https://example.org/custom-archive/2026', $actual );
	}

	/**
	 * Data provider of valid year inputs.
	 *
	 * @return array[]
	 */
	public static function data_valid_years() {
		return array(
			'integer year'        => array( 2024 ),
			'string integer year' => array( '2024' ),
			'past year'           => array( 1999 ),
			'future year'         => array( 2050 ),
		);
	}

	/**
	 * Data provider of empty or falsy year inputs that trigger fallback.
	 *
	 * @return array[]
	 */
	public static function data_empty_years() {
		return array(
			'boolean false' => array( false ),
			'integer zero'  => array( 0 ),
			'string zero'   => array( '0' ),
			'empty string'  => array( '' ),
			'null'          => array( null ),
		);
	}
}
