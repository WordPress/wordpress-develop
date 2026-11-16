<?php
/**
 * Tests for the Secrets API checks in WP_Site_Health and the Secrets API section of WP_Debug_Data.
 *
 * @ticket 66187
 *
 * @group secrets
 * @group site-health
 */
class Tests_Secrets_SiteHealth extends WP_UnitTestCase {

	/**
	 * Loads the Site Health and debug data classes.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
	}

	/**
	 * Returns the Secrets API section of the Site Health debug information.
	 *
	 * @return array<string, mixed> The section.
	 */
	private function debug_section(): array {
		$method = new ReflectionMethod( 'WP_Debug_Data', 'get_wp_secrets_api' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null );
	}

	/**
	 * Returns the Secrets API section's fields from Site Health debug information,
	 * having checked that the section and each of its fields has the expected shape.
	 *
	 * @param array<string, mixed> $section The Secrets API section of the debug information.
	 * @return array<string, array<mixed>> The section's fields, keyed by field name.
	 */
	private function debug_fields( array $section ): array {
		$this->assertArrayHasKey( 'fields', $section, 'The Secrets API section should include a fields entry.' );
		$this->assertIsArray( $section['fields'], 'The Secrets API fields entry should be an array.' );

		$fields = array();

		foreach ( $section['fields'] as $key => $field ) {
			$this->assertIsString( $key, 'Each debug field key should be a string.' );
			$this->assertIsArray( $field, 'Each debug field should be an array.' );

			$fields[ $key ] = $field;
		}

		return $fields;
	}

	/**
	 * Ensure that the three Secrets API checks are registered as direct tests with existing methods.
	 *
	 * @covers WP_Site_Health::get_tests
	 */
	public function test_registers_three_direct_tests(): void {
		$tests = WP_Site_Health::get_tests();

		foreach ( array( 'secrets_api_key_source', 'secrets_api_undecryptable', 'secrets_api_needs_rotation' ) as $key ) {
			$this->assertArrayHasKey( $key, $tests['direct'], "The {$key} test should be registered as a direct test." );
			$this->assertSame( $key, $tests['direct'][ $key ]['test'], "The {$key} test should name its own method." );
			$this->assertTrue( method_exists( 'WP_Site_Health', "get_test_{$key}" ), "WP_Site_Health::get_test_{$key}() should exist." );
		}
	}

