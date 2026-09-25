<?php
/**
 * Tests for wp_set_secret() and wp_get_secret().
 *
 * @group secrets
 */
class Tests_Secrets_WpSetSecretAndWpGetSecret extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	public function test_set_then_get_round_trips() {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'sk_live_secret' ) );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'sk_live_secret', $secret->reveal() );
		$this->assertSame( 'myplugin/api-key', $secret->get_name() );
		$this->assertNotSame( '', $secret->fingerprint() );
	}

	public function test_get_returns_null_for_an_absent_secret() {
		$this->assertNull( wp_get_secret( 'myplugin/never-set' ) );
	}

	public function test_get_defaults_to_the_current_version() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertSame(
			wp_get_secret( 'myplugin/api-key' )->reveal(),
			wp_get_secret( 'myplugin/api-key', WP_Secret_Version::CURRENT )->reveal()
		);
	}

	public function test_get_previous_on_a_never_rotated_secret_is_null_not_error() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * An unrecognised version is a caller mistake, and WordPress reports those as
	 * WP_Error plus a _doing_it_wrong() notice rather than by throwing. The
	 * WP_Error branch of the three-state contract already covers it, so this adds
	 * no fourth state.
	 */
	public function test_an_invalid_version_is_a_wp_error_not_an_exception() {
		$this->setExpectedIncorrectUsage( '_wp_secrets_get' );

		$result = wp_get_secret( 'myplugin/api-key', 'not-a-real-version' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function test_set_rejects_an_invalid_name() {
		$result = wp_set_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_get_rejects_an_invalid_name() {
		$result = wp_get_secret( 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_set_rejects_a_non_string_value() {
		$result = wp_set_secret( 'myplugin/api-key', array( 'not', 'a', 'string' ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_set_accepts_an_empty_string_value() {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', '' ) );
		$this->assertSame( '', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	/**
	 * Overwriting is the only repair path an operator has through this API, so a
	 * corrupted existing record must not block a write.
	 */
	public function test_a_corrupt_record_can_be_overwritten() {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$this->assertTrue( wp_set_secret( 'myplugin/corrupt', 'a fresh value' ) );
		$this->assertSame( 'a fresh value', wp_get_secret( 'myplugin/corrupt' )->reveal() );
	}

	/**
	 * Nothing plaintext reaches the database. Scans every option row rather than just
	 * the secret's own, since the failure this guards against is a plaintext landing
	 * somewhere unexpected.
	 */
	public function test_the_options_table_contains_no_plaintext_after_a_write() {
		global $wpdb;

		wp_set_secret( 'myplugin/api-key', 'UNIQUE-PLAINTEXT-CANARY-9f3a' );

		$this->assertNeverContainsPlaintext(
			'UNIQUE-PLAINTEXT-CANARY-9f3a',
			$wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}", ARRAY_A ),
			'The options table must never contain a plaintext secret.'
		);
	}

	/**
	 * Zeroing the internal plaintext must not reach the vault's copy.
	 *
	 * The retrieval path clears its own local copy once the WP_Secret owns the value;
	 * that only leaves the vault intact because PHP's copy-on-write splits the two
	 * references first.
	 */
	public function test_reveal_still_works_after_the_internal_memzero() {
		wp_set_secret( 'myplugin/api-key', 'must-survive-memzero' );

		$this->assertSame( 'must-survive-memzero', wp_get_secret( 'myplugin/api-key' )->reveal() );
	}

	public function test_no_capability_check_is_applied() {
		wp_set_current_user( 0 ); // No logged-in user, no capabilities at all.

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertInstanceOf( WP_Secret::class, wp_get_secret( 'myplugin/api-key' ) );
	}

	public function test_change_hook_fires_on_create_with_created_action() {
		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			6
		);

		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNotNull( $captured );
		list( $name, $action, $actor_id, $timestamp, $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'created', $action );
		$this->assertSame( '', $old_fingerprint );
		$this->assertNotSame( '', $new_fingerprint );
	}

	public function test_change_hook_fires_on_overwrite_with_updated_action() {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$first_fingerprint = wp_get_secret( 'myplugin/api-key' )->fingerprint();

		$captured = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured ) {
				$captured = $args;
			},
			10,
			6
		);

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		list( , $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'updated', $action );
		$this->assertSame( $first_fingerprint, $old_fingerprint );
		$this->assertNotSame( $old_fingerprint, $new_fingerprint );
	}

	public function test_change_hook_reports_the_current_user_as_actor() {
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

		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertSame( $user_id, $captured_actor );
	}

	public function test_change_hook_never_receives_a_value() {
		$captured_args = null;
		add_action(
			'wp_secret_changed',
			function ( ...$args ) use ( &$captured_args ) {
				$captured_args = $args;
			},
			10,
			6
		);

		wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' );

		foreach ( $captured_args as $arg ) {
			$this->assertIsScalar( $arg );
			$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', (string) $arg );
		}
	}
}
