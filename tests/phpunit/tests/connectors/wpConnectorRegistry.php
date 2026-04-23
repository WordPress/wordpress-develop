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
			'name'           => 'Test Connector',
			'description'    => 'A test connector.',
			'type'           => 'test_type',
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
		$result = $this->registry->register( 'test-provider', self::$default_args );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Connector', $result['name'] );
		$this->assertSame( 'A test connector.', $result['description'] );
		$this->assertSame( 'test_type', $result['type'] );
		$this->assertSame( 'api_key', $result['authentication']['method'] );
		$this->assertSame( 'https://example.com/keys', $result['authentication']['credentials_url'] );
		$this->assertSame( 'connectors_test_type_test_provider_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_generates_setting_name_for_api_key() {
		$result = $this->registry->register( 'myai', self::$default_args );

		$this->assertSame( 'connectors_test_type_myai_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64861
	 */
	public function test_register_generates_setting_name_normalizes_hyphens() {
		$result = $this->registry->register( 'my-ai', self::$default_args );

		$this->assertSame( 'connectors_test_type_my_ai_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_generates_setting_name_using_type_and_id() {
		$args         = self::$default_args;
		$args['type'] = 'email_delivery';

		$result = $this->registry->register( 'sendgrid', $args );

		$this->assertSame( 'connectors_email_delivery_sendgrid_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_uses_custom_setting_name_when_provided() {
		$args                                   = self::$default_args;
		$args['authentication']['setting_name'] = 'wordpress_api_key';

		$result = $this->registry->register( 'custom-setting', $args );

		$this->assertSame( 'wordpress_api_key', $result['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_empty_setting_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                   = self::$default_args;
		$args['authentication']['setting_name'] = '';

		$result = $this->registry->register( 'empty-setting', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_non_string_setting_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                   = self::$default_args;
		$args['authentication']['setting_name'] = 123;

		$result = $this->registry->register( 'non-string-setting', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_stores_constant_name_when_provided() {
		$args                                    = self::$default_args;
		$args['authentication']['constant_name'] = 'MY_PROVIDER_API_KEY';

		$result = $this->registry->register( 'my-provider', $args );

		$this->assertSame( 'MY_PROVIDER_API_KEY', $result['authentication']['constant_name'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_omits_constant_name_when_not_provided() {
		$result = $this->registry->register( 'no-const', self::$default_args );

		$this->assertArrayNotHasKey( 'constant_name', $result['authentication'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_empty_constant_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                    = self::$default_args;
		$args['authentication']['constant_name'] = '';

		$result = $this->registry->register( 'empty-const', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_non_string_constant_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                    = self::$default_args;
		$args['authentication']['constant_name'] = 123;

		$result = $this->registry->register( 'bad-const', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_stores_env_var_name_when_provided() {
		$args                                   = self::$default_args;
		$args['authentication']['env_var_name'] = 'MY_PROVIDER_API_KEY';

		$result = $this->registry->register( 'my-provider', $args );

		$this->assertSame( 'MY_PROVIDER_API_KEY', $result['authentication']['env_var_name'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_omits_env_var_name_when_not_provided() {
		$result = $this->registry->register( 'no-env', self::$default_args );

		$this->assertArrayNotHasKey( 'env_var_name', $result['authentication'] );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_empty_env_var_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                   = self::$default_args;
		$args['authentication']['env_var_name'] = '';

		$result = $this->registry->register( 'empty-env', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64957
	 */
	public function test_register_rejects_non_string_env_var_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                                   = self::$default_args;
		$args['authentication']['env_var_name'] = 123;

		$result = $this->registry->register( 'bad-env', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_no_setting_name_for_none_auth() {
		$args   = array(
			'name'           => 'No Auth Connector',
			'type'           => 'test_type',
			'authentication' => array( 'method' => 'none' ),
		);
		$result = $this->registry->register( 'no-auth', $args );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'setting_name', $result['authentication'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_defaults_description_to_empty_string() {
		$args = array(
			'name'           => 'Minimal',
			'type'           => 'test_type',
			'authentication' => array( 'method' => 'none' ),
		);

		$result = $this->registry->register( 'minimal-provider', $args );

		$this->assertSame( '', $result['description'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_includes_logo_url() {
		$args             = self::$default_args;
		$args['logo_url'] = 'https://example.com/logo.png';

		$result = $this->registry->register( 'with-logo', $args );

		$this->assertArrayHasKey( 'logo_url', $result );
		$this->assertSame( 'https://example.com/logo.png', $result['logo_url'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_logo_url_when_not_provided() {
		$result = $this->registry->register( 'no-logo', self::$default_args );

		$this->assertArrayNotHasKey( 'logo_url', $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_logo_url_when_empty() {
		$args             = self::$default_args;
		$args['logo_url'] = '';

		$result = $this->registry->register( 'empty-logo', $args );

		$this->assertArrayNotHasKey( 'logo_url', $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_includes_plugin_data() {
		$args           = self::$default_args;
		$args['plugin'] = array( 'file' => 'my-plugin/my-plugin.php' );

		$result = $this->registry->register( 'with-plugin', $args );

		$this->assertArrayHasKey( 'plugin', $result );
		$this->assertSame( array( 'file' => 'my-plugin/my-plugin.php' ), $result['plugin'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_omits_plugin_when_not_provided() {
		$result = $this->registry->register( 'no-plugin', self::$default_args );

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
	 * @ticket 64861
	 */
	public function test_register_accepts_id_with_hyphens() {
		$result = $this->registry->register( 'my-provider', self::$default_args );

		$this->assertIsArray( $result );
	}

	/**
	 * @ticket 64861
	 */
	public function test_register_accepts_id_with_underscores() {
		$result = $this->registry->register( 'my_provider', self::$default_args );

		$this->assertIsArray( $result );
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

		$this->registry->register( 'test-duplicate', self::$default_args );
		$result = $this->registry->register( 'test-duplicate', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['name'] );

		$result = $this->registry->register( 'no-name', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_empty_name() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args         = self::$default_args;
		$args['name'] = '';

		$result = $this->registry->register( 'empty-name', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_type() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['type'] );

		$result = $this->registry->register( 'no-type', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_missing_authentication() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args = self::$default_args;
		unset( $args['authentication'] );

		$result = $this->registry->register( 'no-auth', $args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_rejects_invalid_auth_method() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::register' );

		$args                             = self::$default_args;
		$args['authentication']['method'] = 'oauth';

		$result = $this->registry->register( 'bad-auth', $args );

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
		$this->registry->register( 'my-connector', self::$default_args );

		$result = $this->registry->get_registered( 'my-connector' );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Connector', $result['name'] );
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
		$this->registry->register( 'to-remove', self::$default_args );

		$result = $this->registry->unregister( 'to-remove' );

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Connector', $result['name'] );
		$this->assertFalse( $this->registry->is_registered( 'to-remove' ) );
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

	/**
	 * Test registration skips AI connectors when AI is not supported.
	 */
	public function test_register_skips_when_ai_not_supported() {
		add_filter( 'wp_supports_ai', '__return_false' );

		$args         = self::$default_args;
		$args['type'] = 'ai_provider';
		$this->registry->register( 'first', $args );

		$all = $this->registry->get_all_registered();
		$this->assertCount( 0, $all );
	}
}
