<?php
/**
 * Tests for wp_connectors_sanitize_api_key().
 *
 * @group connectors
 * @covers ::wp_connectors_sanitize_api_key
 */
class Tests_Connectors_WpConnectorsSanitizeApiKey extends WP_UnitTestCase {

	const CONNECTOR_ID    = 'wp_test_api_key_connector';
	const API_KEY_SETTING = 'connectors_test_service_api_key';
	const STORED_API_KEY  = 'sk-live-abcdefghijklmnop1234';

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
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => self::API_KEY_SETTING,
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

		delete_option( self::API_KEY_SETTING );

		global $wp_registered_settings;
		$wp_registered_settings = $this->original_registered_settings;

		parent::tear_down();
	}

	/**
	 * @ticket 65821
	 */
	public function test_masked_value_preserves_stored_api_key(): void {
		update_option( self::API_KEY_SETTING, self::STORED_API_KEY );

		update_option( self::API_KEY_SETTING, _wp_connectors_mask_api_key( self::STORED_API_KEY ) );

		$this->assertSame( self::STORED_API_KEY, get_option( self::API_KEY_SETTING ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_submitted_key_replaces_stored_api_key(): void {
		update_option( self::API_KEY_SETTING, self::STORED_API_KEY );

		update_option( self::API_KEY_SETTING, 'sk-live-zyxwvutsrqponmlk9876' );

		$this->assertSame( 'sk-live-zyxwvutsrqponmlk9876', get_option( self::API_KEY_SETTING ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_empty_string_clears_stored_api_key(): void {
		update_option( self::API_KEY_SETTING, self::STORED_API_KEY );

		update_option( self::API_KEY_SETTING, '' );

		$this->assertSame( '', get_option( self::API_KEY_SETTING ) );
	}

	/**
	 * A masked value only stands in for the key it was generated from.
	 *
	 * @ticket 65821
	 */
	public function test_mask_of_a_different_key_does_not_preserve_stored_api_key(): void {
		update_option( self::API_KEY_SETTING, self::STORED_API_KEY );

		$other_mask = _wp_connectors_mask_api_key( 'sk-live-zyxwvutsrqponmlk9876' );
		update_option( self::API_KEY_SETTING, $other_mask );

		$this->assertSame( $other_mask, get_option( self::API_KEY_SETTING ) );
	}

	/**
	 * @ticket 65821
	 */
	public function test_masked_value_with_no_stored_key_is_sanitized_as_text(): void {
		$mask = _wp_connectors_mask_api_key( self::STORED_API_KEY );

		update_option( self::API_KEY_SETTING, $mask );

		$this->assertSame( $mask, get_option( self::API_KEY_SETTING ) );
	}

	/**
	 * A connector plugin may own the setting and register the sanitizer itself, in
	 * which case only the value is passed and the option name comes from the filter.
	 *
	 * @ticket 65821
	 */
	public function test_option_name_falls_back_to_the_current_filter(): void {
		$option = 'connectors_test_plugin_owned_api_key';

		register_setting(
			'connectors',
			$option,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'wp_connectors_sanitize_api_key',
			)
		);

		update_option( $option, self::STORED_API_KEY );
		update_option( $option, _wp_connectors_mask_api_key( self::STORED_API_KEY ) );

		$this->assertSame( self::STORED_API_KEY, get_option( $option ) );

		unregister_setting( 'connectors', $option );
		delete_option( $option );
	}

	/**
	 * @ticket 65821
	 */
	public function test_rest_round_trip_of_masked_response_preserves_stored_api_key(): void {
		wp_set_current_user( self::$administrator_id );

		update_option( self::API_KEY_SETTING, self::STORED_API_KEY );

		$get_request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$get_response = $this->dispatch_settings_request( $get_request );
		$masked       = $get_response->get_data()[ self::API_KEY_SETTING ];

		$this->assertSame( _wp_connectors_mask_api_key( self::STORED_API_KEY ), $masked );

		// Submit the masked response back, as a read-modify-write client would.
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$post_request->set_param( self::API_KEY_SETTING, $masked );
		$post_response = $this->dispatch_settings_request( $post_request );

		$this->assertSame( 200, $post_response->get_status() );
		$this->assertSame( self::STORED_API_KEY, get_option( self::API_KEY_SETTING ) );
		$this->assertSame(
			_wp_connectors_mask_api_key( self::STORED_API_KEY ),
			$post_response->get_data()[ self::API_KEY_SETTING ]
		);
	}

	/**
	 * Dispatches a settings request through the filter that masks connector credentials.
	 *
	 * @param WP_REST_Request $request The request to dispatch.
	 * @return WP_REST_Response The filtered response.
	 */
	private function dispatch_settings_request( WP_REST_Request $request ): WP_REST_Response {
		$response = rest_do_request( $request );
		return apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), rest_get_server(), $request );
	}
}
