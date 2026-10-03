<?php
/**
 * Version slot behaviour: demotion on overwrite, PREVIOUS retrieval, and
 * wp_retire_secret_version(). PREVIOUS retrieval is
 * "the single most important new test in the suite" -- the prior proof-of-concept
 * could not do this at all.
 *
 * @group secrets
 */
class Tests_Secrets_VersionSlots extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	public function test_first_write_leaves_no_previous_slot() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * The headline case: PREVIOUS must actually decrypt back to the prior value.
	 */
	public function test_previous_returns_the_actual_prior_value() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value' );
	}

	public function test_third_write_discards_the_oldest_value() {
		wp_set_secret( 'myplugin/api-key', 'value-a' );
		wp_set_secret( 'myplugin/api-key', 'value-b' );
		wp_set_secret( 'myplugin/api-key', 'value-c' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'value-b' );

		// value-a is gone from the stored record entirely, not merely unreachable
		// through the two named slots.
		$this->assertNeverContainsPlaintext( 'value-a', get_option( '_wp_secret_myplugin/api-key' ) );
	}

	public function test_previous_preserves_its_original_fingerprint() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original_fingerprint = wp_get_secret( 'myplugin/api-key' )->fingerprint();

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertSame( $original_fingerprint, wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS )->fingerprint() );
	}

	public function test_previous_preserves_its_original_created_timestamp() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original_created = get_option( '_wp_secret_myplugin/api-key' )['current']['created'];

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$demoted_created = get_option( '_wp_secret_myplugin/api-key' )['previous']['created'];

		$this->assertSame( $original_created, $demoted_created );
	}

	public function test_previous_preserves_its_needs_rotation_flag() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		// No public API sets this flag yet (that lands with wp_import_option_as_secret()
		// in a later commit); set it directly to prove demotion carries it forward.
		$record                              = get_option( '_wp_secret_myplugin/api-key' );
		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( get_option( '_wp_secret_myplugin/api-key' )['previous']['needs_rotation'] );
	}

	/**
	 * Regression test for a demotion bug found while building this: a slot's AAD
	 * binds its ciphertext to the slot it occupies, so naively copying the current
	 * slot's stored bytes into 'previous' would leave it permanently undecryptable
	 * there. Demoting must re-encrypt, which means the stored ciphertext actually
	 * changes even though the plaintext does not.
	 */
	public function test_demotion_actually_reencrypts_rather_than_copying_ciphertext() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original_ciphertext = get_option( '_wp_secret_myplugin/api-key' )['current']['ct'];

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$demoted_ciphertext = get_option( '_wp_secret_myplugin/api-key' )['previous']['ct'];

		$this->assertNotSame( $original_ciphertext, $demoted_ciphertext );
		// And it must still actually decrypt to the original plaintext.
		$this->assertSame( 'first-value', wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS )->reveal() );
	}

	public function test_previous_on_a_secret_with_no_previous_slot_is_null() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * If the outgoing current slot is already undecryptable (corrupted, or
	 * orphaned by a botched rotation), the write must still succeed rather than
	 * inheriting the old corruption -- refusing here would be the exact
	 * corrupted-record-blocks-everything failure this API is built to avoid.
	 */
	public function test_a_write_succeeds_even_if_the_outgoing_current_slot_cannot_be_decrypted() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record                  = get_option( '_wp_secret_myplugin/api-key' );
		$record['current']['ct'] = base64_encode( 'not decryptable under any key' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'new-value' ) );
		$this->assertSame( 'new-value', wp_get_secret( 'myplugin/api-key' )->reveal() );
		// The undecryptable slot could not be demoted, so it is simply gone.
		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	public function test_retire_clears_previous_and_leaves_current_intact() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ) );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
		$this->assertSame( 'second-value', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	public function test_retire_on_a_secret_with_no_previous_slot_is_a_successful_noop() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ) );
	}

	public function test_retire_on_a_never_set_secret_is_a_successful_noop() {
		$this->assertTrue( wp_retire_secret_version( 'myplugin/never-set' ) );
	}

	public function test_retire_rejects_an_invalid_name() {
		$result = wp_retire_secret_version( 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_retire_fires_the_change_hook_with_the_retired_action() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$fingerprint_being_retired = wp_get_secret( 'myplugin/api-key' )->fingerprint();
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			6
		);

		wp_retire_secret_version( 'myplugin/api-key' );

		list( $name, $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'retired', $action );
		$this->assertSame( $fingerprint_being_retired, $old_fingerprint );
		$this->assertSame( '', $new_fingerprint );
	}

	public function test_retire_does_not_fire_the_change_hook_on_a_noop() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$fired = false;
		add_action(
			'wp_secret_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		wp_retire_secret_version( 'myplugin/api-key' );

		$this->assertFalse( $fired );
	}
}
