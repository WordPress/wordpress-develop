<?php
/**
 * Tests for has_ai_credentials().
 *
 * @covers ::has_ai_credentials
 *
 * @group connectors
 * @group ai
 */
class Tests_Connectors_HasAiCredentials extends WP_UnitTestCase {

	/**
	 * Cleans up options, environment variables, and filters after each test.
	 */
	public function tear_down() {
		delete_option( 'connectors_ai_openai_api_key' );
		delete_option( 'connectors_ai_google_api_key' );
		delete_option( 'connectors_ai_anthropic_api_key' );
		putenv( 'OPENAI_API_KEY' );
		putenv( 'GOOGLE_API_KEY' );
		putenv( 'GEMINI_API_KEY' );
		putenv( 'ANTHROPIC_API_KEY' );

		parent::tear_down();
	}

	/**
	 * Tests that has_ai_credentials returns false when no AI credentials are set.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_no_credentials_set() {
		$this->assertFalse( has_ai_credentials() );
	}

	/**
	 * Tests that has_ai_credentials returns true when an AI provider option is set in the database.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_database_option_is_set() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-database-key' );

		$this->assertTrue( has_ai_credentials() );
	}

	/**
	 * Tests that has_ai_credentials returns true when an AI provider key is set via environment variable.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_env_var_is_set() {
		putenv( 'OPENAI_API_KEY=sk-test-env-key' );

		$this->assertTrue( has_ai_credentials() );
	}

	/**
	 * Tests that has_ai_credentials returns true for other default providers when configured via env var.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_google_or_anthropic_env_var_is_set() {
		putenv( 'ANTHROPIC_API_KEY=sk-ant-test-key' );
		$this->assertTrue( has_ai_credentials() );
		putenv( 'ANTHROPIC_API_KEY' );

		putenv( 'GOOGLE_API_KEY=test-gemini-key' );
		$this->assertTrue( has_ai_credentials() );
		putenv( 'GOOGLE_API_KEY' );
	}

	/**
	 * Tests that empty string options or empty env vars do not count as valid credentials.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_empty_string_set() {
		update_option( 'connectors_ai_openai_api_key', '' );
		putenv( 'OPENAI_API_KEY=' );

		$this->assertFalse( has_ai_credentials() );
	}

	/**
	 * Tests that non-AI connectors do not cause has_ai_credentials to return true.
	 *
	 * @ticket 64924
	 */
	public function test_ignores_non_ai_provider_connectors() {
		$registry = WP_Connector_Registry::get_instance();
		$this->assertNotNull( $registry );

		$registry->register(
			'test_spam_filter',
			array(
				'name'           => 'Spam Filter',
				'type'           => 'spam_filter',
				'description'    => 'Test non-AI connector.',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => 'connectors_spam_filter_api_key',
				),
			)
		);

		update_option( 'connectors_spam_filter_api_key', 'sk-spam-filter-key' );

		$has_ai = has_ai_credentials();

		delete_option( 'connectors_spam_filter_api_key' );
		$registry->unregister( 'test_spam_filter' );

		$this->assertFalse( $has_ai );
	}

	/**
	 * Tests that has_ai_credentials returns false when wp_supports_ai() returns false.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_wp_supports_ai_is_false() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-key' );

		add_filter( 'wp_supports_ai', '__return_false' );
		$has_ai = has_ai_credentials();
		remove_filter( 'wp_supports_ai', '__return_false' );

		$this->assertFalse( $has_ai );
	}

	/**
	 * Tests that the wp_has_ai_credentials filter can override false to true.
	 *
	 * @ticket 64924
	 */
	public function test_wp_has_ai_credentials_filter_can_override_to_true() {
		$filter = static function ( $has_credentials, $connectors ) {
			return true;
		};

		add_filter( 'wp_has_ai_credentials', $filter, 10, 2 );
		$result = has_ai_credentials();
		remove_filter( 'wp_has_ai_credentials', $filter );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that the wp_has_ai_credentials filter can override true to false.
	 *
	 * @ticket 64924
	 */
	public function test_wp_has_ai_credentials_filter_can_override_to_false() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-key' );

		$filter = static function ( $has_credentials, $connectors ) {
			return false;
		};

		add_filter( 'wp_has_ai_credentials', $filter, 10, 2 );
		$result = has_ai_credentials();
		remove_filter( 'wp_has_ai_credentials', $filter );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that the legacy wpai_has_ai_credentials filter works for backward compatibility.
	 *
	 * @ticket 64924
	 */
	public function test_legacy_wpai_has_ai_credentials_filter() {
		$filter = static function ( $has_credentials, $connectors ) {
			return true;
		};

		add_filter( 'wpai_has_ai_credentials', $filter, 10, 2 );
		$result = has_ai_credentials();
		remove_filter( 'wpai_has_ai_credentials', $filter );

		$this->assertTrue( $result );
	}
}
