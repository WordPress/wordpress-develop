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
	public function test_wp_unique_id_from_values( $expected, $data, $prefix ) {
		$output1 = wp_unique_id_from_values( $data );
		$output2 = wp_unique_id_from_values( $data, $prefix );
		$this->assertSame( $expected, $output1 );
		$this->assertSame( $prefix . $expected, $output2 );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_unique_id_from_values() {
		return array(
			'string'          => array(
				'expected' => '469f5989',
				'data'     => array(
					'value' => 'text',
				),
				'prefix'   => 'my-prefix-',
			),
			'integer'         => array(
				'expected' => 'b2f0842e',
				'data'     => array(
					'value' => 123,
				),
				'prefix'   => 'my-prefix-',
			),
			'float'           => array(
				'expected' => 'a756f54d',
				'data'     => array(
					'value' => 1.23,
				),
				'prefix'   => 'my-prefix-',
			),
			'boolean'         => array(
				'expected' => 'bdae8be3',
				'data'     => array(
					'value' => true,
				),
				'prefix'   => 'my-prefix-',
			),
			'object'          => array(
				'expected' => '477bd670',
				'data'     => array(
					'value' => new StdClass(),
				),
				'prefix'   => 'my-prefix-',
			),
			'null'            => array(
				'expected' => 'a860dd95',
				'data'     => array(
					'value' => null,
				),
				'prefix'   => 'my-prefix-',
			),
			'multiple values' => array(
				'expected' => 'ef258a5d',
				'data'     => array(
					'value1' => 'text',
					'value2' => 123,
					'value3' => 1.23,
					'value4' => true,
					'value5' => new StdClass(),
					'value6' => null,
				),
				'prefix'   => 'my-prefix-',
			),
			'nested arrays'   => array(
				'expected' => '4345cae5',
				'data'     => array(
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
				'prefix'   => 'my-prefix-',
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
	 * Data provider.
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
