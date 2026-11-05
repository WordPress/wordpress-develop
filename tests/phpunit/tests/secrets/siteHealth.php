<?php
/**
 * Tests for the Site Health integration in src/wp-admin/includes/secrets-site-health.php.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_SiteHealth extends WP_UnitTestCase {

	/**
	 * Returns the Secrets API section's fields from Site Health debug information,
	 * having checked that the section and each of its fields has the expected shape.
	 *
	 * @param array<string, array<string, mixed>> $info Debug information sections.
	 * @return array<string, array<mixed>> The section's fields, keyed by field name.
	 */
	private function debug_fields( array $info ): array {
		$this->assertArrayHasKey( 'secrets-api', $info, 'The debug information should include a secrets-api section.' );
		$this->assertArrayHasKey( 'fields', $info['secrets-api'], 'The secrets-api section should include a fields entry.' );
		$this->assertIsArray( $info['secrets-api']['fields'], 'The secrets-api fields entry should be an array.' );

		$fields = array();

		foreach ( $info['secrets-api']['fields'] as $key => $field ) {
			$this->assertIsString( $key, 'Each debug field key should be a string.' );
			$this->assertIsArray( $field, 'Each debug field should be an array.' );

			$fields[ $key ] = $field;
		}

		return $fields;
	}

	/**
	 * Ensure that the three Secrets API checks are registered as direct tests with existing callbacks.
	 *
	 * @covers ::wp_secrets_site_health_tests
	 */
	public function test_registers_three_tests_under_direct(): void {
		$tests = wp_secrets_site_health_tests( array() );

		$this->assertArrayHasKey( 'direct', $tests, 'The registered tests should include a direct group.' );
		$this->assertCount( 3, $tests['direct'], 'Exactly three Secrets API tests should be registered as direct tests.' );
		$this->assertArrayHasKey( 'secrets_api_key_source', $tests['direct'], 'The key source test should be registered as a direct test.' );
		$this->assertArrayHasKey( 'secrets_api_undecryptable', $tests['direct'], 'The undecryptable test should be registered as a direct test.' );
		$this->assertArrayHasKey( 'secrets_api_needs_rotation', $tests['direct'], 'The needs rotation test should be registered as a direct test.' );

		foreach ( $tests['direct'] as $key => $test ) {
			$this->assertIsString( $test['test'], 'Each registered test callback should be a function name string.' );
			$this->assertTrue( function_exists( $test['test'] ), "Registered test callback \"{$test['test']}\" for \"{$key}\" does not exist." );
		}
	}

	/**
	 * Ensure that wp_secrets_site_health_tests() keeps tests that were already registered.
	 *
	 * @covers ::wp_secrets_site_health_tests
	 */
	public function test_preserves_existing_tests(): void {
		$tests = wp_secrets_site_health_tests( array( 'direct' => array( 'existing_test' => array( 'label' => 'x' ) ) ) );

		$this->assertArrayHasKey( 'existing_test', $tests['direct'], 'A previously registered test should be preserved.' );
	}

	/**
	 * Ensure that the key source check recommends defining WP_SECRETS_KEY when it is not set.
	 *
	 * @covers ::wp_secrets_site_health_test_key_source
	 */
	public function test_key_source_test_recommends_a_dedicated_key_by_default(): void {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		$result = wp_secrets_site_health_test_key_source();

		$this->assertSame( 'recommended', $result['status'], 'The key source status should be recommended without WP_SECRETS_KEY.' );
		$this->assertStringContainsString( 'WP_SECRETS_KEY', $result['description'], 'The description should recommend defining WP_SECRETS_KEY.' );
	}

	/**
	 * Ensure that the key source check recommends regenerating a WP_SECRETS_KEY not in base64 form.
	 *
	 * Runs in a separate process because WP_SECRETS_KEY is a constant and cannot be
	 * undefined for later tests.
	 *
	 * @covers ::wp_secrets_site_health_test_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_recommends_regeneration_for_a_legacy_key(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$result = wp_secrets_site_health_test_key_source();

		$this->assertSame( 'recommended', $result['status'], 'A legacy WP_SECRETS_KEY should produce a recommended status.' );
		$this->assertStringContainsString( 'generate-key', $result['description'], 'The description should mention generate-key for regenerating the key.' );
	}

	/**
	 * Ensure that the key source check passes for a base64-encoded 32-byte WP_SECRETS_KEY.
	 *
	 * Runs in a separate process because WP_SECRETS_KEY is a constant and cannot be
	 * undefined for later tests.
	 *
	 * @covers ::wp_secrets_site_health_test_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_good_for_a_dedicated_base64_key(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$result = wp_secrets_site_health_test_key_source();

		$this->assertSame( 'good', $result['status'], 'A base64-encoded 32-byte WP_SECRETS_KEY should produce a good status.' );
	}

	/**
	 * Ensure that the key source check is critical when the secrets drop-in failed to load.
	 *
	 * Runs in a separate process because the key manager is cached in a static variable
	 * the first time it is requested, so the broken flag must be set before then.
	 *
	 * @covers ::wp_secrets_site_health_test_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_critical_when_the_keyring_is_broken(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$result = wp_secrets_site_health_test_key_source();

		$this->assertSame( 'critical', $result['status'], 'A broken secrets drop-in should produce a critical status.' );
	}

	/**
	 * Ensure that the undecryptable check passes when every stored secret decrypts.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_good_when_nothing_is_broken(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'good', $result['status'], 'The status should be good when every secret decrypts.' );
	}

	/**
	 * Ensure that the undecryptable check passes when no secrets are stored.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_good_with_no_secrets_at_all(): void {
		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'good', $result['status'], 'The status should be good when no secrets are stored.' );
	}

	/**
	 * Ensure that the undecryptable check is critical and names the failing secret and its fingerprint.
	 *
	 * The stored ciphertext is overwritten to simulate a record that no longer decrypts.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_critical_and_lists_the_broken_secret(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );
		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The stored secret should be retrievable as a WP_Secret.' );
		$fingerprint = $secret->fingerprint();

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		$record['current']['ct'] = base64_encode( 'not decryptable' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'critical', $result['status'], 'An undecryptable secret should produce a critical status.' );
		$this->assertStringContainsString( 'myplugin/api-key', $result['description'], 'The description should name the undecryptable secret.' );
		$this->assertStringContainsString( $fingerprint, $result['description'], 'The description should include the fingerprint of the undecryptable secret.' );
	}

	/**
	 * Ensure that the undecryptable check's description never includes a secret's plaintext value.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_never_contains_a_plaintext(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		$record['current']['ct'] = base64_encode( 'not decryptable' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $result['description'], 'The description should never contain the plaintext value.' );
	}

	/**
	 * Ensure that the rotation check passes for a secret stored directly with wp_set_secret().
	 *
	 * @covers ::wp_secrets_site_health_test_needs_rotation
	 */
	public function test_needs_rotation_test_is_good_by_default(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = wp_secrets_site_health_test_needs_rotation();

		$this->assertSame( 'good', $result['status'], 'A directly stored secret should not need rotation.' );
	}

	/**
	 * Ensure that the rotation check recommends rotating a secret imported from a plain option.
	 *
	 * @covers ::wp_secrets_site_health_test_needs_rotation
	 */
	public function test_needs_rotation_test_is_recommended_after_an_import(): void {
		update_option( 'my_plugin_api_key', 'value' );
		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$result = wp_secrets_site_health_test_needs_rotation();

		$this->assertSame( 'recommended', $result['status'], 'An imported secret should produce a recommended status.' );
		$this->assertStringContainsString( '1 secret was imported', $result['description'], 'The description should report the number of secrets flagged for rotation.' );
	}

	/**
	 * Ensure that the debug information reports the provider, keyring, record version and secret count.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_reports_expected_fields(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$info   = wp_secrets_site_health_debug_info( array() );
		$fields = $this->debug_fields( $info );

		$this->assertArrayHasKey( 'dropin_active', $fields, 'The debug fields should include dropin_active.' );
		$this->assertArrayHasKey( 'provider_class', $fields, 'The debug fields should include provider_class.' );
		$this->assertArrayHasKey( 'keyring_class', $fields, 'The debug fields should include keyring_class.' );
		$this->assertArrayHasKey( 'key_source', $fields, 'The debug fields should include key_source.' );
		$this->assertArrayHasKey( 'record_version', $fields, 'The debug fields should include record_version.' );
		$this->assertArrayHasKey( 'site_secret_count', $fields, 'The debug fields should include site_secret_count.' );

		$this->assertSame( 'No', $fields['dropin_active']['value'], 'No drop-in is installed in the test environment.' );
		$this->assertSame( 'WP_Secrets_Libsodium_Provider', $fields['provider_class']['value'], 'The provider class should be WP_Secrets_Libsodium_Provider.' );
		$this->assertSame( 'WP_Secrets_Config_Key_Provider', $fields['keyring_class']['value'], 'The keyring class should be WP_Secrets_Config_Key_Provider.' );
		$this->assertSame( ( new WP_Secrets_Config_Key_Provider() )->get_key_source(), $fields['key_source']['value'], 'The key source should be reported from the keyring in use.' );
		$this->assertSame( '1', $fields['record_version']['value'], 'The record version should be reported as 1.' );
		$this->assertSame( '1', $fields['site_secret_count']['value'], 'The site secret count should be 1.' );
	}

	/**
	 * Ensure that the debug information reports no active drop-in when none is installed.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_reports_no_dropin_by_default(): void {
		$info = wp_secrets_site_health_debug_info( array() );

		$fields = $this->debug_fields( $info );

		$this->assertArrayHasKey( 'dropin_active', $fields, 'The debug fields should include dropin_active.' );
		$this->assertSame( 'No', $fields['dropin_active']['value'], 'The drop-in should be reported as inactive by default.' );
	}

	/**
	 * Ensure that the debug information never includes a secret's plaintext value.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_never_contains_a_plaintext(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$info = wp_secrets_site_health_debug_info( array() );

		$dump = wp_json_encode( $info['secrets-api'] );

		$this->assertIsString( $dump, 'The secrets-api section should encode to a JSON string.' );
		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $dump, 'The debug information should never contain the plaintext value.' );
	}

	/**
	 * Ensure that the debug information omits network secret counts on a single site install.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_omits_network_fields_on_single_site(): void {
		$info = wp_secrets_site_health_debug_info( array() );

		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ), 'Network secret counts should be omitted on a single site install.' );
	}

	/**
	 * Network secret counts appear only for a super admin on a multisite install –
	 * never for a site administrator who is not one.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_includes_network_fields_only_for_a_super_admin(): void {
		$this->skipWithoutMultisite();

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$info = wp_secrets_site_health_debug_info( array() );
		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ), 'A non-super-admin should not see network secret counts.' );

		grant_super_admin( $user_id );

		$info = wp_secrets_site_health_debug_info( array() );
		$this->assertArrayHasKey( 'network_secret_count', $this->debug_fields( $info ), 'A super admin should see network secret counts.' );
	}
}
