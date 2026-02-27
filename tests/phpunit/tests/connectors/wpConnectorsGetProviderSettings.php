<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_connectors_get_provider_settings().
 *
 * @group connectors
 * @covers ::_wp_connectors_get_provider_settings
 */
class Tests_Connectors_WpConnectorsGetProviderSettings extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	/**
	 * Registers the mock provider once before any tests in this class run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * @ticket 64730
	 */
	public function test_returns_expected_provider_keys() {
		$providers = _wp_connectors_get_provider_settings();

		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'mock_connectors_test', $providers );
		$this->assertCount( 4, $providers );
	}

	/**
	 * @ticket 64730
	 */
	public function test_each_provider_has_required_fields() {
		$providers = _wp_connectors_get_provider_settings();

		foreach ( $providers as $provider_id => $provider_data ) {
			$this->assertArrayHasKey( 'name', $provider_data, "Provider '{$provider_id}' is missing 'name'." );
			$this->assertArrayHasKey( 'description', $provider_data, "Provider '{$provider_id}' is missing 'description'." );
			$this->assertIsString( $provider_data['description'], "Provider '{$provider_id}' description should be a string." );
			$this->assertArrayHasKey( 'credentials_url', $provider_data, "Provider '{$provider_id}' is missing 'credentials_url'." );
			$this->assertArrayHasKey( 'settings', $provider_data, "Provider '{$provider_id}' is missing 'settings'." );
			$this->assertIsArray( $provider_data['settings'], "Provider '{$provider_id}' settings should be an array." );
		}
	}

	/**
	 * @ticket 64730
	 */
	public function test_each_setting_has_required_fields() {
		$providers     = _wp_connectors_get_provider_settings();
		$required_keys = array( 'label', 'description', 'sanitize' );

		foreach ( $providers as $provider_id => $provider_data ) {
			foreach ( $provider_data['settings'] as $setting_name => $config ) {
				foreach ( $required_keys as $key ) {
					$this->assertArrayHasKey( $key, $config, "Setting '{$setting_name}' for provider '{$provider_id}' is missing '{$key}'." );
				}
			}
		}
	}

	/**
	 * @ticket 64730
	 */
	public function test_provider_names_match_expected() {
		$providers = _wp_connectors_get_provider_settings();

		$this->assertSame( 'Gemini', $providers['google']['name'] );
		$this->assertSame( 'OpenAI', $providers['openai']['name'] );
		$this->assertSame( 'Claude', $providers['anthropic']['name'] );
	}

	/**
	 * @ticket 64730
	 */
	public function test_includes_registered_provider_from_registry() {
		$providers = _wp_connectors_get_provider_settings();

		$this->assertArrayHasKey( 'mock_connectors_test', $providers );
		$this->assertSame( 'Mock Connectors Test', $providers['mock_connectors_test']['name'] );
		$this->assertSame( '', $providers['mock_connectors_test']['description'] );
		$this->assertNull( $providers['mock_connectors_test']['credentials_url'] );
		$this->assertArrayHasKey( 'connectors_ai_mock_connectors_test_api_key', $providers['mock_connectors_test']['settings'] );
		$this->assertSame( 'Mock Connectors Test API Key', $providers['mock_connectors_test']['settings']['connectors_ai_mock_connectors_test_api_key']['label'] );
	}
}
