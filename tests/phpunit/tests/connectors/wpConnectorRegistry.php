<?php
/**
 * Tests for the WP_Connector_Registry class.
 *
 * @covers WP_Connector_Registry
 *
 * @group connectors
 *
 * @phpstan-import-type Connector from WP_Connector_Registry
 */
class Tests_Connectors_WpConnectorRegistry extends WP_UnitTestCase {

	/**
	 * Connector registry instance.
	 */
	private WP_Connector_Registry $registry;

	/**
	 * Default valid connector args for testing.
	 *
	 * @var array<string, mixed>
	 * @phpstan-var Connector
	 */
	private static array $default_args;

	/**
	 * Set up each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->registry = new WP_Connector_Registry();

		self::$default_args = array(
			'name'           => 'Test Provider',
			'description'    => 'A test AI provider.',
			'type'           => 'ai_provider',
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://example.com/keys',
			),
		);
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_returns_connector_data() {
		$result = $this->registry->register( 'test_provider', self::$default_args );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Provider', $result['name'] );
		$this->assertSame( 'A test AI provider.', $result['description'] );
		$this->assertSame( 'ai_provider', $result['type'] );
		$this->assertSame( 'api_key', $result['authentication']['method'] );
		$this->assertSame( 'https://example.com/keys', $result['authentication']['credentials_url'] );
		$this->assertSame( 'connectors_ai_test_provider_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_generates_setting_name_for_api_key() {
		$result = $this->registry->register( 'my_ai', self::$default_args );

		$this->assertSame( 'connectors_ai_my_ai_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_no_setting_name_for_none_auth() {
		$args   = array(
			'name'           => 'No Auth Provider',
			'type'           => 'ai_provider',
			'authentication' => array( 'method' => 'none' ),
		);
		$result = $this->registry->register( 'no_auth', $args );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'setting_name', $result['authentication'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_defaults_description_to_empty_string() {
		$args = array(
			'name'           => 'Minimal',
			'type'           => 'ai_provider',
			'authentication' => array( 'method' => 'none' ),
		);

		$result = $this->registry->register( 'minimal', $args );

		$this->assertSame( '', $result['description'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_includes_logo_url() {
		$args             = self::$default_args;
		$args['logo_url'] = 'https://example.com/logo.png';

		$result = $this->registry->register( 'with_logo', $args );

		$this->assertArrayHasKey( 'logo_url', $result );
		$this->assertSame( 'https://example.com/logo.png', $result['logo_url'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_logo_url_when_not_provided() {
		$result = $this->registry->register( 'no_logo', self::$default_args );

		$this->assertArrayNotHasKey( 'logo_url', $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_logo_url_when_empty() {
		$args             = self::$default_args;
		$args['logo_url'] = '';

		$result = $this->registry->register( 'empty_logo', $args );

		$this->assertArrayNotHasKey( 'logo_url', $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_includes_plugin_data() {
		$args           = self::$default_args;
		$args['plugin'] = array( 'slug' => 'my-plugin' );

		$result = $this->registry->register( 'with_plugin', $args );

		$this->assertArrayHasKey( 'plugin', $result );
		$this->assertSame( array( 'slug' => 'my-plugin' ), $result['plugin'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_plugin_when_not_provided() {
		$result = $this->registry->register( 'no_plugin', self::$default_args );

		$this->assertArrayNotHasKey( 'plugin', $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_invalid_id_with_uppercase() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$result = $this->registry->register( 'InvalidId', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_invalid_id_with_dashes() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$result = $this->registry->register( 'my-provider', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_empty_id() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$result = $this->registry->register( '', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_duplicate_id() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$this->registry->register( 'duplicate', self::$default_args );
		$result = $this->registry->register( 'duplicate', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['name'] );

		$result = $this->registry->register( 'no_name', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_empty_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args         = self::$default_args;
		$args['name'] = '';

		$result = $this->registry->register( 'empty_name', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_type() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['type'] );

		$result = $this->registry->register( 'no_type', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_authentication() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['authentication'] );

		$result = $this->registry->register( 'no_auth', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_invalid_auth_method() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                             = self::$default_args;
		$args['authentication']['method'] = 'oauth';

		$result = $this->registry->register( 'bad_auth', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_is_registered_returns_true_for_registered() {
		$this->registry->register( 'exists', self::$default_args );

		$this->assertTrue( $this->registry->is_registered( 'exists' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_is_registered_returns_false_for_unregistered() {
		$this->assertFalse( $this->registry->is_registered( 'does_not_exist' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_registered_returns_connector_data() {
		$this->registry->register( 'my_connector', self::$default_args );

		$result = $this->registry->get_registered( 'my_connector' );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Provider', $result['name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_registered_returns_null_for_unregistered() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::get_registered' );

		$result = $this->registry->get_registered( 'nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_all_registered_returns_all_connectors() {
		$this->registry->register( 'first', self::$default_args );

		$args         = self::$default_args;
		$args['name'] = 'Second Provider';
		$this->registry->register( 'second', $args );

		$all = $this->registry->get_all_registered();

		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'first', $all );
		$this->assertArrayHasKey( 'second', $all );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_all_registered_returns_empty_when_none() {
		$this->assertSame( array(), $this->registry->get_all_registered() );
	}

	/**
	 * @ticket 64791
	 */
	public function test_unregister_removes_connector() {
		$this->registry->register( 'to_remove', self::$default_args );

		$result = $this->registry->unregister( 'to_remove' );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Provider', $result['name'] );
		$this->assertFalse( $this->registry->is_registered( 'to_remove' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_unregister_returns_null_for_unregistered() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::unregister' );

		$result = $this->registry->unregister( 'nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_instance_returns_registry() {
		$instance = WP_Connector_Registry::get_instance();

		$this->assertInstanceOf( WP_Connector_Registry::class, $instance );
	}

	/**
	 * @ticket 64791
	 */
	public function test_set_instance_rejects_after_init() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::set_instance' );

		WP_Connector_Registry::set_instance( new WP_Connector_Registry() );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_instance_returns_same_instance() {
		$instance1 = WP_Connector_Registry::get_instance();
		$instance2 = WP_Connector_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}
}
