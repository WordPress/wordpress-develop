<?php

/**
 * Test wp_fuzzy_number_match().
 *
 * @group functions.php
 * @covers ::wp_fuzzy_number_match
 */
class Tests_Functions_wpFuzzyNumberMatch extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_fuzzy_number_match
	 *
	 * @ticket 54239
	 *
	 * @param int|float $expected  The expected value.
	 * @param int|float $actual    The actual number.
	 * @param int|float $precision The allowed variation.
	 * @param bool      $result    Whether the numbers match within the specified precision.
	 */
	public function test_wp_fuzzy_number_match( $expected, $actual, $precision, $result ) {
		$this->assertSame( $result, wp_fuzzy_number_match( $expected, $actual, $precision ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type int|float $expected  The expected value.
	 *     @type int|float $actual    The actual number.
	 *     @type int|float $precision The allowed variation.
	 *     @type bool      $result    Whether the numbers match within the specified precision.
	 * }
	 */
	public function data_wp_fuzzy_number_match() {
		return array(
			'expected 1 int, actual 1 int'                => array(
				'expected'  => 1,
				'actual'    => 1,
				'precision' => 1,
				'result'    => true,
			),
			'expected 1 int, actual 2 int'                => array(
				'expected'  => 1,
				'actual'    => 2,
				'precision' => 1,
				'result'    => true,
			),
			'expected 1 int, actual 3 int'                => array(
				'expected'  => 1,
				'actual'    => 3,
				'precision' => 1,
				'result'    => false,
			),
			'expected 1 int, actual 1 string'             => array(
				'expected'  => 1,
				'actual'    => '1',
				'precision' => 1,
				'result'    => true,
			),
			'expected 1 int, actual 11 int, precision 10' => array(
				'expected'  => 1,
				'actual'    => 11,
				'precision' => 10,
				'result'    => true,
			),
			'expected 1 int, actual 12 int, precision 10' => array(
				'expected'  => 1,
				'actual'    => 12,
				'precision' => 10,
				'result'    => false,
			),
			'expected 1.234 float, actual 1 int'          => array(
				'expected'  => 1.234,
				'actual'    => 1,
				'precision' => 1,
				'result'    => true,
			),
			'expected 2.234 float, actual 2 int'          => array(
				'expected'  => 1.234,
				'actual'    => 2,
				'precision' => 1,
				'result'    => true,
			),
			'expected 1 int, actual 2.0001 float'         => array(
				'expected'  => 1,
				'actual'    => 2.0001,
				'precision' => 1,
				'result'    => false,
			),
			'expected 1 int, actual 3.23 float'           => array(
				'expected'  => 1,
				'actual'    => 3.234,
				'precision' => 1,
				'result'    => false,
			),
			'expected 1.2e1 float (12), actual 1.3e1 float (13)' => array(
				'expected'  => 1.2e1,
				'actual'    => 1.3e1,
				'precision' => 1,
				'result'    => true,
			),
			'expected 1.2e3 float (1200), actual 1.2e3 float, precision 1000' => array(
				'expected'  => 1.2e3,
				'actual'    => 1.2e3,
				'precision' => 1000,
				'result'    => true,
			),
		);
	}

}
