<?php
/**
 * Tests for _wp_connectors_get_connector_script_module_data().
 *
 * @group connectors
 * @covers ::_wp_connectors_get_connector_script_module_data
 */
class Tests_Connectors_WpConnectorsGetConnectorScriptModuleData extends WP_UnitTestCase {

	const CONNECTOR_ID = 'wp_test_application_password_connector';
	const USERNAME_SETTING_NAME = 'connectors_test_remote_username';
	const APPLICATION_PASSWORD_SETTING_NAME = 'connectors_test_remote_application_password';

	/**
	 * Registers an application password connector before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'description'    => 'Connect to a remote WordPress site.',
				'type'           => 'content_source',
				'authentication' => array(
					'method'                            => 'application_password',
					'credentials_url'                   => 'https://example.com/profile.php',
					'username_setting_name'             => self::USERNAME_SETTING_NAME,
					'application_password_setting_name' => self::APPLICATION_PASSWORD_SETTING_NAME,
				),
			)
		);
	}

	/**
	 * Removes the connector and its settings after each test.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		delete_option( self::USERNAME_SETTING_NAME );
		delete_option( self::APPLICATION_PASSWORD_SETTING_NAME );

		parent::tear_down();
	}

	/**
	 * @ticket 64850
	 */
	public function test_exposes_application_password_metadata_without_credentials(): void {
		update_option( self::USERNAME_SETTING_NAME, 'remote-user' );
		update_option( self::APPLICATION_PASSWORD_SETTING_NAME, 'abcd efgh ijkl mnop 1234' );

		$data = _wp_connectors_get_connector_script_module_data( array() );
		$auth = $data['connectors'][ self::CONNECTOR_ID ]['authentication'];

		$this->assertSame( 'application_password', $auth['method'] );
		$this->assertSame( self::USERNAME_SETTING_NAME, $auth['usernameSettingName'] );
		$this->assertSame( self::APPLICATION_PASSWORD_SETTING_NAME, $auth['applicationPasswordSettingName'] );
		$this->assertSame( 'https://example.com/profile.php', $auth['credentialsUrl'] );
		$this->assertTrue( $auth['isConnected'] );
		$this->assertArrayNotHasKey( 'username', $auth );
		$this->assertArrayNotHasKey( 'applicationPassword', $auth );
	}

	/**
	 * @ticket 64850
	 */
	public function test_is_not_connected_when_one_credential_is_missing(): void {
		update_option( self::USERNAME_SETTING_NAME, 'remote-user' );

		$data = _wp_connectors_get_connector_script_module_data( array() );

		$this->assertFalse( $data['connectors'][ self::CONNECTOR_ID ]['authentication']['isConnected'] );
	}
}
