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
	 * @ticket 55257
	 */
	public function test_map_deep_should_skip_incomplete_objects() {
		$incomplete_object = unserialize( 'O:14:"SuperCustomXyz":1:{s:17:" SuperCustomXyz x";s:4:"test";}' );

		$this->assertSame(
			array(
				0          => $incomplete_object,
				'testdata' => array(
					'a' => '1baba',
					'b' => '2baba',
				),
			),
			map_deep(
				array(
					0          => $incomplete_object,
					'testdata' => array(
						'a' => 1,
						'b' => 2,
					),
				),
				array( $this, 'append_baba' )
			),
			'map_deep should skip incomplete objects while iterating over array values.'
		);

		$this->assertEquals(
			array(
				'testdata' => (object) array(
					'a' => $incomplete_object,
					'b' => 'testbaba',
				),
			),
			map_deep(
				array(
					'testdata' => (object) array(
						'a' => $incomplete_object,
						'b' => 'test',
					),
				),
				array( $this, 'append_baba' )
			),
			'map_deep should skip incomplete objects while iterating over object properties.'
		);
	}

	public function append_baba( $value ) {
		return $value . 'baba';
	}
}
