<?php

/**
 * Test wp_filter_object_list().
 *
 * @group functions.php
 * @covers ::wp_filter_object_list
 */
class Tests_Functions_wpFilterObjectList extends WP_UnitTestCase {
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

	public function test_filter_object_list_and() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field1' => true,
				'field2' => true,
			),
			'AND'
		);
		$this->assertCount( 2, $list );
		$this->assertArrayHasKey( 'foo', $list );
		$this->assertArrayHasKey( 'bar', $list );
	}

	public function test_filter_object_list_or() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field1' => true,
				'field2' => true,
			),
			'OR'
		);
		$this->assertCount( 3, $list );
		$this->assertArrayHasKey( 'foo', $list );
		$this->assertArrayHasKey( 'bar', $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_not() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field2' => true,
				'field3' => true,
			),
			'NOT'
		);
		$this->assertCount( 1, $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_and_field() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field1' => true,
				'field2' => true,
			),
			'AND',
			'name'
		);
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
			),
			$list
		);
	}

	public function test_filter_object_list_or_field() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field2' => true,
				'field3' => true,
			),
			'OR',
			'name'
		);
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
			),
			$list
		);
	}

	public function test_filter_object_list_not_field() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field2' => true,
				'field3' => true,
			),
			'NOT',
			'name'
		);
		$this->assertSame( array( 'baz' => 'baz' ), $list );
	}

	public function test_filter_object_list_nested_array_and() {
		$list = wp_filter_object_list( $this->object_list, array( 'field4' => array( 'blue' ) ), 'AND' );
		$this->assertCount( 1, $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_nested_array_not() {
		$list = wp_filter_object_list( $this->object_list, array( 'field4' => array( 'red' ) ), 'NOT' );
		$this->assertCount( 2, $list );
		$this->assertArrayHasKey( 'bar', $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_nested_array_or() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field3' => true,
				'field4' => array( 'blue' ),
			),
			'OR'
		);
		$this->assertCount( 2, $list );
		$this->assertArrayHasKey( 'foo', $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_nested_array_or_singular() {
		$list = wp_filter_object_list( $this->object_list, array( 'field4' => array( 'blue' ) ), 'OR' );
		$this->assertCount( 1, $list );
		$this->assertArrayHasKey( 'baz', $list );
	}

	public function test_filter_object_list_nested_array_and_field() {
		$list = wp_filter_object_list( $this->object_list, array( 'field4' => array( 'blue' ) ), 'AND', 'name' );
		$this->assertSame( array( 'baz' => 'baz' ), $list );
	}

	public function test_filter_object_list_nested_array_not_field() {
		$list = wp_filter_object_list( $this->object_list, array( 'field4' => array( 'green' ) ), 'NOT', 'name' );
		$this->assertSame(
			array(
				'foo' => 'foo',
				'baz' => 'baz',
			),
			$list
		);
	}

	public function test_filter_object_list_nested_array_or_field() {
		$list = wp_filter_object_list(
			$this->object_list,
			array(
				'field3' => true,
				'field4' => array( 'blue' ),
			),
			'OR',
			'name'
		);
		$this->assertSame(
			array(
				'foo' => 'foo',
				'baz' => 'baz',
			),
			$list
		);
	}
}
