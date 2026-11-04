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
		$this->assertSame( array(), wp_list_secrets() );
	}

	/**
	 * Ensure that each wp_list_secrets() entry has exactly the documented metadata keys, in order.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_lists_every_secret_with_the_expected_keys(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries );
		$this->assertCount( 1, $entries );
		$this->assertSame(
			array( 'name', 'fingerprint', 'created', 'has_previous', 'needs_rotation' ),
			array_keys( $entries[0] )
		);
		$this->assertSame( 'myplugin/api-key', $entries[0]['name'] );
	}

	/**
	 * Never a value, under any circumstance – the entire justification for this
	 * function existing depends on it.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_never_returns_a_value(): void {
		wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' );

		$dump = wp_json_encode( wp_list_secrets() );

		$this->assertIsString( $dump );
		$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', $dump );
	}

	/**
	 * Ensure that a listed fingerprint matches WP_Secret::fingerprint() for the same secret.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_fingerprint_matches_the_secrets_own_fingerprint(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$secret  = wp_get_secret( 'myplugin/api-key' );
		$entries = wp_list_secrets();

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertIsArray( $entries );
		$this->assertSame( $secret->fingerprint(), $entries[0]['fingerprint'] );
	}

	/**
	 * Ensure that has_previous is false for a new secret and true once it has been overwritten.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_has_previous_is_false_before_a_rotation_and_true_after(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		$before = wp_list_secrets();

		$this->assertIsArray( $before );
		$this->assertFalse( $before[0]['has_previous'] );

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$after = wp_list_secrets();

		$this->assertIsArray( $after );
		$this->assertTrue( $after[0]['has_previous'] );
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
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );

		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries );
		$this->assertTrue( $entries[0]['needs_rotation'] );
	}

	/**
	 * Ensure that wp_list_secrets() returns only the secrets in the requested namespace.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_filters_by_namespace(): void {
		wp_set_secret( 'pluginone/key', 'value' );
		wp_set_secret( 'plugintwo/key', 'value' );

		$entries = wp_list_secrets( 'pluginone' );

		$this->assertIsArray( $entries );
		$this->assertSame( array( 'pluginone/key' ), wp_list_pluck( $entries, 'name' ) );
	}

	/**
	 * Ensure that wp_list_secrets() with an empty namespace returns secrets from every namespace.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_empty_namespace_returns_everything(): void {
		wp_set_secret( 'pluginone/key', 'value' );
		wp_set_secret( 'plugintwo/key', 'value' );

		$entries = wp_list_secrets( '' );

		$this->assertIsArray( $entries );
		$this->assertCount( 2, $entries );
	}

	/**
	 * Ensure that a namespace filter matches whole namespaces, not partial prefixes of them.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_namespace_does_not_match_a_prefix_of_a_different_namespace(): void {
		// 'plugin' must not match 'pluginone/key' – only a full "namespace/" prefix.
		wp_set_secret( 'pluginone/key', 'value' );

		$this->assertSame( array(), wp_list_secrets( 'plugin' ) );
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

		$this->assertIsArray( $entries );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'myplugin/corrupt', $entries[0]['name'] );
		$this->assertSame( '', $entries[0]['fingerprint'] );
		$this->assertFalse( $entries[0]['has_previous'] );
	}

	/**
	 * Ensure that wp_list_secrets() excludes network secrets.
	 *
	 * @covers ::wp_list_secrets
	 */
	public function test_does_not_list_network_scope_secrets(): void {
		wp_set_secret( 'myplugin/site-only', 'value' );
		_wp_secrets_set( 'myplugin/network-only', 'value', true );

		$entries = wp_list_secrets();

		$this->assertIsArray( $entries );
		$this->assertSame( array( 'myplugin/site-only' ), wp_list_pluck( $entries, 'name' ) );
	}
}
