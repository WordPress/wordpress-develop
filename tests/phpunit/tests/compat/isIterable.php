<?php

/**
 * @group compat
 *
 * @covers ::is_iterable
 */
class Tests_Compat_isIterable extends WP_UnitTestCase {

	/**
	 * Test that is_iterable() is always available (either from PHP or WP).
	 *
	 * @ticket 43619
	 */
	public function test_is_iterable_availability() {
		$this->assertTrue( function_exists( 'is_iterable' ) );
	}

	/**
	 * Test is_iterable() polyfill.
	 *
	 * @ticket 43619
	 *
	 * @dataProvider data_is_iterable_functionality
	 *
	 * @param mixed $variable    Variable to check.
	 * @param bool  $is_iterable The expected return value of PHP 7.1 is_iterable() function.
	 */
	public function test_is_iterable_functionality( $variable, $is_iterable ) {
		$this->assertSame( $is_iterable, is_iterable( $variable ) );
	}

	/**
	 * Data provider for test_is_iterable_functionality().
	 *
	 * @ticket 43619
	 *
	 * @return array {
	 *     @type array {
	 *         @type mixed $variable    Variable to check.
	 *         @type bool  $is_iterable The expected return value of PHP 7.1 is_iterable() function.
	 *     }
	 * }
	 */
	public function data_is_iterable_functionality() {
		return array(
			'empty array'           => array(
				'variable'    => array(),
				'is_iterable' => true,
			),
			'non-empty array'       => array(
				'variable'    => array( 1, 2, 3 ),
				'is_iterable' => true,
			),
			'Iterator object'       => array(
				'variable'    => new ArrayIterator( array( 1, 2, 3 ) ),
				'is_iterable' => true,
			),
			'null'                  => array(
				'variable'    => null,
				'is_iterable' => false,
			),
			'integer 1'             => array(
				'variable'    => 1,
				'is_iterable' => false,
			),
			'float 3.14'            => array(
				'variable'    => 3.14,
				'is_iterable' => false,
			),
			'plain stdClass object' => array(
				'variable'    => new stdClass(),
				'is_iterable' => false,
			),
		);
	}
}
