<?php

/**
 * @group functions
 *
 * @covers ::_wp_json_sanity_check
 */
class Tests_Functions_WpJsonSanityCheck extends WP_UnitTestCase {

	/**
	 * Test valid array input.
	 *
	 * @dataProvider data_should_return_expected_result_for_valid_arrays
	 *
	 * @param mixed $input The input array to test.
	 * @param mixed $expected The expected result after processing.
	 */
	public function test_should_return_expected_result_for_valid_array( $input, $expected ) {
		$this->assertEquals( $expected, _wp_json_sanity_check( $input, 2 ) );
	}

	/**
	 * Data provider for valid array inputs.
	 *
	 * @return array[]
	 */
	public function data_should_return_expected_result_for_valid_arrays() {
		return array(
			'simple_array'      => array(
				array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
				array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
			),
			'nested_array'      => array(
				array( 'key1' => array( 'subkey1' => 'subvalue1' ) ),
				array( 'key1' => array( 'subkey1' => 'subvalue1' ) ),
			),
			'array_with_object' => array(
				array( 'key1' => (object) array( 'prop' => 'value' ) ),
				array( 'key1' => (object) array( 'prop' => 'value' ) ),
			),
		);
	}

	/**
	 * Test depth limit.
	 */
	public function test_should_throw_exception_when_depth_limit_reached() {
		$this->expectException( Exception::class );
		_wp_json_sanity_check( array( 'key' => 'value' ), -1 );
	}

	/**
	 * Test valid string input.
	 */
	public function test_should_return_expected_result_for_valid_string() {
		$input = 'Hello, World!';
		$this->assertSame( $input, _wp_json_sanity_check( $input, 1 ) );
	}

	/**
	 * Test empty input.
	 */
	public function test_should_return_empty_array_for_empty_input() {
		$this->assertSame( array(), _wp_json_sanity_check( array(), 1 ) );
	}

	/**
	 * Test valid object input.
	 *
	 * @dataProvider data_should_return_expected_result_for_valid_objects
	 *
	 * @param mixed $input The input object to test.
	 * @param mixed $expected The expected result after processing.
	 */
	public function test_should_return_expected_result_for_valid_object( $input, $expected ) {
		$this->assertEquals( $expected, _wp_json_sanity_check( $input, 2 ) );
	}

	/**
	 * Data provider for valid object inputs.
	 *
	 * @return array[]
	 */
	public function data_should_return_expected_result_for_valid_objects() {
		return array(
			'simple_object' => array(
				(object) array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
				(object) array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
			),
			'nested_object' => array(
				(object) array( 'key1' => (object) array( 'subkey1' => 'subvalue1' ) ),
				(object) array( 'key1' => (object) array( 'subkey1' => 'subvalue1' ) ),
			),
		);
	}

	/**
	 * Test mixed input.
	 *
	 * @dataProvider data_should_return_expected_result_for_mixed_inputs
	 *
	 * @param mixed $input The mixed input to test.
	 * @param mixed $expected The expected result after processing.
	 */
	public function test_should_return_expected_result_for_mixed_input( $input, $expected ) {
		$this->assertEquals( $expected, _wp_json_sanity_check( $input, 2 ) );
	}

	/**
	 * Data provider for mixed inputs.
	 *
	 * @return array[]
	 */
	public function data_should_return_expected_result_for_mixed_inputs() {
		return array(
			'mixed_array'  => array(
				array(
					'string' => 'text',
					'array'  => array( 1, 2, 3 ),
					'object' => (object) array( 'key' => 'value' ),
				),
				array(
					'string' => 'text',
					'array'  => array( 1, 2, 3 ),
					'object' => (object) array( 'key' => 'value' ),
				),
			),
			'nested_mixed' => array(
				array( 'key' => array( 'subkey' => (object) array( 'nestedKey' => 'nestedValue' ) ) ),
				array( 'key' => array( 'subkey' => (object) array( 'nestedKey' => 'nestedValue' ) ) ),
			),
		);
	}
}
