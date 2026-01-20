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
	 */
	public function test_wp_parse_slug_list( $input_list, $expected ) {
		$this->assertSameSets( $expected, wp_parse_slug_list( $input_list ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_parse_slug_list() {
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
	 * @return array[]
	 */
	public function data_unexpected_input() {
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
		);
	}
}
