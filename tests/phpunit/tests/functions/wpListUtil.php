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
	 * @param string $index_key    Optional. Field from the element to use as keys for the new array. Default null.
	 */
	public function test_wp_list_util_pluck( $target_array, $target_key, $expected, $index_key = null ) {

		$util = new WP_List_Util( $target_array );

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
	 * Data provider for test_wp_list_util_pluck_simple().
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
	 * @dataProvider data_wp_list_util_sort
	 *
	 * @covers WP_List_Util::sort
	 * @covers ::wp_list_sort
	 *
	 * @param array  $expected      The expected array.
	 * @param array  $target_array  The array to create a list from.
	 * @param array  $orderby       Optional. Either the field name to order by or an array of multiple orderby fields as $orderby => $order.
	 *                              Default empty array.
	 * @param string $order         Optional. Either 'ASC' or 'DESC'. Only used if $orderby is a string. Default 'ASC'.
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
	 * Data provider for test_wp_list_util_sort().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_sort() {
		return array(
			'default'                    => array(
				'expected'     => array(
					2 => 'two',
					3 => 'three',
					1 => 'one',
					4 => 'four',
				),
				'target_array' => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
			),
			'default no keys'            => array(
				'expected'     => array( 'four', 'two', 'three', 'one' ),
				'target_array' => array( 'four', 'two', 'three', 'one' ),
			),
			'default int'                => array(
				'expected'     => array(
					1 => 1,
					2 => 2,
					3 => 3,
					4 => 4,
				),
				'target_array' => array(
					4 => 4,
					2 => 2,
					3 => 3,
					1 => 1,
				),
			),
			'DESC'                       => array(
				'expected'     => array(
					1 => 'two',
					2 => 'three',
					3 => 'one',
					0 => 'four',
				),
				'target_array' => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'orderby'      => 'DESC',
			),
			'empty by DESC'              => array(
				'expected'     => array(
					4 => 'four',
					3 => 'three',
					2 => 'two',
					1 => 'one',
				),
				'target_array' => array(
					4 => 'four',
					2 => 'two',
					3 => 'three',
					1 => 'one',
				),
				'orderby'      => array(),
				'order'        => 'DESC',
			),
			'simple arrays'              => array(
				'expected'     => array(
					array(
						'id'  => 1,
						'val' => 'one',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'target_array' => array(
					array(
						'id'  => 1,
						'val' => 'one',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'orderby'      => array( 'id' ),
			),
			'simple arrays ASC'          => array(
				'expected'     => array(
					array(
						'id'  => 1,
						'val' => 'one',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'target_array' => array(
					array(
						'id'  => 1,
						'val' => 'one',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'orderby'      => array( 'id' => 'asc' ),
			),
			'simple arrays DESC'         => array(
				'expected'     => array(
					array(
						'id'  => 4,
						'val' => 'four',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 1,
						'val' => 'one',
					),
				),
				'target_array' => array(
					array(
						'id'  => 1,
						'val' => 'one',
					),
					array(
						'id'  => 3,
						'val' => 'three',
					),
					array(
						'id'  => 2,
						'val' => 'two',
					),
					array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'orderby'      => array( 'id' => 'desc' ),
			),
			'simple arrays object'       => array(
				'expected'     => array(
					(object) array(
						'group' => 2,
						'id'    => 1,
						'val'   => 'two one',
					),
					(object) array(
						'group' => 2,
						'id'    => 2,
						'val'   => 'two two',
					),
					(object) array(
						'group' => 1,
						'id'    => 3,
						'val'   => 'one three',
					),
					(object) array(
						'group' => 1,
						'id'    => 4,
						'val'   => 'one four',
					),
				),
				'target_array' => array(
					(object) array(
						'group' => 2,
						'id'    => 1,
						'val'   => 'two one',
					),
					(object) array(
						'group' => 1,
						'id'    => 3,
						'val'   => 'one three',
					),
					(object) array(
						'group' => 2,
						'id'    => 2,
						'val'   => 'two two',
					),
					(object) array(
						'group' => 1,
						'id'    => 4,
						'val'   => 'one four',
					),
				),
				'orderby'      => array(
					'id' => 'asc',
				),
			),
			'simple arrays multi object' => array(
				'expected'     => array(
					(object) array(
						'group' => 1,
						'id'    => 4,
						'val'   => 'one four',
					),
					(object) array(
						'group' => 1,
						'id'    => 3,
						'val'   => 'one three',
					),
					(object) array(
						'group' => 2,
						'id'    => 2,
						'val'   => 'two two',
					),
					(object) array(
						'group' => 2,
						'id'    => 1,
						'val'   => 'two one',
					),
				),
				'target_array' => array(
					(object) array(
						'group' => 2,
						'id'    => 1,
						'val'   => 'two one',
					),
					(object) array(
						'group' => 1,
						'id'    => 3,
						'val'   => 'one three',
					),
					(object) array(
						'group' => 2,
						'id'    => 2,
						'val'   => 'two two',
					),
					(object) array(
						'group' => 1,
						'id'    => 4,
						'val'   => 'one four',
					),
				),
				'orderby'      => array(
					'group' => 'asc',
					'id'    => 'desc',
				),
			),
			'simple arrays ASC'          => array(
				'expected'      => array(
					'key1' => array(
						'id'  => 1,
						'val' => 'one',
					),
					'key3' => array(
						'id'  => 2,
						'val' => 'two',
					),
					'key2' => array(
						'id'  => 3,
						'val' => 'three',
					),
					'key4' => array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'target_array'  => array(
					'key1' => array(
						'id'  => 1,
						'val' => 'one',
					),
					'key2' => array(
						'id'  => 3,
						'val' => 'three',
					),
					'key3' => array(
						'id'  => 2,
						'val' => 'two',
					),
					'key4' => array(
						'id'  => 4,
						'val' => 'four',
					),
				),
				'orderby'       => array( 'id' => 'asc' ),
				'order'         => null,
				'preserve_keys' => true,
			),
		);
	}

}
