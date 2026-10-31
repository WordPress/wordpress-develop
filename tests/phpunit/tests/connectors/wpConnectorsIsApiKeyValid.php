<?php

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-provider-trait.php';

/**
 * Tests for _wp_connectors_is_ai_api_key_valid().
 *
 * @group connectors
 * @covers ::_wp_connectors_is_ai_api_key_valid
 */
class Tests_Connectors_WpConnectorsIsApiKeyValid extends WP_UnitTestCase {

	use WP_AI_Client_Mock_Provider_Trait;

	/**
	 * Registers the mock provider once before any tests in this class run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::register_mock_connectors_provider();
	}

	/**
	 * Resets the mock availability flag before each test.
	 */
	public function set_up() {
		parent::set_up();
		self::set_mock_provider_configured( true );
	}

	/**
	 * Tests that an unregistered provider returns null.
	 *
	 * @ticket 64730
	 */
	public function test_unregistered_provider_returns_null() {
		$this->setExpectedIncorrectUsage( '_wp_connectors_is_ai_api_key_valid' );

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'nonexistent_provider' );

		$this->assertNull( $result );
	}

	/**
	 * Tests that a registered and configured provider returns true.
	 *
	 * @ticket 64730
	 */
	public function test_configured_provider_returns_true() {
		self::set_mock_provider_configured( true );

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'mock-connectors-test' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that a registered but unconfigured provider returns false.
	 *
	 * @ticket 64730
	 */
	public function test_unconfigured_provider_returns_false() {
		self::set_mock_provider_configured( false );

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'mock-connectors-test' );

		$this->assertFalse( $result );
	}
}
