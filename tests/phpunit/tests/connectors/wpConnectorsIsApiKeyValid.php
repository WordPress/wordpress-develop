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
		self::register_mock_connectors_http_provider();
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

	/**
	 * Tests that a key the provider accepts returns true.
	 *
	 * @ticket 65551
	 */
	public function test_key_accepted_by_provider_returns_true() {
		$this->mock_models_endpoint_response( 200 );

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'mock-connectors-http-test' );

		$this->assertTrue( $result );
		$this->assertSame( array( 'test-key' ), $this->mock_models_endpoint_api_keys, 'The key should be sent to the provider.' );
	}

	/**
	 * Tests that a key the provider rejects returns false.
	 *
	 * @ticket 65551
	 *
	 * @dataProvider data_rejecting_status_codes
	 *
	 * @param int $status_code HTTP status code the provider responds with.
	 */
	public function test_key_rejected_by_provider_returns_false( $status_code ) {
		$this->mock_models_endpoint_response( $status_code );

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'mock-connectors-http-test' );

		$this->assertFalse( $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int}>
	 */
	public function data_rejecting_status_codes() {
		return array(
			'400 Bad Request'  => array( 400 ),
			'401 Unauthorized' => array( 401 ),
			'403 Forbidden'    => array( 403 ),
		);
	}

	/**
	 * Tests that a key that cannot be verified returns null.
	 *
	 * @ticket 65551
	 *
	 * @dataProvider data_unverifiable_responses
	 *
	 * @param int|WP_Error $response HTTP status code, or a WP_Error for a network failure.
	 */
	public function test_key_that_cannot_be_verified_returns_null( $response ) {
		$this->mock_models_endpoint_response( $response );

		$errors = array();
		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );
		add_action(
			'wp_trigger_error_always_run',
			static function ( $function_name ) use ( &$errors ) {
				$errors[] = $function_name;
			}
		);

		$result = _wp_connectors_is_ai_api_key_valid( 'test-key', 'mock-connectors-http-test' );

		$this->assertNull( $result );
		$this->assertSame( array( '_wp_connectors_is_ai_api_key_valid' ), $errors, 'The failure should be reported.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int|WP_Error}>
	 */
	public function data_unverifiable_responses() {
		return array(
			'network error'             => array( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ),
			'408 Request Timeout'       => array( 408 ),
			'429 Too Many Requests'     => array( 429 ),
			'500 Internal Server Error' => array( 500 ),
			'503 Service Unavailable'   => array( 503 ),
		);
	}

	/**
	 * Tests that a model list cached for one key does not validate another.
	 *
	 * @ticket 65551
	 */
	public function test_cached_model_list_does_not_validate_a_different_key() {
		$this->mock_models_endpoint_response( 200 );
		$this->assertTrue( _wp_connectors_is_ai_api_key_valid( 'valid-key', 'mock-connectors-http-test' ) );

		$this->mock_models_endpoint_response( 401 );
		$this->assertFalse( _wp_connectors_is_ai_api_key_valid( 'invalid-key', 'mock-connectors-http-test' ) );

		$this->assertSame( array( 'valid-key', 'invalid-key' ), $this->mock_models_endpoint_api_keys, 'Each key should be sent to the provider.' );
	}
}
