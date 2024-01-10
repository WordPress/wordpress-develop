<?php

/**
 * Tests for the wp_parse_id_list function.
 *
 * @group functions
 *
 * @covers ::wp_parse_id_list
 */
class Tests_functions_wpParseIdList extends WP_UnitTestCase {

	/**
	 * @ticket 60218
	 *
	 * @dataProvider data_wp_parse_id_list
	 */
	public function test_wp_parse_id_list( $input_list, $expected ) {

		$this->assertSameSets( $expected, wp_parse_id_list( $input_list ) );
	}

	/**
	 * data for test_wp_parse_id_list
	 *
	 * @ticket 60218
	 *
	 * @return array[]
	 */
	public function data_wp_parse_id_list() {
		return array(
			'simple'             => array(
				'input_list' => array( '1', 2, 'string with spaces' ),
				'expected'   => array( 0, 1, 2 ),
			),
			'simple_with_comma'  => array(
				'input_list' => '1,2,string with spaces',
				'expected'   => array( 0, 1, 2 ),
			),
			'array_with_spaces'  => array(
				'input_list' => array( '1 2 string with spaces' ),
				'expected'   => array( 1 ),
			),
			'simple_with_spaces' => array(
				'input_list' => '1 2 string with spaces',
				'expected'   => array( 0, 1, 2 ),
			),
			'array_html'         => array(
				'input_list' => array( '1', 2, 'string <strong>with</strong> <h1>HEADING</h1>' ),
				'expected'   => array( 0, 1, 2 ),
			),
			'simple_html_spaces' => array(
				'input_list' => '1 2 string <strong>with</strong> <h1>HEADING</h1>',
				'expected'   => array( 0, 1, 2 ),
			),
			'dup_id'             => array(
				'input_list' => '1 1 string string',
				'expected'   => array( 0, 1 ),
			),
		);
	}
}
