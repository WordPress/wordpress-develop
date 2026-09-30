<?php
/**
 * Tests for _wp_connectors_sanitize_api_key().
 *
 * @group connectors
 * @covers ::_wp_connectors_sanitize_api_key
 */
class Tests_Connectors_WpConnectorsSanitizeApiKey extends WP_UnitTestCase {

	const CONNECTOR_ID         = 'wp_test_api_key_connector';
	const API_KEY_SETTING_NAME = 'connectors_test_api_key';
	const STORED_KEY           = 'sk-stored-secret-key-1234';

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
	 * Registers an API key connector and its setting before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_registered_settings;
		$this->original_registered_settings = is_array( $wp_registered_settings ) ? $wp_registered_settings : array();

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test API Key Connector',
				'type'           => 'spam_filtering',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => self::API_KEY_SETTING_NAME,
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

		delete_option( self::API_KEY_SETTING_NAME );

		global $wp_registered_settings;
		$wp_registered_settings = $this->original_registered_settings;

		parent::tear_down();
	}

	/**
	 * @ticket 65551
	 */
	public function test_sanitizes_submitted_key(): void {
		update_option( self::API_KEY_SETTING_NAME, " sk-new-key\n" );

		$this->assertSame( 'sk-new-key', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65551
	 */
	public function test_masked_key_preserves_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, self::STORED_KEY );

		update_option( self::API_KEY_SETTING_NAME, _wp_connectors_mask_api_key( self::STORED_KEY ) );

		$this->assertSame( self::STORED_KEY, get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65551
	 */
	public function test_new_key_replaces_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, self::STORED_KEY );

		update_option( self::API_KEY_SETTING_NAME, 'sk-replacement-key-5678' );

		$this->assertSame( 'sk-replacement-key-5678', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65551
	 */
	public function test_empty_string_clears_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, self::STORED_KEY );

		update_option( self::API_KEY_SETTING_NAME, '' );

		$this->assertSame( '', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65551
	 */
	public function test_rest_round_trip_of_masked_response_preserves_stored_key(): void {
		wp_set_current_user( self::$administrator_id );

		update_option( self::API_KEY_SETTING_NAME, self::STORED_KEY );

		$get_request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$get_response = $this->dispatch_settings_request( $get_request );
		$masked_key   = $get_response->get_data()[ self::API_KEY_SETTING_NAME ];

		$this->assertSame( _wp_connectors_mask_api_key( self::STORED_KEY ), $masked_key );

		// Submit the masked key back, as the Connectors screen does when it is saved unchanged.
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$post_request->set_param( self::API_KEY_SETTING_NAME, $masked_key );
		$post_response = $this->dispatch_settings_request( $post_request );

		$this->assertSame( 200, $post_response->get_status() );
		$this->assertSame( self::STORED_KEY, get_option( self::API_KEY_SETTING_NAME ) );
		$this->assertSame( $masked_key, $post_response->get_data()[ self::API_KEY_SETTING_NAME ] );
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
