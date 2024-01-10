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
	public function test_wp_parse_id_list( $list, $expected ) {

		$this->assertSameSets( $expected, wp_parse_id_list( $list ) );
	}

	public function data_wp_parse_id_list() {
		return array(
			'simple'             => array(
				'list'     => array( '1', 2, 'string with spaces' ),
				'expected' => array( 0, 1, 2 ),
			),
			'simple_with_comma'  => array(
				'list'     => '1,2,string with spaces',
				'expected' => array( 0, 1, 2 ),
			),
			'array_with_spaces'  => array(
				'list'     => array( '1 2 string with spaces' ),
				'expected' => array( 1 ),
			),
			'simple_with_spaces' => array(
				'list'     => '1 2 string with spaces',
				'expected' => array( 0, 1, 2 ),
			),
			'array_html'         => array(
				'list'     => array( '1', 2, 'string <strong>with</strong> <h1>HEADING</h1>' ),
				'expected' => array( 0, 1, 2 ),
			),
			'simple_html_spaces' => array(
				'list'     => '1 2 string <strong>with</strong> <h1>HEADING</h1>',
				'expected' => array( 0, 1, 2 ),
			),
			'dup_id'             => array(
				'list'     => '1 1 string string',
				'expected' => array( 0, 1 ),
			),
		);
	}
}
