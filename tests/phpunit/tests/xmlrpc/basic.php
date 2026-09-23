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
	 * Tests that non-scalar credentials return an error instead of causing a fatal error.
	 *
	 * @ticket 66168
	 *
	 * @covers wp_xmlrpc_server::login
	 *
	 * @dataProvider data_login_rejects_non_scalar_credentials
	 *
	 * @param mixed $username The username argument.
	 * @param mixed $password The password argument.
	 */
	public function test_login_rejects_non_scalar_credentials( $username, $password ): void {
		$this->make_user_by_role( 'subscriber' );

		$this->assertFalse( $this->myxmlrpcserver->login( $username, $password ) );
		$this->assertIXRError( $this->myxmlrpcserver->error );
		$this->assertSame( 400, $this->myxmlrpcserver->error->code );

		// A rejected request is not a failed login attempt, so a valid login should still succeed.
		$this->assertInstanceOf( WP_User::class, $this->myxmlrpcserver->login( 'subscriber', 'subscriber' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed, 1: mixed }>
	 */
	public static function data_login_rejects_non_scalar_credentials(): array {
		return array(
			'array username'      => array( array( 'subscriber' ), 'subscriber' ),
			'array password'      => array( 'subscriber', array( 'subscriber' ) ),
			'IXR_Base64 password' => array( 'subscriber', new IXR_Base64( 'subscriber' ) ),
		);
	}

	/**
	 * Tests that integer credentials are still passed through to authentication.
	 *
	 * @ticket 66168
	 *
	 * @covers wp_xmlrpc_server::login
	 */
	public function test_login_passes_integer_credentials_to_authentication(): void {
		$this->assertFalse( $this->myxmlrpcserver->login( 12345, 67890 ) );
		$this->assertIXRError( $this->myxmlrpcserver->error );
		$this->assertSame( 403, $this->myxmlrpcserver->error->code );
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
