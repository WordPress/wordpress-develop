<?php

/**
 * Tests for the XML-RPC demo.addTwoNumbers method.
 *
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::addTwoNumbers
 */
class Tests_XMLRPC_demo_addTwoNumbers extends WP_XMLRPC_UnitTestCase {

	/**
	 * Tests that addTwoNumbers returns the correct sum for valid integer inputs.
	 *
	 * @dataProvider data_valid_integers
	 *
	 * @param int $a        First number.
	 * @param int $b        Second number.
	 * @param int $expected Expected sum.
	 */
	public function test_add_two_numbers_with_valid_integers( $a, $b, $expected ) {
		$result = $this->myxmlrpcserver->addTwoNumbers( array( $a, $b ) );

		$this->assertNotIXRError( $result );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_add_two_numbers_with_valid_integers.
	 *
	 * @return array<string, int[]>
	 */
	public function data_valid_integers(): array {
		return array(
			'two positive integers'         => array( 3, 5, 8 ),
			'positive and negative integer' => array( 10, -3, 7 ),
			'two negative integers'         => array( -5, -7, -12 ),
			'zero and positive integer'     => array( 0, 42, 42 ),
			'zero and negative integer'     => array( 0, -42, -42 ),
			'two zeros'                     => array( 0, 0, 0 ),
			'large integers'                => array( 1000000, 2000000, 3000000 ),
		);
	}

	/**
	 * Tests that addTwoNumbers returns an error when invalid types are passed.
	 *
	 * @dataProvider data_invalid_arguments
	 *
	 * @param mixed  $a       First argument.
	 * @param mixed  $b       Second argument.
	 * @param string $message Description of the test case.
	 */
	public function test_add_two_numbers_with_invalid_arguments( $a, $b, $message ) {
		$result = $this->myxmlrpcserver->addTwoNumbers( array( $a, $b ) );

		$this->assertIsNotInt( $result );
		$this->assertSame( 400, $result->code, $message );
		$this->assertSame(
			'Invalid arguments passed to this XML-RPC method. Requires two integers.',
			$result->message,
			$message
		);
	}

	/**
	 * Data provider for test_add_two_numbers_with_invalid_arguments.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_invalid_arguments(): array {
		return array(
			'first argument is string'          => array( 'abc', 5, 'Should fail when first argument is a string.' ),
			'second argument is string'         => array( 3, 'abc', 'Should fail when second argument is a string.' ),
			'both arguments are strings'        => array( 'foo', 'bar', 'Should fail when both arguments are strings.' ),
			'first argument is float'           => array( 3.14, 5, 'Should fail when first argument is a float.' ),
			'second argument is float'          => array( 3, 5.5, 'Should fail when second argument is a float.' ),
			'both arguments are floats'         => array( 1.1, 2.2, 'Should fail when both arguments are floats.' ),
			'first argument is null'            => array( null, 5, 'Should fail when first argument is null.' ),
			'second argument is null'           => array( 3, null, 'Should fail when second argument is null.' ),
			'first argument is boolean'         => array( true, 5, 'Should fail when first argument is boolean.' ),
			'second argument is boolean'        => array( 3, false, 'Should fail when second argument is boolean.' ),
			'first argument is array'           => array( array( 1 ), 5, 'Should fail when first argument is an array.' ),
			'second argument is array'          => array( 3, array( 2 ), 'Should fail when second argument is an array.' ),
			'numeric string as first argument'  => array( '3', 5, 'Should fail when first argument is a numeric string.' ),
			'numeric string as second argument' => array( 3, '5', 'Should fail when second argument is a numeric string.' ),
		);
	}

	/**
	 * Tests that addTwoNumbers returns an error when no arguments are passed.
	 *
	 * When zero XML-RPC params are provided, IXR_Server passes an empty array to the method.
	 */
	public function test_add_two_numbers_with_no_arguments() {
		$result = $this->myxmlrpcserver->addTwoNumbers( array() );

		$this->assertIsNotInt( $result );
		$this->assertSame( 400, $result->code );
		$this->assertSame(
			'Invalid arguments passed to this XML-RPC method. Requires two integers.',
			$result->message
		);
	}

	/**
	 * Tests that addTwoNumbers returns an error when only one argument is passed.
	 *
	 * When exactly one XML-RPC param is provided, IXR_Server unwraps it from the array
	 * and passes the single value directly to the method (not wrapped in an array).
	 *
	 * @see IXR_Server::call() lines 95-98
	 */
	public function test_add_two_numbers_with_one_argument() {
		// IXR_Server passes single param directly, not as array.
		$result = $this->myxmlrpcserver->addTwoNumbers( 5 );

		$this->assertIsNotInt( $result );
		$this->assertSame( 400, $result->code );
		$this->assertSame(
			'Invalid arguments passed to this XML-RPC method. Requires two integers.',
			$result->message
		);
	}

	/**
	 * Tests that addTwoNumbers returns an error when too many arguments are passed.
	 *
	 * @dataProvider data_too_many_arguments
	 *
	 * @param array  $args    Arguments to pass to addTwoNumbers.
	 * @param string $message Description of the test case.
	 */
	public function test_add_two_numbers_with_too_many_arguments( $args, $message ) {
		$result = $this->myxmlrpcserver->addTwoNumbers( $args );

		$this->assertIsNotInt( $result );
		$this->assertSame( 400, $result->code, $message );
		$this->assertSame(
			'Invalid arguments passed to this XML-RPC method. Requires two integers.',
			$result->message,
			$message
		);
	}

	/**
	 * Data provider for test_add_two_numbers_with_too_many_arguments.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_too_many_arguments(): array {
		return array(
			'three arguments' => array( array( 3, 5, 100 ), 'Should fail with three arguments.' ),
			'four arguments'  => array( array( 10, 20, 30, 40 ), 'Should fail with four arguments.' ),
		);
	}
}
