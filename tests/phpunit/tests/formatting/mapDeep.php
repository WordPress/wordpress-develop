<?php

/**
 * @group formatting
 * @ticket 22300
 *
 * @covers ::map_deep
 */
class Tests_Formatting_MapDeep extends WP_UnitTestCase {

	public function test_map_deep_with_any_function_over_empty_array_should_return_empty_array() {
		$this->assertSame( array(), map_deep( array(), array( $this, 'append_baba' ) ) );
	}

	public function test_map_deep_should_map_each_element_of_array_one_level_deep() {
		$this->assertSame(
			array(
				'ababa',
				'xbaba',
			),
			map_deep(
				array(
					'a',
					'x',
				),
				array( $this, 'append_baba' )
			)
		);
	}

	public function test_map_deep_should_map_each_element_of_array_two_levels_deep() {
		$this->assertSame(
			array(
				'ababa',
				array(
					'xbaba',
				),
			),
			map_deep(
				array(
					'a',
					array(
						'x',
					),
				),
				array( $this, 'append_baba' )
			)
		);
	}

	public function test_map_deep_should_map_each_object_element_of_an_array() {
		$this->assertEqualSets(
			array(
				'var0' => 'ababa',
				'var1' => (object) array(
					'var0' => 'xbaba',
				),
			),
			map_deep(
				array(
					'var0' => 'a',
					'var1' => (object) array(
						'var0' => 'x',
					),
				),
				array( $this, 'append_baba' )
			)
		);
	}

	public function test_map_deep_should_apply_the_function_to_a_string() {
		$this->assertSame( 'xbaba', map_deep( 'x', array( $this, 'append_baba' ) ) );
	}

	public function test_map_deep_should_apply_the_function_to_an_integer() {
		$this->assertSame( '5baba', map_deep( 5, array( $this, 'append_baba' ) ) );
	}

