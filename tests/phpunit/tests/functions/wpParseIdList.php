<?php

/**
 * Tests for the wp_parse_id_list() function.
 *
 * @group functions
 *
 * @covers ::wp_parse_id_list
 */
class Tests_Functions_wpParseIdList extends WP_UnitTestCase {

	/**
	 * @ticket 22074
	 * @ticket 60218
	 *
	 * @dataProvider data_wp_parse_id_list
	 * @dataProvider data_unexpected_input
	 *
	 * @param mixed[]|string|int $input_list
	 * @param array<non-negative-int> $expected
	 */
	public function test_wp_parse_id_list( $input_list, array $expected ): void {
		$parsed_list = wp_parse_id_list( $input_list );
		$this->assertThat(
			$parsed_list,
			$this->callback(
				static fn ( array $arr ) => array_all(
					$arr,
					static fn ( $v ) => is_int( $v ) && $v >= 0
				)
			),
			'Array should contain only non-negative ints.'
		);
		$this->assertSame( $expected, $parsed_list );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input_list: mixed[]|string|int, expected: array<non-negative-int> }>
	 */
	public function data_wp_parse_id_list(): array {
		return array(
			'regular'                  => array(
				'input_list' => '1,2,3,4',
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'double comma'             => array(
				'input_list' => '1, 2,,3,4',
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'duplicate id in a string' => array(
				'input_list' => '1,2,2,3,4',
				'expected'   => array(
					0 => 1,
					1 => 2,
					3 => 3,
					4 => 4,
				),
			),
			'duplicate id in an array' => array(
				'input_list' => array( '1', '2', '3', '4', '3' ),
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'mixed type'               => array(
				'input_list' => array( 1, '2', 3, '4' ),
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'negative ids in a string' => array(
				'input_list' => '-1,2,-3,4',
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'negative ids in an array' => array(
				'input_list' => array( -1, 2, '-3', '4' ),
				'expected'   => array( 1, 2, 3, 4 ),
			),
			'positive int'             => array(
				'input_list' => 5,
				'expected'   => array( 5 ),
			),
			'negative int'             => array(
				'input_list' => -5,
				'expected'   => array( 5 ),
			),
			'zero'                     => array(
				'input_list' => 0,
				'expected'   => array( 0 ),
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input_list: mixed[]|string|int, expected: array<non-negative-int> }>
	 */
	public function data_unexpected_input(): array {
		return array(
			'string with commas' => array(
				'input_list' => '1,2,string with spaces',
				'expected'   => array( 1, 2, 0 ),
			),
			'array'              => array(
				'input_list' => array( '1', 2, 'string with spaces' ),
				'expected'   => array( 1, 2, 0 ),
			),
			'string with spaces' => array(
				'input_list' => '1 2 string with spaces',
				'expected'   => array( 1, 2, 0 ),
			),
			'array with spaces'  => array(
				'input_list' => array( '1 2 string with spaces' ),
				'expected'   => array( 1 ),
			),
			'comma in array'     => array(
				'input_list' => array( '1,2' ),
				'expected'   => array( 1 ),
			),
			'string with html'   => array(
				'input_list' => '1 2 string <strong>with</strong> <h1>HEADING</h1>',
				'expected'   => array( 1, 2, 0 ),
			),
			'array with html'    => array(
				'input_list' => array( '1', 2, 'string <strong>with</strong> <h1>HEADING</h1>' ),
				'expected'   => array( 1, 2, 0 ),
			),
			'array with null'    => array(
				'input_list' => array( 1, 2, null ),
				'expected'   => array( 1, 2 ),
			),
			'array with false'   => array(
				'input_list' => array( 1, 2, false ),
				'expected'   => array( 1, 2, 0 ),
			),
			'array with array'   => array(
				'input_list' => array( 1, array(), 2 ),
				'expected'   => array(
					0 => 1,
					2 => 2,
				),
			),
			'passed assoc array' => array(
				'input_list' => array(
					'one'   => 1,
					'two'   => '2',
					'three' => '3 is company',
				),
				'expected'   => array(
					'one'   => 1,
					'two'   => 2,
					'three' => 3,
				),
			),
		);
	}
}
