<?php
/**
 * Tests for wp_has_ai_credentials().
 *
 * @covers ::wp_has_ai_credentials
 *
 * @group connectors
 * @group ai
 */
class Tests_Connectors_WpHasAiCredentials extends WP_UnitTestCase {

	/**
	 * Cleans up environment variables after each test.
	 */
	public function tear_down() {
		putenv( 'OPENAI_API_KEY' );
		putenv( 'GOOGLE_API_KEY' );
		putenv( 'GEMINI_API_KEY' );
		putenv( 'ANTHROPIC_API_KEY' );

		parent::tear_down();
	}

	/**
	 * Tests that wp_has_ai_credentials returns false when no AI credentials are set.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_no_credentials_set() {
		$this->assertFalse( wp_has_ai_credentials(), 'Should return false when no AI credentials are configured.' );
	}

	/**
	 * Tests that wp_has_ai_credentials returns true when an AI provider option is set in the database.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_database_option_is_set() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-database-key' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when an AI provider option exists in the database.' );
	}

	/**
	 * Tests that wp_has_ai_credentials returns true when OpenAI key is set via environment variable.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_openai_env_var_is_set() {
		putenv( 'OPENAI_API_KEY=sk-test-openai-key' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when OPENAI_API_KEY environment variable is configured.' );
	}

	/**
	 * Tests that wp_has_ai_credentials returns true when Anthropic key is set via environment variable.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_anthropic_env_var_is_set() {
		putenv( 'ANTHROPIC_API_KEY=sk-ant-test-key' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when ANTHROPIC_API_KEY environment variable is configured.' );
	}

	/**
	 * Tests that wp_has_ai_credentials returns true when Google key is set via environment variable.
	 *
	 * @ticket 64924
	 */
	public function test_returns_true_when_google_env_var_is_set() {
		putenv( 'GOOGLE_API_KEY=test-google-key' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when GOOGLE_API_KEY environment variable is configured.' );
	}

	/**
	 * Tests that empty string options or empty env vars do not count as valid credentials.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_empty_string_set() {
		update_option( 'connectors_ai_openai_api_key', '' );
		putenv( 'OPENAI_API_KEY=' );

		$this->assertFalse( wp_has_ai_credentials(), 'Empty option or environment variable should not count as credentials.' );
	}

	/**
	 * Tests that non-AI connectors do not cause wp_has_ai_credentials to return true.
	 *
	 * @ticket 64924
	 */
	public function test_ignores_non_ai_provider_connectors() {
		$registry = WP_Connector_Registry::get_instance();
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

		$has_ai = wp_has_ai_credentials();
		$registry->unregister( 'test_spam_filter' );

		$this->assertFalse( $has_ai, 'Non-AI provider connectors should be ignored.' );
	}

	/**
	 * Tests that wp_has_ai_credentials returns false when wp_supports_ai() returns false.
	 *
	 * @ticket 64924
	 */
	public function test_returns_false_when_wp_supports_ai_is_false() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-key' );

		add_filter( 'wp_supports_ai', '__return_false' );

		$this->assertFalse( wp_has_ai_credentials(), 'Should return false when wp_supports_ai() returns false.' );
	}

	/**
	 * Tests that the pre_wp_has_ai_credentials filter can short-circuit the credential check.
	 *
	 * @ticket 64924
	 */
	public function test_pre_wp_has_ai_credentials_filter_can_short_circuit() {
		add_filter( 'pre_wp_has_ai_credentials', '__return_true' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when pre_wp_has_ai_credentials filter returns true.' );
	}

	/**
	 * Tests that the wp_has_ai_credentials filter can override false to true.
	 *
	 * @ticket 64924
	 */
	public function test_wp_has_ai_credentials_filter_can_override_to_true() {
		add_filter( 'wp_has_ai_credentials', '__return_true' );

		$this->assertTrue( wp_has_ai_credentials(), 'Should return true when wp_has_ai_credentials filter overrides to true.' );
	}

	/**
	 * Tests that the wp_has_ai_credentials filter can override true to false.
	 *
	 * @ticket 64924
	 */
	public function test_wp_has_ai_credentials_filter_can_override_to_false() {
		update_option( 'connectors_ai_openai_api_key', 'sk-test-key' );

		add_filter( 'wp_has_ai_credentials', '__return_false' );

		$this->assertFalse( wp_has_ai_credentials(), 'Should return false when wp_has_ai_credentials filter overrides to false.' );
	}
}
