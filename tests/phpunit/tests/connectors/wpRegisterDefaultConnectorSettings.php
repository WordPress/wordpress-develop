<?php

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	const CONNECTOR_ID  = 'wp_test_non_ai_connector';
	const SETTING_NAME  = 'connectors_test_non_ai_api_key';

	/**
	 * Removes the test connector and setting after each test.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		unregister_setting( 'connectors', self::SETTING_NAME );

		parent::tear_down();
	}

	/**
	 * @ticket 64730
	 */
	public function test_non_ai_connector_skipped_when_is_active_missing(): void {
		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Non-AI Connector',
				'description'    => '',
				'type'           => 'spam_filtering',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => self::SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();

		$this->assertArrayNotHasKey( self::SETTING_NAME, get_registered_settings() );
	}
}
