<?php
/**
 * Tests for wp_delete_secret().
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WpDeleteSecret extends WP_UnitTestCase {

	/**
	 * Ensure that wp_delete_secret() removes a stored secret so it can no longer be retrieved.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_deletes_an_existing_secret(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$this->assertTrue( wp_delete_secret( 'myplugin/api-key' ), 'Deleting an existing secret should return true.' );
		$this->assertNull( wp_get_secret( 'myplugin/api-key' ), 'A deleted secret should no longer be retrievable.' );
	}

	/**
	 * Ensure that wp_delete_secret() returns true for a secret that was never stored.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_deleting_an_absent_secret_is_not_an_error(): void {
		$this->assertTrue( wp_delete_secret( 'myplugin/never-set' ), 'Deleting an absent secret should return true.' );
	}

	/**
	 * Ensure that wp_delete_secret() returns an invalid name error for a malformed name.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_rejects_an_invalid_name(): void {
		$result = wp_delete_secret( 'Not A Valid Name' );

		$this->assertWPError( $result, 'An invalid name should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should use the invalid name error code.' );
	}

	/**
	 * Ensure that deleting a secret fires wp_secret_changed with the deleted action and old fingerprint.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_change_hook_fires_with_deleted_action(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );
		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret, 'The stored secret should be retrievable as a WP_Secret.' );

		$fingerprint = $secret->fingerprint();

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			7
		);

		$this->assertTrue( wp_delete_secret( 'myplugin/api-key' ), 'Deleting myplugin/api-key should succeed.' );

		$this->assertIsArray( $captured, 'The wp_secret_changed hook should fire.' );

		list( $name, $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name, 'The hook should receive the secret name.' );
		$this->assertSame( 'deleted', $action, 'The hook action should be deleted.' );
		$this->assertSame( $fingerprint, $old_fingerprint, 'The old fingerprint should match the deleted secret.' );
		$this->assertSame( '', $new_fingerprint, 'The new fingerprint should be empty after deletion.' );
	}

	/**
	 * Ensure that wp_secret_changed does not fire when deleting a secret that does not exist.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_change_hook_does_not_fire_when_nothing_was_deleted(): void {
		$fired = false;
		add_action(
			'wp_secret_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->assertTrue( wp_delete_secret( 'myplugin/never-set' ), 'Deleting myplugin/never-set should succeed.' );

		$this->assertFalse( $fired, 'The hook should not fire when nothing was deleted.' );
	}

	/**
	 * A corrupted record must still be removable. Refusing here would leave a secret
	 * permanently stuck – unreadable, unrepairable, and undeletable without editing
	 * the database by hand.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_a_corrupt_record_is_still_deletable(): void {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$this->assertTrue( wp_delete_secret( 'myplugin/corrupt' ), 'Deleting a corrupt record should return true.' );
		$this->assertNull( wp_get_secret( 'myplugin/corrupt' ), 'A deleted corrupt record should no longer be retrievable.' );
	}

	/**
	 * Ensure that wp_delete_secret() succeeds without a logged-in user.
	 *
	 * Capability checks belong at the operator boundary (CLI, admin screens), not in the
	 * API itself, so plugins can manage their own secrets from any context.
	 *
	 * @covers ::wp_delete_secret
	 */
	public function test_no_capability_check_is_applied(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );
		wp_set_current_user( 0 );

		$this->assertTrue( wp_delete_secret( 'myplugin/api-key' ), 'Deleting a secret without a logged-in user should succeed.' );
	}
}
