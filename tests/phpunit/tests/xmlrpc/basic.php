<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . WPINC . '/class-IXR.php';
require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_Basic extends WP_XMLRPC_UnitTestCase {
	public function test_enabled() {
		$result = $this->myxmlrpcserver->wp_getOptions( array( 1, 'username', 'password' ) );

		$this->assertIXRError( $result );
		// If disabled, 405 would result.
		$this->assertSame( 403, $result->code );
	}

	public function test_login_pass_ok() {
		$this->make_user_by_role( 'subscriber' );

		$this->assertTrue( $this->myxmlrpcserver->login_pass_ok( 'subscriber', 'subscriber' ) );
		$this->assertInstanceOf( 'WP_User', $this->myxmlrpcserver->login( 'subscriber', 'subscriber' ) );
	}

	public function test_login_pass_bad() {
		$this->make_user_by_role( 'subscriber' );

		$this->assertFalse( $this->myxmlrpcserver->login_pass_ok( 'username', 'password' ) );
		$this->assertFalse( $this->myxmlrpcserver->login( 'username', 'password' ) );

		// The auth will still fail due to authentication blocking after the first failed attempt.
		$this->assertFalse( $this->myxmlrpcserver->login_pass_ok( 'subscriber', 'subscriber' ) );
	}

	/**
	 * @ticket 34336
	 */
	public function test_multicall_invalidates_all_calls_after_invalid_call() {
		$editor_id = $this->make_user_by_role( 'editor' );
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $editor_id,
			)
		);

		$method_calls = array(
			// Valid login.
			array(
				'methodName' => 'wp.editPost',
				'params'     => array(
					0,
					'editor',
					'editor',
					$post_id,
					array(
						'title' => 'Title 1',
					),
				),
			),
			// *Invalid* login.
			array(
				'methodName' => 'wp.editPost',
				'params'     => array(
					0,
					'editor',
					'password',
					$post_id,
					array(
						'title' => 'Title 2',
					),
				),
			),
			// Valid login.
			array(
				'methodName' => 'wp.editPost',
				'params'     => array(
					0,
					'editor',
					'editor',
					$post_id,
					array(
						'title' => 'Title 3',
					),
				),
			),
		);

		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall( $method_calls );

		$this->assertArrayNotHasKey( 'faultCode', $result[0] );
		$this->assertArrayHasKey( 'faultCode', $result[1] );
		$this->assertArrayHasKey( 'faultCode', $result[2] );
	}

	/**
	 * Tests that a multicall entry with non-array params returns a fault without stopping the remaining calls.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::multiCall
	 */
	public function test_multicall_with_non_array_params(): void {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall(
			array( // @phpstan-ignore argument.type (Intentionally passing non-array params.)
				array(
					'methodName' => 'demo.sayHello',
					'params'     => 'x',
				),
				array(
					'methodName' => 'demo.sayHello',
					'params'     => array(),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'faultCode', $result[0] );
		$this->assertSame( -32602, $result[0]['faultCode'] );
		$this->assertSame( array( 'Hello!' ), $result[1] );
	}

	/**
	 * Tests that a multicall entry with a single-member struct as params passes the struct through intact.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::call
	 */
	public function test_multicall_with_single_member_struct_params(): void {
		$this->myxmlrpcserver->callbacks = array(
			'test.echo' => array( $this, 'echo_args' ),
		);

		$result = $this->myxmlrpcserver->multiCall(
			array(
				array(
					'methodName' => 'test.echo',
					'params'     => array( 'foo' => 'bar' ),
				),
			)
		);

		$this->assertSame( array( array( array( 'foo' => 'bar' ) ) ), $result );
	}

	/**
	 * Returns the args passed to an XML-RPC method callback.
	 *
	 * @param mixed $args Method args.
	 * @return mixed The args.
	 */
	public function echo_args( $args ) {
		return $args;
	}

	/**
	 * Tests that a multicall entry with missing params does not cause a fatal error.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::multiCall
	 */
	public function test_multicall_with_missing_params(): void {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall(
			array(
				array(
					'methodName' => 'demo.sayHello',
				),
			)
		);

		$this->assertSame( array( array( 'Hello!' ) ), $result );
	}

	/**
	 * Tests that a system.multicall call with a non-array argument returns a fault.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::multiCall
	 */
	public function test_multicall_with_non_array_argument(): void {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall( 'x' ); // @phpstan-ignore argument.type (Intentionally passing a non-array argument.)

		$this->assertIXRError( $result );
		$this->assertSame( -32600, $result->code );
	}

	/**
	 * Tests that a non-struct multicall entry returns a fault without stopping the remaining calls.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::multiCall
	 */
	public function test_multicall_with_non_struct_entry(): void {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall(
			array( // @phpstan-ignore argument.type (Intentionally passing a non-struct entry.)
				'x',
				array(
					'methodName' => 'demo.sayHello',
					'params'     => array(),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'faultCode', $result[0] );
		$this->assertSame( -32600, $result[0]['faultCode'] );
		$this->assertSame( array( 'Hello!' ), $result[1] );
	}

	/**
	 * Tests that a multicall entry with no methodName returns a fault without stopping the remaining calls.
	 *
	 * @ticket 66160
	 *
	 * @covers IXR_Server::multiCall
	 */
	public function test_multicall_with_missing_method_name(): void {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall(
			array( // @phpstan-ignore argument.type (Intentionally omitting methodName.)
				array( 'params' => array() ),
				array(
					'methodName' => 'demo.sayHello',
					'params'     => array(),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'faultCode', $result[0] );
		$this->assertSame( -32600, $result[0]['faultCode'] );
		$this->assertSame( array( 'Hello!' ), $result[1] );
	}

	/**
	 * @ticket 36586
	 */
	public function test_isStruct_on_non_numerically_indexed_array() {
		$value = new IXR_Value( array( '0.0' => 100 ) );

		$return  = "<struct>\n";
		$return .= "  <member><name>0.0</name><value><int>100</int></value></member>\n";
		$return .= '</struct>';

		$this->assertXmlStringEqualsXmlString( $return, $value->getXML() );
	}

	public function test_disabled() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		$testcase_xmlrpc_server = new wp_xmlrpc_server();
		$result                 = $testcase_xmlrpc_server->wp_getOptions( array( 1, 'username', 'password' ) );

		$this->assertIXRError( $result );
		$this->assertSame( 405, $result->code );
	}
}
