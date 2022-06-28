<?php

/**
 * @group functions.php
 * @covers ::wp_is_numeric_array
 */
class Tests_Functions_wpIsNumericArray extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_is_numeric_array
	 *
	 * @ticket 53971
	 *
	 * @param mixed $input    Input to test.
	 * @param array $expected Expected result.
	 */
	public function test_wp_is_numeric_array( $input, $expected ) {
		$this->assertSame( $expected, wp_is_numeric_array( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_is_numeric_array() {
		return array(
			'no index'             => array(
				'test_array' => array( 'www', 'eee' ),
				'expected'   => true,
			),
			'text index'           => array(
				'test_array' => array( 'www' => 'eee' ),
				'expected'   => false,
			),
			'numeric index'        => array(
				'test_array' => array( 99 => 'eee' ),
				'expected'   => true,
			),
			'- numeric index'      => array(
				'test_array' => array( -11 => 'eee' ),
				'expected'   => true,
			),
			'numeric string index' => array(
				'test_array' => array( '11' => 'eee' ),
				'expected'   => true,
			),
			'nested number index'  => array(
				'test_array' => array(
					'next' => array(
						11 => 'vvv',
					),
				),
				'expected'   => false,
			),
			'nested string index'  => array(
				'test_array' => array(
					'11' => array(
						'eee' => 'vvv',
					),
				),
				'expected'   => true,
			),
			'not an array'         => array(
				'test_array' => null,
				'expected'   => false,
			),
		);
	}
}
