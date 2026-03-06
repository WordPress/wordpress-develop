<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_connectors_get_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_connectors_get_connector_settings
 */
class Tests_Connectors_WpConnectorsGetConnectorSettings extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	/**
	 * Registers the mock provider once before any tests in this class run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Unregisters the mock provider setting added by `init`.
	 */
	public static function tear_down_after_class() {
		self::unregister_mock_connector_setting();
		parent::tear_down_after_class();
	}

	/**
	 * @ticket 64730
	 */
	public function test_returns_expected_connector_keys() {
		$connectors = _wp_connectors_get_connector_settings();

		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayHasKey( 'openai', $connectors );
		$this->assertArrayHasKey( 'anthropic', $connectors );
		$this->assertArrayHasKey( 'mock_connectors_test', $connectors );
		$this->assertCount( 4, $connectors );
	}

	/**
	 * @ticket 64730
	 */
	public function test_each_connector_has_required_fields() {
		$connectors = _wp_connectors_get_connector_settings();

		$this->assertNotEmpty( $connectors, 'Connector settings should not be empty.' );

		foreach ( $connectors as $connector_id => $connector_data ) {
			$this->assertArrayHasKey( 'name', $connector_data, "Connector '{$connector_id}' is missing 'name'." );
			$this->assertIsString( $connector_data['name'], "Connector '{$connector_id}' name should be a string." );
			$this->assertNotEmpty( $connector_data['name'], "Connector '{$connector_id}' name should not be empty." );
			$this->assertArrayHasKey( 'description', $connector_data, "Connector '{$connector_id}' is missing 'description'." );
			$this->assertIsString( $connector_data['description'], "Connector '{$connector_id}' description should be a string." );
			$this->assertArrayHasKey( 'type', $connector_data, "Connector '{$connector_id}' is missing 'type'." );
			$this->assertContains( $connector_data['type'], array( 'ai_provider' ), "Connector '{$connector_id}' has unexpected type '{$connector_data['type']}'." );
			$this->assertArrayHasKey( 'authentication', $connector_data, "Connector '{$connector_id}' is missing 'authentication'." );
			$this->assertIsArray( $connector_data['authentication'], "Connector '{$connector_id}' authentication should be an array." );
			$this->assertArrayHasKey( 'method', $connector_data['authentication'], "Connector '{$connector_id}' authentication is missing 'method'." );
			$this->assertContains( $connector_data['authentication']['method'], array( 'api_key', 'none' ), "Connector '{$connector_id}' has unexpected authentication method." );
		}
	}

	/**
	 * @ticket 64730
	 */
	public function test_api_key_connectors_have_setting_name_and_credentials_url() {
		$connectors    = _wp_connectors_get_connector_settings();
		$api_key_count = 0;

		foreach ( $connectors as $connector_id => $connector_data ) {
			if ( 'api_key' !== $connector_data['authentication']['method'] ) {
				continue;
			}

			++$api_key_count;

			$this->assertArrayHasKey( 'setting_name', $connector_data['authentication'], "Connector '{$connector_id}' authentication is missing 'setting_name'." );
			$this->assertSame(
				"connectors_ai_{$connector_id}_api_key",
				$connector_data['authentication']['setting_name'],
				"Connector '{$connector_id}' setting_name does not match expected format."
			);
			$this->assertArrayHasKey( 'credentials_url', $connector_data['authentication'], "Connector '{$connector_id}' authentication is missing 'credentials_url'." );
		}

		$this->assertGreaterThan( 0, $api_key_count, 'At least one connector should use api_key authentication.' );
	}

	/**
	 * @ticket 64730
	 */
	public function test_featured_provider_names_match_expected() {
		$connectors = _wp_connectors_get_connector_settings();

		$this->assertSame( 'Google', $connectors['google']['name'] );
		$this->assertSame( 'OpenAI', $connectors['openai']['name'] );
		$this->assertSame( 'Anthropic', $connectors['anthropic']['name'] );
	}

	/**
	 * @ticket 64730
	 */
	public function test_includes_registered_provider_from_registry() {
		$connectors = _wp_connectors_get_connector_settings();
		$mock       = $connectors['mock_connectors_test'];

		$this->assertSame( 'Mock Connectors Test', $mock['name'] );
		$this->assertSame( '', $mock['description'] );
		$this->assertSame( 'ai_provider', $mock['type'] );
		$this->assertSame( 'api_key', $mock['authentication']['method'] );
		$this->assertNull( $mock['authentication']['credentials_url'] );
		$this->assertSame( 'connectors_ai_mock_connectors_test_api_key', $mock['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_filter_can_add_new_connector() {
		$callback = static function ( $connectors ) {
			$connectors['my_email_service'] = array(
				'name'           => 'My Email Service',
				'description'    => 'Send transactional emails.',
				'type'           => 'email_service',
				'authentication' => array( 'method' => 'none' ),
			);
			return $connectors;
		};
		add_filter( 'wp_connectors_settings', $callback );

		$connectors = _wp_connectors_get_connector_settings();
		remove_filter( 'wp_connectors_settings', $callback );

		$this->assertArrayHasKey( 'my_email_service', $connectors );
		$this->assertSame( 'My Email Service', $connectors['my_email_service']['name'] );
		$this->assertSame( 'email_service', $connectors['my_email_service']['type'] );
		$this->assertSame( 'none', $connectors['my_email_service']['authentication']['method'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_filter_can_modify_existing_connector() {
		$callback = static function ( $connectors ) {
			$connectors['google']['description'] = 'Custom description for Google.';
			return $connectors;
		};
		add_filter( 'wp_connectors_settings', $callback );

		$connectors = _wp_connectors_get_connector_settings();
		remove_filter( 'wp_connectors_settings', $callback );

		$this->assertSame( 'Custom description for Google.', $connectors['google']['description'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_filter_can_remove_connector() {
		$callback = static function ( $connectors ) {
			unset( $connectors['openai'] );
			return $connectors;
		};
		add_filter( 'wp_connectors_settings', $callback );

		$connectors = _wp_connectors_get_connector_settings();
		remove_filter( 'wp_connectors_settings', $callback );

		$this->assertArrayNotHasKey( 'openai', $connectors );
		// Other connectors remain.
		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayHasKey( 'anthropic', $connectors );
	}

	/**
	 * @ticket 64791
	 */
	public function test_filter_receives_all_default_connectors_with_setting_name() {
		$received = null;

		$callback = static function ( $connectors ) use ( &$received ) {
			$received = $connectors;
			return $connectors;
		};
		add_filter( 'wp_connectors_settings', $callback );

		_wp_connectors_get_connector_settings();
		remove_filter( 'wp_connectors_settings', $callback );

		$this->assertArrayHasKey( 'google', $received );
		$this->assertArrayHasKey( 'openai', $received );
		$this->assertArrayHasKey( 'anthropic', $received );
		$this->assertArrayHasKey( 'mock_connectors_test', $received );

		// The filter receives fully populated data, including setting_name for API-key connectors.
		$this->assertSame( 'connectors_ai_openai_api_key', $received['openai']['authentication']['setting_name'] );
		$this->assertSame( 'connectors_ai_anthropic_api_key', $received['anthropic']['authentication']['setting_name'] );
		$this->assertSame( 'connectors_ai_google_api_key', $received['google']['authentication']['setting_name'] );
	}
}
