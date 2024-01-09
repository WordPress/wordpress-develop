<?php

/**
 * Tests for the wp_parse_slug_list function.
 *
 * @group functions
 *
 * @covers ::wp_parse_slug_list
 */
class Tests_functions_wpParseSlugList extends WP_UnitTestCase {

	/**
	 * @ticket 60217
	 *
	 * @dataProvider data_wp_parse_slug_list
	 */
	public function test_wp_parse_slug_list( $list, $expected ) {

		$this->assertSameSets( $expected, wp_parse_slug_list( $list ) );
	}

	public function data_wp_parse_slug_list() {
		return array(
			'simple'             => array(
				'list'     => array( '1', 2, 'string with spaces' ),
				'expected' => array( '1', '2', 'string-with-spaces' ),
			),
			'simple_with_comma'  => array(
				'list'     => '1,2,string with spaces',
				'expected' => array( '1', '2', 'string', 'with', 'spaces' ),
			),
			'array_with_spaces'  => array(
				'list'     => array( '1 2 string with spaces' ),
				'expected' => array( '1-2-string-with-spaces' ),
			),
			'simple_with_spaces' => array(
				'list'     => '1 2 string with spaces',
				'expected' => array( '1', '2', 'string', 'with', 'spaces' ),
			),
			'array_html'         => array(
				'list'     => array( '1', 2, 'string <strong>with</strong> <h1>HEADING</h1>' ),
				'expected' => array( '1', '2', 'string-with-heading' ),
			),
			'simple_html_spaces' => array(
				'list'     => '1 2 string <strong>with</strong> <h1>HEADING</h1>',
				'expected' => array( '1', '2', 'string', 'with', 'heading' ),
			),
		);
	}
}
