<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_connectors_rest_settings_dispatch().
 *
 * @group connectors
 * @group restapi
 * @covers ::_wp_connectors_rest_settings_dispatch
 */
class Tests_Connectors_WpConnectorsRestSettingsDispatch extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	/**
	 * The setting name for the registered mock AI provider connector.
	 *
	 * @var string
	 */
	private const MOCK_SETTING = 'connectors_ai_mock_connectors_test_api_key';

	/**
	 * A connector id registered as an AI provider but absent from the AI Client
	 * registry, so its key can never be verified (validation returns null).
	 *
	 * @var string
	 */
	private const UNVERIFIABLE_ID = 'mock-connectors-unverifiable';

	/**
	 * The setting name for the unverifiable connector.
	 *
	 * @var string
	 */
	private const UNVERIFIABLE_SETTING = 'connectors_test_unverifiable_api_key';

	/**
	 * Registers the mock provider once before any tests in this class run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Resets the mock availability flag and registers the unverifiable connector.
	 */
	public function set_up() {
		parent::set_up();
		self::set_mock_provider_configured( true );

		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && ! $registry->is_registered( self::UNVERIFIABLE_ID ) ) {
			$registry->register(
				self::UNVERIFIABLE_ID,
				array(
					'name'           => 'Mock Unverifiable',
					'description'    => '',
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'       => 'api_key',
						'setting_name' => self::UNVERIFIABLE_SETTING,
					),
				)
			);
		}
	}

	/**
	 * Builds a POST request and response for the settings endpoint.
	 *
	 * @param string $setting_name The connector setting key.
	 * @param string $value        The submitted/stored key value.
	 * @return array{0: WP_REST_Response, 1: WP_REST_Request} The response and request.
	 */
	private function make_settings_update( string $setting_name, string $value ): array {
		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );

		$response = new WP_REST_Response( array( $setting_name => $value ) );

		return array( $response, $request );
	}

	/**
	 * A validation result that is not an explicit failure must preserve the stored key.
	 *
	 * @ticket 65551
	 */
	public function test_indeterminate_validation_preserves_key() {
		$this->setExpectedIncorrectUsage( '_wp_connectors_is_ai_api_key_valid' );

		update_option( self::UNVERIFIABLE_SETTING, 'existing-valid-key' );

		list( $response, $request ) = $this->make_settings_update( self::UNVERIFIABLE_SETTING, 'existing-valid-key' );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );

		$this->assertSame(
			'existing-valid-key',
			get_option( self::UNVERIFIABLE_SETTING ),
			'The stored key should be preserved when validation is indeterminate.'
		);

		$data = $result->get_data();
		$this->assertNotSame(
			'',
			$data[ self::UNVERIFIABLE_SETTING ],
			'The response value should not be emptied when validation is indeterminate.'
		);
	}

	/**
	 * An explicitly invalid key must be discarded.
	 *
	 * @ticket 65551
	 */
	public function test_invalid_key_is_discarded() {
		self::set_mock_provider_configured( false );

		update_option( self::MOCK_SETTING, 'bad-key' );

		list( $response, $request ) = $this->make_settings_update( self::MOCK_SETTING, 'bad-key' );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );

		$this->assertSame(
			'',
			get_option( self::MOCK_SETTING ),
			'An invalid key should be discarded.'
		);

		$data = $result->get_data();
		$this->assertSame( '', $data[ self::MOCK_SETTING ] );
	}

	/**
	 * A valid key must be preserved and masked in the response.
	 *
	 * @ticket 65551
	 */
	public function test_valid_key_is_preserved_and_masked() {
		self::set_mock_provider_configured( true );

		update_option( self::MOCK_SETTING, 'a-valid-secret-key' );

		list( $response, $request ) = $this->make_settings_update( self::MOCK_SETTING, 'a-valid-secret-key' );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );

		$this->assertSame(
			'a-valid-secret-key',
			get_option( self::MOCK_SETTING ),
			'A valid key should be preserved in the database.'
		);

		$data = $result->get_data();
		$this->assertNotSame(
			'a-valid-secret-key',
			$data[ self::MOCK_SETTING ],
			'The raw key should never be exposed in the response.'
		);
		$this->assertStringEndsWith(
			'-key',
			$data[ self::MOCK_SETTING ],
			'The masked value should retain the final characters of the key.'
		);
	}

	/**
	 * Read (GET) requests must never validate or discard the stored key.
	 *
	 * @ticket 65551
	 */
	public function test_get_request_does_not_discard_key() {
		self::set_mock_provider_configured( false );

		update_option( self::MOCK_SETTING, 'a-valid-secret-key' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = new WP_REST_Response( array( self::MOCK_SETTING => 'a-valid-secret-key' ) );

		_wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );

		$this->assertSame(
			'a-valid-secret-key',
			get_option( self::MOCK_SETTING ),
			'A GET request must not discard the stored key, even for an unconfigured provider.'
		);
	}
}
