<?php
/**
 * Tests for WP_Secrets_Key_Manager.
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsKeyManager extends WP_UnitTestCase {

	public function test_get_root_key_generates_and_persists_one() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ) );

		$root_key = $manager->get_root_key();

		$this->assertIsString( $root_key );
		$this->assertSame( 32, strlen( $root_key ) );
		$this->assertNotFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ) );
	}

	public function test_get_root_key_is_stable_on_the_same_instance() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame( $manager->get_root_key(), $manager->get_root_key() );
	}

	public function test_get_root_key_persists_across_instances() {
		$first  = new WP_Secrets_Key_Manager();
		$root_a = $first->get_root_key();

		$second = new WP_Secrets_Key_Manager();
		$root_b = $second->get_root_key();

		$this->assertSame( $root_a, $root_b );
	}

	public function test_get_root_key_uses_an_existing_value_rather_than_regenerating() {
		$keyring = new WP_Secrets_Config_Key_Provider();
		$known   = str_repeat( 'R', 32 );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $keyring->wrap( $known ) );

		$manager = new WP_Secrets_Key_Manager( $keyring );

		$this->assertSame( $known, $manager->get_root_key() );
	}

	public function test_get_master_key_matches_manual_derivation() {
		$manager  = new WP_Secrets_Key_Manager();
		$root_key = $manager->get_root_key();

		$expected = sodium_crypto_kdf_derive_from_key( 32, get_current_blog_id(), 'wpsecsit', $root_key );

		$this->assertSame( $expected, $manager->get_master_key( 'site' ) );
	}

	public function test_get_master_key_differs_per_site_id() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertNotSame(
			$manager->get_master_key( 'site', 5 ),
			$manager->get_master_key( 'site', 6 )
		);
	}

	public function test_get_master_key_site_and_network_scope_differ() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertNotSame(
			$manager->get_master_key( 'site', 1 ),
			$manager->get_master_key( 'network' )
		);
	}

	public function test_get_master_key_network_scope_is_stable() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame(
			$manager->get_master_key( 'network' ),
			$manager->get_master_key( 'network' )
		);
	}

	public function test_get_master_key_defaults_site_id_to_the_current_blog() {
		$manager = new WP_Secrets_Key_Manager();

		$this->assertSame(
			$manager->get_master_key( 'site' ),
			$manager->get_master_key( 'site', get_current_blog_id() )
		);
	}

	public function test_get_master_key_reports_an_invalid_scope_as_a_wp_error() {
		$manager = new WP_Secrets_Key_Manager();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Key_Manager::get_master_key' );

		$result = $manager->get_master_key( 'bogus' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	/**
	 * @dataProvider data_invalid_site_ids
	 */
	public function test_get_master_key_reports_an_invalid_site_id_as_a_wp_error( $site_id ) {
		$manager = new WP_Secrets_Key_Manager();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Key_Manager::get_master_key' );

		$result = $manager->get_master_key( 'site', $site_id );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function data_invalid_site_ids() {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -1 ),
			'string'   => array( 'five' ),
		);
	}

	/**
	 * The root key is shared network-wide (one row via update_site_option()), so a
	 * master key derived on one blog must be derivable identically on another --
	 * this is the entire reason network secrets can be read from any blog. Requires
	 * the multisite suite; skips under single-site since there is only one blog to
	 * compare against.
	 */
	public function test_network_scope_master_key_is_identical_across_blogs() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves the root key is shared network-wide.' );
		}

		$second_blog_id = self::factory()->blog->create();

		$manager               = new WP_Secrets_Key_Manager();
		$network_key_on_blog_1 = $manager->get_master_key( 'network' );

		switch_to_blog( $second_blog_id );
		$network_key_on_blog_2 = ( new WP_Secrets_Key_Manager() )->get_master_key( 'network' );
		restore_current_blog();

		$this->assertSame( $network_key_on_blog_1, $network_key_on_blog_2 );
	}

	/**
	 * Site-scope master keys, by contrast, must differ per blog even though every
	 * blog derives from the same shared root key.
	 */
	public function test_site_scope_master_key_differs_across_blogs() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves per-blog cryptographic separation.' );
		}

		$second_blog_id = self::factory()->blog->create();

		$manager            = new WP_Secrets_Key_Manager();
		$site_key_on_blog_1 = $manager->get_master_key( 'site' );

		switch_to_blog( $second_blog_id );
		$site_key_on_blog_2 = ( new WP_Secrets_Key_Manager() )->get_master_key( 'site' );
		restore_current_blog();

		$this->assertNotSame( $site_key_on_blog_1, $site_key_on_blog_2 );
	}

	/**
	 * Simulates single-site-to-multisite conversion: moves the wrapped root key
	 * from its main-site option row into sitemeta, the way a fresh
	 * WP_Secrets_Key_Manager finds it after `wp core multisite-convert`.
	 *
	 * Requires the multisite suite; skips under single-site since sitemeta and
	 * the main site's options are the same table there.
	 */
	public function test_get_root_key_adopts_a_pre_conversion_root_key() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves the main-site option row is adopted.' );
		}

		$manager  = new WP_Secrets_Key_Manager();
		$root_key = $manager->get_root_key();

		$main_site_id = get_main_site_id();
		$wrapped      = get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );

		delete_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		update_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$fresh_manager = new WP_Secrets_Key_Manager();
		$adopted_key   = $fresh_manager->get_root_key();

		$this->assertTrue( hash_equals( $root_key, $adopted_key ) );
		$this->assertNotFalse( get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION ) );
		$this->assertFalse( get_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION ) );
	}

	/**
	 * Requires the multisite suite; skips under single-site for the same reason
	 * as the adoption test above.
	 */
	public function test_secret_written_before_conversion_still_decrypts() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves secrets survive conversion.' );
		}

		$main_site_id = get_main_site_id();

		$this->assertTrue( wp_set_secret( 'secrets-api/pre-conversion', 'shh' ) );

		$wrapped = get_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		delete_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION );
		update_blog_option( $main_site_id, WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $wrapped );

		$secret = wp_get_secret( 'secrets-api/pre-conversion' );

		$this->assertNotWPError( $secret );
		$this->assertSame( 'shh', $secret->reveal() );
	}

	/**
	 * Requires the multisite suite; skips under single-site for the same reason
	 * as the adoption test above.
	 */
	public function test_rotate_site_key_works_after_conversion_before_any_read() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: proves rotation adopts a pre-conversion key too.' );
		}

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

		$this->assertNotWPError( $rotated );
		$this->assertTrue( $rotated );

		$master_after = $fresh_manager->get_master_key( 'site' );

		$this->assertSame( $master_before, $master_after );
	}

	public function test_rotate_fails_when_no_root_key_exists() {
		$manager = new WP_Secrets_Key_Manager();
		$keyring = new WP_Secrets_Config_Key_Provider();

		$result = $manager->rotate_site_key( $keyring, $keyring );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_rotate_fails_when_the_old_keyring_is_wrong() {
		$right_keyring = new WP_Secrets_Config_Key_Provider();
		$manager       = new WP_Secrets_Key_Manager( $right_keyring );
		$manager->get_root_key();

		$wrong_keyring = new class() implements WP_Secrets_Keyring {
			public function wrap( $key_material ) {
				return base64_encode( $key_material );
			}
			public function unwrap( $wrapped ) {
				return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'wrong key' );
			}
			public function get_key_source() {
				return 'test double';
			}
		};

		$result = $manager->rotate_site_key( $wrong_keyring, $right_keyring );

		$this->assertWPError( $result );
	}

	/**
	 * A site-key rotation re-wraps the root key without changing its raw bytes, so
	 * every derived master key -- and therefore every stored secret -- is unaffected.
	 * This is the whole point of the envelope design, so it gets an end-to-end proof
	 * rather than just testing rotate_site_key() in isolation.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rotation_does_not_change_any_derived_master_key() {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'A', 32 ) ) );
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'B', 32 ) ) );

		$old_keyring = new WP_Secrets_Config_Key_Provider( true );
		$new_keyring = new WP_Secrets_Config_Key_Provider( false );

		$manager_under_old_key = new WP_Secrets_Key_Manager( $old_keyring );
		$manager_under_old_key->get_root_key(); // Generates, wrapped under the old key.

		$master_before = $manager_under_old_key->get_master_key( 'site', 7 );

		$rotated = $manager_under_old_key->rotate_site_key( $old_keyring, $new_keyring );
		$this->assertNotWPError( $rotated );

		$manager_under_new_key = new WP_Secrets_Key_Manager( $new_keyring );
		$master_after          = $manager_under_new_key->get_master_key( 'site', 7 );

		$this->assertSame( $master_before, $master_after );

		// The old keyring alone is no longer sufficient: the stored root key is now
		// wrapped under the new key. Checked with a fresh manager instance, since
		// $manager_under_old_key's own cache was correctly primed by the rotation
		// it just performed and is not the thing under test here.
		$fresh_manager_under_old_key = new WP_Secrets_Key_Manager( $old_keyring );
		$this->assertWPError( $fresh_manager_under_old_key->get_root_key() );
	}

	public function test_unwrap_is_called_once_across_repeated_master_key_derivations() {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( 32, strlen( $manager->get_master_key( 'site', $i + 1 ) ) );
			$this->assertSame( 32, strlen( $manager->get_master_key( 'network' ) ) );
		}

		$this->assertSame( 1, $mock->unwrap_call_count() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unwrap_is_called_once_across_many_secret_reads() {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$GLOBALS['wp_secrets_keyring'] = $mock;

		$this->assertNotWPError( wp_set_secret( 'conformance/root-key-cache', 'the-value' ) );

		for ( $i = 0; $i < 10; $i++ ) {
			$secret = wp_get_secret( 'conformance/root-key-cache' );
			$this->assertInstanceOf( 'WP_Secret', $secret );
			$this->assertSame( 'the-value', $secret->reveal() );
		}

		$this->assertSame( 1, $mock->unwrap_call_count() );
	}

	public function test_rotate_site_key_updates_the_cache_without_another_unwrap() {
		$mock        = new Mock_Keyring();
		$second_mock = new Mock_Keyring();
		$root        = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager  = new WP_Secrets_Key_Manager( $mock );
		$root_key = $manager->get_root_key();

		$this->assertNotWPError( $manager->rotate_site_key( $mock, $second_mock ) );

		$this->assertSame( $root_key, $manager->get_root_key() );
		$this->assertSame( 1, $mock->unwrap_call_count() );
		$this->assertSame( 0, $second_mock->unwrap_call_count() );
	}

	public function test_a_changed_wrapped_value_is_unwrapped_again_rather_than_served_from_cache() {
		$mock       = new Mock_Keyring();
		$root       = random_bytes( 32 );
		$other_root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );
		$this->assertSame( $root, $manager->get_root_key() );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $other_root ) );

		$this->assertSame( $other_root, $manager->get_root_key() );
		$this->assertSame( 2, $mock->unwrap_call_count() );
	}

	public function test_an_unwrap_error_is_not_cached() {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		$mock->configure_fail_unwrap( true );
		$this->assertWPError( $manager->get_root_key() );

		$mock->configure_fail_unwrap( false );
		$this->assertSame( $root, $manager->get_root_key() );

		$this->assertSame( $root, $manager->get_root_key() );
		$this->assertSame( 2, $mock->unwrap_call_count() );
	}

	public function test_generate_root_key_primes_the_cache() {
		$mock = new Mock_Keyring();

		$manager = new WP_Secrets_Key_Manager( $mock );
		$manager->get_root_key();
		$manager->get_master_key( 'site' );

		$this->assertSame( 0, $mock->unwrap_call_count() );
		$this->assertSame( 1, $mock->wrap_call_count() );
	}

	public function test_the_returned_root_key_is_a_copy_the_caller_can_zero() {
		$mock = new Mock_Keyring();
		$root = random_bytes( 32 );
		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, $mock->wrap( $root ) );

		$manager = new WP_Secrets_Key_Manager( $mock );

		$copy = $manager->get_root_key();
		wp_secrets_memzero( $copy );

		$this->assertSame( $root, $manager->get_root_key() );
		$this->assertSame( 1, $mock->unwrap_call_count() );
	}
}
