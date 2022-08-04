<?php

/**
 * Tests for the _wp_array_set() function
 *
 * @since 5.8.0
 *
 * @group functions.php
 * @covers ::_wp_array_set
 */
class Tests_Functions_wpArraySet extends WP_UnitTestCase {

	/**
	 * Tests _wp_array_set() with invalid parameters.
	 *
	 * @ticket 53175
	 */
	public function test_wp_array_set_invalid_parameters() {
		$test = 3;
		_wp_array_set( $test, array( 'a' ), 1 );
		$this->assertSame(
			$test,
			3
		);

		$test_array = array( 'a' => 2 );
		_wp_array_set( $test_array, 'a', 3 );
		$this->assertSame(
			$test_array,
			array( 'a' => 2 )
		);

		$test_array = array( 'a' => 2 );
		_wp_array_set( $test_array, null, 3 );
		$this->assertSame(
			$test_array,
			array( 'a' => 2 )
		);

		$test_array = array( 'a' => 2 );
		_wp_array_set( $test_array, array(), 3 );
		$this->assertSame(
			$test_array,
			array( 'a' => 2 )
		);

		$test_array = array( 'a' => 2 );
		_wp_array_set( $test_array, array( 'a', array() ), 3 );
		$this->assertSame(
			$test_array,
			array( 'a' => 2 )
		);
	}

	/**
	 * Tests _wp_array_set() with simple non-subtree path.
	 *
	 * @ticket 53175
	 */
	public function test_wp_array_set_simple_non_subtree() {
		$test_array = array();
		_wp_array_set( $test_array, array( 'a' ), 1 );
		$this->assertSame(
			$test_array,
			array( 'a' => 1 )
		);

		$test_array = array( 'a' => 2 );
		_wp_array_set( $test_array, array( 'a' ), 3 );
		$this->assertSame(
			$test_array,
			array( 'a' => 3 )
		);

		$test_array = array( 'b' => 1 );
		_wp_array_set( $test_array, array( 'a' ), 3 );
		$this->assertSame(
			$test_array,
			array(
				'b' => 1,
				'a' => 3,
			)
		);
	}

	/**
	 * Tests _wp_array_set() with subtree paths.
	 *
	 * @ticket 53175
	 */
	public function test_wp_array_set_subtree() {
		$test_array = array();
		_wp_array_set( $test_array, array( 'a', 'b', 'c' ), 1 );
		$this->assertSame(
			$test_array,
			array( 'a' => array( 'b' => array( 'c' => 1 ) ) )
		);

		$test_array = array( 'b' => 3 );
		_wp_array_set( $test_array, array( 'a', 'b', 'c' ), 1 );
		$this->assertSame(
			$test_array,
			array(
				'b' => 3,
				'a' => array( 'b' => array( 'c' => 1 ) ),
			)
		);

		$test_array = array(
			'b' => 3,
			'a' => 1,
		);
		_wp_array_set( $test_array, array( 'a', 'b', 'c' ), 1 );
		$this->assertSame(
			$test_array,
			array(
				'b' => 3,
				'a' => array( 'b' => array( 'c' => 1 ) ),
			)
		);

		$test_array = array(
			'b' => 3,
			'a' => array(),
		);
		_wp_array_set( $test_array, array( 'a', 'b', 'c' ), 1 );
		$this->assertSame(
			$test_array,
			array(
				'b' => 3,
				'a' => array( 'b' => array( 'c' => 1 ) ),
			)
		);
	}
}
