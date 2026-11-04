<?php
/**
 * Extension point tests: a plugin-supplied store or
 * keyring must actually be used, both independently and in combination, and
 * failure anywhere in either must fail closed rather than falling back to local
 * defaults.
 *
 * Every test here injects its override via $GLOBALS['wp_secrets_store'] /
 * $GLOBALS['wp_secrets_keyring'] – the same globals a secrets.php drop-in would
 * set – rather than actually placing a drop-in file on disk. _wp_secrets_get_store()
 * and _wp_secrets_get_key_manager() cache what they resolve to in a static local the
 * first time either is called in a process, and by the time any test method runs,
 * dozens of earlier tests have already triggered that caching with the defaults.
 *
 * Running each test in a separate process gives it a fresh process with nothing
 * cached yet, so setting the global before the first call actually takes effect.
 * This tests the real consumption logic in _wp_secrets_get_store() and
 * _wp_secrets_get_key_manager(). It does not cover wp_load_secrets_dropin() loading
 * a drop-in file from disk, which cannot be driven the same way.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_ExtensionPoints extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	/**
	 * Ensure that a secret round-trips through a plugin-supplied store paired with the default keyring.
	 *
	 * @covers ::_wp_secrets_get_store
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_store_with_the_default_keyring_round_trips(): void {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$GLOBALS['wp_secrets_store'] = new Mock_Store();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Writing through the mock store should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'Reading through the mock store should return a WP_Secret.' );
		$this->assertSame( 'value', $secret->reveal(), 'The secret should reveal the value written through the mock store.' );
	}

	/**
	 * Ensure that a secret round-trips through a plugin-supplied keyring paired with the default store.
	 *
	 * @covers ::_wp_secrets_get_key_manager
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_keyring_with_the_default_store_round_trips(): void {
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Writing through the mock keyring should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'Reading through the mock keyring should return a WP_Secret.' );
		$this->assertSame( 'value', $secret->reveal(), 'The secret should reveal the value written through the mock keyring.' );
	}

	/**
	 * Ensure that a secret round-trips when both the store and the keyring are plugin-supplied.
	 *
	 * @covers ::_wp_secrets_get_store
	 * @covers ::_wp_secrets_get_key_manager
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_store_and_mock_keyring_together_round_trip(): void {
		$GLOBALS['wp_secrets_store']   = new Mock_Store();
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Writing through the mock store and keyring should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'Reading through the mock store and keyring should return a WP_Secret.' );
		$this->assertSame( 'value', $secret->reveal(), 'The secret should reveal the value written through the mock store and keyring.' );
	}

	/**
	 * A store that can read but not write: an existing value seeded before write
	 * support is turned off must remain readable, while a new write is rejected.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_read_succeeds_while_write_fails(): void {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'seeded-before-write-broke' ), 'Setting myplugin/api-key to seeded-before-write-broke should succeed.' );

		$store->configure_fail( 'set', true );

		$write_result = wp_set_secret( 'myplugin/api-key', 'new-value' );
		$read_result  = wp_get_secret( 'myplugin/api-key' );

		$this->assertWPError( $write_result, 'A write to a store that cannot write should return a WP_Error.' );
		$this->assertInstanceOf( WP_Secret::class, $read_result, 'A read from a store that cannot write should still return a WP_Secret.' );
		$this->assertSame( 'seeded-before-write-broke', $read_result->reveal(), 'The secret should reveal the value seeded before writes were disabled.' );
	}

	/**
	 * Ensure that an unreachable plugin-supplied store fails reads and writes without a local fallback.
	 *
	 * @covers ::_wp_secrets_get_store
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unreachable_store_fails_closed_with_no_local_fallback(): void {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store->configure_fail( 'get', true )->configure_fail( 'set', true );

		$set_result = wp_set_secret( 'myplugin/api-key', 'value' );
		$get_result = wp_get_secret( 'myplugin/api-key' );

		$this->assertWPError( $set_result, 'A write to an unreachable store should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $set_result->get_error_code(), 'A write to an unreachable store should report the store as unavailable.' );
		$this->assertWPError( $get_result, 'A read from an unreachable store should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $get_result->get_error_code(), 'A read from an unreachable store should report the store as unavailable.' );

		/*
		 * Fails closed, not merely differently: nothing was written to the real
		 * default store either, which a silent fallback would have done.
		 */
		$this->assertFalse( get_option( '_wp_secret_myplugin/api-key' ), 'Nothing should be written to the default options store as a fallback.' );
		$this->assertFalse( get_site_option( '_wp_network_secret_myplugin/api-key' ), 'Nothing should be written to the default network store as a fallback.' );
	}

	/**
	 * Ensure that wp_list_secrets() returns a WP_Error when the store cannot list its secrets.
	 *
	 * An unreachable store must not be reported as an empty listing, which would read
	 * as "no secrets exist", and the error passed back must not carry a stored value.
	 *
	 * @covers ::wp_list_secrets
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_listing_fails_closed_when_the_store_cannot_list(): void {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-5c1e' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-5c1e should succeed.' );
		$store->configure_fail( 'list', true );

		$result = wp_list_secrets();

		$this->assertWPError( $result, 'A store that cannot list should be reported as an error, not an empty list.' );
		$this->assertSame( WP_SECRETS_ERROR_STORE_UNAVAILABLE, $result->get_error_code(), 'The store error should be passed through unchanged.' );
		$this->assertNeverContainsPlaintext( 'UNIQUE-PLAINTEXT-CANARY-5c1e', $result, 'A listing error should never carry a stored value.' );
	}

	/**
	 * Ensure that a keyring that cannot wrap keys fails the write and stores nothing locally.
	 *
	 * @covers ::_wp_secrets_get_key_manager
	 * @covers ::wp_set_secret
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unreachable_keyring_fails_closed_with_no_local_fallback(): void {
		$GLOBALS['wp_secrets_keyring'] = ( new Mock_Keyring() )->configure_fail_wrap( true );

		$result = wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $result, 'A write through a keyring that cannot wrap should return a WP_Error.' );
		$this->assertFalse( get_option( '_wp_secret_myplugin/api-key' ), 'Nothing should be written to the default options store as a fallback.' );
		$this->assertFalse( get_site_option( '_wp_network_secret_myplugin/api-key' ), 'Nothing should be written to the default network store as a fallback.' );
	}

	/**
	 * The store must never be handed a plaintext, under any circumstance – this is
	 * the whole point of the envelope. Mock_Store records every record it is
	 * given; this asserts the plaintext appears nowhere in any of them.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_store_is_never_handed_a_plaintext(): void {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$this->assertNotEmpty( $store->get_received_records(), 'The store should have been handed the encrypted record.' );
		$this->assertNeverContainsPlaintext(
			'UNIQUE-PLAINTEXT-CANARY-9f3a',
			$store->get_received_records(),
			'The store was handed a plaintext.'
		);
	}

	/**
	 * Simulates the state a broken secrets.php drop-in leaves behind
	 * ($GLOBALS['wp_secrets_dropin_broken'], set by wp_load_secrets_dropin(), which
	 * runs once per request before any test and so cannot be driven directly) and
	 * confirms every operation fails closed with WP_Error rather than a fatal error
	 * or a silent fallback to local storage.
	 *
	 * @covers ::_wp_secrets_get_provider
	 * @covers WP_Secrets_Broken_Provider::set
	 * @covers WP_Secrets_Broken_Provider::get
	 * @covers WP_Secrets_Broken_Provider::delete
	 * @covers WP_Secrets_Broken_Provider::list_secrets
	 * @covers WP_Secrets_Broken_Provider::retire_previous
	 * @covers WP_Secrets_Broken_Provider::is_writable
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_state_fails_every_operation_closed(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'value' ), 'wp_set_secret() should return a WP_Error when the drop-in is broken.' );
		$this->assertWPError( wp_get_secret( 'myplugin/api-key' ), 'wp_get_secret() should return a WP_Error when the drop-in is broken.' );
		$this->assertWPError( wp_delete_secret( 'myplugin/api-key' ), 'wp_delete_secret() should return a WP_Error when the drop-in is broken.' );
		$this->assertWPError( wp_list_secrets(), 'wp_list_secrets() should return a WP_Error when the drop-in is broken.' );
		$this->assertWPError( wp_retire_secret_version( 'myplugin/api-key' ), 'wp_retire_secret_version() should return a WP_Error when the drop-in is broken.' );
		$this->assertFalse( wp_secrets_provider_is_writable(), 'The provider should not report as writable when the drop-in is broken.' );
	}
}
