<?php
/**
 * Tests for wp_set_secret() and wp_get_secret().
 *
 * @group secrets
 */
class Tests_Secrets_WpSetSecretAndWpGetSecret extends WP_UnitTestCase {

	use WP_Secrets_Assertions;

	public function test_set_then_get_round_trips(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'sk_live_secret' ) );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'sk_live_secret', $secret->reveal() );
		$this->assertSame( 'myplugin/api-key', $secret->get_name() );
		$this->assertNotSame( '', $secret->fingerprint() );
	}

	public function test_get_returns_null_for_an_absent_secret(): void {
		$this->assertNull( wp_get_secret( 'myplugin/never-set' ) );
	}

	public function test_get_defaults_to_the_current_version(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$default = wp_get_secret( 'myplugin/api-key' );
		$current = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::CURRENT );

		$this->assertInstanceOf( WP_Secret::class, $default );
		$this->assertInstanceOf( WP_Secret::class, $current );
		$this->assertSame( $default->reveal(), $current->reveal() );
	}

	public function test_get_previous_on_a_never_rotated_secret_is_null_not_error(): void {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNull( wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS ) );
	}

	/**
	 * An unrecognised version is a caller mistake, and WordPress reports those as
	 * WP_Error plus a _doing_it_wrong() notice rather than by throwing. The
	 * WP_Error branch of the three-state contract already covers it, so this adds
	 * no fourth state.
	 */
	public function test_an_invalid_version_is_a_wp_error_not_an_exception(): void {
		$this->setExpectedIncorrectUsage( '_wp_secrets_get' );

		$result = wp_get_secret( 'myplugin/api-key', 'not-a-real-version' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function test_set_rejects_an_invalid_name(): void {
		$result = wp_set_secret( 'Not A Valid Name', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_get_rejects_an_invalid_name(): void {
		$result = wp_get_secret( 'Not A Valid Name' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_set_rejects_a_non_string_value(): void {
		$result = wp_set_secret( 'myplugin/api-key', array( 'not', 'a', 'string' ) ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_set_accepts_an_empty_string_value(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', '' ) );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( '', $secret->reveal() );
	}

	/**
	 * Overwriting is the only repair path an operator has through this API, so a
	 * corrupted existing record must not block a write.
	 */
	public function test_a_corrupt_record_can_be_overwritten(): void {
		update_option( '_wp_secret_myplugin/corrupt', 'not a record at all', false );

		$this->assertTrue( wp_set_secret( 'myplugin/corrupt', 'a fresh value' ) );

		$secret = wp_get_secret( 'myplugin/corrupt' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'a fresh value', $secret->reveal() );
	}

	/**
	 * Nothing plaintext reaches the database. Scans every option row rather than just
	 * the secret's own, since the failure this guards against is a plaintext landing
	 * somewhere unexpected.
	 */
	public function test_the_options_table_contains_no_plaintext_after_a_write(): void {
		global $wpdb;

		$this->assertInstanceOf( wpdb::class, $wpdb );

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
	public function test_reveal_still_works_after_the_internal_memzero(): void {
		wp_set_secret( 'myplugin/api-key', 'must-survive-memzero' );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'must-survive-memzero', $secret->reveal() );
	}

	public function test_no_capability_check_is_applied(): void {
		wp_set_current_user( 0 ); // No logged-in user, no capabilities at all.

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertInstanceOf( WP_Secret::class, wp_get_secret( 'myplugin/api-key' ) );
	}

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

		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertNotNull( $captured );
		list( $name, $action, $actor_id, $timestamp, $old_fingerprint, $new_fingerprint, $network ) = $captured;

		$this->assertSame( 'myplugin/api-key', $name );
		$this->assertSame( 'created', $action );
		$this->assertSame( '', $old_fingerprint );
		$this->assertNotSame( '', $new_fingerprint );
		$this->assertFalse( $network );
	}

	public function test_change_hook_fires_on_overwrite_with_updated_action(): void {
		wp_set_secret( 'myplugin/api-key', 'first-value' );
		$first = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $first );
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

		wp_set_secret( 'myplugin/api-key', 'second-value' );

		$this->assertIsArray( $captured );
		list( , $action, , , $old_fingerprint, $new_fingerprint ) = $captured;

		$this->assertSame( 'updated', $action );
		$this->assertSame( $first_fingerprint, $old_fingerprint );
		$this->assertNotSame( $old_fingerprint, $new_fingerprint );
	}

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

		wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertSame( $user_id, $captured_actor );
	}

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

		wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' );

		$this->assertIsArray( $captured_args );

		foreach ( $captured_args as $arg ) {
			$this->assertIsScalar( $arg );
			$this->assertStringNotContainsString( 'a-plaintext-value-that-must-not-leak', (string) $arg );
		}
	}

	/**
	 * The options API trims option names, so a name with a trailing newline
	 * would share a row with the name without it while encrypting for itself.
	 *
	 * @ticket 66414
	 */
	public function test_a_trailing_newline_name_cannot_overwrite_the_secret_without_it(): void {
		wp_set_secret( 'myplugin/api-key', 'value-plain' );

		$result = wp_set_secret( "myplugin/api-key\n", 'value-newline' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );

		$secret = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $secret );
		$this->assertSame( 'value-plain', $secret->reveal() );
	}

	/**
	 * @ticket 66414
	 *
	 * @requires PHP 8.2
	 */
	public function test_an_exception_while_setting_does_not_expose_the_value_in_the_trace(): void {
		if ( ini_get( 'zend.exception_ignore_args' ) ) {
			$this->markTestSkipped( 'Exception traces do not include arguments with zend.exception_ignore_args enabled.' );
		}

		add_action(
			'wp_secret_changed',
			static function () {
				throw new RuntimeException( 'Listener failed.' );
			}
		);

		$exception = null;

		try {
			wp_set_secret( 'myplugin/api-key', 'a-plaintext-value-that-must-not-leak' );
		} catch ( RuntimeException $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( RuntimeException::class, $exception );

		$string_args = array();
		foreach ( $exception->getTrace() as $frame ) {
			foreach ( $frame['args'] ?? array() as $arg ) {
				if ( is_string( $arg ) ) {
					$string_args[] = $arg;
				}
			}
		}

		$this->assertContains( 'myplugin/api-key', $string_args, 'The trace should include the other arguments.' );
		$this->assertNotContains( 'a-plaintext-value-that-must-not-leak', $string_args );
	}

	/**
	 * @ticket 66414
	 *
	 * @requires PHP 8.2
	 *
	 * @dataProvider data_parameters_that_receive_a_secret_value
	 *
	 * @param callable-string|array{0: class-string, 1: string} $target    The function or method.
	 * @param string                                             $parameter The parameter name.
	 */
	public function test_parameters_that_receive_a_secret_value_are_sensitive( $target, string $parameter ): void {
		$reflection = is_array( $target ) ? new ReflectionMethod( $target[0], $target[1] ) : new ReflectionFunction( $target );

		foreach ( $reflection->getParameters() as $reflection_parameter ) {
			if ( $parameter === $reflection_parameter->getName() ) {
				$this->assertNotEmpty( $reflection_parameter->getAttributes( SensitiveParameter::class ) );
				return;
			}
		}

		$this->fail( "Parameter \${$parameter} not found." );
	}

	/**
	 * @return array<string, array{0: callable-string|array{0: class-string, 1: string}, 1: string}>
	 */
	public static function data_parameters_that_receive_a_secret_value(): array {
		return array(
			'wp_set_secret()'                              => array( 'wp_set_secret', 'value' ),
			'wp_set_network_secret()'                      => array( 'wp_set_network_secret', 'value' ),
			'_wp_secrets_set()'                            => array( '_wp_secrets_set', 'value' ),
			'WP_Secrets_Provider::set()'                   => array( array( WP_Secrets_Provider::class, 'set' ), 'value' ),
			'WP_Secrets_Libsodium_Provider::set()'         => array( array( WP_Secrets_Libsodium_Provider::class, 'set' ), 'value' ),
			'WP_Secrets_Broken_Provider::set()'            => array( array( WP_Secrets_Broken_Provider::class, 'set' ), 'value' ),
			'WP_Secrets_Cipher::encrypt_value()'           => array( array( WP_Secrets_Cipher::class, 'encrypt_value' ), 'plaintext' ),
			'WP_Secrets_Cipher::fingerprint()'             => array( array( WP_Secrets_Cipher::class, 'fingerprint' ), 'plaintext' ),
			'WP_Secrets_Cipher::encrypt_value() key'       => array( array( WP_Secrets_Cipher::class, 'encrypt_value' ), 'master_key' ),
			'WP_Secrets_Cipher::decrypt_value() key'       => array( array( WP_Secrets_Cipher::class, 'decrypt_value' ), 'master_key' ),
			'WP_Secrets_Cipher::fingerprint() key'         => array( array( WP_Secrets_Cipher::class, 'fingerprint' ), 'master_key' ),
			'WP_Secrets_Keyring::wrap()'                   => array( array( WP_Secrets_Keyring::class, 'wrap' ), 'key_material' ),
			'WP_Secrets_Config_Key_Provider::wrap()'       => array( array( WP_Secrets_Config_Key_Provider::class, 'wrap' ), 'key_material' ),
			'WP_Secrets_Broken_Keyring::wrap()'            => array( array( WP_Secrets_Broken_Keyring::class, 'wrap' ), 'key_material' ),
			'WP_Secrets_Libsodium_Provider::demote_slot()' => array( array( WP_Secrets_Libsodium_Provider::class, 'demote_slot' ), 'master_key' ),
		);
	}
}