	/**
	 * Ensure that the key source check offers a generated define line when WP_SECRETS_KEY is not defined.
	 *
	 * Runs in a separate process, where the test bootstrap does not define WP_SECRETS_KEY.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_offers_a_generated_key_when_none_is_defined(): void {
		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'recommended', $result['status'], 'The key source status should be recommended without WP_SECRETS_KEY.' );
		$this->assertStringContainsString( 'Add this line', $result['description'], 'The description should ask for the line to be added.' );
		$this->assertMatchesRegularExpression( '/define\( &#039;WP_SECRETS_KEY&#039;, &#039;([A-Za-z0-9+\/]{43}=)&#039; \);/', $result['description'], 'The description should contain a define line with a base64-encoded 32-byte key.' );
	}

	/**
	 * Ensure that the key source check offers a replacement line when WP_SECRETS_KEY is the placeholder.
	 *
	 * Runs in a separate process because WP_SECRETS_KEY is a constant and cannot be
	 * undefined for later tests.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_offers_a_replacement_for_the_placeholder(): void {
		define( 'WP_SECRETS_KEY', 'put your unique phrase here' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'recommended', $result['status'], 'The key source status should be recommended for the placeholder.' );
		$this->assertStringContainsString( 'Replace the <code>WP_SECRETS_KEY</code> line', $result['description'], 'The description should ask for the line to be replaced.' );
	}

	/**
	 * Ensure that the key source check still detects a missing key on a translated site.
	 *
	 * The key source text is translated, so the check must not depend on it.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_offers_a_generated_key_on_a_translated_site(): void {
		add_filter(
			'gettext',
			static function ( $translation, $text ) {
				return '%s is not defined' === $text ? '%s ist nicht definiert' : $translation;
			},
			10,
			2
		);

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'recommended', $result['status'], 'The key source status should be recommended without WP_SECRETS_KEY on a translated site.' );
		$this->assertStringContainsString( 'Add this line', $result['description'], 'The description should ask for the line to be added on a translated site.' );
	}

	/**
	 * Ensure that the key source check says a legacy-form WP_SECRETS_KEY can still be replaced before secrets exist.
	 *
	 * Runs in a separate process because WP_SECRETS_KEY is a constant and cannot be
	 * undefined for later tests.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_recommends_regeneration_for_a_legacy_key(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'recommended', $result['status'], 'A legacy WP_SECRETS_KEY should produce a recommended status.' );
		$this->assertStringContainsString( 'can still be replaced', $result['description'], 'The description should say that the key can still be replaced.' );
		$this->assertStringNotContainsString( 'generate-key', $result['description'], 'The description should not mention generate-key, which is not a core or WP-CLI command.' );
	}

	/**
	 * Ensure that the key source check says to keep a legacy-form WP_SECRETS_KEY once secrets are stored.
	 *
	 * Replacing the key then would make the stored secrets unreadable.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_warns_to_keep_a_legacy_key_once_secrets_are_stored(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'recommended', $result['status'], 'A legacy WP_SECRETS_KEY with stored secrets should produce a recommended status.' );
		$this->assertStringContainsString( 'Keep its current value', $result['description'], 'The description should say to keep the current value.' );
	}

	/**
	 * Ensure that the key source check is critical when the key cannot unwrap the stored root key.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 */
	public function test_key_source_test_is_critical_when_the_key_does_not_match_the_stored_secrets(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		// Stands in for a root key that was wrapped with a different site key.
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, base64_encode( random_bytes( 72 ) ) );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'critical', $result['status'], 'A key that cannot unwrap the root key should produce a critical status.' );
		$this->assertStringContainsString( 'Restoring the previous value', $result['description'], 'The description should say that restoring the previous value fixes it.' );
	}

	/**
	 * Ensure that the key source check is critical when WP_SECRETS_KEY was removed after secrets were stored.
	 *
	 * The root key is wrapped with a keyring that reads WP_SECRETS_KEY_PREVIOUS,
	 * because WP_SECRETS_KEY can only be defined once in a process.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_critical_when_the_key_was_removed_after_secrets_were_stored(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'P', 32 ) ) );

		$previous = new WP_Secrets_Config_Key_Provider( true );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $previous->wrap( random_bytes( 32 ) ) );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'critical', $result['status'], 'A removed WP_SECRETS_KEY with stored secrets should produce a critical status.' );
		$this->assertStringContainsString( '<code>WP_SECRETS_KEY</code> was changed or removed', $result['description'], 'The description should say that WP_SECRETS_KEY was changed or removed.' );
	}

	/**
	 * Ensure that the key source check reports the error of a drop-in keyring that cannot unwrap the root key.
	 *
	 * A keyring from a secrets.php drop-in fails for its own reasons, such as an
	 * unreachable key service. The result reports its error, and does not send
	 * the site owner to wp-config.php.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_reports_the_error_of_a_dropin_keyring_that_cannot_unwrap(): void {
		$keyring = new Mock_Keyring();
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $keyring->wrap( random_bytes( 32 ) ) );
		$keyring->configure_fail_unwrap();

		$GLOBALS['wp_secrets_keyring'] = $keyring;

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'critical', $result['status'], 'A drop-in keyring that cannot unwrap the root key should produce a critical status.' );
		$this->assertStringContainsString( 'unwrap() configured to fail', $result['description'], 'The description should contain the keyring\'s own error.' );
		$this->assertStringNotContainsString( 'WP_SECRETS_KEY', $result['description'], 'The description should not mention WP_SECRETS_KEY for a drop-in keyring.' );
	}

	/**
	 * Ensure that the key source check passes for a base64-encoded 32-byte WP_SECRETS_KEY.
	 *
	 * Runs in a separate process because WP_SECRETS_KEY is a constant and cannot be
	 * undefined for later tests.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_good_for_a_dedicated_base64_key(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'good', $result['status'], 'A base64-encoded 32-byte WP_SECRETS_KEY should produce a good status.' );
	}

	/**
	 * Ensure that the key source check is critical when the secrets drop-in failed to load.
	 *
	 * Runs in a separate process because the key manager is cached in a static variable
	 * the first time it is requested, so the broken flag must be set before then.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_critical_when_the_keyring_is_broken(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'critical', $result['status'], 'A broken secrets drop-in should produce a critical status.' );
	}

	/**
	 * Ensure that the key source check is critical for a broken drop-in on a translated site.
	 *
	 * The broken keyring describes itself with a translated string.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_critical_when_the_keyring_is_broken_on_a_translated_site(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		add_filter(
			'gettext',
			static function ( $translation, $text ) {
				return 'broken secrets.php drop-in' === $text ? 'defektes secrets.php-Drop-in' : $translation;
			},
			10,
			2
		);

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'critical', $result['status'], 'A broken secrets drop-in should produce a critical status on a translated site.' );
	}

	/**
	 * Ensure that the key source check passes when a drop-in replaces the provider.
	 *
	 * A provider from a secrets.php drop-in does not use the default keyring.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_source_test_is_good_when_a_dropin_replaces_the_provider(): void {
		$provider = $this->createMock( WP_Secrets_Provider::class );
		$provider->method( 'get_label' )->willReturn( 'Example Vault' );

		$GLOBALS['wp_secrets_provider'] = $provider;

		$result = ( new WP_Site_Health() )->get_test_secrets_api_key_source();

		$this->assertSame( 'good', $result['status'], 'A provider drop-in should produce a good status.' );
		$this->assertStringContainsString( 'Example Vault', $result['description'], 'The description should name the provider.' );
	}

	/**
	 * Ensure that the undecryptable check is critical when the secrets cannot be listed.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_undecryptable
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_undecryptable_test_is_critical_when_the_secrets_cannot_be_listed(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$result = ( new WP_Site_Health() )->get_test_secrets_api_undecryptable();

		$this->assertSame( 'critical', $result['status'], 'A provider that cannot list secrets should produce a critical status.' );
	}

	/**
	 * Ensure that the rotation check is recommended when the secrets cannot be listed.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_needs_rotation
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_needs_rotation_test_is_recommended_when_the_secrets_cannot_be_listed(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$result = ( new WP_Site_Health() )->get_test_secrets_api_needs_rotation();

		$this->assertSame( 'recommended', $result['status'], 'A provider that cannot list secrets should produce a recommended status.' );
	}

	/**
	 * Ensure that the undecryptable check passes when every stored secret decrypts.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_undecryptable
	 */
	public function test_undecryptable_test_is_good_when_nothing_is_broken(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_undecryptable();

		$this->assertSame( 'good', $result['status'], 'The status should be good when every secret decrypts.' );
	}

	/**
	 * Ensure that the undecryptable check passes when no secrets are stored.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_undecryptable
	 */
	public function test_undecryptable_test_is_good_with_no_secrets_at_all(): void {
		$result = ( new WP_Site_Health() )->get_test_secrets_api_undecryptable();

		$this->assertSame( 'good', $result['status'], 'The status should be good when no secrets are stored.' );
	}

	/**
	 * Ensure that the undecryptable check is critical and names the failing secret and its fingerprint.
	 *
	 * The stored ciphertext is overwritten to simulate a record that no longer decrypts.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_undecryptable
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

		$result = ( new WP_Site_Health() )->get_test_secrets_api_undecryptable();

		$this->assertSame( 'critical', $result['status'], 'An undecryptable secret should produce a critical status.' );
		$this->assertStringContainsString( 'myplugin/api-key', $result['description'], 'The description should name the undecryptable secret.' );
		$this->assertStringContainsString( $fingerprint, $result['description'], 'The description should include the fingerprint of the undecryptable secret.' );
	}

	/**
	 * Ensure that the undecryptable check's description never includes a secret's plaintext value.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_undecryptable
	 */
	public function test_undecryptable_test_never_contains_a_plaintext(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		$record['current']['ct'] = base64_encode( 'not decryptable' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_undecryptable();

		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $result['description'], 'The description should never contain the plaintext value.' );
	}

	/**
	 * Ensure that the rotation check passes for a secret stored directly with wp_set_secret().
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_needs_rotation
	 */
	public function test_needs_rotation_test_is_good_by_default(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_needs_rotation();

		$this->assertSame( 'good', $result['status'], 'A directly stored secret should not need rotation.' );
	}

	/**
	 * Ensure that the rotation check recommends rotating a secret imported from a plain option.
	 *
	 * @covers WP_Site_Health::get_test_secrets_api_needs_rotation
	 */
	public function test_needs_rotation_test_is_recommended_after_an_import(): void {
		update_option( 'my_plugin_api_key', 'value' );
		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$result = ( new WP_Site_Health() )->get_test_secrets_api_needs_rotation();

		$this->assertSame( 'recommended', $result['status'], 'An imported secret should produce a recommended status.' );
		$this->assertStringContainsString( '1 secret was imported', $result['description'], 'The description should report the number of secrets flagged for rotation.' );
	}

	/**
	 * Ensure that the debug information reports the provider, keyring, record version and secret count.
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 */
	public function test_debug_info_reports_expected_fields(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$info   = $this->debug_section();
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
	 * Ensure that the debug information does not describe the keyring when a drop-in replaces the provider.
	 *
	 * A provider from a secrets.php drop-in does not use the default keyring.
	 *
	 * @ticket 66380
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_debug_info_does_not_describe_the_keyring_when_a_dropin_replaces_the_provider(): void {
		$provider = $this->createMock( WP_Secrets_Provider::class );
		$provider->method( 'get_label' )->willReturn( 'Example Vault' );
		$provider->method( 'list_secrets' )->willReturn( array() );

		$GLOBALS['wp_secrets_provider'] = $provider;

		$fields = $this->debug_fields( $this->debug_section() );

		$this->assertSame( 'Not used by the provider', $fields['keyring_class']['value'], 'The keyring class should be marked as not used.' );
		$this->assertSame( 'Not used by the provider', $fields['key_source']['value'], 'The key source should be marked as not used.' );
	}

	/**
	 * Ensure that the debug information reports no active drop-in when none is installed.
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 */
	public function test_debug_info_reports_no_dropin_by_default(): void {
		$info = $this->debug_section();

		$fields = $this->debug_fields( $info );

		$this->assertArrayHasKey( 'dropin_active', $fields, 'The debug fields should include dropin_active.' );
		$this->assertSame( 'No', $fields['dropin_active']['value'], 'The drop-in should be reported as inactive by default.' );
	}

	/**
	 * Ensure that the debug information never includes a secret's plaintext value.
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 */
	public function test_debug_info_never_contains_a_plaintext(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$info = $this->debug_section();

		$dump = wp_json_encode( $info );

		$this->assertIsString( $dump, 'The Secrets API section should encode to a JSON string.' );
		$this->assertStringNotContainsString( 'UNIQUE-PLAINTEXT-CANARY-9f3a', $dump, 'The debug information should never contain the plaintext value.' );
	}

	/**
	 * Ensure that the debug information omits network secret counts on a single site install.
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 */
	public function test_debug_info_omits_network_fields_on_single_site(): void {
		$info = $this->debug_section();

		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ), 'Network secret counts should be omitted on a single site install.' );
	}

	/**
	 * Network secret counts appear only for a super admin on a multisite install –
	 * never for a site administrator who is not one.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Debug_Data::get_wp_secrets_api
	 */
	public function test_debug_info_includes_network_fields_only_for_a_super_admin(): void {
		$this->skipWithoutMultisite();

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$info = $this->debug_section();
		$this->assertArrayNotHasKey( 'network_secret_count', $this->debug_fields( $info ), 'A non-super-admin should not see network secret counts.' );

		grant_super_admin( $user_id );

		$info = $this->debug_section();
		$this->assertArrayHasKey( 'network_secret_count', $this->debug_fields( $info ), 'A super admin should see network secret counts.' );
	}
}
