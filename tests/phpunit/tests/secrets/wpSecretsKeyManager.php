<?php
/**
 * Tests for WP_Secrets_Key_Manager.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsKeyManager extends WP_UnitTestCase {

	/**
	 * Ensure that the first call to get a root key generates 32 bytes and stores the wrapped value.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_get_root_key_generates_and_persists_one(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'No root key should be stored before the first get_root_key() call.' );

		$root_key = $manager->get_root_key();

		$this->assertIsString( $root_key, 'get_root_key() should return a string.' );
		$this->assertSame( 32, strlen( $root_key ), 'The generated root key should be 32 bytes long.' );
		$this->assertNotFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'The wrapped root key should be stored after the first get_root_key() call.' );
	}

	/**
	 * Ensure that repeated root key reads on one key manager return the same bytes.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_get_root_key_is_stable_on_the_same_instance(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame( $manager->get_root_key(), $manager->get_root_key(), 'Repeated get_root_key() calls on one instance should return the same bytes.' );
	}

	/**
	 * Ensure that a new key manager reads the stored root key rather than generating another.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_get_root_key_persists_across_instances(): void {
		$first  = new WP_Secrets_Key_Manager();
		$root_a = $first->get_root_key();

		$second = new WP_Secrets_Key_Manager();
		$root_b = $second->get_root_key();

		$this->assertSame( $root_a, $root_b, 'A second key manager should return the same root key as the first.' );
	}

	/**
	 * Ensure that a pre-existing wrapped root key is unwrapped and returned as is.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_get_root_key_uses_an_existing_value_rather_than_regenerating(): void {
		$keyring = new WP_Secrets_Config_Key_Provider();
		$known   = str_repeat( 'R', 32 );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $keyring->wrap( $known ) );

		$manager = new WP_Secrets_Key_Manager( $keyring );

		$this->assertSame( $known, $manager->get_root_key(), 'get_root_key() should return the pre-existing root key unchanged.' );
	}

	/**
	 * Ensure that the site master key is the KDF subkey of the root key for the current blog.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_matches_manual_derivation(): void {
		$manager  = new WP_Secrets_Key_Manager();
		$root_key = $manager->get_root_key();
		$this->assertIsString( $root_key, 'get_root_key() should return a string.' );

		$expected = sodium_crypto_kdf_derive_from_key( 32, get_current_blog_id(), 'wpsecsit', $root_key );

		$this->assertSame( $expected, $manager->get_master_key( 'site' ), 'The site master key should match a manual KDF derivation from the root key.' );
	}

	/**
	 * Ensure that site-scope master keys differ between site IDs.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_differs_per_site_id(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertNotSame(
			$manager->get_master_key( 'site', 5 ),
			$manager->get_master_key( 'site', 6 ),
			'Site-scope master keys for different site IDs should differ.'
		);
	}

	/**
	 * Ensure that the network master key differs from the master key for site ID 1.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_site_and_network_scope_differ(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertNotSame(
			$manager->get_master_key( 'site', 1 ),
			$manager->get_master_key( 'network' ),
			'The network master key should differ from the site master key for site ID 1.'
		);
	}

	/**
	 * Ensure that the network master key is identical across repeated derivations.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_network_scope_is_stable(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame(
			$manager->get_master_key( 'network' ),
			$manager->get_master_key( 'network' ),
			'Repeated network master key derivations should return the same bytes.'
		);
	}

	/**
	 * Ensure that a site-scope master key without a site ID is derived for the current blog.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_defaults_site_id_to_the_current_blog(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame(
			$manager->get_master_key( 'site' ),
			$manager->get_master_key( 'site', get_current_blog_id() ),
			'A site master key without a site ID should match the one for the current blog.'
		);
	}

	/**
	 * Ensure that an unknown scope triggers _doing_it_wrong() and returns an invalid argument error.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_get_master_key_reports_an_invalid_scope_as_a_wp_error(): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Key_Manager::get_master_key' );

		$result = $manager->get_master_key( 'bogus' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'An invalid scope should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid scope should return the invalid argument error code.' );
	}

	/**
	 * Ensure that a site ID below 1 triggers _doing_it_wrong() and returns an invalid argument error.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 *
	 * @dataProvider data_get_master_key_reports_an_invalid_site_id_as_a_wp_error
	 *
	 * @param int $site_id A value that is not a usable blog id.
	 */
	public function test_get_master_key_reports_an_invalid_site_id_as_a_wp_error( $site_id ): void {
		$manager = new WP_Secrets_Key_Manager();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Key_Manager::get_master_key' );

		$result = $manager->get_master_key( 'site', $site_id );

		$this->assertWPError( $result, 'An invalid site ID should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid site ID should return the invalid argument error code.' );
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function data_get_master_key_reports_an_invalid_site_id_as_a_wp_error(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -1 ),
		);
	}

	/**
	 * The root key is shared network-wide (one row via update_site_option()), so a
	 * master key derived on one blog must be derivable identically on another –
	 * this is the entire reason network secrets can be read from any blog. Requires
	 * the multisite suite; skips under single-site since there is only one blog to
	 * compare against.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_network_scope_master_key_is_identical_across_blogs(): void {
		$this->skipWithoutMultisite();

		$second_blog_id = self::factory()->blog->create();

		$manager               = new WP_Secrets_Key_Manager();
		$network_key_on_blog_1 = $manager->get_master_key( 'network' );

		switch_to_blog( $second_blog_id );
		$network_key_on_blog_2 = ( new WP_Secrets_Key_Manager() )->get_master_key( 'network' );
		restore_current_blog();

		$this->assertSame( $network_key_on_blog_1, $network_key_on_blog_2, 'The network master key should be identical on both blogs.' );
	}

	/**
	 * Site-scope master keys, by contrast, must differ per blog even though every
	 * blog derives from the same shared root key.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_site_scope_master_key_differs_across_blogs(): void {
		$this->skipWithoutMultisite();

		$second_blog_id = self::factory()->blog->create();

		$manager            = new WP_Secrets_Key_Manager();
		$site_key_on_blog_1 = $manager->get_master_key( 'site' );

		switch_to_blog( $second_blog_id );
		$site_key_on_blog_2 = ( new WP_Secrets_Key_Manager() )->get_master_key( 'site' );
		restore_current_blog();

		$this->assertNotSame( $site_key_on_blog_1, $site_key_on_blog_2, 'The site master key should differ between blogs.' );
	}

	/**
	 * Simulates single-site-to-multisite conversion: moves the wrapped root key
	 * from its main-site option row into sitemeta, the way a fresh
	 * WP_Secrets_Key_Manager finds it after `wp core multisite-convert`.
	 *
	 * Requires the multisite suite; skips under single-site since sitemeta and
	 * the main site's options are the same table there.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_get_root_key_adopts_a_pre_conversion_root_key(): void {
		$this->skipWithoutMultisite();

		$manager  = new WP_Secrets_Key_Manager();
		$root_key = $manager->get_root_key();

		$main_site_id = get_main_site_id();
		$wrapped      = get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );

		delete_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		update_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$fresh_manager = new WP_Secrets_Key_Manager();
		$adopted_key   = $fresh_manager->get_root_key();

		$this->assertIsString( $root_key, 'The original root key should be a string.' );
		$this->assertIsString( $adopted_key, 'The adopted root key should be a string.' );
		$this->assertTrue( hash_equals( $root_key, $adopted_key ), 'The adopted root key should match the pre-conversion root key.' );
		$this->assertNotFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'The adopted root key should be stored in sitemeta.' );
		$this->assertFalse( get_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION ), 'The main-site option row should be removed after adoption.' );
	}

	/**
	 * Requires the multisite suite; skips under single-site for the same reason
	 * as the adoption test above.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 * @covers ::wp_get_secret
	 */
	public function test_secret_written_before_conversion_still_decrypts(): void {
		$this->skipWithoutMultisite();

		$main_site_id = get_main_site_id();

		$this->assertTrue( wp_set_secret( 'secrets-api/pre-conversion', 'shh' ), 'Storing the secret before conversion should succeed.' );

		$wrapped = get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		delete_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		update_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$secret = wp_get_secret( 'secrets-api/pre-conversion' );

		$this->assertNotWPError( $secret, 'Reading the secret after conversion should not return a WP_Error.' );
		$this->assertInstanceOf( WP_Secret::class, $secret, 'The secret read after conversion should be a WP_Secret.' );
		$this->assertSame( 'shh', $secret->reveal(), 'The secret should reveal its original value after conversion.' );
	}

	/**
	 * Requires the multisite suite; skips under single-site for the same reason
	 * as the adoption test above.
	 *
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Secrets_Key_Manager::rotate_site_key
	 */
	public function test_rotate_site_key_works_after_conversion_before_any_read(): void {
		$this->skipWithoutMultisite();

		$main_site_id  = get_main_site_id();
		$right_keyring = new WP_Secrets_Config_Key_Provider();
		$manager       = new WP_Secrets_Key_Manager( $right_keyring );

		$manager->get_root_key();
		$master_before = $manager->get_master_key( 'site' );

		$wrapped = get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		delete_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		update_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$fresh_manager = new WP_Secrets_Key_Manager( $right_keyring );
		$rotated       = $fresh_manager->rotate_site_key( $right_keyring, $right_keyring );

		$this->assertNotWPError( $rotated, 'Rotating after conversion should not return a WP_Error.' );
		$this->assertTrue( $rotated, 'Rotating after conversion should return true.' );

		$master_after = $fresh_manager->get_master_key( 'site' );

		$this->assertSame( $master_before, $master_after, 'The site master key should be unchanged by rotation after conversion.' );
	}

	/**
	 * Ensure that rotating the site key before any root key exists returns a store error.
	 *
	 * @covers WP_Secrets_Key_Manager::rotate_site_key
	 */
	public function test_rotate_fails_when_no_root_key_exists(): void {
		$manager = new WP_Secrets_Key_Manager();
		$keyring = new WP_Secrets_Config_Key_Provider();

		$result = $manager->rotate_site_key( $keyring, $keyring );

		$this->assertWPError( $result, 'Rotating without a stored root key should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code(), 'Rotating without a stored root key should return the store unavailable error code.' );
	}

	/**
	 * Ensure that rotation fails when the old keyring cannot unwrap the stored root key.
	 *
	 * @covers WP_Secrets_Key_Manager::rotate_site_key
	 */
	public function test_rotate_fails_when_the_old_keyring_is_wrong(): void {
		$right_keyring = new WP_Secrets_Config_Key_Provider();
		$manager       = new WP_Secrets_Key_Manager( $right_keyring );
		$manager->get_root_key();

		$wrong_keyring = new class() implements WP_Secrets_Keyring {
			/**
			 * @param string $key_material Raw key material.
			 * @return string
			 */
			public function wrap( string $key_material ) {
				return base64_encode( $key_material );
			}

			/**
			 * @param string $wrapped Wrapped value.
			 * @return WP_Error
			 */
			public function unwrap( string $wrapped ) {
				return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'wrong key' );
			}

			/**
			 * @return string
			 */
			public function get_key_source(): string {
				return 'test double';
			}
		};

		$result = $manager->rotate_site_key( $wrong_keyring, $right_keyring );

		$this->assertWPError( $result, 'Rotating with an old keyring that cannot unwrap should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'The old keyring\'s unwrap error should be passed through.' );
	}

	/**
	 * A site-key rotation re-wraps the root key without changing its raw bytes, so
	 * every derived master key – and therefore every stored secret – is unaffected.
	 * This is the whole point of the envelope design, so it gets an end-to-end proof
	 * rather than just testing rotate_site_key() in isolation.
	 *
	 * @covers WP_Secrets_Key_Manager::rotate_site_key
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rotation_does_not_change_any_derived_master_key(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'A', 32 ) ) );
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'B', 32 ) ) );

		$old_keyring = new WP_Secrets_Config_Key_Provider( true );
		$new_keyring = new WP_Secrets_Config_Key_Provider( false );

		$manager_under_old_key = new WP_Secrets_Key_Manager( $old_keyring );
		$manager_under_old_key->get_root_key(); // Generates, wrapped under the old key.

		$master_before = $manager_under_old_key->get_master_key( 'site', 7 );

		$rotated = $manager_under_old_key->rotate_site_key( $old_keyring, $new_keyring );
		$this->assertNotWPError( $rotated, 'Rotating from the old keyring to the new one should not return a WP_Error.' );

		$manager_under_new_key = new WP_Secrets_Key_Manager( $new_keyring );
		$master_after          = $manager_under_new_key->get_master_key( 'site', 7 );

		$this->assertSame( $master_before, $master_after, 'The derived master key should be unchanged by rotation.' );

		/*
		 * The old keyring alone is no longer sufficient: the stored root key is now
		 * wrapped under the new key. Checked with a fresh manager instance, since
		 * $manager_under_old_key's own cache was correctly primed by the rotation
		 * it just performed and is not the thing under test here.
		 */
		$fresh_manager_under_old_key = new WP_Secrets_Key_Manager( $old_keyring );
		$this->assertWPError( $fresh_manager_under_old_key->get_root_key(), 'The old keyring alone should no longer unwrap the rotated root key.' );
	}

	/**
	 * Ensure that deriving many master keys unwraps the root key only once per key manager.
	 *
	 * Guards the per-request root key cache, which keeps a remote keyring such as a KMS
	 * or HSM to a single call per request rather than one per derivation.
	 *
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_unwrap_is_called_once_across_repeated_master_key_derivations(): void {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		for ( $i = 0; $i < 5; $i++ ) {
			$site_key = $manager->get_master_key( 'site', $i + 1 );
			$this->assertIsString( $site_key, 'Each site master key should be a string.' );
			$this->assertSame( 32, strlen( $site_key ), 'Each site master key should be 32 bytes long.' );

			$network_key = $manager->get_master_key( 'network' );
			$this->assertIsString( $network_key, 'Each network master key should be a string.' );
			$this->assertSame( 32, strlen( $network_key ), 'Each network master key should be 32 bytes long.' );
		}

		$this->assertSame( 1, $mock->unwrap_call_count(), 'The root key should be unwrapped only once across all derivations.' );
	}

	/**
	 * Ensure that repeated wp_get_secret() calls unwrap the root key only once.
	 *
	 * Runs in a separate process because _wp_secrets_get_key_manager() memoizes the key
	 * manager in a static, so the mock keyring global is only picked up on first use.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unwrap_is_called_once_across_many_secret_reads(): void {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$GLOBALS['wp_secrets_keyring'] = $mock;

		$this->assertTrue( wp_set_secret( 'conformance/root-key-cache', 'the-value' ), 'Storing the secret should succeed.' );

		for ( $i = 0; $i < 10; $i++ ) {
			$secret = wp_get_secret( 'conformance/root-key-cache' );
			$this->assertInstanceOf( 'WP_Secret', $secret, 'Each read should return a WP_Secret.' );
			$this->assertSame( 'the-value', $secret->reveal(), 'Each read should reveal the stored value.' );
		}

		$this->assertSame( 1, $mock->unwrap_call_count(), 'The root key should be unwrapped only once across all secret reads.' );
	}

	/**
	 * Ensure that rotation reuses the cached root key and leaves the cache valid afterward.
	 *
	 * @covers WP_Secrets_Key_Manager::rotate_site_key
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_rotate_site_key_updates_the_cache_without_another_unwrap(): void {
		$mock        = new Mock_Keyring();
		$second_mock = new Mock_Keyring();
		$root        = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager  = new WP_Secrets_Key_Manager( $mock );
		$root_key = $manager->get_root_key();

		$this->assertNotWPError( $manager->rotate_site_key( $mock, $second_mock ), 'Rotating the site key should not return a WP_Error.' );

		$this->assertSame( $root_key, $manager->get_root_key(), 'The cached root key should be unchanged after rotation.' );
		$this->assertSame( 1, $mock->unwrap_call_count(), 'The original keyring should have unwrapped only once.' );
		$this->assertSame( 0, $second_mock->unwrap_call_count(), 'The new keyring should not have been asked to unwrap.' );
	}

	/**
	 * Ensure that the cached root key is discarded when the stored wrapped value changes.
	 *
	 * Overwrites the stored option directly to simulate another request restoring or
	 * re-wrapping the root key mid-request.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_a_changed_wrapped_value_is_unwrapped_again_rather_than_served_from_cache(): void {
		$mock       = new Mock_Keyring();
		$root       = random_bytes( 32 );
		$other_root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );
		$this->assertSame( $root, $manager->get_root_key(), 'get_root_key() should return the initially stored root key.' );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $other_root ) );

		$this->assertSame( $other_root, $manager->get_root_key(), 'get_root_key() should return the new root key after the stored value changes.' );
		$this->assertSame( 2, $mock->unwrap_call_count(), 'The changed wrapped value should trigger a second unwrap.' );
	}

	/**
	 * Ensure that a failed unwrap is retried on the next call and a later success is cached.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_an_unwrap_error_is_not_cached(): void {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		/*
		 * Each call is assigned before it is asserted on: get_root_key() answers
		 * differently as the keyring's state changes, so no two calls are the
		 * same expression as far as a reader (or an analyzer) should assume.
		 */
		$mock->configure_fail_unwrap( true );
		$while_failing = $manager->get_root_key();
		$this->assertWPError( $while_failing, 'get_root_key() should return a WP_Error while unwrap fails.' );

		$mock->configure_fail_unwrap( false );
		$after_recovery = $manager->get_root_key();
		$this->assertSame( $root, $after_recovery, 'get_root_key() should return the root key once unwrap recovers.' );

		$from_cache = $manager->get_root_key();
		$this->assertSame( $root, $from_cache, 'get_root_key() should return the root key from cache after recovery.' );
		$this->assertSame( 2, $mock->unwrap_call_count(), 'The root key should be unwrapped once for the failure and once for the recovery.' );
	}

	/**
	 * Ensure that a newly generated root key is cached so deriving a master key needs no unwrap.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 * @covers WP_Secrets_Key_Manager::generate_root_key
	 */
	public function test_generate_root_key_primes_the_cache(): void {
		$mock = new Mock_Keyring();

		$manager = new WP_Secrets_Key_Manager( $mock );
		$manager->get_root_key();
		$manager->get_master_key( 'site' );

		$this->assertSame( 0, $mock->unwrap_call_count(), 'A newly generated root key should not need unwrapping.' );
		$this->assertSame( 1, $mock->wrap_call_count(), 'The newly generated root key should be wrapped exactly once.' );
	}

	/**
	 * Ensure that zeroing the returned root key does not wipe the key manager's cached copy.
	 *
	 * Callers are required to zero the root key they receive, so the copy handed out must
	 * never share storage with the cache.
	 *
	 * @covers WP_Secrets_Key_Manager::get_root_key
	 */
	public function test_the_returned_root_key_is_a_copy_the_caller_can_zero(): void {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		$copy = $manager->get_root_key();
		$this->assertIsString( $copy, 'get_root_key() should return a string.' );
		wp_secrets_memzero( $copy );

		$this->assertSame( $root, $manager->get_root_key(), 'Zeroing the returned copy should not wipe the cached root key.' );
		$this->assertSame( 1, $mock->unwrap_call_count(), 'The cached root key should be served without another unwrap.' );
	}
}
