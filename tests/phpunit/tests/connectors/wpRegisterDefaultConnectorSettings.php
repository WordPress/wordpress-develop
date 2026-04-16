<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_register_default_connector_settings().
 *
 * @group connectors
 * @covers ::_wp_register_default_connector_settings
 */
class Tests_Connectors_WpRegisterDefaultConnectorSettings extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	/**
	 * Original connector registry instance.
	 *
	 * @var WP_Connector_Registry|null
	 */
	private ?WP_Connector_Registry $original_registry;

	/**
	 * Snapshot of registered settings before each test.
	 *
	 * @var array
	 */
	private array $original_registered_settings = array();

	/**
	 * Registers the mock provider once before any tests in this class run.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Unregisters the mock provider setting added by `init`.
	 */
	public static function tear_down_after_class(): void {
		self::unregister_mock_connector_setting();
		parent::tear_down_after_class();
	}

	/**
	 * Stores the original registry and settings before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_registry = WP_Connector_Registry::get_instance();

		global $wp_registered_settings;
		$this->original_registered_settings = $wp_registered_settings;
	}

	/**
	 * Restores the original registry and registered settings after each test.
	 */
	public function tear_down(): void {
		global $wp_registered_settings;
		$wp_registered_settings = $this->original_registered_settings;

		$this->set_registry_instance( $this->original_registry );

		parent::tear_down();
	}

	/**
	 * @ticket 64730
	 */
	public function test_ai_connector_settings_still_auto_register(): void {
		$setting_name = 'connectors_ai_mock_connectors_test_api_key';
		unregister_setting( 'connectors', $setting_name );

		$mock = wp_get_connector( 'mock-connectors-test' );
		$this->assertIsArray( $mock );

		$registry = new WP_Connector_Registry();
		$this->set_registered_connectors(
			$registry,
			array(
				'mock-connectors-test' => $mock,
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
		$property->setValue( $registry, $connectors );
	}

	/**
	 * Sets the static registry instance.
	 *
	 * @param WP_Connector_Registry|null $registry Connector registry instance.
	 */
	private function set_registry_instance( ?WP_Connector_Registry $registry ): void {
		$property = new ReflectionProperty( WP_Connector_Registry::class, 'instance' );
		$property->setValue( null, $registry );
	}
}
