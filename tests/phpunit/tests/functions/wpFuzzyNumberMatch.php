<?php

/**
 * Test wp_fuzzy_number_match().
 *
 * @group functions.php
 * @covers ::wp_fuzzy_number_match
 */
class Tests_Functions_WpFuzzyNumberMatch extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_fuzzy_number_match
	 *
	 * @param $expected
	 * @param $actual
	 * @param $precision
	 * @param $result
	 */
	public function test_wp_fuzzy_number_match( $expected, $actual, $precision, $result ) {
		$this->assertEquals( $result, wp_fuzzy_number_match( $expected, $actual, $precision ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_fuzzy_number_match() {
		return array(
			1            => array(
				'expected'  => 1,
				'actual'    => 1,
				'precision' => 1,
				'result'    => true,
			),
			2            => array(
				'expected'  => 1,
				'actual'    => 2,
				'precision' => 1,
				'result'    => true,
			),
			3            => array(
				'expected'  => 1,
				'actual'    => 3,
				'precision' => 1,
				'result'    => false,
			),
			'1 string'   => array(
				'expected'  => 1,
				'actual'    => '1',
				'precision' => 1,
				'result'    => true,
			),
			'11 with 10' => array(
				'expected'  => 1,
				'actual'    => 11,
				'precision' => 10,
				'result'    => true,
			),
			'12 with 10' => array(
				'expected'  => 1,
				'actual'    => 12,
				'precision' => 10,
				'result'    => false,
			),
		);

	}

}
