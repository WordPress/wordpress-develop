<?php
/**
 * Tests for _wp_connectors_rest_settings_dispatch().
 *
 * @group connectors
 * @covers ::_wp_connectors_rest_settings_dispatch
 */
class Tests_Connectors_WpConnectorsRestSettingsDispatch extends WP_UnitTestCase {

	const CONNECTOR_ID             = 'wp_test_application_password_connector';
	const CREDENTIALS_SETTING_NAME = 'connectors_test_remote_credentials';

	/**
	 * Registers an application password connector before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Test Remote WordPress Connector',
				'type'           => 'content_source',
				'authentication' => array(
					'method'       => 'application_password',
					'setting_name' => self::CREDENTIALS_SETTING_NAME,
				),
			)
		);
	}

	/**
	 * Removes the test connector after each test.
	 */
	public function tear_down(): void {
		$registry = WP_Connector_Registry::get_instance();
		if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
			$registry->unregister( self::CONNECTOR_ID );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 64850
	 */
	public function test_masks_application_password_but_not_username(): void {
		$application_password = 'abcd efgh ijkl mnop 1234';
		$response             = new WP_REST_Response(
			array(
				self::CREDENTIALS_SETTING_NAME => array(
					'username' => 'remote-user',
					'password' => $application_password,
				),
			)
		);
		$request              = new WP_REST_Request( 'GET', '/wp/v2/settings' );

		$result = _wp_connectors_rest_settings_dispatch( $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame( 'remote-user', $data[ self::CREDENTIALS_SETTING_NAME ]['username'] );
		$this->assertSame( str_repeat( "\u{2022}", 16 ), $data[ self::CREDENTIALS_SETTING_NAME ]['password'] );
		$this->assertNotSame( $application_password, $data[ self::CREDENTIALS_SETTING_NAME ]['password'] );
	}
}
