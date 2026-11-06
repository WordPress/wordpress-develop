<?php

/**
 * Tests for WP_Rewrite::get_year_permastruct(), get_month_permastruct() and get_day_permastruct().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::get_year_permastruct
 * @covers WP_Rewrite::get_month_permastruct
 * @covers WP_Rewrite::get_day_permastruct
 */
class Tests_Rewrite_GetYearMonthDayPermastruct extends WP_UnitTestCase {

	/**
	 * Returns a WP_Rewrite object that is initialized for a permalink structure.
	 *
	 * @param string $structure Permalink structure.
	 * @return WP_Rewrite The object.
	 */
	private function get_rewrite( $structure ) {
		update_option( 'permalink_structure', $structure );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->init();

		return $wp_rewrite;
	}

	/**
	 * Tests the structures of the years, months and days, for each order of the date.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures
	 *
	 * @param string $structure Permalink structure.
	 * @param string $year      Expected structure of the years.
	 * @param string $month     Expected structure of the months.
	 * @param string $day       Expected structure of the days.
	 */
	public function test_should_return_the_structures_of_the_parts_of_the_date( $structure, $year, $month, $day ) {
		$wp_rewrite = $this->get_rewrite( $structure );

		$this->assertSame( $year, $wp_rewrite->get_year_permastruct(), 'The structure of the years is not right.' );
		$this->assertSame( $month, $wp_rewrite->get_month_permastruct(), 'The structure of the months is not right.' );
		$this->assertSame( $day, $wp_rewrite->get_day_permastruct(), 'The structure of the days is not right.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_structures() {
		return array(
			'year, month and day' => array(
				'/%year%/%monthnum%/%day%/%postname%/',
				'/%year%/',
				'/%year%/%monthnum%/',
				'/%year%/%monthnum%/%day%',
			),
			'day, month and year' => array(
				'/%day%/%monthnum%/%year%/%postname%/',
				'/%year%',
				'/%monthnum%/%year%',
				'/%day%/%monthnum%/%year%',
			),
			'month, day and year' => array(
				'/%monthnum%/%day%/%year%/%postname%/',
				'/%year%',
				'/%monthnum%/%year%',
				'/%monthnum%/%day%/%year%',
			),
			'default order'       => array(
				'/%postname%/',
				'/%year%/',
				'/%year%/%monthnum%/',
				'/%year%/%monthnum%/%day%',
			),
			'a front'             => array(
				'/blog/%year%/%monthnum%/%day%/%postname%/',
				'/blog/%year%/',
				'/blog/%year%/%monthnum%/',
				'/blog/%year%/%monthnum%/%day%',
			),
			'post ID'             => array(
				'/archives/%post_id%',
				'/archives/date/%year%/',
				'/archives/date/%year%/%monthnum%/',
				'/archives/date/%year%/%monthnum%/%day%',
			),
		);
	}

	/**
	 * Tests that false is returned without permalinks, for each of the three.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_methods
	 *
	 * @param string $method Name of the method.
	 */
	public function test_should_return_false_without_permalinks( $method ) {
		$this->assertFalse( $this->get_rewrite( '' )->$method() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_methods() {
		return array(
			'year'  => array( 'get_year_permastruct' ),
			'month' => array( 'get_month_permastruct' ),
			'day'   => array( 'get_day_permastruct' ),
		);
	}

	/**
	 * Tests that the structure of the days is the structure of the dates.
	 *
	 * @ticket 66496
	 */
	public function test_should_use_the_structure_of_the_dates_for_days() {
		$wp_rewrite = $this->get_rewrite( '/%year%/%monthnum%/%postname%/' );

		$this->assertSame( $wp_rewrite->get_date_permastruct(), $wp_rewrite->get_day_permastruct() );
	}

	/**
	 * Tests that the structures do not have repeated slashes.
	 *
	 * @ticket 66496
	 */
	public function test_should_not_return_repeated_slashes() {
		$wp_rewrite = $this->get_rewrite( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertStringNotContainsString( '//', $wp_rewrite->get_year_permastruct() );
		$this->assertStringNotContainsString( '//', $wp_rewrite->get_month_permastruct() );
	}
}
