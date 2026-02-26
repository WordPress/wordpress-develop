<?php
/**
 * Tests for _wp_connectors_get_provider_settings().
 *
 * @group connectors
 * @covers ::_wp_connectors_get_provider_settings
 */
class Tests_Connectors_WpConnectorsGetProviderSettings extends WP_UnitTestCase {

	/**
	 * @ticket 64730
	 */
	public function test_returns_expected_provider_keys() {
		$settings = _wp_connectors_get_provider_settings();

		$this->assertArrayHasKey( 'connectors_ai_google_api_key', $settings );
		$this->assertArrayHasKey( 'connectors_ai_openai_api_key', $settings );
		$this->assertArrayHasKey( 'connectors_ai_anthropic_api_key', $settings );
		$this->assertCount( 3, $settings );
	}

	/**
	 * @ticket 64730
	 */
	public function test_each_setting_has_required_fields() {
		$settings      = _wp_connectors_get_provider_settings();
		$required_keys = array( 'provider', 'label', 'description', 'mask', 'sanitize' );

		foreach ( $settings as $setting_name => $config ) {
			foreach ( $required_keys as $key ) {
				$this->assertArrayHasKey( $key, $config, "Setting '{$setting_name}' is missing '{$key}'." );
			}
		}
	}

	/**
	 * @ticket 64730
	 */
	public function test_provider_values_match_expected() {
		$settings = _wp_connectors_get_provider_settings();

		$this->assertSame( 'google', $settings['connectors_ai_google_api_key']['provider'] );
		$this->assertSame( 'openai', $settings['connectors_ai_openai_api_key']['provider'] );
		$this->assertSame( 'anthropic', $settings['connectors_ai_anthropic_api_key']['provider'] );
	}
}
