<?php
/**
 * Tests for wp_list_secrets().
 *
 * @group secrets
 */
class Tests_Secrets_WpListSecrets extends WP_UnitTestCase {

	public function test_returns_an_empty_array_when_nothing_is_set() {
		$this->assertSame( array(), wp_list_secrets() );
	}

	public function test_lists_every_secret_with_the_expected_keys() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$entries = wp_list_secrets();

		$this->assertCount( 1, $entries );
		$this->assertSame(
			array( 'name', 'fingerprint', 'created', 'has_previous', 'needs_rotation' ),
			array_keys( $entries[0] )
		);
		$this->assertSame( 'myplugin/api-key', $entries[0]['name'] );
	}

	/**
	 * Never a value, under any circumstance -- the entire justification for this
	 * function existing depends on it.
	 */
	public function test_never_returns_a_value() {
		wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' );

		$dump = wp_json_encode( wp_list_secrets() );

		$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', $dump );
	}

	public function test_fingerprint_matches_the_secrets_own_fingerprint() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$fingerprint = wp_get_secret( 'myplugin/api-key' )->fingerprint();
		$entries     = wp_list_secrets();

		$this->assertSame( $fingerprint, $entries[0]['fingerprint'] );
	}

	public function test_has_previous_is_false_before_a_rotation_and_true_after() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		$this->assertFalse( wp_list_secrets()[0]['has_previous'] );

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( wp_list_secrets()[0]['has_previous'] );
	}

	public function test_needs_rotation_reflects_the_current_slots_flag() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record                              = get_option( '_wp_secret_myplugin/api-key' );
		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertTrue( wp_list_secrets()[0]['needs_rotation'] );
	}

	public function test_filters_by_namespace() {
		wp_set_secret( 'pluginone/key', 'value' );
		wp_set_secret( 'plugintwo/key', 'value' );

		$names = wp_list_pluck( wp_list_secrets( 'pluginone' ), 'name' );

		$this->assertSame( array( 'pluginone/key' ), $names );
	}

	public function test_empty_namespace_returns_everything() {
		wp_set_secret( 'pluginone/key', 'value' );
		wp_set_secret( 'plugintwo/key', 'value' );

		$this->assertCount( 2, wp_list_secrets( '' ) );
	}

	public function test_namespace_does_not_match_a_prefix_of_a_different_namespace() {
		// 'plugin' must not match 'pluginone/key' -- only a full "namespace/" prefix.
		wp_set_secret( 'pluginone/key', 'value' );

		$this->assertSame( array(), wp_list_secrets( 'plugin' ) );
	}

	public function test_a_non_string_namespace_is_a_wp_error_not_an_exception() {
		$this->setExpectedIncorrectUsage( '_wp_secrets_list' );

		$result = wp_list_secrets( array( 'not', 'a', 'string' ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	/**
	 * A corrupted record must still appear in the list -- Site Health's
	 * undecryptable-secrets check depends on being able to see it exists.
	 */
	public function test_a_corrupt_record_is_still_listed_with_blank_metadata() {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$entries = wp_list_secrets();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'myplugin/corrupt', $entries[0]['name'] );
		$this->assertSame( '', $entries[0]['fingerprint'] );
		$this->assertFalse( $entries[0]['has_previous'] );
	}

	public function test_does_not_list_network_scope_secrets() {
		wp_set_secret( 'myplugin/site-only', 'value' );
		_wp_secrets_set( 'myplugin/network-only', 'value', true );

		$names = wp_list_pluck( wp_list_secrets(), 'name' );

		$this->assertSame( array( 'myplugin/site-only' ), $names );
	}
}
