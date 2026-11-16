<?php
/**
 * Tests for WP_Secrets_Config_Key_Provider.
 *
 * Constants (WP_SECRETS_KEY, WP_SECRETS_KEY_PREVIOUS) that this class reads can only
 * be defined once per PHP process, so any test that needs a specific value for them
 * runs in a separate process. LOGGED_IN_KEY/LOGGED_IN_SALT are already defined by the
 * WordPress test suite's own bootstrap before any test of ours can run, in every
 * process including an isolated one, so the salt-fallback path is exercised against
 * whatever the ambient harness defines them as rather than a value this suite picks.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsConfigKeyProvider extends WP_UnitTestCase {

	/**
	 * Calls a private method on an instance.
	 *
	 * @param object            $instance Object to call the method on.
	 * @param string            $method   Method name.
	 * @param array<int, mixed> $args     Positional arguments.
	 * @return mixed Whatever the method returns.
	 */
	private function invoke_private( object $instance, string $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $instance, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $instance, $args );
	}

	/**
	 * Ensure that WP_Secrets_Config_Key_Provider implements WP_Secrets_Keyring.
	 *
	 * @coversNothing
	 */
	public function test_implements_the_keyring_interface(): void {
		$this->assertInstanceOf( WP_Secrets_Keyring::class, new WP_Secrets_Config_Key_Provider(), 'WP_Secrets_Config_Key_Provider should implement WP_Secrets_Keyring.' ); // @phpstan-ignore method.alreadyNarrowedType (Pins the interface the class declares, so removing it fails a test.)
	}

	/**
	 * Ensure that unwrapping a wrapped value returns the original key material.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::wrap
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 */
	public function test_wrap_then_unwrap_round_trips(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$wrapped = $provider->wrap( $material );
		$this->assertIsString( $wrapped, 'wrap() should return a string.' );

		$unwrapped = $provider->unwrap( $wrapped );
		$this->assertSame( $material, $unwrapped, 'unwrap() should return the original key material.' );
	}

	/**
	 * Ensure that wrapping empty key material returns an invalid value error.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::wrap
	 */
	public function test_wrap_rejects_an_empty_string(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->wrap( '' );

		$this->assertWPError( $result, 'Wrapping an empty string should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'Wrapping an empty string should return the invalid value error code.' );
	}

	/**
	 * Ensure that wrapping the same key material twice uses a fresh nonce each time.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::wrap
	 */
	public function test_two_wraps_of_the_same_material_produce_different_output(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$this->assertNotSame( $provider->wrap( $material ), $provider->wrap( $material ), 'Two wraps of the same key material should produce different output.' );
	}

	/**
	 * Ensure that unwrapping an empty string returns a key unavailable error.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 */
	public function test_unwrap_rejects_an_empty_string(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( '' );

		$this->assertWPError( $result, 'Unwrapping an empty string should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'Unwrapping an empty string should return the key unavailable error code.' );
	}

	/**
	 * Ensure that unwrapping a value that is not strict base64 returns a key unavailable error.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 */
	public function test_unwrap_rejects_garbage_base64(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( 'not valid base64 at all!!!' );

		$this->assertWPError( $result, 'Unwrapping invalid base64 should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'Unwrapping invalid base64 should return the key unavailable error code.' );
	}

	/**
	 * Ensure that unwrapping data no longer than a nonce returns a key unavailable error.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 */
	public function test_unwrap_rejects_data_too_short_to_contain_a_nonce(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( base64_encode( 'x' ) );

		$this->assertWPError( $result, 'Unwrapping data shorter than a nonce should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'Unwrapping data shorter than a nonce should return the key unavailable error code.' );
	}

	/**
	 * Ensure that unwrapping a ciphertext with a modified byte fails authentication.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 */
	public function test_unwrap_rejects_a_tampered_ciphertext(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$wrapped  = $provider->wrap( random_bytes( 32 ) );
		$this->assertIsString( $wrapped, 'wrap() should return a string.' );

		$raw = base64_decode( $wrapped, true );
		$this->assertIsString( $raw, 'The wrapped value should be valid strict base64.' );

		$tampered_raw               = $raw;
		$last_byte                  = strlen( $tampered_raw ) - 1;
		$tampered_raw[ $last_byte ] = chr( ( ord( $tampered_raw[ $last_byte ] ) + 1 ) % 256 );

		$result = $provider->unwrap( base64_encode( $tampered_raw ) );

		$this->assertWPError( $result, 'Unwrapping a tampered ciphertext should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'Unwrapping a tampered ciphertext should return the key unavailable error code.' );
	}

	/**
	 * Ensure that the key source names LOGGED_IN_KEY when WP_SECRETS_KEY is not defined.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 */
	public function test_get_key_source_reports_the_salt_fallback_when_wp_secrets_key_is_unset(): void {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		if ( defined( 'WP_SECRETS_KEY' ) ) {
			$this->markTestSkipped( 'Needs WP_SECRETS_KEY to be undefined; it is defined in this process.' );
		}

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'LOGGED_IN_KEY', $provider->get_key_source(), 'The key source should name LOGGED_IN_KEY when WP_SECRETS_KEY is unset.' );
	}

	/**
	 * Ensure that only the canonical base64 encoding of exactly 32 bytes is recognized.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::is_canonical_base64_32
	 *
	 * @dataProvider data_is_canonical_base64_32
	 *
	 * @param string|int $value    Candidate value.
	 * @param bool       $expected Whether it is the canonical base64 encoding of 32 bytes.
	 */
	public function test_is_canonical_base64_32( $value, $expected ): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'is_canonical_base64_32', array( $value ) ), 'is_canonical_base64_32() should return the expected result for this value.' );
	}

	/**
	 * @return array<string, array{0: string|int, 1: bool}>
	 */
	public static function data_is_canonical_base64_32(): array {
		return array(
			'exactly 32 bytes, canonical encoding' => array( base64_encode( str_repeat( 'A', 32 ) ), true ),
			'31 bytes'                             => array( base64_encode( str_repeat( 'A', 31 ) ), false ),
			'33 bytes'                             => array( base64_encode( str_repeat( 'A', 33 ) ), false ),
			'not base64 at all'                    => array( 'not-base64-32!!', false ),
			'non-canonical padding'                => array( base64_encode( str_repeat( 'A', 32 ) ) . ' ', false ),
			'empty string'                         => array( '', false ),
			'not a string'                         => array( 12345, false ),
		);
	}

	/**
	 * Ensure that salt values are rejected when missing, empty, non-string or the sample placeholder.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::are_usable_salt_values
	 *
	 * @dataProvider data_are_usable_salt_values
	 *
	 * @param string|int|null $key      Candidate LOGGED_IN_KEY value.
	 * @param string          $salt     Candidate LOGGED_IN_SALT value.
	 * @param bool            $expected Whether the pair is usable as key material.
	 */
	public function test_are_usable_salt_values( $key, $salt, $expected ): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'are_usable_salt_values', array( $key, $salt ) ), 'are_usable_salt_values() should return the expected result for this pair.' );
	}

	/**
	 * @return array<string, array{0: string|int|null, 1: string, 2: bool}>
	 */
	public static function data_are_usable_salt_values(): array {
		return array(
			'both usable'             => array( 'a real key', 'a real salt', true ),
			'key is the placeholder'  => array( 'put your unique phrase here', 'a real salt', false ),
			'salt is the placeholder' => array( 'a real key', 'put your unique phrase here', false ),
			'key is null (undefined)' => array( null, 'a real salt', false ),
			'salt is empty'           => array( 'a real key', '', false ),
			'key is not a string'     => array( 12345, 'a real salt', false ),
		);
	}

	/**
	 * Ensure that a canonical base64 WP_SECRETS_KEY is used as the decoded raw bytes.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_derives_raw_bytes_when_wp_secrets_key_is_canonical_base64_32(): void {
		$raw = str_repeat( 'A', 32 );
		define( 'WP_SECRETS_KEY', base64_encode( $raw ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $raw, $this->invoke_private( $provider, 'get_site_key' ), 'A canonical base64 WP_SECRETS_KEY should be used as the decoded raw bytes.' );
	}

	/**
	 * Known-answer vector: sodium_crypto_generichash( 'not-base64-32!!', '', 32 ),
	 * computed independently and pinned here so an accidental change to the
	 * algorithm, its output length, or its inputs is caught rather than silently
	 * changing what key every existing secret is wrapped under.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_derives_a_known_answer_via_the_legacy_interpretation(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertIsString( $key, 'get_site_key() should return a string for a legacy WP_SECRETS_KEY.' );
		$this->assertSame(
			'db0aa0426b1dfaecc1878c103914462b0066eca0cdd050654a179588fed74b21',
			bin2hex( $key ),
			'The legacy site key should match the pinned known-answer vector.'
		);
	}

	/**
	 * A base64 string that decodes cleanly but not to 32 bytes must fall through to
	 * the legacy (hashed) interpretation rather than being treated as raw key
	 * material of the wrong length.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wrong_length_base64_falls_through_to_legacy_interpretation(): void {
		$sixteen_bytes = base64_encode( str_repeat( 'B', 16 ) );
		define( 'WP_SECRETS_KEY', $sixteen_bytes );

		$provider = new WP_Secrets_Config_Key_Provider();
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertSame(
			sodium_crypto_generichash( $sixteen_bytes, '', 32 ),
			$key,
			'A wrong-length base64 WP_SECRETS_KEY should be hashed via the legacy interpretation.'
		);
	}

	/**
	 * Ensure that a non-string WP_SECRETS_KEY fails closed with a key unavailable error.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_non_string_wp_secrets_key_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', 424242 );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result, 'A non-string WP_SECRETS_KEY should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'A non-string WP_SECRETS_KEY should return the key unavailable error code.' );
	}

	/**
	 * Ensure that the key source identifies a canonical base64 WP_SECRETS_KEY.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_base64_form(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'base64', $provider->get_key_source(), 'The key source should identify a canonical base64 WP_SECRETS_KEY.' );
	}

	/**
	 * Ensure that the key source identifies a WP_SECRETS_KEY using the legacy interpretation.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_legacy_form(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'legacy', $provider->get_key_source(), 'The key source should identify a legacy WP_SECRETS_KEY.' );
	}

	/**
	 * Ensure that a previous-key provider derives its site key from WP_SECRETS_KEY_PREVIOUS.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::__construct
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_reads_the_previous_constant(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( true );

		$this->assertSame( str_repeat( 'C', 32 ), $this->invoke_private( $provider, 'get_site_key' ), 'A previous-key provider should use the bytes from WP_SECRETS_KEY_PREVIOUS.' );
	}

	/**
	 * Defining only the previous-key constant must not affect the current-key
	 * provider: it should still fall through to the salt fallback.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_previous_key_constant_does_not_leak_into_the_current_key_path(): void {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( false );
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertNotWPError( $key, 'The current-key provider should derive a site key from the salts.' );
		$this->assertNotSame( str_repeat( 'C', 32 ), $key, 'The current-key provider should not use WP_SECRETS_KEY_PREVIOUS.' );
		$this->assertSame(
			sodium_crypto_generichash( LOGGED_IN_KEY . LOGGED_IN_SALT, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES ),
			$key,
			'The current-key provider should use the key derived from LOGGED_IN_KEY and LOGGED_IN_SALT.'
		);
	}

	/**
	 * Ensure that a previous-key provider fails when WP_SECRETS_KEY_PREVIOUS is not defined.
	 *
	 * The previous-key provider must fail closed rather than fall back to the salts, or
	 * a rotation could unwrap under a key the site never configured as the old one.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_without_the_constant_defined_is_unavailable(): void {
		$provider = new WP_Secrets_Config_Key_Provider( true );
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result, 'A previous-key provider without WP_SECRETS_KEY_PREVIOUS should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'Without WP_SECRETS_KEY_PREVIOUS, the previous-key provider should return key unavailable.' );
	}

	/**
	 * A key rotation unwraps under the outgoing key and wraps under the incoming
	 * one. This proves the two providers actually disagree when they should.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::wrap
	 * @covers WP_Secrets_Config_Key_Provider::unwrap
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_old_and_new_key_providers_are_not_interchangeable(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'D', 32 ) ) );
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'E', 32 ) ) );

		$old_provider = new WP_Secrets_Config_Key_Provider( true );
		$new_provider = new WP_Secrets_Config_Key_Provider( false );

		$wrapped_under_old = $old_provider->wrap( random_bytes( 32 ) );
		$this->assertIsString( $wrapped_under_old, 'The old provider should wrap to a string.' );

		$this->assertWPError( $new_provider->unwrap( $wrapped_under_old ), 'The new provider should not unwrap a value wrapped by the old provider.' );
		$this->assertIsString( $old_provider->unwrap( $wrapped_under_old ), 'The old provider should unwrap its own wrapped value.' );
	}
}
