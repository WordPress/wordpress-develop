<?php

/**
 * Tests the wp_total_pages function.
 *
 * @group functions.php
 * @covers ::wp_total_pages
 */
class Tests_Functions_wpTotalPages extends WP_UnitTestCase {
	/**
	 * Provides test scenarios for all possible scenarios in wp_total_pages().
	 *
	 * @return array
	 */
	public function data_provider() {

		return array(
			'10-1'        => array(
				'total_items' => 10,
				'per_page'    => 1,
				'expected '   => 10,
			),
			'10-1-string' => array(
				'total_items' => '10',
				'per_page'    => '1',
				'expected '   => 10,
			),
			'10-11'       => array(
				'total_items' => 10,
				'per_page'    => 11,
				'expected '   => 1,
			),
			'10-19'       => array(
				'total_items' => 10,
				'per_page'    => 19,
				'expected '   => 1,
			),
			'101-20'      => array(
				'total_items' => 101,
				'per_page'    => 20,
				'expected '   => 6,
			),
		);
	}

	/**
	 * Tests wp_total_pages().
	 *
	 * @dataProvider data_provider
	 *
	 * @param int $total_items
	 * @param int $per_page
	 * @param bool $expected
	 *
	 * @ticket 30238
	 * @ticket 39868
	 */
	public function test_wp_total_pages( $total_items, $per_page, $expected ) {
		$this->assertSame( wp_total_pages( $total_items, $per_page ), $expected );
	}
}