	public function test_map_deep_should_map_each_property_of_an_object() {
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			(object) array(
				'var0' => 'ababa',
				'var1' => 'xbaba',
			),
			map_deep(
				(object) array(
					'var0' => 'a',
					'var1' => 'x',
				),
				array( $this, 'append_baba' )
			)
		);
	}

	public function test_map_deep_should_map_each_array_property_of_an_object() {
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			(object) array(
				'var0' => 'ababa',
				'var1' => array(
					'xbaba',
				),
			),
			map_deep(
				(object) array(
					'var0' => 'a',
					'var1' => array(
						'x',
					),
				),
				array( $this, 'append_baba' )
			)
		);
	}

	public function test_map_deep_should_map_each_object_property_of_an_object() {
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			(object) array(
				'var0' => 'ababa',
				'var1' => (object) array(
					'var0' => 'xbaba',
				),
			),
			map_deep(
				(object) array(
					'var0' => 'a',
					'var1' => (object) array(
						'var0' => 'x',
					),
				),
				array( $this, 'append_baba' )
			)
		);
	}

	/**
	 * @ticket 35058
	 */
	public function test_map_deep_should_map_object_properties_passed_by_reference() {
		$object_a = (object) array( 'var0' => 'a' );
		$object_b = (object) array(
			'var0' => &$object_a->var0,
			'var1' => 'x',
		);
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			(object) array(
				'var0' => 'ababa',
				'var1' => 'xbaba',
			),
			map_deep( $object_b, array( $this, 'append_baba' ) )
		);
	}

	/**
	 * @ticket 35058
	 */
	public function test_map_deep_should_map_array_elements_passed_by_reference() {
		$array_a = array( 'var0' => 'a' );
		$array_b = array(
			'var0' => &$array_a['var0'],
			'var1' => 'x',
		);
		$this->assertSame(
			array(
				'var0' => 'ababa',
				'var1' => 'xbaba',
			),
			map_deep( $array_b, array( $this, 'append_baba' ) )
		);
	}

	/**
	 * @ticket 53160
	 * @dataProvider data_array_object_flags
	 *
	 * @param int    $flags          ArrayObject flags.
	 * @param string $expected_value Expected value of the stored entry.
	 */
	public function test_map_deep_should_map_array_object_entries_and_public_properties( $flags, $expected_value ) {
		$value = new class( array( 'entry' => 'x' ), $flags ) extends ArrayObject {
			public $property = 'a';
		};

		$result = map_deep( $value, array( $this, 'append_baba' ) );

		$this->assertSame( $value, $result, 'The original object should be returned.' );
		$this->assertSame( $flags, $result->getFlags(), 'The flags should not change.' );
		$this->assertSame( 'ababa', $result->property, 'Public properties should still be mapped.' );
		$this->assertSame( array( 'entry' => $expected_value ), $result->getArrayCopy() );
	}

	/**
	 * Data provider for ArrayObject flag combinations.
	 *
	 * @return array[]
	 */
	public static function data_array_object_flags() {
		return array(
			'default'                     => array( 0, 'xbaba' ),
			'array entries as properties' => array( ArrayObject::ARRAY_AS_PROPS, 'xbaba' ),
			'public properties only'      => array( ArrayObject::STD_PROP_LIST, 'x' ),
			'both flags'                  => array( ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS, 'x' ),
		);
	}

	/**
	 * @ticket 53160
	 */
	public function test_map_deep_should_map_nested_array_objects() {
		$inner = new ArrayObject( array( 'x' ) );
		$outer = new ArrayObject(
			array(
				'array'  => array( 'a' ),
				'object' => (object) array( 'inner' => $inner ),
			)
		);

		$result = map_deep( array( 'outer' => $outer ), array( $this, 'append_baba' ) );

		$this->assertSame( $outer, $result['outer'] );
		$this->assertSame( array( 'ababa' ), $outer['array'] );
		$this->assertSame( $inner, $outer['object']->inner );
		$this->assertSame( array( 'xbaba' ), $inner->getArrayCopy() );
	}

	/**
	 * @ticket 53160
	 */
	public function test_map_deep_should_map_array_object_backed_by_an_object() {
		$storage = (object) array( 'entry' => 'x' );
		$value   = new ArrayObject( $storage );

		$this->assertSame( $value, map_deep( $value, array( $this, 'append_baba' ) ) );
		$this->assertSame( 'xbaba', $storage->entry );
	}

	/**
	 * @ticket 53160
	 */
	public function test_map_deep_should_not_call_callback_for_empty_array_object() {
		$value = new ArrayObject();

		$result = map_deep(
			$value,
			function () {
				$this->fail( 'The callback should not be called for an empty ArrayObject.' );
			}
		);

		$this->assertSame( $value, $result );
		$this->assertSame( array(), $result->getArrayCopy() );
	}

	/**
	 * @ticket 53160
	 * @dataProvider data_array_object_callers
	 *
	 * @param callable $callback Function using map_deep().
	 * @param string   $input    Input value.
	 * @param string   $expected Expected mapped value.
	 */
	public function test_map_deep_callers_should_map_array_object_entries( $callback, $input, $expected ) {
		$value = new ArrayObject( array( 'entry' => $input ) );

		$this->assertSame( $value, $callback( $value ) );
		$this->assertSame( array( 'entry' => $expected ), $value->getArrayCopy() );
	}

	/**
	 * Data provider for functions using map_deep().
	 *
	 * @return array[]
	 */
	public static function data_array_object_callers() {
		return array(
			'strip slashes'  => array( 'stripslashes_deep', "a\\'b", "a'b" ),
			'encode URL'     => array( 'urlencode_deep', 'a b', 'a+b' ),
			'encode raw URL' => array( 'rawurlencode_deep', 'a b', 'a%20b' ),
			'decode URL'     => array( 'urldecode_deep', 'a+b', 'a b' ),
		);
	}

	public function append_baba( $value ) {
		return $value . 'baba';
	}
}
