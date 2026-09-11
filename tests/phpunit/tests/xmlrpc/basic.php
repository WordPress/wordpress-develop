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
	 * Tests that non-scalar credentials are rejected before reaching wp_authenticate().
	 *
	 * A non-scalar password triggers a fatal error there, and a non-scalar username
	 * an E_USER_WARNING.
	 *
	 * @ticket 65600
	 *
	 * @dataProvider data_non_scalar_credentials
	 *
	 * @covers wp_xmlrpc_server::login
	 *
	 * @param mixed $username Username argument.
	 * @param mixed $password Password argument.
	 */
	public function test_login_with_non_scalar_credentials( $username, $password ): void {
		$this->assertFalse( $this->myxmlrpcserver->login( $username, $password ), 'The login did not fail.' );
		$this->assertIXRError( $this->myxmlrpcserver->error );
		$this->assertSame( 403, $this->myxmlrpcserver->error->code, 'The error code was not 403.' );
		$this->assertSame( 'Incorrect username or password.', $this->myxmlrpcserver->error->message, 'The error message did not match.' );
	}

	/**
	 * Data provider for test_login_with_non_scalar_credentials.
	 *
	 * @return array[]
	 */
	public static function data_non_scalar_credentials(): array {
		return array(
			'an array as password'  => array( 'subscriber', array() ),
			'an array as username'  => array( array(), 'subscriber' ),
			'an object as password' => array( 'subscriber', new IXR_Date( '20260806T00:00:00' ) ),
			'null as password'      => array( 'subscriber', null ),
		);
	}

	/**
	 * Tests that numeric credentials, which XML-RPC sends as `<int>` values,
	 * continue to authenticate.
	 *
	 * @ticket 65600
	 *
	 * @covers wp_xmlrpc_server::login
	 */
	public function test_login_with_numeric_credentials(): void {
		self::factory()->user->create(
			array(
				'user_login' => '12345',
				'user_pass'  => '67890',
				'role'       => 'subscriber',
			)
		);

		$this->assertInstanceOf( 'WP_User', $this->myxmlrpcserver->login( 12345, 67890 ) );
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
