<?php

/**
 * Test WPDB _real_escape() method.
 *
 * @group wpdb
 *
 * @covers wpdb::_real_escape
 */
class Tests_DB_RealEscape extends WP_UnitTestCase {

	/**
	 * Test that various types of input passed to `wpdb::_real_escape()` are handled correctly.
	 *
	 * Note: this test does not test the actual escaping or other logic in the function.
	 * It just and only tests and documents how the function handles various input types.
	 *
	 * @ticket 53635
	 *
	 * @dataProvider data_real_escape_input_type_handling
	 *
	 * @param mixed  $input    The input to escape.
	 * @param string $expected The expected function output.
	 */
	public function test_real_escape_input_type_handling( $input, $expected ) {
		global $wpdb;

		$this->assertSame( $expected, $wpdb->_real_escape( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @var array
	 */
	public function data_real_escape_input_type_handling() {
		return array(
			'null'             => array(
				'input'    => null,
				'expected' => '',
			),
			'boolean false'    => array(
				'input'    => false,
				'expected' => '',
			),
			'boolean true'     => array(
				'input'    => true,
				'expected' => '1',
			),
			'integer zero'     => array(
				'input'    => 0,
				'expected' => '0',
			),
			'negative integer' => array(
				'input'    => -1327,
				'expected' => '-1327',
			),
			'positive integer' => array(
				'input'    => 47896,
				'expected' => '47896',
			),
			'float zero'       => array(
				'input'    => 0.0,
				'expected' => '0',
			),
			'positive float'   => array(
				'input'    => 25.52,
				'expected' => '25.52',
			),
			'simple string'    => array(
				'input'    => 'foobar',
				'expected' => 'foobar',
			),
			'empty array'      => array(
				'input'    => array(),
				'expected' => '',
			),
			'non-empty array'  => array(
				'input'    => array( 1, 2, 3 ),
				'expected' => '',
			),
			'simple object'    => array(
				'input'    => new stdClass(),
				'expected' => '',
			),
		);
	}
}
