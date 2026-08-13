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
	 * Registers the mock AI provider connector once for the class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Registers an application password connector before each test.
	 */
	public function set_up(): void {
		parent::set_up();

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
	 * Ensures a stored AI provider API key is not re-validated, and therefore not
	 * reset, when it is not part of the current settings update.
	 *
	 * @ticket 65867
	 */
	public function test_does_not_validate_or_reset_unsubmitted_ai_key(): void {
		$stored_key = 'sk-stored-valid-key';
		update_option( self::AI_KEY_SETTING_NAME, $stored_key );

		self::set_mock_provider_configured( false );

		// An update that does not submit the AI key (e.g. saving another setting).
		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( 'title', 'New Site Title' );

		// The settings endpoint response always contains every registered setting.
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

		self::set_mock_provider_configured( true );
		delete_option( self::AI_KEY_SETTING_NAME );
	}
}
