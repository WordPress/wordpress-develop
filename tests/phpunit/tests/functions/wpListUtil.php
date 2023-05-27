<?php

/**
 * Test WP_List_Util class.
 *
 * @group functions.php
 */
class Tests_Functions_wpListUtil extends WP_UnitTestCase {

	/**
	 * @covers WP_List_Util::get_input
	 */
	public function test_wp_list_util_get_input() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_input() );
	}

	/**
	 * @covers WP_List_Util::get_output
	 */
	public function test_wp_list_util_get_output_immediately() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_output() );
	}

	/**
	 * @covers WP_List_Util::get_output
	 */
	public function test_wp_list_util_get_output() {
		$expected = array(
			(object) array(
				'foo' => 'bar',
				'bar' => 'baz',
			),
		);

		$util   = new WP_List_Util(
			array(
				(object) array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				(object) array( 'bar' => 'baz' ),
			)
		);
		$actual = $util->filter( array( 'foo' => 'bar' ) );

		$this->assertEqualSets( $expected, $actual );
		$this->assertEqualSets( $expected, $util->get_output() );
	}

	/**
	 * @ticket 55300
	 *
	 * @dataProvider data_wp_list_util_pluck
	 *
	 * @covers WP_List_Util::pluck
	 * @covers ::wp_list_pluck
	 *
	 * @param array  $target_array The array to create the list from.
	 * @param string $target_key   The key to pluck.
	 * @param array  $expected     The expected array.
	 * @param string $index_key    Optional. Field from the element to use as keys for the new array.
	 *                             Default null.
	 */
	public function test_wp_list_util_pluck( $target_array, $target_key, $expected, $index_key = null ) {
		$util   = new WP_List_Util( $target_array );
		$actual = $util->pluck( $target_key, $index_key );

		$this->assertEqualSetsWithIndex(
			$expected,
			$actual,
			'The plucked value did not match the expected value.'
		);

		$this->assertEqualSetsWithIndex(
			$expected,
			$util->get_output(),
			'::get_output() did not return the expected value.'
		);
	}

	/**
	 * Data provider for test_wp_list_util_pluck().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_pluck() {
		return array(
			'simple'        => array(
				'target_array' => array(
					0 => array( 'foo' => 'bar' ),
				),
				'target_key'   => 'foo',
				'expected'     => array( 'bar' ),
			),
			'simple_object' => array(
				'target_array' => array(
					0 => (object) array( 'foo' => 'bar' ),
				),
				'target_key'   => 'foo',
				'expected'     => array( 'bar' ),
			),
		);
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with invalid input.
	 *
	 * @ticket 56650
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 * @covers WP_List_Util::pluck
	 * @covers ::wp_list_pluck
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field' ) );
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with an index key and invalid input.
	 *
	 * @ticket 56650
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 * @covers WP_List_Util::pluck
	 * @covers ::wp_list_pluck
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_index_key_and_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field', 'an_index_key' ) );
	}

	/**
	 * Data provider that provides invalid input arrays.
	 *
	 * @return array
	 */
	public function data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input() {
		return array(
			'int[] 0'                   => array( array( 0 ) ),
			'int[] 1'                   => array( array( 1 ) ),
			'int[] -1'                  => array( array( -1 ) ),
			'float[] 0.0'               => array( array( 0.0 ) ),
			'float[] 1.0'               => array( array( 1.0 ) ),
			'float[] -1.0'              => array( array( -1.0 ) ),
			'string[] and empty string' => array( array( '' ) ),
			'string[] and "0"'          => array( array( '0' ) ),
			'string[] and "1"'          => array( array( '1' ) ),
			'string[] and "-1"'         => array( array( '-1' ) ),
			'array and null'            => array( array( null ) ),
			'array and false'           => array( array( false ) ),
			'array and true'            => array( array( true ) ),
		);
	}

	/**
	 * @ticket 55300
	 *
	 * @covers WP_List_Util::sort
	 * @covers ::wp_list_sort
	 */
	public function test_wp_list_util_sort_simple() {
		$expected     = array(
			1 => 'one',
			2 => 'two',
			3 => 'three',
			4 => 'four',
		);
		$target_array = array(
			4 => 'four',
			2 => 'two',
			3 => 'three',
			1 => 'one',
		);

		$util   = new WP_List_Util( $target_array );
		$actual = $util->sort();

		$this->assertEqualSets(
			$expected,
			$actual,
			'The sorted value did not match the expected value.'
		);

		$this->assertEqualSets(
			$expected,
			$util->get_output(),
			'::get_output() did not return the expected value.'
		);
	}

	/**
	 * @ticket 55300
	 *
	 * @dataProvider data_wp_list_util_sort_string_arrays
	 * @dataProvider data_wp_list_util_sort_int_arrays
	 * @dataProvider data_wp_list_util_sort_arrays_of_arrays
	 * @dataProvider data_wp_list_util_sort_object_arrays
	 *
	 * @covers WP_List_Util::sort
	 * @covers ::wp_list_sort
	 *
	 * @param array  $expected      The expected array.
	 * @param array  $target_array  The array to create a list from.
	 * @param array  $orderby       Optional. Either the field name to order by or an array
	 *                              of multiple orderby fields as `$orderby => $order`.
	 *                              Default empty array.
	 * @param string $order         Optional. Either 'ASC' or 'DESC'. Only used if `$orderby`
	 *                              is a string. Default 'ASC'.
	 * @param bool   $preserve_keys Optional. Whether to preserve keys. Default false.
	 */
	public function test_wp_list_util_sort( $expected, $target_array, $orderby = array(), $order = 'ASC', $preserve_keys = false ) {
		$util   = new WP_List_Util( $target_array );
		$actual = $util->sort( $orderby, $order, $preserve_keys );

		$this->assertEqualSetsWithIndex(
			$expected,
			$actual,
			'The sorted value did not match the expected value.'
		);

		$this->assertEqualSetsWithIndex(
			$expected,
			$util->get_output(),
			'::get_output() did not return the expected value.'
		);
	}

	/**
	 * Data provider that provides string arrays to test_wp_list_util_sort().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort_string_arrays() {
		return array(
			'string[], no keys, no ordering'     => array(
				'expected'     => array( 'four', 'two', 'three', 'one' ),
				'target_array' => array( 'four', 'two', 'three', 'one' ),
			),
			'string[], int keys, no ordering'    => array(
				'expected'     => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'target_array' => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
			),
			'string[], int keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'target_array'  => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'string[], string keys, no ordering' => array(
				'expected'     => array(
					'four'  => 'four',
					'two'   => 'two',
					'three' => 'three',
					'one'   => 'one',
				),
				'target_array' => array(
					'four'  => 'four',
					'two'   => 'two',
					'three' => 'three',
					'one'   => 'one',
				),
			),
			'string[], string keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => 'four',
					'two'   => 'two',
					'three' => 'three',
					'one'   => 'one',
				),
				'target_array'  => array(
					'four'  => 'four',
					'two'   => 'two',
					'three' => 'three',
					'one'   => 'one',
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
		);
	}

	/**
	 * Data provider that provides int arrays for test_wp_list_util_sort().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort_int_arrays() {
		return array(
			'int[], no keys, no ordering'     => array(
				'expected'     => array( 4, 2, 3, 1 ),
				'target_array' => array( 4, 2, 3, 1 ),
			),
			'int[], int keys, no ordering'    => array(
				'expected'     => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
				'target_array' => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
			),
			'int[], int keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
				'target_array'  => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'int[], string keys, no ordering' => array(
				'expected'     => array(
					'four'  => 4,
					'two'   => 2,
					'three' => 3,
					'one'   => 1,
				),
				'target_array' => array(
					'four'  => 4,
					'two'   => 2,
					'three' => 3,
					'one'   => 1,
				),
			),
			'int[], string keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => 4,
					'two'   => 2,
					'three' => 3,
					'one'   => 1,
				),
				'target_array'  => array(
					'four'  => 4,
					'two'   => 2,
					'three' => 3,
					'one'   => 1,
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
		);
	}

	/**
	 * Data provider that provides arrays of arrays for test_wp_list_util_sort().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort_arrays_of_arrays() {
		return array(
			'array[], no keys, no ordering'     => array(
				'expected'     => array(
					array( 'four' ),
					array( 'two' ),
					array( 'three' ),
					array( 'one' ),
				),
				'target_array' => array(
					array( 'four' ),
					array( 'two' ),
					array( 'three' ),
					array( 'one' ),
				),
			),
			'array[], int keys, no ordering'    => array(
				'expected'     => array(
					4 => array( 'four' ),
					2 => array( 'two' ),
					3 => array( 'three' ),
					1 => array( 'one' ),
				),
				'target_array' => array(
					4 => array( 'four' ),
					2 => array( 'two' ),
					3 => array( 'three' ),
					1 => array( 'one' ),
				),
			),
			'array[], int keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					4 => array( 'value' => 'four' ),
					2 => array( 'value' => 'two' ),
					3 => array( 'value' => 'three' ),
					1 => array( 'value' => 'one' ),
				),
				'target_array'  => array(
					4 => array( 'value' => 'four' ),
					2 => array( 'value' => 'two' ),
					3 => array( 'value' => 'three' ),
					1 => array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'array[], int keys, $orderby an existing field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					array(
						'id'    => 1,
						'value' => 'one',
					),
					array(
						'id'    => 2,
						'value' => 'two',
					),
					array(
						'id'    => 3,
						'value' => 'three',
					),
					array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					4 => array(
						'id'    => 4,
						'value' => 'four',
					),
					2 => array(
						'id'    => 2,
						'value' => 'two',
					),
					3 => array(
						'id'    => 3,
						'value' => 'three',
					),
					1 => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'array[], int keys, $orderby an existing field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					3 => array(
						'id'    => 4,
						'value' => 'four',
					),
					2 => array(
						'id'    => 3,
						'value' => 'three',
					),
					1 => array(
						'id'    => 2,
						'value' => 'two',
					),
					0 => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'target_array'  => array(
					array(
						'id'    => 1,
						'value' => 'one',
					),
					array(
						'id'    => 2,
						'value' => 'two',
					),
					array(
						'id'    => 3,
						'value' => 'three',
					),
					array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'array[], string keys, no ordering' => array(
				'expected'     => array(
					'four'  => array( 'value' => 'four' ),
					'two'   => array( 'value' => 'two' ),
					'three' => array( 'value' => 'three' ),
					'one'   => array( 'value' => 'one' ),
				),
				'target_array' => array(
					'four'  => array( 'value' => 'four' ),
					'two'   => array( 'value' => 'two' ),
					'three' => array( 'value' => 'three' ),
					'one'   => array( 'value' => 'one' ),
				),
			),
			'array[], string keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => array( 'value' => 'four' ),
					'two'   => array( 'value' => 'two' ),
					'three' => array( 'value' => 'three' ),
					'one'   => array( 'value' => 'one' ),
				),
				'target_array'  => array(
					'four'  => array( 'value' => 'four' ),
					'two'   => array( 'value' => 'two' ),
					'three' => array( 'value' => 'three' ),
					'one'   => array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'array[], string keys, $orderby an existing field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					array(
						'id'    => 1,
						'value' => 'one',
					),
					array(
						'id'    => 2,
						'value' => 'two',
					),
					array(
						'id'    => 3,
						'value' => 'three',
					),
					array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'array[], string keys, $orderby an existing field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'target_array'  => array(
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'array[], string keys, $orderby an existing field, $order = asc (lowercase) and $preserve_keys = false' => array(
				'expected'      => array(
					array(
						'id'    => 1,
						'value' => 'one',
					),
					array(
						'id'    => 2,
						'value' => 'two',
					),
					array(
						'id'    => 3,
						'value' => 'three',
					),
					array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => 'id',
				'order'         => 'asc',
				'preserve_keys' => false,
			),
			'array[], string keys, $orderby an existing field, no order and $preserve_keys = false' => array(
				'expected'      => array(
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'target_array'  => array(
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'orderby'       => array( 'id' ),
				'order'         => null,
				'preserve_keys' => true,
			),
			'array[], string keys, $orderby two existing fields, differing orders and $preserve_keys = false' => array(
				'expected'      => array(
					array(
						'id'    => 1,
						'value' => 'one',
					),
					array(
						'id'    => 2,
						'value' => 'two',
					),
					array(
						'id'    => 3,
						'value' => 'three',
					),
					array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					'four'  => array(
						'id'    => 4,
						'value' => 'four',
					),
					'two'   => array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => array(
						'id'    => 3,
						'value' => 'three',
					),
					'one'   => array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => array(
					'id'    => 'asc',
					'value' => 'DESC',
				),
				'order'         => null,
				'preserve_keys' => false,
			),
		);
	}

	/**
	 * Data provider that provides object arrays for test_wp_list_util_sort().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort_object_arrays() {
		return array(
			'object[], no keys, no ordering'     => array(
				'expected'     => array(
					(object) array( 'four' ),
					(object) array( 'two' ),
					(object) array( 'three' ),
					(object) array( 'one' ),
				),
				'target_array' => array(
					(object) array( 'four' ),
					(object) array( 'two' ),
					(object) array( 'three' ),
					(object) array( 'one' ),
				),
			),
			'object[], int keys, no ordering'    => array(
				'expected'     => array(
					4 => (object) array( 'four' ),
					2 => (object) array( 'two' ),
					3 => (object) array( 'three' ),
					1 => (object) array( 'one' ),
				),
				'target_array' => array(
					4 => (object) array( 'four' ),
					2 => (object) array( 'two' ),
					3 => (object) array( 'three' ),
					1 => (object) array( 'one' ),
				),
			),
			'object[], int keys, $orderby an existing field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					(object) array(
						'id'    => 1,
						'value' => 'one',
					),
					(object) array(
						'id'    => 2,
						'value' => 'two',
					),
					(object) array(
						'id'    => 3,
						'value' => 'three',
					),
					(object) array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					4 => (object) array(
						'id'    => 4,
						'value' => 'four',
					),
					2 => (object) array(
						'id'    => 2,
						'value' => 'two',
					),
					3 => (object) array(
						'id'    => 3,
						'value' => 'three',
					),
					1 => (object) array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'object[], int keys, $orderby an existing field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					3 => (object) array(
						'id'    => 4,
						'value' => 'four',
					),
					2 => (object) array(
						'id'    => 3,
						'value' => 'three',
					),
					1 => (object) array(
						'id'    => 2,
						'value' => 'two',
					),
					0 => (object) array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'target_array'  => array(
					(object) array(
						'id'    => 1,
						'value' => 'one',
					),
					(object) array(
						'id'    => 2,
						'value' => 'two',
					),
					(object) array(
						'id'    => 3,
						'value' => 'three',
					),
					(object) array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'object[], string keys, no ordering' => array(
				'expected'     => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'target_array' => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
			),
			'object[], string keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'target_array'  => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'object[], string keys, $orderby an existing field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					(object) array(
						'id'    => 1,
						'value' => 'one',
					),
					(object) array(
						'id'    => 2,
						'value' => 'two',
					),
					(object) array(
						'id'    => 3,
						'value' => 'three',
					),
					(object) array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'target_array'  => array(
					'four'  => (object) array(
						'id'    => 4,
						'value' => 'four',
					),
					'two'   => (object) array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => (object) array(
						'id'    => 3,
						'value' => 'three',
					),
					'one'   => (object) array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'object[], string keys, $orderby an existing field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => (object) array(
						'id'    => 4,
						'value' => 'four',
					),
					'three' => (object) array(
						'id'    => 3,
						'value' => 'three',
					),
					'two'   => (object) array(
						'id'    => 2,
						'value' => 'two',
					),
					'one'   => (object) array(
						'id'    => 1,
						'value' => 'one',
					),
				),
				'target_array'  => array(
					'one'   => (object) array(
						'id'    => 1,
						'value' => 'one',
					),
					'two'   => (object) array(
						'id'    => 2,
						'value' => 'two',
					),
					'three' => (object) array(
						'id'    => 3,
						'value' => 'three',
					),
					'four'  => (object) array(
						'id'    => 4,
						'value' => 'four',
					),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
		);
	}

	/**
	 * Tests non-existent '$orderby' fields.
	 *
	 * In PHP < 7.0.0, the sorting behavior is different, which Core does not
	 * currently handle. Until this is fixed, or the minimum PHP version is
	 * raised to PHP 7.0.0+, these tests will be skipped on PHP < 7.0.0.
	 *
	 * @ticket 55300
	 *
	 * @dataProvider data_wp_list_util_sort_php_7_or_greater
	 *
	 * @covers WP_List_Util::sort
	 * @covers ::wp_list_sort
	 *
	 * @param array  $expected      The expected array.
	 * @param array  $target_array  The array to create a list from.
	 * @param array  $orderby       Optional. Either the field name to order by or an array
	 *                              of multiple orderby fields as `$orderby => $order`.
	 *                              Default empty array.
	 * @param string $order         Optional. Either 'ASC' or 'DESC'. Only used if `$orderby`
	 *                              is a string. Default 'ASC'.
	 * @param bool   $preserve_keys Optional. Whether to preserve keys. Default false.
	 */
	public function test_wp_list_util_sort_php_7_or_greater( $expected, $target_array, $orderby = array(), $order = 'ASC', $preserve_keys = false ) {
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			$this->markTestSkipped( 'This test can only run on PHP 7.0 or greater due to an unstable sort order.' );
		}

		$util   = new WP_List_Util( $target_array );
		$actual = $util->sort( $orderby, $order, $preserve_keys );

		$this->assertEqualSetsWithIndex(
			$expected,
			$actual,
			'The sorted value did not match the expected value.'
		);
		$this->assertEqualSetsWithIndex(
			$expected,
			$util->get_output(),
			'::get_output() did not return the expected value.'
		);
	}

	/**
	 * Data provider for test_wp_list_util_sort_php_7_or_greater().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort_php_7_or_greater() {
		return array(
			'int[], int keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array( 4, 2, 3, 1 ),
				'target_array'  => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'int[], string keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array( 4, 2, 3, 1 ),
				'target_array'  => array(
					'four'  => 4,
					'two'   => 2,
					'three' => 3,
					'one'   => 1,
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'string[], int keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array( 'four', 'two', 'three', 'one' ),
				'target_array'  => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'string[], string keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array( 'four', 'two', 'three', 'one' ),
				'target_array'  => array(
					'four'  => 'four',
					'two'   => 'two',
					'three' => 'three',
					'one'   => 'one',
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'array[], int keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					array( 'value' => 'four' ),
					array( 'value' => 'two' ),
					array( 'value' => 'three' ),
					array( 'value' => 'one' ),
				),
				'target_array'  => array(
					4 => array( 'value' => 'four' ),
					2 => array( 'value' => 'two' ),
					3 => array( 'value' => 'three' ),
					1 => array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'array[], string keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					array( 'value' => 'four' ),
					array( 'value' => 'two' ),
					array( 'value' => 'three' ),
					array( 'value' => 'one' ),
				),
				'target_array'  => array(
					'four'  => array( 'value' => 'four' ),
					'two'   => array( 'value' => 'two' ),
					'three' => array( 'value' => 'three' ),
					'one'   => array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'object[], int keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					(object) array( 'value' => 'four' ),
					(object) array( 'value' => 'two' ),
					(object) array( 'value' => 'three' ),
					(object) array( 'value' => 'one' ),
				),
				'target_array'  => array(
					4 => (object) array( 'value' => 'four' ),
					2 => (object) array( 'value' => 'two' ),
					3 => (object) array( 'value' => 'three' ),
					1 => (object) array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'object[], int keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					4 => (object) array( 'value' => 'four' ),
					2 => (object) array( 'value' => 'two' ),
					3 => (object) array( 'value' => 'three' ),
					1 => (object) array( 'value' => 'one' ),
				),
				'target_array'  => array(
					4 => (object) array( 'value' => 'four' ),
					2 => (object) array( 'value' => 'two' ),
					3 => (object) array( 'value' => 'three' ),
					1 => (object) array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
			'object[], string keys, $orderby a non-existent field, $order = ASC and $preserve_keys = false' => array(
				'expected'      => array(
					(object) array( 'value' => 'four' ),
					(object) array( 'value' => 'two' ),
					(object) array( 'value' => 'three' ),
					(object) array( 'value' => 'one' ),
				),
				'target_array'  => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'ASC',
				'preserve_keys' => false,
			),
			'object[], string keys, $orderby a non-existent field, $order = DESC and $preserve_keys = true' => array(
				'expected'      => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'target_array'  => array(
					'four'  => (object) array( 'value' => 'four' ),
					'two'   => (object) array( 'value' => 'two' ),
					'three' => (object) array( 'value' => 'three' ),
					'one'   => (object) array( 'value' => 'one' ),
				),
				'orderby'       => 'id',
				'order'         => 'DESC',
				'preserve_keys' => true,
			),
		);
	}

}
