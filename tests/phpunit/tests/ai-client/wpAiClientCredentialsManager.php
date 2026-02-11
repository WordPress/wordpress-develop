<?php
/**
 * Tests for WP_AI_Client_Credentials_Manager.
 *
 * @group ai-client
 * @covers WP_AI_Client_Credentials_Manager
 */
class Tests_AI_Client_Credentials_Manager extends WP_UnitTestCase {

	/**
	 * Saved global providers metadata to restore after each test.
	 *
	 * @var array|null
	 */
	private $saved_providers_metadata;

	/**
	 * Saves state before each test.
	 */
	public function set_up() {
		parent::set_up();
		global $wp_ai_client_providers_metadata;
		$this->saved_providers_metadata = $wp_ai_client_providers_metadata;
	}

	/**
	 * Restores state after each test.
	 */
	public function tear_down() {
		global $wp_ai_client_providers_metadata;
		$wp_ai_client_providers_metadata = $this->saved_providers_metadata;
		delete_option( WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS );
		parent::tear_down();
	}

	/**
	 * Test that collect_providers initializes the global as an array.
	 *
	 * @ticket TBD
	 */
	public function test_collect_providers_initializes_global() {
		global $wp_ai_client_providers_metadata;
		$wp_ai_client_providers_metadata = null;

		$manager = new WP_AI_Client_Credentials_Manager();
		$manager->collect_providers();

		$this->assertIsArray( $wp_ai_client_providers_metadata );

		// Each entry should have the expected structure.
		foreach ( $wp_ai_client_providers_metadata as $provider_id => $metadata ) {
			$this->assertIsString( $provider_id );
			$this->assertArrayHasKey( 'id', $metadata );
			$this->assertArrayHasKey( 'name', $metadata );
			$this->assertArrayHasKey( 'type', $metadata );
			$this->assertArrayHasKey( 'ai_client_classnames', $metadata );
			$this->assertIsArray( $metadata['ai_client_classnames'] );
		}
	}

	/**
	 * Test that collect_providers does not duplicate entries when called multiple times.
	 *
	 * @ticket TBD
	 */
	public function test_collect_providers_deduplicates() {
		global $wp_ai_client_providers_metadata;
		$wp_ai_client_providers_metadata = null;

		$manager = new WP_AI_Client_Credentials_Manager();
		$manager->collect_providers();

		$first_count = count( $wp_ai_client_providers_metadata );

		// Calling again should not duplicate providers.
		$manager->collect_providers();
		$this->assertCount( $first_count, $wp_ai_client_providers_metadata );
	}

	/**
	 * Test that collect_providers preserves existing entries in the global.
	 *
	 * @ticket TBD
	 */
	public function test_collect_providers_preserves_existing_entries() {
		global $wp_ai_client_providers_metadata;

		// Seed the global with a fake provider entry not in the SDK registry.
		$wp_ai_client_providers_metadata = array(
			'test-provider' => array(
				'id'                   => 'test-provider',
				'name'                 => 'Test Provider',
				'type'                 => 'cloud',
				'ai_client_classnames' => array( 'SomeOtherClient' => true ),
			),
		);

		$manager = new WP_AI_Client_Credentials_Manager();
		$manager->collect_providers();

		// The test-provider entry should still exist (not removed by collect_providers).
		$this->assertArrayHasKey( 'test-provider', $wp_ai_client_providers_metadata );
		$this->assertSame( 'Test Provider', $wp_ai_client_providers_metadata['test-provider']['name'] );
	}

	/**
	 * Test that get_all_providers_metadata returns ProviderMetadata objects.
	 *
	 * @ticket TBD
	 */
	public function test_get_all_providers_metadata_returns_provider_metadata_objects() {
		$manager   = new WP_AI_Client_Credentials_Manager();
		$providers = $manager->get_all_providers_metadata();

		$this->assertIsArray( $providers );
		foreach ( $providers as $metadata ) {
			$this->assertInstanceOf( WordPress\AiClient\Providers\DTO\ProviderMetadata::class, $metadata );
		}
	}

	/**
	 * Test that get_all_cloud_providers_metadata only returns cloud providers.
	 *
	 * @ticket TBD
	 */
	public function test_get_all_cloud_providers_metadata_filters_to_cloud_only() {
		$manager         = new WP_AI_Client_Credentials_Manager();
		$cloud_providers = $manager->get_all_cloud_providers_metadata();

		$this->assertIsArray( $cloud_providers );
		foreach ( $cloud_providers as $metadata ) {
			$this->assertTrue(
				$metadata->getType()->isCloud(),
				sprintf( 'Provider "%s" should be a cloud provider.', $metadata->getId() )
			);
		}
	}

	/**
	 * Test that register_settings creates the setting.
	 *
	 * @ticket TBD
	 */
	public function test_register_settings_creates_setting() {
		$manager = new WP_AI_Client_Credentials_Manager();
		$manager->register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey(
			WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS,
			$registered
		);

		// Clean up.
		unregister_setting( 'ai', WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS );
	}

	/**
	 * Test that register_settings does not register twice.
	 *
	 * @ticket TBD
	 */
	public function test_register_settings_idempotent() {
		$manager = new WP_AI_Client_Credentials_Manager();
		$manager->register_settings();
		$manager->register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey(
			WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS,
			$registered
		);

		// Clean up.
		unregister_setting( 'ai', WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS );
	}

