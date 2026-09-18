<?php
/**
 * Tests for connector registration companion functions.
 *
 * @group connectors
 * @covers ::wp_is_connector_registered
 * @covers ::wp_get_connector
 * @covers ::wp_get_connectors
 */
class Tests_Connectors_WpRegisterConnector extends WP_UnitTestCase {

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
