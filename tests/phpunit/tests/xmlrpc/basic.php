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
	 * Ensures IXR_Server::call() does not fatal when $args is not an array.
	 *
	 * @ticket 65124
	 */
	public function test_call_with_non_array_args_does_not_fatal() {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		// Passing a string instead of an array must not produce a TypeError on PHP 8+.
		$result = $this->myxmlrpcserver->call( 'system.listMethods', 'not-an-array' );

		// The dispatch may return an IXR_Error or a value, but it must not fatal.
		$this->assertNotNull( $result );
	}

	/**
	 * Ensures system.multicall returns a fault for malformed per-call entries
	 * rather than triggering a fatal error.
	 *
	 * @ticket 65124
	 *
	 * @dataProvider data_malformed_multicall_payloads
	 *
	 * @param array $method_calls    Method calls payload supplied to multiCall().
	 * @param int   $expected_index  Index in the response expected to contain a fault.
	 */
	public function test_multicall_rejects_malformed_calls( $method_calls, $expected_index ) {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall( $method_calls );

		$this->assertArrayHasKey( 'faultCode', $result[ $expected_index ] );
		$this->assertSame( -32602, $result[ $expected_index ]['faultCode'] );
	}

	public function data_malformed_multicall_payloads() {
		return array(
			'params is a string' => array(
				array(
					array(
						'methodName' => 'system.listMethods',
						'params'     => 'evil',
					),
				),
				0,
			),
			'params is null'     => array(
				array(
					array(
						'methodName' => 'system.listMethods',
						'params'     => null,
					),
				),
				0,
			),
			'missing params key' => array(
				array(
					array( 'methodName' => 'system.listMethods' ),
				),
				0,
			),
			'call is a scalar'   => array(
				array( 'just-a-string' ),
				0,
			),
		);
	}

	/**
	 * Ensures system.multicall returns a top-level error when the methodcalls
	 * payload itself is not an array.
	 *
	 * @ticket 65124
	 */
	public function test_multicall_with_non_array_methodcalls_returns_error() {
		$this->myxmlrpcserver->callbacks = $this->myxmlrpcserver->methods;

		$result = $this->myxmlrpcserver->multiCall( 'not-an-array' );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( -32600, $result->code );
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
