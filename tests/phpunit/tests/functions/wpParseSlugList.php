<?php

/**
 * Tests for the wp_parse_slug_list() function.
 *
 * @group functions
 *
 * @covers ::wp_parse_slug_list
 */
class Tests_Functions_WpParseSlugList extends WP_UnitTestCase {

	/**
	 * @ticket 35582
	 * @ticket 60217
	 *
	 * @dataProvider data_wp_parse_slug_list
	 * @dataProvider data_unexpected_input
	 *
	 * @param mixed[]|string $input_list
	 * @param list<string> $expected
	 */
	public function test_wp_parse_slug_list( $input_list, array $expected ): void {
		$parsed_list = wp_parse_slug_list( $input_list );
		$this->assertTrue( array_is_list( $parsed_list ), 'Expected value to be a list.' );
		$this->assertThat(
			$parsed_list,
			$this->callback(
				static fn ( array $arr ) => array_all(
					$arr,
					static fn ( $v ) => is_string( $v )
				)
			),
			'Array should contain only non-negative ints.'
		);
		$this->assertSame( $expected, $parsed_list );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input_list: mixed[]|string, expected: list<string> }>
	 */
	public function data_wp_parse_slug_list(): array {
		return array(
			'regular'                    => array(
				'input_list' => 'apple,banana,carrot,dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'double comma'               => array(
				'input_list' => 'apple, banana,,carrot,dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'duplicate slug in a string' => array(
				'input_list' => 'apple,banana,carrot,carrot,dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'duplicate slug in an array' => array(
				'input_list' => array( 'apple', 'banana', 'carrot', 'carrot', 'dog' ),
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'string with spaces'         => array(
				'input_list' => 'apple banana carrot dog',
				'expected'   => array( 'apple', 'banana', 'carrot', 'dog' ),
			),
			'array with spaces'          => array(
				'input_list' => array( 'apple ', 'banana carrot', 'd o g' ),
				'expected'   => array( 'apple', 'banana-carrot', 'd-o-g' ),
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input_list: mixed[]|string, expected: list<string> }>
	 */
	public function data_unexpected_input(): array {
		return array(
			'string with commas' => array(
				'input_list' => '1,2,string with spaces',
				'expected'   => array( '1', '2', 'string', 'with', 'spaces' ),
			),
			'array'              => array(
				'input_list' => array( '1', 2, 'string with spaces' ),
				'expected'   => array( '1', '2', 'string-with-spaces' ),
			),
			'string with spaces' => array(
				'input_list' => '1 2 string with spaces',
				'expected'   => array( '1', '2', 'string', 'with', 'spaces' ),
			),
			'array with spaces'  => array(
				'input_list' => array( '1 2 string with spaces' ),
				'expected'   => array( '1-2-string-with-spaces' ),
			),
			'string with html'   => array(
				'input_list' => '1 2 string <strong>with</strong> <h1>HEADING</h1>',
				'expected'   => array( '1', '2', 'string', 'with', 'heading' ),
			),
			'array with html'    => array(
				'input_list' => array( '1', 2, 'string <strong>with</strong> <h1>HEADING</h1>' ),
				'expected'   => array( '1', '2', 'string-with-heading' ),
			),
			'array with null'    => array(
				'input_list' => array( 1, 2, null ),
				'expected'   => array( '1', '2' ),
			),
			'array with false'   => array(
				'input_list' => array( 1, 2, false ),
				'expected'   => array( '1', '2', '' ),
			),
			'array with array'   => array(
				'input_list' => array( 1, array(), 2 ),
				'expected'   => array( '1', '2' ),
			),
			'array with tag'     => array(
				'input_list' => array( 1, '<br>', 2 ),
				'expected'   => array( '1', '', '2' ),
			),
			'passed assoc array' => array(
				'input_list' => array(
					'one'   => 'foo',
					'two'   => 'bar',
					'three' => 'baz',
				),
				'expected'   => array( 'foo', 'bar', 'baz' ),
			),
		);
	}
}
