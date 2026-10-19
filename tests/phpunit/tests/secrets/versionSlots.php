<?php
/**
 * Version slot behaviour: demotion on overwrite, PREVIOUS retrieval, and
 * wp_retire_secret_version(). PREVIOUS retrieval is
 * "the single most important new test in the suite" – the prior proof-of-concept
 * could not do this at all.
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

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( $slot, $record );
		$this->assertIsArray( $record[ $slot ] );

		return $record[ $slot ];
	}

	public function test_first_write_leaves_no_previous_slot(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * The headline case: PREVIOUS must actually decrypt back to the prior value.
	 */
	public function test_previous_returns_the_actual_prior_value(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value' );
	}

	public function test_third_write_discards_the_oldest_value(): void {
		wp_set_secret( 'myplugin/api-key', 'value-a' );
		wp_set_secret( 'myplugin/api-key', 'value-b' );
		wp_set_secret( 'myplugin/api-key', 'value-c' );

		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'value-b' );

		// value-a is gone from the stored record entirely, not merely unreachable
		// through the two named slots.
		$this->assertNeverContainsPlaintext( 'value-a', get_option( '_wp_secret_myplugin/api-key' ) );
	}

	public function test_previous_preserves_its_original_fingerprint(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $original );
		$original_fingerprint = $original->fingerprint();

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );
		$this->assertInstanceOf( WP_Secret::class, $previous );
		$this->assertSame( $original_fingerprint, $previous->fingerprint() );
	}

	public function test_previous_preserves_its_original_created_timestamp(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original_created = $this->stored_slot( WP_Secret_Version::CURRENT )['created'];

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$demoted_created = $this->stored_slot( WP_Secret_Version::PREVIOUS )['created'];

		$this->assertSame( $original_created, $demoted_created );
	}

	public function test_previous_preserves_its_needs_rotation_flag(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );

		// No public API sets this flag yet (that lands with wp_import_option_as_secret()
		// in a later commit); set it directly to prove demotion carries it forward.
		$record = get_option( '_wp_secret_myplugin/api-key' );
		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );
		$record['current']['needs_rotation'] = true;
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( $this->stored_slot( WP_Secret_Version::PREVIOUS )['needs_rotation'] );
	}

	/**
	 * Regression test for a demotion bug found while building this: a slot's AAD
	 * binds its ciphertext to the slot it occupies, so naively copying the current
	 * slot's stored bytes into 'previous' would leave it permanently undecryptable
	 * there. Demoting must re-encrypt, which means the stored ciphertext actually
	 * changes even though the plaintext does not.
	 */
	public function test_demotion_actually_reencrypts_rather_than_copying_ciphertext(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original_ciphertext = $this->stored_slot( WP_Secret_Version::CURRENT )['ct'];

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$demoted_ciphertext = $this->stored_slot( WP_Secret_Version::PREVIOUS )['ct'];

		$this->assertNotSame( $original_ciphertext, $demoted_ciphertext );
		// And it must still actually decrypt to the original plaintext.
		$previous = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );
		$this->assertInstanceOf( WP_Secret::class, $previous );
		$this->assertSame( 'first-value', $previous->reveal() );
	}

	public function test_previous_on_a_secret_with_no_previous_slot_is_null(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * If the outgoing current slot is already undecryptable (corrupted, or
	 * orphaned by a botched rotation), the write must still succeed rather than
	 * inheriting the old corruption – refusing here would be the exact
	 * corrupted-record-blocks-everything failure this API is built to avoid.
	 */
	public function test_a_write_succeeds_even_if_the_outgoing_current_slot_cannot_be_decrypted(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record = get_option( '_wp_secret_myplugin/api-key' );
		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'current', $record );
		$this->assertIsArray( $record['current'] );
		$record['current']['ct'] = base64_encode( 'not decryptable under any key' );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'new-value' ) );
		$current = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $current );
		$this->assertSame( 'new-value', $current->reveal() );
		// The undecryptable slot could not be demoted, so it is simply gone.
		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	public function test_retire_clears_previous_and_leaves_current_intact(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ) );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
		$current = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $current );
		$this->assertSame( 'second-value', $current->reveal() );
	}

	public function test_retire_on_a_secret_with_no_previous_slot_is_a_successful_noop(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertTrue( wp_retire_secret_version( 'myplugin/api-key' ) );
	}

	public function test_retire_on_a_never_set_secret_is_a_successful_noop(): void {
		$this->assertTrue( wp_retire_secret_version( 'myplugin/never-set' ) );
	}

	public function test_retire_rejects_an_invalid_name(): void {
		$result = wp_retire_secret_version( 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_retire_fires_the_change_hook_with_the_retired_action(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$original = wp_get_secret( 'myplugin/api-key' );
		$this->assertInstanceOf( WP_Secret::class, $original );
		$fingerprint_being_retired = $original->fingerprint();
		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		wp_retire_secret_version( 'myplugin/api-key' );

		$this->assertIsArray( $captured );
		$this->assertCount( 7, $captured );

		list( $name, $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'retired', $action );
		$this->assertSame( $fingerprint_being_retired, $old_fingerprint );
		$this->assertSame( '', $new_fingerprint );
	}

	public function test_retire_does_not_fire_the_change_hook_on_a_noop(): void {
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
