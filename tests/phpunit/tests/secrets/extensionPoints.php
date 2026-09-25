<?php
/**
 * Extension point tests: a plugin-supplied store or
 * keyring must actually be used, both independently and in combination, and
 * failure anywhere in either must fail closed rather than falling back to local
 * defaults.
 *
 * Every test here injects its override via $GLOBALS['wp_secrets_store'] /
 * $GLOBALS['wp_secrets_keyring'] -- the same globals a secrets.php drop-in would
 * set -- rather than actually placing a drop-in file on disk. _wp_secrets_get_store()
 * and _wp_secrets_get_key_manager() cache what they resolve to in a static local the
 * first time either is called in a process, and by the time any test method runs,
 * dozens of earlier tests have already triggered that caching with the defaults.
 *
 * @runInSeparateProcess gives each test a fresh process with nothing cached yet, so
 * setting the global before the first call actually takes effect. This tests the
 * real consumption logic in _wp_secrets_get_store()/_wp_secrets_get_key_manager();
 * see docs/journal/test-coverage-gaps.md for what this does not cover (the drop-in's own file
 * loading, which cannot be driven the same way).
 *
 * @group secrets
 */
class Tests_Secrets_ExtensionPoints extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_store_with_the_default_keyring_round_trips() {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$GLOBALS['wp_secrets_store'] = new Mock_Store();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertSame( 'value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_keyring_with_the_default_store_round_trips() {
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertSame( 'value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mock_store_and_mock_keyring_together_round_trip() {
		$GLOBALS['wp_secrets_store']   = new Mock_Store();
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertSame( 'value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	/**
	 * A store that can read but not write: an existing value seeded before write
	 * support is turned off must remain readable, while a new write is rejected.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_read_succeeds_while_write_fails() {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		wp_set_secret( 'myplugin/api-key', 'seeded-before-write-broke' );

		$store->configure_fail( 'set', true );

		$write_result = wp_set_secret( 'myplugin/api-key', 'new-value' );
		$read_result  = wp_get_secret( 'myplugin/api-key' );

		$this->assertWPError( $write_result );
		$this->assertInstanceOf( WP_Secret::class, $read_result );
		$this->assertSame( 'seeded-before-write-broke', $read_result->reveal() );
	}


	/**
	 * A store that refuses writes must still serve reads. This previously configured
	 * a capability flag that nothing consulted, so it passed regardless of behavior.
	 * It now makes set() actually fail, and checks both halves.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_store_that_refuses_writes_still_serves_reads() {
		// Isolated, so this test supplies the site key rather than relying on the test config's salts.
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'k', 32 ) ) );

		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		wp_set_secret( 'myplugin/api-key', 'value' );
		$store->configure_fail( 'set', true );

		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'another' ) );
		$this->assertSame( 'value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unreachable_store_fails_closed_with_no_local_fallback() {
		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store->configure_fail( 'get', true )->configure_fail( 'set', true );

		$set_result = wp_set_secret( 'myplugin/api-key', 'value' );
		$get_result = wp_get_secret( 'myplugin/api-key' );

		$this->assertWPError( $set_result );
		$this->assertWPError( $get_result );

		// Fails closed, not merely differently: nothing was written to the real
		// default store either, which a silent fallback would have done.
		$this->assertFalse( get_option( '_wp_secret_myplugin/api-key' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unreachable_keyring_fails_closed_with_no_local_fallback() {
		$GLOBALS['wp_secrets_keyring'] = ( new Mock_Keyring() )->configure_fail_wrap( true );

		$result = wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $result );
		$this->assertFalse( get_option( '_wp_secret_myplugin/api-key' ) );
	}

	/**
	 * The store must never be handed a plaintext, under any circumstance -- this is
	 * the whole point of the envelope. Mock_Store records every record it is
	 * given; this asserts the plaintext appears nowhere in any of them.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_store_is_never_handed_a_plaintext() {
		$store                       = new Mock_Store();
		$GLOBALS['wp_secrets_store'] = $store;

		wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' );

		$this->assertNeverContainsPlaintext(
			'UNIQUE-PLAINTEXT-CANARY-9f3a',
			$store->get_received_records(),
			'The store was handed a plaintext.'
		);
	}

	/**
	 * Simulates the state a broken secrets.php drop-in leaves behind
	 * ($GLOBALS['wp_secrets_dropin_broken'] set by wp_load_secrets_dropin() --
	 * see that function's docblock in secrets-api.php for why the drop-in's own file
	 * loading cannot be driven the same way this simulates its aftermath) and
	 * confirms every operation fails closed with WP_Error rather than a fatal error
	 * or a silent fallback to local storage.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_state_fails_every_operation_closed() {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertWPError( wp_get_secret( 'myplugin/api-key' ) );
		$this->assertWPError( wp_delete_secret( 'myplugin/api-key' ) );
		$this->assertWPError( wp_list_secrets() );
		$this->assertWPError( wp_retire_secret_version( 'myplugin/api-key' ) );
		$this->assertFalse( wp_secrets_provider_is_writable() );
	}
}
