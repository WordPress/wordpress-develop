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
	 * @param int|float $expected The expected value.
	 * @param int|float $actual The actual number.
	 * @param int|float $precision The allowed variation.
	 * @param bool $result Whether the numbers match within the specified precision.
	 *
	 * @return void
	 */
	public function test_wp_fuzzy_number_match( $expected, $actual, $precision, $result ) {
		$this->assertSame( $result, wp_fuzzy_number_match( $expected, $actual, $precision ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_fuzzy_number_match() {

		$tests = array(
			'1 int'                                  => array(
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
			'1.234 float'                            => array(
				'expected'  => 1.234,
				'actual'    => 1,
				'precision' => 1,
				'result'    => true,
			),
			'2.234 float'                            => array(
				'expected'  => 1.234,
				'actual'    => 2,
				'precision' => 1,
				'result'    => true,
			),
			'actual 2.0001 float'                    => array(
				'expected'  => 1,
				'actual'    => 2.0001,
				'precision' => 1,
				'result'    => false,
			),
			'3.23 float'                             => array(
				'expected'  => 1,
				'actual'    => 3.234,
				'precision' => 1,
				'result'    => false,
			),

			'1.2e1(twelve) to 1.3e1(thirteen) float' => array(
				'expected'  => 1.2e1,
				'actual'    => 1.3e1,
				'precision' => 1,
				'result'    => true,
			),

			'1.2e3 to 1.2e3 float one thousand and two hundred 1000' => array(
				'expected'  => 1.2e3,
				'actual'    => 1.2e3,
				'precision' => 1000,
				'result'    => true,
			),

		);

	}

}
