<?php

/**
 * Test wp_list_filter().
 *
 * @group functions
 *
 * @covers ::wp_list_filter
 */
class Tests_Functions_wpListFilter extends WP_UnitTestCase {

	/**
	 * @ticket 53987
	 *
	 * @dataProvider data_wp_list_filter
	 *
	 * @param array  $input_list An array of objects to filter.
	 * @param array  $args       An array of key => value arguments to match
	 *                           against each object.
	 * @param string $operator   The logical operation to perform.
	 * @param array  $expected   Expected result.
	 */
	public function test_wp_list_filter( $input_list, $args, $operator, $expected ) {
		$this->assertEqualSetsWithIndex( $expected, wp_list_filter( $input_list, $args, $operator ) );
	}

	/**
	 * Tests that the 'AND' operator is used when no operator is passed.
	 *
	 * @ticket 53987
	 */
	public function test_wp_list_filter_should_default_to_and_operator() {
		$input_list = array(
			(object) array(
				'foo' => 'bar',
				'bar' => 'baz',
			),
			(object) array( 'foo' => 'bar' ),
		);

		$this->assertEqualSetsWithIndex(
			array( 0 => $input_list[0] ),
			wp_list_filter(
				$input_list,
				array(
					'foo' => 'bar',
					'bar' => 'baz',
				)
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_list_filter() {
		return array(
			'string instead of array'                   => array(
				'foo',
				array(),
				'AND',
				array(),
			),
			'object instead of array'                   => array(
				(object) array( 'foo' ),
				array(),
				'AND',
				array(),
			),
			'empty args'                                => array(
				array( 'foo', 'bar' ),
				array(),
				'AND',
				array( 'foo', 'bar' ),
			),
			'invalid operator'                          => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'XOR',
				array(),
			),
			'single argument to match'                  => array(
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
					(object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array( 'foo' => 'bar' ),
				'AND',
				array(
					0 => (object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					3 => (object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
			),
			'all must match'                            => array(
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
						'bar' => 'baz',
					),
					(object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				'AND',
				array(
					0 => (object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
				),
			),
			'any must match'                            => array(
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
						'bar' => 'baz',
					),
					(object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'value',
					'bar' => 'baz',
				),
				'OR',
				array(
					0 => (object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					2 => (object) array(
						'foo' => 'baz',
						'key' => 'value',
						'bar' => 'baz',
					),
					3 => (object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
			),
			'none must match'                           => array(
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
					(object) array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'value',
					'bar' => 'baz',
				),
				'NOT',
				array(
					1 => (object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'string to int comparison'                  => array(
				array(
					(object) array(
						'foo' => '1',
					),
				),
				array( 'foo' => 1 ),
				'AND',
				array(
					0 => (object) array(
						'foo' => '1',
					),
				),
			),
			'bool to string comparison'                 => array(
				array(
					(object) array(
						'foo' => true,
					),
				),
				array( 'foo' => 'yes' ),
				'AND',
				array(
					0 => (object) array(
						'foo' => true,
					),
				),
			),
			'null instead of array'                     => array(
				null,
				array( 'foo' => 'bar' ),
				'AND',
				array(),
			),
			'empty list'                                => array(
				array(),
				array( 'foo' => 'bar' ),
				'AND',
				array(),
			),

			/*
			 * Empty arguments are checked before the operator is validated,
			 * so the original list is returned even for an invalid operator.
			 */
			'empty args, invalid operator'              => array(
				array( 'foo', 'bar' ),
				array(),
				'XOR',
				array( 'foo', 'bar' ),
			),

			// The operator is uppercased before it is validated.
			'lowercase AND operator'                    => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'and',
				array(
					0 => (object) array( 'foo' => 'bar' ),
				),
			),
			'mixed case OR operator'                    => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'oR',
				array(
					0 => (object) array( 'foo' => 'bar' ),
				),
			),
			'lowercase NOT operator'                    => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'not',
				array(
					1 => (object) array( 'foo' => 'baz' ),
				),
			),

			// Lists of arrays are supported alongside lists of objects.
			'arrays: single argument to match'          => array(
				array(
					array(
						'foo' => 'bar',
						'key' => 'foo',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array( 'foo' => 'bar' ),
				'AND',
				array(
					0 => array(
						'foo' => 'bar',
						'key' => 'foo',
					),
					2 => array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
			),
			'arrays: all must match'                    => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
					),
					array( 'foo' => 'bar' ),
				),
				array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				'AND',
				array(
					0 => array(
						'foo' => 'bar',
						'bar' => 'baz',
					),
				),
			),
			'arrays: any must match'                    => array(
				array(
					array( 'foo' => 'bar' ),
					array( 'bar' => 'baz' ),
					array( 'foo' => 'baz' ),
				),
				array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				'OR',
				array(
					0 => array( 'foo' => 'bar' ),
					1 => array( 'bar' => 'baz' ),
				),
			),
			'arrays: none must match'                   => array(
				array(
					array( 'foo' => 'bar' ),
					array( 'bar' => 'baz' ),
					array( 'foo' => 'baz' ),
				),
				array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				'NOT',
				array(
					2 => array( 'foo' => 'baz' ),
				),
			),
			'mixed arrays and objects'                  => array(
				array(
					array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'bar' ),
					array( 'foo' => 'baz' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'AND',
				array(
					0 => array( 'foo' => 'bar' ),
					1 => (object) array( 'foo' => 'bar' ),
				),
			),

			/*
			 * Values that are neither arrays nor objects can never match,
			 * so they are only returned by the 'NOT' operator.
			 */
			'scalar values with AND'                    => array(
				array( 'a string', 42, null, array( 'foo' => 'bar' ) ),
				array( 'foo' => 'bar' ),
				'AND',
				array(
					3 => array( 'foo' => 'bar' ),
				),
			),
			'scalar values with NOT'                    => array(
				array( 'a string', 42, null, array( 'foo' => 'bar' ) ),
				array( 'foo' => 'bar' ),
				'NOT',
				array(
					0 => 'a string',
					1 => 42,
					2 => null,
				),
			),

			/*
			 * Array values are checked with array_key_exists(), so a null value
			 * matches, while object properties are checked with isset(),
			 * so a null property does not.
			 */
			'array with a null value matching null'     => array(
				array( array( 'foo' => null ) ),
				array( 'foo' => null ),
				'AND',
				array(
					0 => array( 'foo' => null ),
				),
			),
			'object with a null property matching null' => array(
				array( (object) array( 'foo' => null ) ),
				array( 'foo' => null ),
				'AND',
				array(),
			),

			'string keys are preserved'                 => array(
				array(
					'first'  => (object) array( 'foo' => 'bar' ),
					'second' => (object) array( 'foo' => 'baz' ),
					'third'  => (object) array( 'foo' => 'bar' ),
				),
				array( 'foo' => 'bar' ),
				'AND',
				array(
					'first' => (object) array( 'foo' => 'bar' ),
					'third' => (object) array( 'foo' => 'bar' ),
				),
			),
			'no matches with OR'                        => array(
				array(
					(object) array( 'foo' => 'baz' ),
					(object) array( 'foo' => 'qux' ),
				),
				array( 'foo' => 'bar' ),
				'OR',
				array(),
			),
			'all matches with NOT'                      => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'bar' ),
				),
				array( 'foo' => 'bar' ),
				'NOT',
				array(),
			),
		);
	}
}
