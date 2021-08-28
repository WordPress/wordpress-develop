<?php

/**
 * @group compat
 */
class Tests_Compat extends WP_UnitTestCase {

	function test_hash_hmac_simple() {
		$this->assertSame( '140d1cb79fa12e2a31f32d35ad0a2723', _hash_hmac( 'md5', 'simple', 'key' ) );
		$this->assertSame( '993003b95758e0ac2eba451a4c5877eb1bb7b92a', _hash_hmac( 'sha1', 'simple', 'key' ) );
	}

	function test_hash_hmac_padding() {
		$this->assertSame( '3c1399103807cf12ec38228614416a8c', _hash_hmac( 'md5', 'simple', '65 character key 65 character key 65 character key 65 character k' ) );
		$this->assertSame( '4428826d20003e309d6c2a6515891370daf184ea', _hash_hmac( 'sha1', 'simple', '65 character key 65 character key 65 character key 65 character k' ) );
	}

	function test_hash_hmac_output() {
		$this->assertSame( array( 1 => '140d1cb79fa12e2a31f32d35ad0a2723' ), unpack( 'H32', _hash_hmac( 'md5', 'simple', 'key', true ) ) );
		$this->assertSame( array( 1 => '993003b95758e0ac2eba451a4c5877eb1bb7b92a' ), unpack( 'H40', _hash_hmac( 'sha1', 'simple', 'key', true ) ) );
	}

	function test_json_encode_decode() {
		$this->expectDeprecation();

		require_once ABSPATH . WPINC . '/class-json.php';
		$json = new Services_JSON();
		// Super basic test to verify Services_JSON is intact and working.
		$this->assertSame( '["foo"]', $json->encodeUnsafe( array( 'foo' ) ) );
		$this->assertSame( array( 'foo' ), $json->decode( '["foo"]' ) );
	}

	/**
	 * Test that is_countable() is always available (either from PHP or WP).
	 *
	 * @ticket 43583
	 */
	function test_is_countable_availability() {
		$this->assertTrue( function_exists( 'is_countable' ) );
	}

	/**
	 * Test is_countable() polyfill.
	 *
	 * @ticket 43583
	 *
	 * @dataProvider countable_variable_test_data
	 *
	 * @param mixed $variable     Variable to check.
	 * @param bool  $is_countable The expected return value of PHP 7.3 is_countable() function.
	 */
	function test_is_countable_functionality( $variable, $is_countable ) {
		$this->assertSame( is_countable( $variable ), $is_countable );
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
	public function countable_variable_test_data() {
		return array(
			array( true, false ),
			array( new stdClass(), false ),
			array( new ArrayIteratorFake(), true ),
			array( new CountableFake(), true ),
			array( 16, false ),
			array( null, false ),
			array( array( 1, 2, 3 ), true ),
			array( (array) 1, true ),
			array( (object) array( 'foo', 'bar', 'baz' ), false ),
		);
	}

	/**
	 * Test is_countable() polyfill for ResourceBundle.
	 *
	 * @ticket 43583
	 *
	 * @requires extension intl
	 */
	function test_is_countable_ResourceBundle() {
		$this->assertTrue( is_countable( new ResourceBundle( 'en', null ) ) );
	}

	/**
	 * Test is_countable() polyfill for SimpleXMLElement.
	 *
	 * @ticket 43583
	 *
	 * @requires extension simplexml
	 */
	function test_is_countable_SimpleXMLElement() {
		$this->assertTrue( is_countable( new SimpleXMLElement( '<xml><tag>1</tag><tag>2</tag></xml>' ) ) );
	}

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

class ArrayIteratorFake extends ArrayIterator {
}

class CountableFake implements Countable {
	#[ReturnTypeWillChange]
	public function count() {
		return 16;
	}
}
