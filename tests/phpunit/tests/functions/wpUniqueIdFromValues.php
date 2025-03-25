<?php

/**
 * Test cases for the `wp_unique_id_from_values()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.8.0
 *
 * @group functions.php
 * @covers ::wp_unique_id_from_values
 */
class Tests_Functions_WpUniqueIdFromValues extends WP_UnitTestCase {

	/**
	 * Test that the function returns consistent ids for the passed params.
	 *
	 * @ticket 62985
	 *
	 * @dataProvider data_wp_unique_id_from_values
	 *
	 * @since 6.8.0
	 */
	public function test_wp_unique_id_from_values( $data, $prefix ) {
		// Generate IDs
		$output1 = wp_unique_id_from_values( $data );
		$output2 = wp_unique_id_from_values( $data, $prefix );

		// Ensure that the same input produces the same ID.
		$this->assertSame( $output1, wp_unique_id_from_values( $data ) );
		$this->assertSame( $output2, wp_unique_id_from_values( $data, $prefix ) );

		// Ensure that the prefixed ID is the prefix + the original ID
		$this->assertSame( $prefix . $output1, $output2 );
	}

	/**
	 * Test that different input data generates distinct IDs.
	 *
	 * @ticket 62985
	 *
	 * @dataProvider data_wp_unique_id_from_values
	 *
	 * @since 6.8.0
	 */
	public function test_wp_unique_id_from_values_uniqueness( $data, $prefix ) {
		// Generate IDs
		$output1 = wp_unique_id_from_values( $data );
		$output2 = wp_unique_id_from_values( $data, $prefix );

		// Modify the data slightly to generate a different ID.
		$data_modified          = $data;
		$data_modified['value'] = 'modified';

		// Generate new IDs with the modified data
		$output3 = wp_unique_id_from_values( $data_modified );
		$output4 = wp_unique_id_from_values( $data_modified, $prefix );

		// Assert that the IDs for different data are distinct
		$this->assertNotSame( $output1, $output3 );
		$this->assertNotSame( $output2, $output4 );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_unique_id_from_values() {
		return array(
			'string'          => array(
				'data'   => array( 'value' => 'text' ),
				'prefix' => 'my-prefix-',
			),
			'integer'         => array(
				'data'   => array( 'value' => 123 ),
				'prefix' => 'my-prefix-',
			),
			'float'           => array(
				'data'   => array( 'value' => 1.23 ),
				'prefix' => 'my-prefix-',
			),
			'boolean'         => array(
				'data'   => array( 'value' => true ),
				'prefix' => 'my-prefix-',
			),
			'object'          => array(
				'data'   => array( 'value' => new StdClass() ),
				'prefix' => 'my-prefix-',
			),
			'null'            => array(
				'data'   => array( 'value' => null ),
				'prefix' => 'my-prefix-',
			),
			'multiple values' => array(
				'data'   => array(
					'value1' => 'text',
					'value2' => 123,
					'value3' => 1.23,
					'value4' => true,
					'value5' => new StdClass(),
					'value6' => null,
				),
				'prefix' => 'my-prefix-',
			),
			'nested arrays'   => array(
				'data'   => array(
					'list1' => array(
						'value1' => 'text',
						'value2' => 123,
						'value3' => 1.23,
					),
					'list2' => array(
						'value4' => true,
						'value5' => new StdClass(),
						'value6' => null,
					),
				),
				'prefix' => 'my-prefix-',
			),
		);
	}

	/**
	 * Test that passing an empty array is not allowed.
	 *
	 * @ticket 62985
	 *
	 * @expectedIncorrectUsage wp_unique_id_from_values
	 *
	 * @since 6.8.0
	 */
	public function test_wp_unique_id_from_values_empty_array() {
		wp_unique_id_from_values( array(), 'my-prefix-' );
	}

	/**
	 * Test that passing non-array data throws an error.
	 *
	 * @ticket 62985
	 *
	 * @dataProvider data_wp_unique_id_from_values_invalid_data
	 *
	 * @since 6.8.0
	 */
	public function test_wp_unique_id_from_values_invalid_data( $data, $prefix ) {
		$this->expectException( TypeError::class );

		wp_unique_id_from_values( $data, $prefix );
	}

	/**
	 * Data provider for invalid data tests.
	 *
	 * @return array[]
	 */
	public function data_wp_unique_id_from_values_invalid_data() {
		return array(
			'string'  => array(
				'data'   => 'text',
				'prefix' => '',
			),
			'integer' => array(
				'data'   => 123,
				'prefix' => '',
			),
			'float'   => array(
				'data'   => 1.23,
				'prefix' => '',
			),
			'boolean' => array(
				'data'   => true,
				'prefix' => '',
			),
			'object'  => array(
				'data'   => new StdClass(),
				'prefix' => '',
			),
			'null'    => array(
				'data'   => null,
				'prefix' => '',
			),
		);
	}
}
