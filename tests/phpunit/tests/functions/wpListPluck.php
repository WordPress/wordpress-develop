<?php

/**
 * Test wp_list_pluck().
 *
 * @group functions.php
 * @covers ::wp_list_pluck
 */
class Tests_Functions_wpListPluck extends WP_UnitTestCase {
	public $object_list = array();
	public $array_list  = array();

	public function set_up() {
		parent::set_up();
		$this->array_list['foo'] = array(
			'name'   => 'foo',
			'id'     => 'f',
			'field1' => true,
			'field2' => true,
			'field3' => true,
			'field4' => array( 'red' ),
		);
		$this->array_list['bar'] = array(
			'name'   => 'bar',
			'id'     => 'b',
			'field1' => true,
			'field2' => true,
			'field3' => false,
			'field4' => array( 'green' ),
		);
		$this->array_list['baz'] = array(
			'name'   => 'baz',
			'id'     => 'z',
			'field1' => true,
			'field2' => false,
			'field3' => false,
			'field4' => array( 'blue' ),
		);
		foreach ( $this->array_list as $key => $value ) {
			$this->object_list[ $key ] = (object) $value;
		}
	}

	public function test_wp_list_pluck_array_and_object() {
		$list = wp_list_pluck( $this->object_list, 'name' );
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
				'baz' => 'baz',
			),
			$list
		);

		$list = wp_list_pluck( $this->array_list, 'name' );
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
				'baz' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_index_key() {
		$list = wp_list_pluck( $this->array_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_object_index_key() {
		$list = wp_list_pluck( $this->object_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_missing_index_key() {
		$list = wp_list_pluck( $this->array_list, 'name', 'nonexistent' );
		$this->assertSame(
			array(
				0 => 'foo',
				1 => 'bar',
				2 => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_partial_missing_index_key() {
		$array_list = $this->array_list;
		unset( $array_list['bar']['id'] );
		$list = wp_list_pluck( $array_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				0   => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_mixed_index_key() {
		$mixed_list        = $this->array_list;
		$mixed_list['bar'] = (object) $mixed_list['bar'];
		$list              = wp_list_pluck( $mixed_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 16895
	 */
	public function test_wp_list_pluck_containing_references() {
		$ref_list = array(
			& $this->object_list['foo'],
			& $this->object_list['bar'],
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );

		$list = wp_list_pluck( $ref_list, 'name' );
		$this->assertSame(
			array(
				'foo',
				'bar',
			),
			$list
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );
	}

	/**
	 * @ticket 16895
	 */
	public function test_wp_list_pluck_containing_references_keys() {
		$ref_list = array(
			& $this->object_list['foo'],
			& $this->object_list['bar'],
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );

		$list = wp_list_pluck( $ref_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
			),
			$list
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );
	}

	/**
	 * @dataProvider data_test_wp_list_pluck
	 *
	 * @param array      $list      List of objects or arrays.
	 * @param int|string $field     Field from the object to place instead of the entire object
	 * @param int|string $index_key Field from the object to use as keys for the new array.
	 * @param array      $expected  Expected result.
	 */
	public function test_wp_list_pluck( $list, $field, $index_key, $expected ) {
		$this->assertSameSetsWithIndex( $expected, wp_list_pluck( $list, $field, $index_key ) );
	}

	public function data_test_wp_list_pluck() {
		return array(
			'arrays'                         => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
					),
					array( 'foo' => 'baz' ),
				),
				'foo',
				null,
				array( 'bar', 'foo', 'baz' ),
			),
			'arrays with index key'          => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'foo'   => 'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
			'arrays with index key missing'  => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
			'objects'                        => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
					),
					(object) array( 'foo' => 'baz' ),
				),
				'foo',
				null,
				array( 'bar', 'foo', 'baz' ),
			),
			'objects with index key'         => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					(object) array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'foo'   => 'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
			'objects with index key missing' => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					(object) array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
		);
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with invalid input.
	 *
	 * @ticket 56650
	 *
	 * @covers ::wp_list_pluck
	 * @covers WP_List_Util::pluck
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field' ) ); // I think...
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with an index key and invalid input.
	 *
	 * @ticket 56650
	 *
	 * @covers ::wp_list_pluck
	 * @covers WP_List_Util::pluck
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_index_key_and_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field', 'an_index_key' ) ); // I think...
	}

	/**
	 * Data provider: Provides invalid input arrays.
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
}
