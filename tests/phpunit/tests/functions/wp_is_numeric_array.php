wp_is_numeric_array<?php

/**
 * @group formatting
 * @group functions.php
 * @covers ::wp_is_numeric_array
 */
class Tests_Functions_wp_is_numeric_array extends WP_UnitTestCase {

	/**
	 * @ticket 53971
	 *
	 * @dataProvider data_wp_is_numeric_array
	 *
	 * @param array $test_array Test value.
	 * @param array $expected   Expected return value.
	 */
	function test_wp_is_numeric_array( $test_array, $expected ) {
		$this->assertSame( $expected, wp_is_numeric_array( $test_array ) );
	}

	/**
	 * Data provider for test_add_magic_quotes.
	 *
	 * @return array[] Test parameters {
	 *     @type array $test_array Test value.
	 *     @type array $expected   Expected return value.
	 * }
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
