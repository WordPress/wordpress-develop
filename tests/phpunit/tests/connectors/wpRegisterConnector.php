<?php
/**
 * Tests for wp_register_connector() and companion functions.
 *
 * @group connectors
 * @covers ::wp_register_connector
 * @covers ::wp_is_connector_registered
 * @covers ::wp_get_connector
 * @covers ::wp_get_connectors
 */
class Tests_Connectors_WpRegisterConnector extends WP_UnitTestCase {

	/**
	 * Default valid connector args for testing.
	 *
	 * @var array
	 */
	private static $default_args = array();

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$default_args = array(
			'name'           => 'Test Connector',
			'description'    => 'A test connector.',
			'type'           => 'ai_provider',
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://example.com/keys',
			),
		);
	}

	/**
	 * Helper to simulate the wp_connectors_init action for registration.
	 *
	 * @param callable $callback The registration callback to run.
	 */
	private function simulate_doing_wp_connectors_init_action( callable $callback ): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_connectors_init';
		$callback();
		array_pop( $wp_current_filter );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_fails_outside_action() {
		$this->setExpectedIncorrectUsage( 'wp_register_connector' );

		$result = wp_register_connector( 'outside_action', self::$default_args );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_register_succeeds_during_action() {
		$result = null;

		$this->simulate_doing_wp_connectors_init_action(
			function () use ( &$result ) {
				$result = wp_register_connector( 'during_action', self::$default_args );
			}
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Test Connector', $result['name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_is_connector_registered_returns_true_for_default() {
		// Default connectors are registered via wp_connectors_init.
		$this->assertTrue( wp_is_connector_registered( 'openai' ) );
		$this->assertTrue( wp_is_connector_registered( 'google' ) );
		$this->assertTrue( wp_is_connector_registered( 'anthropic' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_is_connector_registered_returns_false_for_unregistered() {
		$this->assertFalse( wp_is_connector_registered( 'nonexistent_provider' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_connector_returns_data_for_default() {
		$connector = wp_get_connector( 'openai' );

		$this->assertIsArray( $connector );
		$this->assertSame( 'OpenAI', $connector['name'] );
		$this->assertSame( 'ai_provider', $connector['type'] );
		$this->assertSame( 'api_key', $connector['authentication']['method'] );
		$this->assertSame( 'connectors_ai_openai_api_key', $connector['authentication']['setting_name'] );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_connector_returns_null_for_unregistered() {
		$this->setExpectedIncorrectUsage( 'WP_Connector_Registry::get_registered' );

		$result = wp_get_connector( 'nonexistent_provider' );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_get_connectors_returns_all_defaults() {
		$connectors = wp_get_connectors();

		$this->assertArrayHasKey( 'openai', $connectors );
		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayHasKey( 'anthropic', $connectors );
	}
}
