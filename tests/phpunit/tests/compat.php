<?php

/**
 * @group compat
 */
class Tests_Compat extends WP_UnitTestCase {

	/**
	 * Test that is_iterable() is always available (either from PHP or WP).
	 *
	 * @ticket 43619
	 */
	function test_is_iterable_availability() {
		$this->assertTrue( function_exists( 'is_iterable' ) );
	}

	/**
	 * Test is_iterable() polyfill.
	 *
	 * @ticket 43619
	 *
	 * @dataProvider iterable_variable_test_data
	 *
	 * @param mixed $variable    Variable to check.
	 * @param bool  $is_iterable The expected return value of PHP 7.1 is_iterable() function.
	 */
	function test_is_iterable_functionality( $variable, $is_iterable ) {
		$this->assertSame( is_iterable( $variable ), $is_iterable );
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
	public function iterable_variable_test_data() {
		return array(
			array( array(), true ),
			array( array( 1, 2, 3 ), true ),
			array( new ArrayIterator( array( 1, 2, 3 ) ), true ),
			array( 1, false ),
			array( 3.14, false ),
			array( new stdClass(), false ),
		);
	}
}
