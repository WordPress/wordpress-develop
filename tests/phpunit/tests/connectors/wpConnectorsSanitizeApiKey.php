<?php
/**
 * Tests for wp_connectors_sanitize_api_key().
 *
 * @group connectors
 * @covers ::wp_connectors_sanitize_api_key
 */
class Tests_Connectors_WpConnectorsSanitizeApiKey extends WP_UnitTestCase {

	const CONNECTOR_ID         = 'wp_test_api_key_connector';
	const API_KEY_SETTING_NAME = 'connectors_test_remote_api_key';

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
	 * Registers a non-AI API key connector and its setting before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_registered_settings;
		$this->original_registered_settings = is_array( $wp_registered_settings ) ? $wp_registered_settings : array();

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote API Key Connector',
				'type'           => 'content_source',
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
	 * @ticket 65821
	 */
	public function test_sanitizes_submitted_key_as_text(): void {
		update_option( self::API_KEY_SETTING_NAME, "  ak-live-9f3b2c8d1e4a7601\t" );

		$this->assertSame( 'ak-live-9f3b2c8d1e4a7601', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_masked_key_preserves_stored_key(): void {
		$stored_key = 'ak-live-9f3b2c8d1e4a7601';
		update_option( self::API_KEY_SETTING_NAME, $stored_key );

		update_option( self::API_KEY_SETTING_NAME, _wp_connectors_mask_api_key( $stored_key ) );

		$this->assertSame( $stored_key, get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_new_key_replaces_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		update_option( self::API_KEY_SETTING_NAME, 'ak-live-0000000000009999' );

		$this->assertSame( 'ak-live-0000000000009999', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_empty_string_clears_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		update_option( self::API_KEY_SETTING_NAME, '' );

		$this->assertSame( '', get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * A mask of a different key is a new value, not a placeholder for the stored key.
	 *
	 * @ticket 65821
	 */
	public function test_mask_of_a_different_key_does_not_preserve_stored_key(): void {
		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		$other_mask = _wp_connectors_mask_api_key( 'ak-live-0000000000009999' );
		update_option( self::API_KEY_SETTING_NAME, $other_mask );

		$this->assertSame( $other_mask, get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * A value that starts with a bullet but is not exactly the stored mask is stored as typed.
	 *
	 * @ticket 65821
	 */
	public function test_value_starting_with_bullet_is_stored_as_new_key(): void {
		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		update_option( self::API_KEY_SETTING_NAME, "\u{2022}ak-new-key" );

		$this->assertSame( "\u{2022}ak-new-key", get_option( self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * With nothing stored there is no key for a mask to stand in for.
	 *
	 * @ticket 65821
	 */
	public function test_mask_is_stored_as_is_when_no_key_is_stored(): void {
		$mask = _wp_connectors_mask_api_key( 'ak-live-9f3b2c8d1e4a7601' );

		$this->assertSame( $mask, wp_connectors_sanitize_api_key( $mask, self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_non_string_value_is_sanitized_to_empty_string(): void {
		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		$this->assertSame( '', wp_connectors_sanitize_api_key( array( 'not-a-string' ), self::API_KEY_SETTING_NAME ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_rest_round_trip_of_masked_response_preserves_stored_key(): void {
		wp_set_current_user( self::$administrator_id );

		$stored_key = 'ak-live-9f3b2c8d1e4a7601';
		update_option( self::API_KEY_SETTING_NAME, $stored_key );

		$get_response = $this->dispatch_settings_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) );
		$masked_key   = $get_response->get_data()[ self::API_KEY_SETTING_NAME ];

		$this->assertSame(
			_wp_connectors_mask_api_key( $stored_key ),
			$masked_key,
			'The GET response should carry the mask of the stored key.'
		);

		// Submit the masked response back, as a read-modify-write client would.
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$post_request->set_param( self::API_KEY_SETTING_NAME, $masked_key );
		$post_response = $this->dispatch_settings_request( $post_request );

		$this->assertSame( 200, $post_response->get_status(), 'Posting the masked response back should succeed.' );
		$this->assertSame(
			$stored_key,
			get_option( self::API_KEY_SETTING_NAME ),
			'Posting the masked response back should keep the stored key.'
		);
		$this->assertSame(
			$masked_key,
			$post_response->get_data()[ self::API_KEY_SETTING_NAME ],
			'The POST response should still carry the masked key.'
		);
	}

	/**
	 * @ticket 65821
	 */
	public function test_rest_empty_string_clears_stored_key(): void {
		wp_set_current_user( self::$administrator_id );

		update_option( self::API_KEY_SETTING_NAME, 'ak-live-9f3b2c8d1e4a7601' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( self::API_KEY_SETTING_NAME, '' );
		$response = $this->dispatch_settings_request( $request );

		$this->assertSame( 200, $response->get_status(), 'Posting an empty string should succeed.' );
		$this->assertSame( '', get_option( self::API_KEY_SETTING_NAME ), 'Posting an empty string should clear the stored key.' );
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