	/**
	 * Test that sanitize_credentials filters out unknown providers.
	 *
	 * @ticket TBD
	 */
	public function test_sanitize_credentials_filters_unknown_providers() {
		global $wp_ai_client_providers_metadata;

		// Seed a cloud provider in the global.
		$wp_ai_client_providers_metadata = array(
			'test-cloud' => array(
				'id'                   => 'test-cloud',
				'name'                 => 'Test Cloud',
				'type'                 => 'cloud',
				'ai_client_classnames' => array( 'TestClient' => true ),
			),
		);

		$manager = new WP_AI_Client_Credentials_Manager();

		$input = array(
			'test-cloud'           => 'sk-valid-key',
			'nonexistent_provider' => 'sk-invalid-key',
		);

		$result = $manager->sanitize_credentials( $input );

		$this->assertArrayHasKey( 'test-cloud', $result );
		$this->assertArrayNotHasKey( 'nonexistent_provider', $result );
	}

	/**
	 * Test that sanitize_credentials applies sanitize_text_field.
	 *
	 * @ticket TBD
	 */
	public function test_sanitize_credentials_sanitizes_values() {
		global $wp_ai_client_providers_metadata;

		$wp_ai_client_providers_metadata = array(
			'test-cloud' => array(
				'id'                   => 'test-cloud',
				'name'                 => 'Test Cloud',
				'type'                 => 'cloud',
				'ai_client_classnames' => array( 'TestClient' => true ),
			),
		);

		$manager = new WP_AI_Client_Credentials_Manager();

		$input = array(
			'test-cloud' => "  sk-key-with-whitespace\t",
		);

		$result = $manager->sanitize_credentials( $input );

		$this->assertSame( 'sk-key-with-whitespace', $result['test-cloud'] );
	}

	/**
	 * Test that sanitize_credentials returns empty array for non-array input.
	 *
	 * @ticket TBD
	 */
	public function test_sanitize_credentials_returns_empty_for_non_array() {
		$manager = new WP_AI_Client_Credentials_Manager();

		$this->assertSame( array(), $manager->sanitize_credentials( 'not-an-array' ) );
		$this->assertSame( array(), $manager->sanitize_credentials( null ) );
	}

	/**
	 * Test that sanitize_credentials removes non-string values.
	 *
	 * @ticket TBD
	 */
	public function test_sanitize_credentials_removes_non_string_values() {
		global $wp_ai_client_providers_metadata;

		$wp_ai_client_providers_metadata = array(
			'test-cloud' => array(
				'id'                   => 'test-cloud',
				'name'                 => 'Test Cloud',
				'type'                 => 'cloud',
				'ai_client_classnames' => array( 'TestClient' => true ),
			),
		);

		$manager = new WP_AI_Client_Credentials_Manager();

		$input = array(
			'test-cloud' => array( 'not', 'a', 'string' ),
		);

		$result = $manager->sanitize_credentials( $input );

		$this->assertArrayNotHasKey( 'test-cloud', $result );
	}

	/**
	 * Test that sanitize_credentials filters out non-cloud providers.
	 *
	 * @ticket TBD
	 */
	public function test_sanitize_credentials_filters_non_cloud_providers() {
		global $wp_ai_client_providers_metadata;

		$wp_ai_client_providers_metadata = array(
			'test-cloud'  => array(
				'id'                   => 'test-cloud',
				'name'                 => 'Test Cloud',
				'type'                 => 'cloud',
				'ai_client_classnames' => array( 'TestClient' => true ),
			),
			'test-server' => array(
				'id'                   => 'test-server',
				'name'                 => 'Test Server',
				'type'                 => 'server',
				'ai_client_classnames' => array( 'TestClient' => true ),
			),
		);

		$manager = new WP_AI_Client_Credentials_Manager();

		$input = array(
			'test-cloud'  => 'sk-cloud-key',
			'test-server' => 'sk-server-key',
		);

		$result = $manager->sanitize_credentials( $input );

		$this->assertArrayHasKey( 'test-cloud', $result );
		$this->assertArrayNotHasKey( 'test-server', $result );
	}

	/**
	 * Test that pass_credentials_to_client skips providers not in the registry.
	 *
	 * @ticket TBD
	 */
	public function test_pass_credentials_to_client_skips_unregistered_providers() {
		$manager = new WP_AI_Client_Credentials_Manager();

		// Set credentials for a provider that doesn't exist in the SDK registry.
		update_option(
			WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS,
			array( 'nonexistent-provider' => 'sk-test-key' )
		);

		// This should not throw any errors.
		$manager->pass_credentials_to_client();

		// Verify by checking the registry doesn't have the provider.
		$registry = WordPress\AiClient\AiClient::defaultRegistry();
		$this->assertFalse( $registry->hasProvider( 'nonexistent-provider' ) );
	}

	/**
	 * Test that pass_credentials_to_client handles invalid option value gracefully.
	 *
	 * @ticket TBD
	 */
	public function test_pass_credentials_to_client_handles_invalid_option() {
		$manager = new WP_AI_Client_Credentials_Manager();

		// Set a non-array value for the option.
		update_option(
			WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS,
			'not-an-array'
		);

		// This should trigger _doing_it_wrong but not fatal.
		$this->setExpectedIncorrectUsage( 'WP_AI_Client_Credentials_Manager::pass_credentials_to_client' );
		$manager->pass_credentials_to_client();
	}

	/**
	 * Test that pass_credentials_to_client skips empty API keys.
	 *
	 * @ticket TBD
	 */
	public function test_pass_credentials_to_client_skips_empty_keys() {
		$manager = new WP_AI_Client_Credentials_Manager();

		// Set credentials with empty values for a non-existent provider.
		update_option(
			WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS,
			array( 'some-provider' => '' )
		);

		// Should not throw any errors - empty keys are silently skipped.
		$manager->pass_credentials_to_client();
		$this->assertTrue( true );
	}
}
