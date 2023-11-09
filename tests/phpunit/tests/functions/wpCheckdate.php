<?php

/**
 * Tests for the wp_checkdate function.
 *
 * @ticket 59825
 *
 * @group date
 * @group functions
 *
 * @covers ::wp_checkdate
 */
class Tests_functions_wpCheckdate extends WP_UnitTestCase {

	/**
	 * Does it work.
	 *
	 */
	public function test_wp_checkdate() {

		$a1 = new MockAction();
		add_filter( 'wp_checkdate', array( $a1, 'filter' ) );
		$this->assertTrue( wp_checkdate( 1, 1, 1, 1 - 1 - 1 ) );
		$this->assertSame( 1, $a1->get_call_count() );
	}

	/**
	 * Will it take stings.
	 *
	 */
	public function test_wp_checkdate_strings() {

		$a1 = new MockAction();
		add_filter( 'wp_checkdate', array( $a1, 'filter' ) );

		$this->assertTrue( wp_checkdate( '1', '1', '1', '1-1-1' ) );
		$this->assertSame( 1, $a1->get_call_count() );
	}

	/**
	 * Check that the results of the filter is returned.
	 *
	 */
	public function test_wp_checkdate_passes_source_date_to_filter() {
		add_filter( 'wp_checkdate', array( $this, 'wp_checkdate_filter' ), 10, 2 );

		$this->assertsame( 'filtered', wp_checkdate( '1', '1', '1', 'source_date' ) );
	}

	/**
	 * Filter for test test_wp_checkdate_passes_source_date_to_filter().
	 *
	 *
	 * @param $is_date
	 * @param $source_date
	 *
	 * @return string
	 */
	public function wp_checkdate_filter( $is_date, $source_date ) {
		$this->assertSame( $source_date, 'source_date' );

		return 'filtered';
	}

	/**
	 * Check a bad date returns false.
	 *
	 */
	public function test_wp_checkdate_bad_date() {

		$a1 = new MockAction();
		add_filter( 'wp_checkdate', array( $a1, 'filter' ) );

		$this->assertFalse( wp_checkdate( '99', '1', '1', '1-1-1' ) );
		$this->assertSame( 1, $a1->get_call_count() );
	}
}
