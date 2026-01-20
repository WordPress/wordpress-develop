<?php

/**
 * Tests for the wp_parse_list() function.
 *
 * @group functions
 *
 * @covers ::wp_parse_list
 */
class Tests_Functions_wpParseList extends WP_UnitTestCase {

	/**
	 * @ticket 43977
	 *
	 * @dataProvider data_wp_parse_list
	 */
	public function test_wp_parse_list( $input_list, $expected ) {
		$this->assertSameSets( $expected, wp_parse_list( $input_list ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_parse_list() {
		return array(
			'ids only'           => array(
				'input_list' => '1,2,3,4',
				'expected'   => array( '1', '2', '3', '4' ),
			),
			'slugs only'         => array(
				'input_list' => 'apple,banana,carrot,dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'ids and slugs'      => array(
				'input_list' => '1,2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'space after comma'  => array(
				'input_list' => '1, 2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'double comma'       => array(
				'input_list' => '1,2,apple,,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'leading comma'      => array(
				'input_list' => ',1,2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'trailing comma'     => array(
				'input_list' => '1,2,apple,banana,',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'space before comma' => array(
				'input_list' => '1,2 ,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'empty string'       => array(
				'input_list' => '',
				'expected'   => array(),
			),
			'comma only'         => array(
				'input_list' => ',',
				'expected'   => array(),
			),
			'double comma only'  => array(
				'input_list' => ',,',
				'expected'   => array(),
			),
		);
	}
}
