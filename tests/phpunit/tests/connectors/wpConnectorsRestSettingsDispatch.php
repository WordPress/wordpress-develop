<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_connectors_rest_settings_dispatch().
 *
 * @group connectors
 * @covers ::_wp_connectors_rest_settings_dispatch
 */
class Tests_Connectors_WpConnectorsRestSettingsDispatch extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	const CONNECTOR_ID             = 'wp_test_application_password_connector';
	const CREDENTIALS_SETTING_NAME = 'connectors_test_remote_credentials';
	const AI_KEY_SETTING_NAME      = 'connectors_ai_mock_connectors_test_api_key';

	/**
	 * Registers the mock AI provider connector once before any tests in this class run.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Unregisters the mock AI provider's setting after all tests in this class have run.
	 */
	public static function tear_down_after_class(): void {
		self::unregister_mock_connector_setting();
		parent::tear_down_after_class();
	}

	/**
	 * Registers an application password connector before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		self::set_mock_provider_configured( true );

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
	}

	/**
	 * Removes the test connector after each test.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 64850
	 */
	public function test_masks_application_password_but_not_username(): void {
		$application_password = 'abcd efgh ijkl mnop 1234';
		$response             = new WP_REST_Response(
			array(
				self::CREDENTIALS_SETTING_NAME => array(
					'username' => 'remote-user',
					'password' => $application_password,
				),
			)
		);
		$request              = new WP_REST_Request( 'GET', '/wp/v2/settings' );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame( 'remote-user', $data[ self::CREDENTIALS_SETTING_NAME ]['username'] );
		$this->assertSame( str_repeat( "\u{2022}", 16 ), $data[ self::CREDENTIALS_SETTING_NAME ]['password'] );
		$this->assertNotSame( $application_password, $data[ self::CREDENTIALS_SETTING_NAME ]['password'] );
	}

	/**
	 * Ensures a stored AI provider key is not re-validated, and therefore not reset,
	 * when it is not part of the current settings update.
	 *
	 * The /wp/v2/settings response always carries every registered setting, so the
	 * response data alone cannot tell which keys the request actually submitted.
	 *
	 * @ticket 65554
	 */
	public function test_does_not_validate_or_reset_unsubmitted_ai_key(): void {
		$stored_key = 'sk-stored-valid-key';
		update_option( self::AI_KEY_SETTING_NAME, $stored_key );

		// A provider that would fail validation if it were consulted.
		self::set_mock_provider_configured( false );

		// An update that submits an unrelated setting.
		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( 'title', 'New Site Title' );
		$response = new WP_REST_Response( array( self::AI_KEY_SETTING_NAME => $stored_key ) );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame(
			$stored_key,
			get_option( self::AI_KEY_SETTING_NAME ),
			'An AI provider key that was not submitted should not be reset.'
		);
		$this->assertSame(
			_wp_connectors_mask_api_key( $stored_key ),
			$data[ self::AI_KEY_SETTING_NAME ],
			'The stored AI provider key should still be masked in the response.'
		);
	}

	/**
	 * Ensures a submitted AI provider key that fails validation is still discarded.
	 *
	 * @ticket 65554
	 */
	public function test_discards_submitted_invalid_ai_key(): void {
		$submitted_key = 'sk-submitted-invalid-key';
		update_option( self::AI_KEY_SETTING_NAME, $submitted_key );

		self::set_mock_provider_configured( false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( self::AI_KEY_SETTING_NAME, $submitted_key );
		$response = new WP_REST_Response( array( self::AI_KEY_SETTING_NAME => $submitted_key ) );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame(
			'',
			get_option( self::AI_KEY_SETTING_NAME ),
			'A submitted AI provider key that fails validation should be discarded.'
		);
		$this->assertSame(
			'',
			$data[ self::AI_KEY_SETTING_NAME ],
			'The discarded key should be returned as an empty string.'
		);
	}

	/**
	 * Ensures a submitted AI provider key that passes validation is kept and masked.
	 *
	 * @ticket 65554
	 */
	public function test_keeps_and_masks_submitted_valid_ai_key(): void {
		$submitted_key = 'sk-submitted-valid-key';
		update_option( self::AI_KEY_SETTING_NAME, $submitted_key );

		self::set_mock_provider_configured( true );

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( self::AI_KEY_SETTING_NAME, $submitted_key );
		$response = new WP_REST_Response( array( self::AI_KEY_SETTING_NAME => $submitted_key ) );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame(
			$submitted_key,
			get_option( self::AI_KEY_SETTING_NAME ),
			'A submitted AI provider key that passes validation should be kept.'
		);
		$this->assertSame(
			_wp_connectors_mask_api_key( $submitted_key ),
			$data[ self::AI_KEY_SETTING_NAME ],
			'The submitted AI provider key should be masked in the response.'
		);
	}
}
