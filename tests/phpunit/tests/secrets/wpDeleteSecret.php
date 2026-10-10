<?php
/**
 * Tests for wp_delete_secret().
 *
 * @group secrets
 */
class Tests_Secrets_WpDeleteSecret extends WP_UnitTestCase {

	public function test_deletes_an_existing_secret(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertTrue( wp_delete_secret( 'myplugin/api-key' ) );
		$this->assertNull( wp_get_secret( 'myplugin/api-key' ) );
	}

	public function test_deleting_an_absent_secret_is_not_an_error(): void {
		$this->assertTrue( wp_delete_secret( 'myplugin/never-set' ) );
	}

	public function test_rejects_an_invalid_name(): void {
		$result = wp_delete_secret( 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_change_hook_fires_with_deleted_action(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );
		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );

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

		wp_delete_secret( 'myplugin/api-key' );

		$this->assertIsArray( $captured );

		list( $name, $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'deleted', $action );
		$this->assertSame( $fingerprint, $old_fingerprint );
		$this->assertSame( '', $new_fingerprint );
	}

	public function test_change_hook_does_not_fire_when_nothing_was_deleted(): void {
		$fired = false;
		add_action(
			'wp_secret_changed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		wp_delete_secret( 'myplugin/never-set' );

		$this->assertFalse( $fired );
	}

	/**
	 * A corrupted record must still be removable. Refusing here would leave a secret
	 * permanently stuck – unreadable, unrepairable, and undeletable without editing
	 * the database by hand.
	 */
	public function test_a_corrupt_record_is_still_deletable(): void {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$this->assertTrue( wp_delete_secret( 'myplugin/corrupt' ) );
		$this->assertNull( wp_get_secret( 'myplugin/corrupt' ) );
	}

	public function test_no_capability_check_is_applied(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );
		wp_set_current_user( 0 );

		$this->assertTrue( wp_delete_secret( 'myplugin/api-key' ) );
	}
}
