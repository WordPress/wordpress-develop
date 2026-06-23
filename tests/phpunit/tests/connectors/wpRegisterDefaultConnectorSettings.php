<?php

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	const CONNECTOR_ID                      = 'wp_test_non_ai_connector';
	const SETTING_NAME                      = 'connectors_test_non_ai_api_key';
	const USERNAME_SETTING_NAME             = 'connectors_test_non_ai_username';
	const APPLICATION_PASSWORD_SETTING_NAME = 'connectors_test_non_ai_application_password';

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
		$this->original_registered_settings = is_array( $wp_registered_settings ) ? $wp_registered_settings : array();
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

	/**
	 * @ticket 64850
	 */
	public function test_application_password_connector_registers_both_settings(): void {
		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => '',
				'type'           => 'content_source',
				'authentication' => array(
					'method'                            => 'application_password',
					'username_setting_name'             => self::USERNAME_SETTING_NAME,
					'application_password_setting_name' => self::APPLICATION_PASSWORD_SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();

		$registered_settings = get_registered_settings();
		$this->assertArrayHasKey( self::USERNAME_SETTING_NAME, $registered_settings );
		$this->assertArrayHasKey( self::APPLICATION_PASSWORD_SETTING_NAME, $registered_settings );
		$this->assertSame( 'Test Remote WordPress Connector Username', $registered_settings[ self::USERNAME_SETTING_NAME ]['label'] );
		$this->assertSame( 'Test Remote WordPress Connector Application Password', $registered_settings[ self::APPLICATION_PASSWORD_SETTING_NAME ]['label'] );
		$this->assertTrue( $registered_settings[ self::USERNAME_SETTING_NAME ]['show_in_rest'] );
		$this->assertTrue( $registered_settings[ self::APPLICATION_PASSWORD_SETTING_NAME ]['show_in_rest'] );
	}

	/**
	 * @ticket 64850
	 *
	 * @dataProvider data_application_password_settings
	 *
	 * @param string $pre_registered_setting_name Setting name registered by the connector plugin.
	 * @param string $other_setting_name          Setting name registered by Core.
	 */
	public function test_application_password_connector_skips_already_registered_settings( string $pre_registered_setting_name, string $other_setting_name ): void {
		register_setting(
			'connectors',
			$pre_registered_setting_name,
			array(
				'type'              => 'string',
				'label'             => 'Plugin-owned setting',
				'description'       => 'Registered by the connector plugin.',
				'default'           => 'plugin-default',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => '',
				'type'           => 'content_source',
				'authentication' => array(
					'method'                            => 'application_password',
					'username_setting_name'             => self::USERNAME_SETTING_NAME,
					'application_password_setting_name' => self::APPLICATION_PASSWORD_SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();

		$registered_settings = get_registered_settings();
		$this->assertArrayHasKey( $pre_registered_setting_name, $registered_settings );
		$this->assertArrayHasKey( $other_setting_name, $registered_settings );
		$this->assertSame( 'Plugin-owned setting', $registered_settings[ $pre_registered_setting_name ]['label'] );
		$this->assertSame( 'plugin-default', $registered_settings[ $pre_registered_setting_name ]['default'] );
		$this->assertFalse( $registered_settings[ $pre_registered_setting_name ]['show_in_rest'] );
	}

	/**
	 * Data provider for application-password settings.
	 *
	 * @return array<string, array{string, string}> Test cases.
	 */
	public function data_application_password_settings(): array {
		return array(
			'username setting already registered' => array(
				self::USERNAME_SETTING_NAME,
				self::APPLICATION_PASSWORD_SETTING_NAME,
			),
			'application password setting already registered' => array(
				self::APPLICATION_PASSWORD_SETTING_NAME,
				self::USERNAME_SETTING_NAME,
			),
		);
	}
}
