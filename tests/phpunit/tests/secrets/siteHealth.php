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
		$this->assertArrayHasKey( 'secrets-api', $info );
		$this->assertArrayHasKey( 'fields', $info['secrets-api'] );
		$this->assertIsArray( $info['secrets-api']['fields'] );

		$fields = array();

		foreach ( $info['secrets-api']['fields'] as $key => $field ) {
			$this->assertIsString( $key );
			$this->assertIsArray( $field );

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

		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( 'secrets_api_key_source', $tests['direct'] );
		$this->assertArrayHasKey( 'secrets_api_undecryptable', $tests['direct'] );
		$this->assertArrayHasKey( 'secrets_api_needs_rotation', $tests['direct'] );

		foreach ( $tests['direct'] as $key => $test ) {
			$this->assertIsString( $test['test'] );
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

		$this->assertArrayHasKey( 'existing_test', $tests['direct'] );
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

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'WP_SECRETS_KEY', $result['description'] );
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

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'generate-key', $result['description'] );
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

		$this->assertSame( 'good', $result['status'] );
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

		$this->assertSame( 'critical', $result['status'] );
	}

	/**
	 * Ensure that the undecryptable check passes when every stored secret decrypts.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_good_when_nothing_is_broken(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Ensure that the undecryptable check passes when no secrets are stored.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_good_with_no_secrets_at_all(): void {
		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Ensure that the undecryptable check is critical and names the failing secret and its fingerprint.
	 *
	 * The stored ciphertext is overwritten to simulate a record that no longer decrypts.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_is_critical_and_lists_the_broken_secret(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );
		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$fingerprint = $secret->fingerprint();

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );

		$record['current']['ct'] = base64_encode( 'not decryptable' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'myplugin/api-key', $result['description'] );
		$this->assertStringContainsString( $fingerprint, $result['description'] );
	}

	/**
	 * Ensure that the undecryptable check's description never includes a secret's plaintext value.
	 *
	 * @covers ::wp_secrets_site_health_test_undecryptable
	 */
	public function test_undecryptable_test_never_contains_a_plaintext(): void {
		wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );

		$record['current']['ct'] = base64_encode( 'not decryptable' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_secrets_site_health_test_undecryptable();

		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $result['description'] );
	}

	/**
	 * Ensure that the rotation check passes for a secret stored directly with wp_set_secret().
	 *
	 * @covers ::wp_secrets_site_health_test_needs_rotation
	 */
	public function test_needs_rotation_test_is_good_by_default(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$result = wp_secrets_site_health_test_needs_rotation();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Ensure that the rotation check recommends rotating a secret imported from a plain option.
	 *
	 * @covers ::wp_secrets_site_health_test_needs_rotation
	 */
	public function test_needs_rotation_test_is_recommended_after_an_import(): void {
		update_option( 'my_plugin_api_key', 'value' );
		wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' );

		$result = wp_secrets_site_health_test_needs_rotation();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( '1 secret was imported', $result['description'], 'The description should report the number of secrets flagged for rotation.' );
	}

	/**
	 * Ensure that the debug information reports the provider, keyring, record version and secret count.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_reports_expected_fields(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$info   = wp_secrets_site_health_debug_info( array() );
		$fields = $this->debug_fields( $info );

		$this->assertArrayHasKey( 'dropin_active', $fields );
		$this->assertArrayHasKey( 'provider_class', $fields );
		$this->assertArrayHasKey( 'keyring_class', $fields );
		$this->assertArrayHasKey( 'key_source', $fields );
		$this->assertArrayHasKey( 'record_version', $fields );
		$this->assertArrayHasKey( 'site_secret_count', $fields );

		$this->assertSame( 'No', $fields['dropin_active']['value'], 'No drop-in is installed in the test environment.' );
		$this->assertSame( 'WP_Secrets_Libsodium_Provider', $fields['provider_class']['value'] );
		$this->assertSame( 'WP_Secrets_Config_Key_Provider', $fields['keyring_class']['value'] );
		$this->assertSame( ( new WP_Secrets_Config_Key_Provider() )->get_key_source(), $fields['key_source']['value'], 'The key source should be reported from the keyring in use.' );
		$this->assertSame( '1', $fields['record_version']['value'] );
		$this->assertSame( '1', $fields['site_secret_count']['value'] );
	}

	/**
	 * Ensure that the debug information reports no active drop-in when none is installed.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_reports_no_dropin_by_default(): void {
		$info = wp_secrets_site_health_debug_info( array() );

		$fields = $this->debug_fields( $info );

		$this->assertArrayHasKey( 'dropin_active', $fields );
		$this->assertSame( 'No', $fields['dropin_active']['value'] );
	}

	/**
	 * Ensure that the debug information never includes a secret's plaintext value.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_never_contains_a_plaintext(): void {
		wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' );

		$info = wp_secrets_site_health_debug_info( array() );

		$dump = wp_json_encode( $info['secrets-api'] );

		$this->assertIsString( $dump );
		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $dump );
	}

	/**
	 * Ensure that the debug information omits network secret counts on a single site install.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_omits_network_fields_on_single_site(): void {
		$info = wp_secrets_site_health_debug_info( array() );

		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ) );
	}

	/**
	 * Network secret counts appear only for a super admin on a multisite install –
	 * never for a site administrator who is not one.
	 *
	 * @covers ::wp_secrets_site_health_debug_info
	 */
	public function test_debug_info_includes_network_fields_only_for_a_super_admin(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$info = wp_secrets_site_health_debug_info( array() );
		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ), 'A non-super-admin must not see network secret counts.' );

		grant_super_admin( $user_id );

		$info = wp_secrets_site_health_debug_info( array() );
		$this->assertArrayHasKey( 'network_secret_count', $this->debug_fields( $info ) );
	}
}
