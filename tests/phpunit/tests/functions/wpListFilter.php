<?php

/**
 * Test wp_list_filter().
 *
 * @group functions.php
 * @covers ::wp_list_filter
 */
class Tests_Functions_wpListFilter extends WP_UnitTestCase {

	/**
	 * @dataProvider data_test_wp_list_filter
	 *
	 * @param array  $list     An array of objects to filter.
	 * @param array  $args     An array of key => value arguments to match
	 *                         against each object.
	 * @param string $operator The logical operation to perform.
	 * @param array  $expected Expected result.
	 */
	public function test_wp_list_filter( $list, $args, $operator, $expected ) {
		$this->assertEqualSetsWithIndex( $expected, wp_list_filter( $list, $args, $operator ) );
	}

	public function data_test_wp_list_filter() {
		return array(
			'string instead of array'  => array(
				'foo',
				array(),
				'AND',
				array(),
			),
			'object instead of array'  => array(
				(object) array( 'foo' ),
				array(),
				'AND',
				array(),
			),
			'empty args'               => array(
				array( 'foo', 'bar' ),
				array(),
				'AND',
				array( 'foo', 'bar' ),
			),
			'invalid operator'         => array(
				array(
					(object) array( 'foo' => 'bar' ),
					(object) array( 'foo' => 'baz' ),
				),
				array( 'foo' => 'bar' ),
				'XOR',
				array(),
			),
			'single argument to match' => array(
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
			'all must match'           => array(
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
			'any must match'           => array(
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
			'none must match'          => array(
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
		);
	}
}
