<?php
/**
 * Tests for _wp_connectors_get_connector_script_module_data().
 *
 * @group connectors
 * @covers ::_wp_connectors_get_connector_script_module_data
 */
class Tests_Connectors_WpConnectorsGetConnectorScriptModuleData extends WP_UnitTestCase {

	const CONNECTOR_ID             = 'wp_test_application_password_connector';
	const CREDENTIALS_SETTING_NAME = 'connectors_test_remote_credentials';
	const CREDENTIALS_ENV_VAR_NAME = 'WP_TESTS_CONNECTOR_REMOTE_CREDENTIALS';

	/**
	 * Registers an application password connector before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->register_connector( array() );
	}

	/**
	 * Removes the connector, its settings, and environment overrides after each test.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		delete_option( self::CREDENTIALS_SETTING_NAME );
		putenv( self::CREDENTIALS_ENV_VAR_NAME );

		parent::tear_down();
	}

	/**
	 * Registers the test connector with extra authentication arguments.
	 *
	 * @param array $auth_extra Extra authentication arguments to merge in.
	 */
	private function register_connector( array $auth_extra ): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		$registry->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => 'Connect to a remote WordPress site.',
				'type'           => 'content_source',
				'authentication' => array_merge(
					array(
						'method'          => 'application_password',
						'credentials_url' => 'https://example.com/profile.php',
						'setting_name'    => self::CREDENTIALS_SETTING_NAME,
					),
					$auth_extra
				),
			)
		);
	}

	/**
	 * @ticket 64850
	 */
	public function test_exposes_application_password_metadata_without_credentials(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => 'abcd efgh ijkl mnop 1234',
			)
		);

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'application_password', $auth['method'] );
		$this->assertSame( self::CREDENTIALS_SETTING_NAME, $auth['settingName'] );
		$this->assertSame( 'https://example.com/profile.php', $auth['credentialsUrl'] );
		$this->assertSame( 'database', $auth['keySource'] );
		$this->assertTrue( $auth['isConnected'] );
		$this->assertArrayNotHasKey( 'username', $auth );
		$this->assertArrayNotHasKey( 'applicationPassword', $auth );
		$this->assertArrayNotHasKey( 'password', $auth );
	}

	/**
	 * @ticket 64850
	 */
	public function test_is_not_connected_when_one_credential_is_missing(): void {
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'remote-user',
				'password' => '',
			)
		);

		$data = _wp_connectors_get_connector_script_module_data( array() );

		$this->assertFalse( $data['connectors'][ self::CONNECTOR_ID ]['authentication']['isConnected'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_environment_variable_credentials_mark_connector_connected(): void {
		$this->register_connector( array( 'env_var_name' => self::CREDENTIALS_ENV_VAR_NAME ) );
		putenv( self::CREDENTIALS_ENV_VAR_NAME . '=remote-user:abcd efgh ijkl mnop 1234' );

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'env', $auth['keySource'] );
		$this->assertTrue( $auth['isConnected'] );
		$this->assertArrayNotHasKey( 'username', $auth );
		$this->assertArrayNotHasKey( 'password', $auth );
	}

	/**
	 * @ticket 64850
	 */
	public function test_constant_credentials_mark_connector_connected(): void {
		if ( ! defined( 'WP_TESTS_CONNECTOR_REMOTE_CREDENTIALS_CONSTANT' ) ) {
			define( 'WP_TESTS_CONNECTOR_REMOTE_CREDENTIALS_CONSTANT', 'remote-user:abcd efgh ijkl mnop 1234' );
		}
		$this->register_connector( array( 'constant_name' => 'WP_TESTS_CONNECTOR_REMOTE_CREDENTIALS_CONSTANT' ) );

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'constant', $auth['keySource'] );
		$this->assertTrue( $auth['isConnected'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_environment_variable_takes_precedence_over_database(): void {
		$this->register_connector( array( 'env_var_name' => self::CREDENTIALS_ENV_VAR_NAME ) );
		putenv( self::CREDENTIALS_ENV_VAR_NAME . '=env-user:env-password' );
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'db-user',
				'password' => 'db-password',
			)
		);

		$data = _wp_connectors_get_connector_script_module_data( array() );

		$this->assertSame( 'env', $data['connectors'][ self::CONNECTOR_ID ]['authentication']['keySource'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_malformed_environment_variable_credentials_fall_back_to_database(): void {
		$this->setExpectedIncorrectUsage( 'wp_connectors_get_application_password_credentials' );

		$this->register_connector( array( 'env_var_name' => self::CREDENTIALS_ENV_VAR_NAME ) );
		putenv( self::CREDENTIALS_ENV_VAR_NAME . '=missing-a-separator' );
		update_option(
			self::CREDENTIALS_SETTING_NAME,
			array(
				'username' => 'db-user',
				'password' => 'db-password',
			)
		);

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'database', $auth['keySource'] );
		$this->assertTrue( $auth['isConnected'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_malformed_environment_variable_credentials_without_fallback_are_not_connected(): void {
		$this->setExpectedIncorrectUsage( 'wp_connectors_get_application_password_credentials' );

		$this->register_connector( array( 'env_var_name' => self::CREDENTIALS_ENV_VAR_NAME ) );
		putenv( self::CREDENTIALS_ENV_VAR_NAME . '=missing-a-separator' );

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'none', $auth['keySource'] );
		$this->assertFalse( $auth['isConnected'] );
	}

	/**
	 * @ticket 64850
	 */
	public function test_environment_variable_password_may_contain_colons(): void {
		$this->register_connector( array( 'env_var_name' => self::CREDENTIALS_ENV_VAR_NAME ) );
		putenv( self::CREDENTIALS_ENV_VAR_NAME . '=remote-user:pass:with:colons' );

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'env', $auth['keySource'] );
		$this->assertTrue( $auth['isConnected'] );
	}
}
