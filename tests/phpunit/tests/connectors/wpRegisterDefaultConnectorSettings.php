<?php

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	const CONNECTOR_ID = 'wp_test_non_ai_connector';
	const SETTING_NAME = 'connectors_test_non_ai_api_key';

	/**
	 * Snapshot of registered settings before each test.
	 *
	 * @var array
	 */
	private array $original_registered_settings = array();

	/**
	 * Snapshots the registered settings before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_registered_settings;
		$this->original_registered_settings = $wp_registered_settings;
	}

	/**
	 * Removes the test connector and restores registered settings.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		global $wp_registered_settings;
		$wp_registered_settings = $this->original_registered_settings;

		parent::tear_down();
	}

	/**
	 * @ticket 65099
	 */
	public function test_non_ai_connector_skipped_when_is_active_returns_false(): void {
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
				'plugin'         => array(
					'file'      => 'test/test.php',
					'is_active' => static function (): bool {
						return false;
					},
				),
			)
		);

		_wp_register_default_connector_settings();

		$this->assertArrayNotHasKey( self::SETTING_NAME, get_registered_settings() );
	}

	/**
	 * @ticket 65099
	 */
	public function test_non_ai_connector_registers_setting_when_is_active_returns_true(): void {
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
				'plugin'         => array(
					'file'      => 'test/test.php',
					'is_active' => static function (): bool {
						return true;
					},
				),
			)
		);

		_wp_register_default_connector_settings();

		$this->assertArrayHasKey( self::SETTING_NAME, get_registered_settings() );
	}
}
