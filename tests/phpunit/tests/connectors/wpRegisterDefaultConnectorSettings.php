<?php

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	/**
	 * Original connector registry instance.
	 *
	 * @var WP_Connector_Registry|null
	 */
	private ?WP_Connector_Registry $original_registry;

	/**
	 * Setting names touched during a test.
	 *
	 * @var string[]
	 */
	private array $touched_settings = array();

	/**
	 * Stores the original registry before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_registry = WP_Connector_Registry::get_instance();
	}

	/**
	 * Restores the original registry and connector settings after each test.
	 */
	public function tear_down(): void {
		foreach ( array_unique( $this->touched_settings ) as $setting_name ) {
			unregister_setting( 'connectors', $setting_name );
		}

		$this->set_registry_instance( $this->original_registry );

		if ( null !== $this->original_registry ) {
			_wp_register_default_connector_settings();
		}

		parent::tear_down();
	}

	/**
	 * @ticket 64730
	 */
	public function test_ai_connector_settings_still_auto_register(): void {
		$setting_name = 'connectors_ai_openai_api_key';
		$this->touch_setting( $setting_name );
		unregister_setting( 'connectors', $setting_name );

		$openai = wp_get_connector( 'openai' );
		$this->assertIsArray( $openai );

		$registry = new WP_Connector_Registry();
		$this->set_registered_connectors(
			$registry,
			array(
				'openai' => $openai,
			)
		);
		$this->set_registry_instance( $registry );

		_wp_register_default_connector_settings();

		$this->assertArrayHasKey( $setting_name, get_registered_settings() );
	}

	/**
	 * @ticket 64730
	 */
	public function test_non_ai_connector_settings_auto_register_when_plugin_is_active_returns_true(): void {
		$setting_name = 'connectors_spam_filtering_test_active_api_key';
		$this->touch_setting( $setting_name );

		$registry = $this->create_non_ai_registry(
			'test-active',
			$setting_name,
			static function (): bool {
				return true;
			}
		);
		$this->set_registry_instance( $registry );

		_wp_register_default_connector_settings();

		$this->assertArrayHasKey( $setting_name, get_registered_settings() );
	}

	/**
	 * @ticket 64730
	 */
	public function test_non_ai_connector_settings_do_not_auto_register_when_plugin_is_active_missing(): void {
		$setting_name = 'connectors_spam_filtering_test_missing_api_key';
		$this->touch_setting( $setting_name );

		$registry = $this->create_non_ai_registry( 'test-missing', $setting_name );
		$this->set_registry_instance( $registry );

		_wp_register_default_connector_settings();

		$this->assertArrayNotHasKey( $setting_name, get_registered_settings() );
	}

	/**
	 * @ticket 64730
	 */
	public function test_non_ai_connector_settings_do_not_auto_register_when_plugin_is_active_returns_false(): void {
		$setting_name = 'connectors_spam_filtering_test_inactive_api_key';
		$this->touch_setting( $setting_name );

		$registry = $this->create_non_ai_registry(
			'test-inactive',
			$setting_name,
			static function (): bool {
				return false;
			}
		);
		$this->set_registry_instance( $registry );

		_wp_register_default_connector_settings();

		$this->assertArrayNotHasKey( $setting_name, get_registered_settings() );
	}

	/**
	 * @ticket 64730
	 */
	public function test_non_ai_connector_settings_do_not_auto_register_when_plugin_is_active_not_callable(): void {
		$setting_name = 'connectors_spam_filtering_test_invalid_api_key';
		$this->touch_setting( $setting_name );

		$registry = $this->create_non_ai_registry( 'test-invalid', $setting_name, 'not_a_callback' );
		$this->set_registry_instance( $registry );

		_wp_register_default_connector_settings();

		$this->assertArrayNotHasKey( $setting_name, get_registered_settings() );
	}

	/**
	 * Creates a registry containing a single non-AI connector.
	 *
	 * @param string $connector_id  Connector ID.
	 * @param string $setting_name  Setting name.
	 * @param mixed  $plugin_status Optional. Value to inject into plugin.is_active.
	 * @return WP_Connector_Registry
	 */
	private function create_non_ai_registry( string $connector_id, string $setting_name, $plugin_status = null ): WP_Connector_Registry {
		$registry = new WP_Connector_Registry();
		$registry->register(
			$connector_id,
			array(
				'name'           => 'Test Connector',
				'description'    => 'A test connector.',
				'type'           => 'spam_filtering',
				'plugin'         => array(
					'file' => 'test-plugin/test-plugin.php',
				),
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://example.com/keys',
					'setting_name'    => $setting_name,
				),
			)
		);

		$connectors = $this->get_registered_connectors( $registry );

		if ( null !== $plugin_status ) {
			$connectors[ $connector_id ]['plugin']['is_active'] = $plugin_status;
		}

		$this->set_registered_connectors( $registry, $connectors );

		return $registry;
	}

	/**
	 * Gets the registered connectors from a registry instance.
	 *
	 * @param WP_Connector_Registry $registry Connector registry.
	 * @return array<string, array>
	 */
	private function get_registered_connectors( WP_Connector_Registry $registry ): array {
		$property = new ReflectionProperty( WP_Connector_Registry::class, 'registered_connectors' );
		if ( method_exists( $property, 'setAccessible' ) ) {
			$property->setAccessible( true );
		}

		return $property->getValue( $registry );
	}

	/**
	 * Sets the registered connectors for a registry instance.
	 *
	 * @param WP_Connector_Registry $registry   Connector registry.
	 * @param array<string, array>  $connectors Connector data.
	 */
	private function set_registered_connectors( WP_Connector_Registry $registry, array $connectors ): void {
		$property = new ReflectionProperty( WP_Connector_Registry::class, 'registered_connectors' );
		if ( method_exists( $property, 'setAccessible' ) ) {
			$property->setAccessible( true );
		}

		$property->setValue( $registry, $connectors );
	}

	/**
	 * Sets the static registry instance.
	 *
	 * @param WP_Connector_Registry|null $registry Connector registry instance.
	 */
	private function set_registry_instance( ?WP_Connector_Registry $registry ): void {
		$property = new ReflectionProperty( WP_Connector_Registry::class, 'instance' );
		if ( method_exists( $property, 'setAccessible' ) ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, $registry );
	}

	/**
	 * Tracks a setting name for cleanup.
	 *
	 * @param string $setting_name Setting name.
	 */
	private function touch_setting( string $setting_name ): void {
		$this->touched_settings[] = $setting_name;
	}
}
