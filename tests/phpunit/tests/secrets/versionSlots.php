<?php
/**
 * Tests for version slots: demotion on overwrite, retrieval of the previous
 * version, and wp_retire_secret_version().
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_VersionSlots extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	/**
	 * Reads one slot of the stored record for 'myplugin/api-key' straight from
	 * the options table, bypassing the API.
	 *
	 * @param string $slot A WP_Secret_Version constant.
	 * @return array<mixed> The slot as stored.
	 */
	private function stored_slot( string $slot ): array {
		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( $slot, $record, 'The stored record should contain the requested slot.' );
		$this->assertIsArray( $record[ $slot ], 'The requested slot of the stored record should be an array.' );

		return $record[ $slot ];
	}

	/**
	 * The headline case: PREVIOUS must actually decrypt back to the prior value.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_previous_returns_the_actual_prior_value(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', false, 'The previous slot should decrypt to the first value.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', false, 'The current slot should decrypt to the second value.' );
	}

	/**
	 * Ensure that a third write discards the oldest value from the stored record.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_third_write_discards_the_oldest_value(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value-a' ), 'Setting myplugin/api-key to value-a should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value-b' ), 'Setting myplugin/api-key to value-b should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value-c' ), 'Setting myplugin/api-key to value-c should succeed.' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'value-b', false, 'The previous slot should decrypt to the second of three values.' );

		/*
		 * value-a is gone from the stored record entirely, not merely unreachable
		 * through the two named slots.
		 */
		$this->assertNeverContainsPlaintext( 'value-a', get_option( '_wp_secret_myplugin/api-key' ), 'The stored record should no longer contain the oldest value.' );
	}

	/**
	 * Saving the current value again, as a settings form does, must not discard
	 * the previous value a rotation still depends on.
	 *
	 * @ticket 66353
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_rewriting_the_current_value_keeps_the_previous_slot(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value again should succeed.' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', false, 'Rewriting the current value should leave the previous slot holding the first value.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', false, 'The current slot should still decrypt to the rewritten value.' );
	}

	/**
	 * Ensure that writing the value a secret already holds succeeds without changing the stored record.
	 *
	 * @ticket 66353
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_rewriting_the_current_value_leaves_the_stored_record_untouched(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );
		$before = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Rewriting the current value should report success.' );

		$this->assertSame( $before, get_option( '_wp_secret_myplugin/api-key' ), 'Rewriting the current value should leave the stored record unchanged.' );
	}

	/**
	 * Ensure that writing the value a secret already holds does not fire wp_secret_changed.
	 *
	 * @ticket 66353
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_rewriting_the_current_value_does_not_fire_the_change_hook(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$fired = false;
		add_action(
			'wp_secret_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value again should succeed.' );

		$this->assertFalse( $fired, 'Rewriting the current value should not fire wp_secret_changed.' );
	}

	/**
	 * Re-saving an imported value through a settings form has not rotated it.
	 *
	 * @ticket 66353
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_rewriting_the_current_value_keeps_its_needs_rotation_flag(): void {
		update_option( 'my_plugin_api_key', 'imported-value' );
		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing my_plugin_api_key as myplugin/api-key should succeed.' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'imported-value' ), 'Setting myplugin/api-key to imported-value should succeed.' );

		$this->assertTrue( $this->stored_slot( WP_Secret_Version::CURRENT )['needs_rotation'], 'Rewriting an imported value should keep its needs_rotation flag.' );
	}

	/**
	 * Importing an option that holds the value already stored still means that
	 * value sat in plaintext, so it is flagged, without discarding the previous
	 * version.
	 *
	 * @ticket 66353
	 *
	 * @covers ::wp_import_option_as_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_importing_the_current_value_flags_it_and_keeps_the_previous_slot(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );
		update_option( 'my_plugin_api_key', 'second-value' );

		$this->assertTrue( wp_import_option_as_secret( 'my_plugin_api_key', 'myplugin/api-key' ), 'Importing an option holding the current value should succeed.' );

		$this->assertTrue( $this->stored_slot( WP_Secret_Version::CURRENT )['needs_rotation'], 'The imported current value should be flagged as needing rotation.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', false, 'Importing the current value should leave the previous slot holding the first value.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', false, 'The current slot should still decrypt to the imported value.' );
	}

	/**
	 * Ensure that a demoted value keeps the fingerprint it had as the current version.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_previous_preserves_its_original_fingerprint(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$original = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $original, 'The first value should be readable as a WP_Secret.' );
		$original_fingerprint = $original->fingerprint();

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );
		$this->assertInstanceOf( WP_Secret::class, $previous, 'The demoted value should be readable as a WP_Secret.' );
		$this->assertSame( $original_fingerprint, $previous->fingerprint(), 'The demoted value should keep its original fingerprint.' );
	}

	/**
	 * Ensure that a demoted value keeps the created timestamp from its original write.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_previous_preserves_its_original_created_timestamp(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$original_created = $this->stored_slot( WP_Secret_Version::CURRENT )['created'];

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$demoted_created = $this->stored_slot( WP_Secret_Version::PREVIOUS )['created'];

		$this->assertSame( $original_created, $demoted_created, 'The demoted value should keep its original created timestamp.' );
	}

	/**
	 * Ensure that a demoted value carries its needs-rotation flag into the previous slot.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_previous_preserves_its_needs_rotation_flag(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );

		/*
		 * Set the flag directly rather than through wp_import_option_as_secret(), so
		 * this test depends only on demotion carrying the flag forward.
		 */
		$record = get_option( '_wp_secret_myplugin/api-key' );
		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot of the stored record should be an array.' );
		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$this->assertTrue( $this->stored_slot( WP_Secret_Version::PREVIOUS )['needs_rotation'], 'The demoted value should keep its needs_rotation flag.' );
	}

	/**
	 * Regression test for a demotion bug found while building this: a slot's AAD
	 * binds its ciphertext to the slot it occupies, so naively copying the current
	 * slot's stored bytes into 'previous' would leave it permanently undecryptable
	 * there. Demoting must re-encrypt, which means the stored ciphertext actually
	 * changes even though the plaintext does not.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_demotion_actually_reencrypts_rather_than_copying_ciphertext(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$original_ciphertext = $this->stored_slot( WP_Secret_Version::CURRENT )['ct'];

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$demoted_ciphertext = $this->stored_slot( WP_Secret_Version::PREVIOUS )['ct'];

		$this->assertNotSame( $original_ciphertext, $demoted_ciphertext, 'Demotion should re-encrypt the value rather than copy its ciphertext.' );
		// And it must still actually decrypt to the original plaintext.
		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );
		$this->assertInstanceOf( WP_Secret::class, $previous, 'The demoted value should be readable as a WP_Secret.' );
		$this->assertSame( 'first-value', $previous->reveal(), 'The demoted value should decrypt to the original plaintext.' );
	}

	/**
	 * If the outgoing current slot is already undecryptable (corrupted, or
	 * orphaned by a botched rotation), the write must still succeed rather than
	 * inheriting the old corruption – refusing here would be the exact
	 * corrupted-record-blocks-everything failure this API is built to avoid.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_a_write_succeeds_even_if_the_outgoing_current_slot_cannot_be_decrypted(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );
		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot of the stored record should be an array.' );
		$record['current']['ct'] = base64_encode( 'not decryptable under any key' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'new-value' ), 'A write over an undecryptable current slot should succeed.' );
		$current = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $current, 'The new current value should be readable as a WP_Secret.' );
		$this->assertSame( 'new-value', $current->reveal(), 'The new current value should decrypt to the value just written.' );
		// The undecryptable slot could not be demoted, so it is simply gone.
		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ), 'An undecryptable current slot should not be demoted to the previous slot.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() removes the previous version and keeps the current one.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_clears_previous_and_leaves_current_intact(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ), 'Retiring the previous version should succeed.' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ), 'The previous version should be gone after retiring it.' );
		$current = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $current, 'The current version should still be readable as a WP_Secret.' );
		$this->assertSame( 'second-value', $current->reveal(), 'The current version should be unchanged by retiring the previous one.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() returns true for a secret with no previous version.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_on_a_secret_with_no_previous_slot_is_a_successful_noop(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ), 'Retiring with no previous version should report success.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() returns true for a secret that was never set.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_on_a_never_set_secret_is_a_successful_noop(): void {
		$this->assertTrue( wp_retire_secret_version( 'myplugin/never-set' ), 'Retiring a secret that was never set should report success.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() rejects an invalid secret name.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_rejects_an_invalid_name(): void {
		$result = wp_retire_secret_version( 'Not A Valid Name' );

		$this->assertWPError( $result, 'An invalid name should be rejected with a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should be rejected with WP_SECRETS_ERROR_INVALID_NAME.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() fires wp_secret_changed as retired, with the old fingerprint.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_fires_the_change_hook_with_the_retired_action(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$original = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $original, 'The first value should be readable as a WP_Secret.' );
		$fingerprint_being_retired = $original->fingerprint();
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ), 'Retiring the previous version of myplugin/api-key should succeed.' );

		$this->assertIsArray( $captured, 'wp_secret_changed should fire when the previous version is retired.' );
		$this->assertCount( 7, $captured, 'wp_secret_changed should be passed seven arguments.' );

		list( $name, $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name, 'The change hook should report the name of the secret.' );
		$this->assertSame( 'retired', $action, 'The change hook should report the retired action.' );
		$this->assertSame( $fingerprint_being_retired, $old_fingerprint, 'The change hook should report the retired fingerprint as the old fingerprint.' );
		$this->assertSame( '', $new_fingerprint, 'The change hook should report an empty new fingerprint.' );
	}

	/**
	 * Ensure that wp_retire_secret_version() does not fire wp_secret_changed when nothing is retired.
	 *
	 * @covers ::wp_retire_secret_version
	 */
	public function test_retire_does_not_fire_the_change_hook_on_a_noop(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$fired = false;
		add_action(
			'wp_secret_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ), 'Retiring the previous version of myplugin/api-key should succeed.' );

		$this->assertFalse( $fired, 'A no-op retire should not fire wp_secret_changed.' );
	}
}
