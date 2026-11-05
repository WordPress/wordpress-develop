<?php
/**
 * Tests for wp_list_secrets().
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpListSecrets extends WP_UnitTestCase {

	/**
	 * Ensure that wp_list_secrets() returns an empty array when no secrets are stored.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_returns_an_empty_array_when_nothing_is_set(): void {
		$this->assertSame( array(), wp_list_secrets(), 'An empty store should list no secrets.' );
	}

	/**
	 * Ensure that each wp_list_secrets() entry has exactly the documented metadata keys, in order.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_lists_every_secret_with_the_expected_keys(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertCount( 1, $entries, 'Exactly one secret should be listed.' );
		$this->assertSame(
			array( 'name', 'fingerprint', 'created', 'has_previous', 'needs_rotation' ),
			array_keys( $entries[0] ),
			'The entry should have exactly the documented metadata keys, in order.'
		);
		$this->assertSame( 'myplugin/api-key', $entries[0]['name'], 'The entry should carry the secret name.' );
	}

	/**
	 * Never a value, under any circumstance – the entire justification for this
	 * function existing depends on it.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_never_returns_a_value(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' ), 'Setting myplugin/api-key to a-plaintext-value-that-must-not-leak should succeed.' );

		$dump = wp_json_encode( wp_list_secrets() );

		$this->assertIsString( $dump, 'The listing should encode to a JSON string.' );
		$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', $dump, 'The listing should never contain the plaintext value.' );
	}

	/**
	 * Ensure that a listed fingerprint matches WP_Secret::fingerprint() for the same secret.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_fingerprint_matches_the_secrets_own_fingerprint(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$secret  = wp_get_secret( 'myplugin/api-key' );
		$entries = wp_list_secrets();

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The secret should be a WP_Secret.' );
		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertCount( 1, $entries, 'Exactly one secret should be listed.' );
		$this->assertSame( $secret->fingerprint(), $entries[0]['fingerprint'], 'The listed fingerprint should match the fingerprint of the secret.' );
	}

	/**
	 * Ensure that has_previous is false for a new secret and true once it has been overwritten.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_has_previous_is_false_before_a_rotation_and_true_after(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );

		$before = wp_list_secrets();

		$this->assertIsArray( $before, 'wp_list_secrets() should return an array before the overwrite.' );
		$this->assertFalse( $before[0]['has_previous'], 'has_previous should be false before the secret is overwritten.' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$after = wp_list_secrets();

		$this->assertIsArray( $after, 'wp_list_secrets() should return an array after the overwrite.' );
		$this->assertTrue( $after[0]['has_previous'], 'has_previous should be true after the secret is overwritten.' );
	}

	/**
	 * Ensure that the needs_rotation entry reflects the flag stored on the current version.
	 *
	 * The stored record is edited directly to set the flag, so the test exercises
	 * wp_list_secrets() without depending on wp_import_option_as_secret().
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_needs_rotation_reflects_the_current_slots_flag(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertCount( 1, $entries, 'Exactly one secret should be listed.' );
		$this->assertTrue( $entries[0]['needs_rotation'], 'needs_rotation should reflect the flag stored on the current slot.' );
	}

	/**
	 * Ensure that wp_list_secrets() returns only the secrets in the requested namespace.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_filters_by_namespace(): void {
		$this->assertTrue( wp_set_secret( 'pluginone/key', 'value' ), 'Setting pluginone/key to value should succeed.' );
		$this->assertTrue( wp_set_secret( 'plugintwo/key', 'value' ), 'Setting plugintwo/key to value should succeed.' );

		$entries = wp_list_secrets( 'pluginone' );

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertSame( array( 'pluginone/key' ), wp_list_pluck( $entries, 'name' ), 'Only secrets in the requested namespace should be listed.' );
	}

	/**
	 * Ensure that wp_list_secrets() with an empty namespace returns secrets from every namespace.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_empty_namespace_returns_everything(): void {
		$this->assertTrue( wp_set_secret( 'pluginone/key', 'value' ), 'Setting pluginone/key to value should succeed.' );
		$this->assertTrue( wp_set_secret( 'plugintwo/key', 'value' ), 'Setting plugintwo/key to value should succeed.' );

		$entries = wp_list_secrets( '' );

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertCount( 2, $entries, 'An empty namespace should list secrets from every namespace.' );
	}

	/**
	 * Ensure that a namespace filter matches whole namespaces, not partial prefixes of them.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_namespace_does_not_match_a_prefix_of_a_different_namespace(): void {
		// 'plugin' must not match 'pluginone/key' – only a full "namespace/" prefix.
		$this->assertTrue( wp_set_secret( 'pluginone/key', 'value' ), 'Setting pluginone/key to value should succeed.' );

		$this->assertSame( array(), wp_list_secrets( 'plugin' ), 'A namespace should not match a prefix of a different namespace.' );
	}

	/**
	 * A corrupted record must still appear in the list – Site Health's
	 * undecryptable-secrets check depends on being able to see it exists.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_a_corrupt_record_is_still_listed_with_blank_metadata(): void {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertCount( 1, $entries, 'The corrupt record should still be listed.' );
		$this->assertSame( 'myplugin/corrupt', $entries[0]['name'], 'The corrupt record entry should carry its name.' );
		$this->assertSame( '', $entries[0]['fingerprint'], 'The corrupt record entry should have a blank fingerprint.' );
		$this->assertFalse( $entries[0]['has_previous'], 'The corrupt record entry should report no previous version.' );
	}

	/**
	 * Ensure that wp_list_secrets() excludes network secrets.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_does_not_list_network_scope_secrets(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/site-only', 'value' ), 'Setting myplugin/site-only to value should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/network-only', 'value' ), 'Setting network secret myplugin/network-only to value should succeed.' );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries, 'wp_list_secrets() should return an array.' );
		$this->assertSame( array( 'myplugin/site-only' ), wp_list_pluck( $entries, 'name' ), 'Only site-scope secrets should be listed.' );
	}
}
