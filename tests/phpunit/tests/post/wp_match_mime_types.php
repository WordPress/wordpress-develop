<?php

/**
 * Tests for the wp_match_mime_types function.
 *
 * @group post
 *
 * @covers ::wp_match_mime_types
 */
class Tests_Post_wp_match_mime_type extends WP_UnitTestCase {

	/**
	 * @ticket 60003
	 *
	 * @dataProvider data_wp_match_mime_types
	 */
	public function test_wp_match_mime_types( $wildcard_mime_types, $real_mime_types, $expected ) {

		$this->assertEqualSets( $expected, wp_match_mime_types( $wildcard_mime_types, $real_mime_types ) );
	}

	/**
	 * Returns an array of test cases for the data_wp_match_mime_types method.
	 *
	 * @return array An array of test cases, each containing 'wildcard_mime_types', 'real_mime_types',
	 *                and 'expected' keys representing the input values and the expected output.
	 */
	public function data_wp_match_mime_types() {

		return array(
			'default'      => array(
				'wildcard_mime_types' => 'image',
				'real_mime_types'     => 'image/jpeg',
				'expected'            => array( array( 'image/jpeg' ) ),
			),
			'null'         => array(
				'wildcard_mime_types' => 'image',
				'real_mime_types'     => null,
				'expected'            => array(), // return early
			),
			'missing_parm' => array(
				'wildcard_mime_types' => null,
				'real_mime_types'     => 'image/jpeg',
				'expected'            => array(), // return early
			),
			'null_string'  => array(
				'wildcard_mime_types' => 'image',
				'real_mime_types'     => 'null',
				'expected'            => array(), // not found
			),
			'png'          => array(
				'wildcard_mime_types' => 'png',
				'real_mime_types'     => 'image/jpeg, image/png',
				'expected'            => array( array( 'image/png' ) ),
			),
		);
	}
}
