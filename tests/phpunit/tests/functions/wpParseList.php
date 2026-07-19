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
	 *
	 * @param mixed[]|string $input_list
	 * @param list<scalar> $expected
	 */
	public function test_wp_parse_list( $input_list, array $expected ): void {
		$parsed_list = wp_parse_list( $input_list );
		$this->assertTrue( array_is_list( $parsed_list ), 'Expected value to be a list.' );
		$this->assertSameSets( $expected, $parsed_list );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input_list: mixed[]|string, expected: list<scalar> }>
	 */
	public function data_wp_parse_list(): array {
		return array(
			'ids only'            => array(
				'input_list' => '1,2,3,4',
				'expected'   => array( '1', '2', '3', '4' ),
			),
			'slugs only'          => array(
				'input_list' => 'apple,banana,carrot,dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'ids and slugs'       => array(
				'input_list' => '1,2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'space after comma'   => array(
				'input_list' => '1, 2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'double comma'        => array(
				'input_list' => '1,2,apple,,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'leading comma'       => array(
				'input_list' => ',1,2,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'trailing comma'      => array(
				'input_list' => '1,2,apple,banana,',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'space before comma'  => array(
				'input_list' => '1,2 ,apple,banana',
				'expected'   => array( '1', '2', 'apple', 'banana' ),
			),
			'empty string'        => array(
				'input_list' => '',
				'expected'   => array(),
			),
			'comma only'          => array(
				'input_list' => ',',
				'expected'   => array(),
			),
			'double comma only'   => array(
				'input_list' => ',,',
				'expected'   => array(),
			),
			'passed scalar array' => array(
				'input_list' => array( 'foo', true, false, 1, 3.14 ),
				'expected'   => array( 'foo', true, false, 1, 3.14 ),
			),
			'passed mixed array'  => array(
				'input_list' => array( null, 'foo', array(), true, new stdClass(), false, 1, 3.14 ),
				'expected'   => array( 'foo', true, false, 1, 3.14 ),
			),
		);
	}
}
