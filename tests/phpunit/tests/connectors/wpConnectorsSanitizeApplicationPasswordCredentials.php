<?php
/**
 * Tests for wp_connectors_sanitize_application_password_credentials().
 *
 * @group connectors
 * @covers ::wp_connectors_sanitize_application_password_credentials
 */
class Tests_Connectors_WpConnectorsSanitizeApplicationPasswordCredentials extends WP_UnitTestCase {

	const CONNECTOR_ID             = 'wp_test_application_password_connector';
	const CREDENTIALS_SETTING_NAME = 'connectors_test_remote_credentials';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $administrator_id;

	/**
	 * Snapshot of registered settings before each test.
	 *
	 * @var array
	 */
	private array $original_registered_settings = array();

	/**
	 * Creates an administrator for REST settings requests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$administrator_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Removes the administrator.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$administrator_id );
	}

	/**
	 * Registers an application password connector and its credentials setting before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_registered_settings;
		$this->original_registered_settings = is_array( $wp_registered_settings ) ? $wp_registered_settings : array();

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'application_password',
					'setting_name' => self::CREDENTIALS_SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();
	}

	/**
	 * Removes the test connector, its option, and restores registered settings.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		delete_option( self::CREDENTIALS_SETTING_NAME );

		global $wp_registered_settings;
		$wp_registered_settings = $this->original_registered_settings;

		parent::tear_down();
	}

	/**
	 * @ticket 64850
	 */
	public function test_sanitizes_submitted_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => "  remote-user\t",
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_empty_sanitized_username_discards_submitted_password(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => '%ab',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$this->assertSame(
			array(
				'username' => '',
				'password' => '',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_missing_fields_preserve_stored_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		update_option( self::CREDENTIALS_SETTING_NAME, array( 'username' => 'renamed-user' ) );

		$this->assertSame(
			array(
				'username' => 'renamed-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_masked_password_preserves_stored_password(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => str_repeat( "\u{2022}", 16 ),
			)
		);

		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * Only the exact mask stands in for the stored password.
	 *
	 * @ticket 65821
	 */
	public function test_bullet_prefixed_password_that_is_not_the_mask_is_stored(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		// Starts with bullets, but it is the API key mask rather than the password mask.
		$submitted = _wp_connectors_mask_api_key( 'abcd efgh ijkl mnop 1234' );

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => $submitted,
			)
		);

		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => $submitted,
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_non_array_value_preserves_stored_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		update_option( self::CREDENTIALS_SETTING_NAME, 'garbage' );

		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_non_string_fields_preserve_stored_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 123,
				'password' => array( 'not-a-string' ),
			)
		);

		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_empty_strings_clear_stored_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => '',
				'password' => '',
			)
		);

		$this->assertSame(
			array(
				'username' => '',
				'password' => '',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_rest_round_trip_of_masked_response_preserves_stored_password(): void {
		wp_set_current_user( self::$administrator_id );

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$get_request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$get_response = $this->dispatch_settings_request( $get_request );
		$credentials  = $get_response->get_data()[ self::CREDENTIALS_SETTING_NAME ];

		$this->assertSame( str_repeat( "\u{2022}", 16 ), $credentials['password'] );

		// Submit the masked response back, as a read-modify-write client would.
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$post_request->set_param( self::CREDENTIALS_SETTING_NAME, $credentials );
		$post_response = $this->dispatch_settings_request( $post_request );

		$this->assertSame( 200, $post_response->get_status() );
		$this->assertSame(
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
		$this->assertSame(
			str_repeat( "\u{2022}", 16 ),
			$post_response->get_data()[ self::CREDENTIALS_SETTING_NAME ]['password']
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_rest_partial_username_update_preserves_stored_password(): void {
		wp_set_current_user( self::$administrator_id );

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( self::CREDENTIALS_SETTING_NAME, array( 'username' => 'renamed-user' ) );
		$response = $this->dispatch_settings_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'username' => 'renamed-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_rest_empty_strings_clear_stored_credentials(): void {
		wp_set_current_user( self::$administrator_id );

		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => '',
				'password' => '',
			)
		);
		$response = $this->dispatch_settings_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'username' => '',
				'password' => '',
			),
			get_option( self::CREDENTIALS_SETTING_NAME )
		);
	}

	/**
	 * Dispatches a settings REST request and applies the `rest_post_dispatch`
	 * filter, mirroring how WP_REST_Server::serve_request() produces responses.
	 *
	 * @param WP_REST_Request $request The request to dispatch.
	 * @return WP_REST_Response The filtered response.
	 */
	private function dispatch_settings_request( WP_REST_Request $request ): WP_REST_Response {
		$response = rest_do_request( $request );
		return apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), rest_get_server(), $request );
	}
}
