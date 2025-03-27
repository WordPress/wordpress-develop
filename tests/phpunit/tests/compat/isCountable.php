<?php

/**
 * @group compat
 *
 * @covers ::is_countable
 */
class Tests_Compat_isCountable extends WP_UnitTestCase {

	/**
	 * Test that is_countable() is always available (either from PHP or WP).
	 *
	 * @ticket 43583
	 */
	public function test_is_countable_availability() {
		$this->assertTrue( function_exists( 'is_countable' ) );
	}

	/**
	 * Test is_countable() polyfill.
	 *
	 * @ticket 43583
	 *
	 * @dataProvider data_is_countable_functionality
	 *
	 * @param mixed $variable     Variable to check.
	 * @param bool  $is_countable The expected return value of PHP 7.3 is_countable() function.
	 */
	public function test_is_countable_functionality( $variable, $is_countable ) {
		$this->assertSame( $is_countable, is_countable( $variable ) );
	}

	/**
	 * Data provider for test_is_countable_functionality().
	 *
	 * @ticket 43583
	 *
	 * @return array {
	 *     @type array {
	 *         @type mixed $variable     Variable to check.
	 *         @type bool  $is_countable The expected return value of PHP 7.3 is_countable() function.
	 *     }
	 * }
	 */
	public function data_is_countable_functionality() {
		return array(
			'boolean true'                     => array(
				'variable'     => true,
				'is_countable' => false,
			),
			'plain stdClass object'            => array(
				'variable'     => new stdClass(),
				'is_countable' => false,
			),
			'Array iterator object'            => array(
				'variable'     => new ArrayIteratorFakeForIsCountable(),
				'is_countable' => true,
			),
			'Countable object'                 => array(
				'variable'     => new CountableFakeForIsCountable(),
				'is_countable' => true,
			),
			'integer 16'                       => array(
				'variable'     => 16,
				'is_countable' => false,
			),
			'null'                             => array(
				'variable'     => null,
				'is_countable' => false,
			),
			'non-empty array, 3 items'         => array(
				'variable'     => array( 1, 2, 3 ),
				'is_countable' => true,
			),
			'non-empty array, 1 item via cast' => array(
				'variable'     => (array) 1,
				'is_countable' => true,
			),
			'array cast to object'             => array(
				'variable'     => (object) array( 'foo', 'bar', 'baz' ),
				'is_countable' => false,
			),
		);
	}

	/**
	 * Test is_countable() polyfill for ResourceBundle.
	 *
	 * @ticket 43583
	 *
	 * @requires extension intl
	 */
	public function test_is_countable_ResourceBundle() {
		$this->assertTrue( is_countable( new ResourceBundle( 'en', null ) ) );
	}

	/**
	 * Test is_countable() polyfill for SimpleXMLElement.
	 *
	 * @ticket 43583
	 *
	 * @requires extension simplexml
	 */
	public function test_is_countable_SimpleXMLElement() {
		$this->assertTrue( is_countable( new SimpleXMLElement( '<xml><tag>1</tag><tag>2</tag></xml>' ) ) );
	}
}

class ArrayIteratorFakeForIsCountable extends ArrayIterator {
}

class CountableFakeForIsCountable implements Countable {
	#[ReturnTypeWillChange]
	public function count() {
		return 16;
	}
}
