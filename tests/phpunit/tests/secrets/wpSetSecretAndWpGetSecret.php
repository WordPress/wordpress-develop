<?php
/**
 * Tests for wp_set_secret() and wp_get_secret().
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpSetSecretAndWpGetSecret extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	/**
	 * Ensure that a stored secret is retrieved with its original value, name and a fingerprint.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_set_then_get_round_trips(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'sk_live_secret' ), 'wp_set_secret() should return true on success.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'wp_get_secret() should return a WP_Secret.' );
		$this->assertSame( 'sk_live_secret', $secret->reveal(), 'The secret should reveal its original value.' );
		$this->assertSame( 'myplugin/api-key', $secret->get_name(), 'The secret should report its name.' );
		$this->assertNotSame( '', $secret->fingerprint(), 'The secret fingerprint should not be empty.' );
	}

	/**
	 * Ensure that wp_get_secret() returns the current version when no version is requested.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_get_defaults_to_the_current_version(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$default = wp_get_secret( 'myplugin/api-key' );
		$current = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( WP_Secret::class, $default, 'The default lookup should return a WP_Secret.' );
		$this->assertInstanceOf( WP_Secret::class, $current, 'The current version lookup should return a WP_Secret.' );
		$this->assertSame( $default->reveal(), $current->reveal(), 'The default lookup should reveal the same value as the current version.' );
	}

	/**
	 * An unrecognised version is a caller mistake, and WordPress reports those as
	 * WP_Error plus a _doing_it_wrong() notice rather than by throwing. The
	 * WP_Error branch of the three-state contract already covers it, so this adds
	 * no fourth state.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_an_invalid_version_is_a_wp_error_not_an_exception(): void {
		$this->setExpectedIncorrectUsage( '_wp_secrets_get' );

		$result = wp_get_secret( 'myplugin/api-key', 'not-a-real-version' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'An invalid version should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid version should use the invalid argument error code.' );
	}

	/**
	 * Ensure that wp_set_secret() returns an invalid name error for a malformed name.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_set_rejects_an_invalid_name(): void {
		$result = wp_set_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result, 'An invalid name should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should use the invalid name error code.' );
	}

	/**
	 * Ensure that wp_get_secret() returns an invalid name error for a malformed name.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_get_rejects_an_invalid_name(): void {
		$result = wp_get_secret( 'Not A Valid Name' );

		$this->assertWPError( $result, 'An invalid name should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should use the invalid name error code.' );
	}

	/**
	 * Ensure that wp_set_secret() returns an invalid value error for a non-string value.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_set_rejects_a_non_string_value(): void {
		$result = wp_set_secret( 'myplugin/api-key', array( 'not', 'a', 'string' ) ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'A non-string value should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'A non-string value should use the invalid value error code.' );
	}

	/**
	 * Ensure that an empty string can be stored and retrieved as a secret value.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_set_accepts_an_empty_string_value(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', '' ), 'Storing an empty string should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The empty secret should be retrievable as a WP_Secret.' );
		$this->assertSame( '', $secret->reveal(), 'The secret should reveal an empty string.' );
	}

	/**
	 * Overwriting is the only repair path an operator has through this API, so a
	 * corrupted existing record must not block a write.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_a_corrupt_record_can_be_overwritten(): void {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$this->assertTrue( wp_set_secret( 'myplugin/corrupt', 'a fresh value' ), 'Overwriting a corrupt record should succeed.' );

		$secret = wp_get_secret( 'myplugin/corrupt' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The overwritten secret should be retrievable as a WP_Secret.' );
		$this->assertSame( 'a fresh value', $secret->reveal(), 'The secret should reveal the fresh value.' );
	}

	/**
	 * Nothing plaintext reaches the database. Scans every option row rather than just
	 * the secret's own, since the failure this guards against is a plaintext landing
	 * somewhere unexpected.
	 *
	 * @covers ::wp_set_secret
	 * @covers WP_Secrets_Libsodium_Provider::set
	 */
	public function test_the_options_table_contains_no_plaintext_after_a_write(): void {
		global $wpdb;

		$this->assertInstanceOf( wpdb::class, $wpdb, 'The global $wpdb should be a wpdb instance.' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' ), 'Setting myplugin/api-key to UNIQUE-PLAINTEXT-CANARY-9f3a should succeed.' );

		$this->assertNeverContainsPlaintext(
			'UNIQUE-PLAINTEXT-CANARY-9f3a',
			$wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}", ARRAY_A ),
			'The options table should never contain a plaintext secret.'
		);
	}

	/**
	 * Zeroing the internal plaintext must not reach the vault's copy.
	 *
	 * The retrieval path clears its own local copy once the WP_Secret owns the value;
	 * that only leaves the vault intact because PHP's copy-on-write splits the two
	 * references first.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_reveal_still_works_after_the_internal_memzero(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'must-survive-memzero' ), 'Setting myplugin/api-key to must-survive-memzero should succeed.' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The stored secret should be retrievable as a WP_Secret.' );
		$this->assertSame( 'must-survive-memzero', $secret->reveal(), 'The secret should reveal its value after the internal memzero.' );
	}

	/**
	 * Ensure that wp_set_secret() and wp_get_secret() succeed without a logged-in user.
	 *
	 * Capability checks belong at the operator boundary (CLI, admin screens), not in the
	 * API itself, so plugins can use their own secrets from any context, such as cron.
	 *
	 * @covers ::wp_set_secret
	 * @covers ::wp_get_secret
	 */
	public function test_no_capability_check_is_applied(): void {
		wp_set_current_user( 0 ); // No logged-in user, no capabilities at all.

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Storing a secret without a logged-in user should succeed.' );
		$this->assertInstanceOf( WP_Secret::class, wp_get_secret( 'myplugin/api-key' ), 'Retrieving a secret without a logged-in user should return a WP_Secret.' );
	}

	/**
	 * Ensure that storing a new secret fires wp_secret_changed as created, with its actor and time.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_change_hook_fires_on_create_with_created_action(): void {
		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		$before = time();
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );
		$after = time();

		$this->assertNotNull( $captured, 'The wp_secret_changed hook should fire.' );
		list( $name, $action, $actor_id, $timestamp, $old_fingerprint, $new_fingerprint, $network ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name, 'The hook should receive the secret name.' );
		$this->assertSame( 'created', $action, 'The hook action should be created.' );
		$this->assertSame( 0, $actor_id, 'With no user logged in, the actor should be reported as 0.' );
		$this->assertGreaterThanOrEqual( $before, $timestamp, 'The timestamp should not predate the write.' );
		$this->assertLessThanOrEqual( $after, $timestamp, 'The timestamp should not postdate the write.' );
		$this->assertSame( '', $old_fingerprint, 'The old fingerprint should be empty for a new secret.' );
		$this->assertNotSame( '', $new_fingerprint, 'The new fingerprint should not be empty.' );
		$this->assertFalse( $network, 'The network flag should be false for a site secret.' );
	}

	/**
	 * Ensure that overwriting a secret fires wp_secret_changed as updated, with a new fingerprint.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_change_hook_fires_on_overwrite_with_updated_action(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Setting myplugin/api-key to first-value should succeed.' );
		$first = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $first, 'The first secret should be retrievable as a WP_Secret.' );
		$first_fingerprint = $first->fingerprint();

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Setting myplugin/api-key to second-value should succeed.' );

		$this->assertIsArray( $captured, 'The wp_secret_changed hook should fire.' );
		list( , $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'updated', $action, 'The hook action should be updated.' );
		$this->assertSame( $first_fingerprint, $old_fingerprint, 'The old fingerprint should match the first value.' );
		$this->assertNotSame( $old_fingerprint, $new_fingerprint, 'The new fingerprint should differ from the old one.' );
	}

	/**
	 * Ensure that wp_secret_changed reports the current user's ID as the actor.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_change_hook_reports_the_current_user_as_actor(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$captured_actor = null;
		add_action(
			'wp_secret_changed',
			function ( $name, $action, $actor_id ) use ( &$captured_actor ) {
				$captured_actor = $actor_id;
			},
			10,
			3
		);

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$this->assertSame( $user_id, $captured_actor, 'The hook should report the current user as the actor.' );
	}

	/**
	 * Ensure that wp_secret_changed never receives the secret's plaintext value.
	 *
	 * Any callback can listen to this hook, including logging and auditing plugins, so
	 * every argument passed to it must be safe to record.
	 *
	 * @covers ::wp_set_secret
	 */
	public function test_change_hook_never_receives_a_value(): void {
		$captured_args = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured_args ) {
				$captured_args = $args;
			},
			10,
			7
		);

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' ), 'Setting myplugin/api-key to a-plaintext-value-that-must-not-leak should succeed.' );

		$this->assertIsArray( $captured_args, 'The wp_secret_changed hook should fire.' );

		foreach ( $captured_args as $arg ) {
			$this->assertIsScalar( $arg, 'Each hook argument should be scalar.' );
			$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', (string) $arg, 'No hook argument should contain the plaintext value.' );
		}
	}
}
