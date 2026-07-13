<?php

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	const CONNECTOR_ID             = 'wp_test_non_ai_connector';
	const SETTING_NAME             = 'connectors_test_non_ai_api_key';
	const CREDENTIALS_SETTING_NAME = 'connectors_test_non_ai_credentials';

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
	public function test_application_password_connector_registers_credentials_setting(): void {
		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => '',
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'application_password',
					'setting_name' => self::CREDENTIALS_SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();

		$registered_settings = get_registered_settings();
		$this->assertArrayHasKey( self::CREDENTIALS_SETTING_NAME, $registered_settings );
		$this->assertSame( 'object', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['type'] );
		$this->assertSame( 'Test Remote WordPress Connector Credentials', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['label'] );
		$this->assertSame(
			array(
				'username' => '',
				'password' => '',
			),
			$registered_settings[ self::CREDENTIALS_SETTING_NAME ]['default']
		);
		$this->assertSame( 'object', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['show_in_rest']['schema']['type'] );
		$this->assertArrayHasKey( 'username', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['show_in_rest']['schema']['properties'] );
		$this->assertArrayHasKey( 'password', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['show_in_rest']['schema']['properties'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_application_password_connector_skips_already_registered_setting(): void {
		register_setting(
			'connectors',
			self::CREDENTIALS_SETTING_NAME,
			array(
				'type'              => 'object',
				'label'             => 'Plugin-owned setting',
				'description'       => 'Registered by the connector plugin.',
				'default'           => array(
					'username' => 'plugin-default',
					'password' => '',
				),
				'show_in_rest'      => false,
				'sanitize_callback' => 'wp_connectors_sanitize_application_password_credentials',
			)
		);

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => '',
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'application_password',
					'setting_name' => self::CREDENTIALS_SETTING_NAME,
				),
			)
		);

		_wp_register_default_connector_settings();

		$registered_settings = get_registered_settings();
		$this->assertArrayHasKey( self::CREDENTIALS_SETTING_NAME, $registered_settings );
		$this->assertSame( 'Plugin-owned setting', $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['label'] );
		$this->assertSame(
			array(
				'username' => 'plugin-default',
				'password' => '',
			),
			$registered_settings[ self::CREDENTIALS_SETTING_NAME ]['default']
		);
			$this->assertFalse( $registered_settings[ self::CREDENTIALS_SETTING_NAME ]['show_in_rest'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_already_registered_setting_skips_before_is_active_callback(): void {
		$is_active_called = false;

		register_setting(
			'connectors',
			self::CREDENTIALS_SETTING_NAME,
			array(
				'type'         => 'object',
				'show_in_rest' => false,
			)
		);

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => '',
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'application_password',
					'setting_name' => self::CREDENTIALS_SETTING_NAME,
				),
				'plugin'         => array(
					'is_active' => static function () use ( &$is_active_called ): bool {
						$is_active_called = true;
						return true;
					},
				),
			)
		);

		_wp_register_default_connector_settings();

		$this->assertFalse( $is_active_called );
	}
}
