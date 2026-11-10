<?php
/**
 * Tests for WP_Secrets_Config_Key_Provider.
 *
 * Constants (WP_SECRETS_KEY, WP_SECRETS_KEY_PREVIOUS) that this class reads can only
 * be defined once per PHP process, so any test that needs a specific value for them,
 * or needs them undefined, runs in a separate process. The test bootstrap defines
 * WP_SECRETS_KEY only outside separate processes.
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
	 * Ensure that the key status and source report an undefined WP_SECRETS_KEY.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_status
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_status_reports_an_undefined_key(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( WP_Secrets_Config_Key_Provider::KEY_STATUS_UNDEFINED, $provider->get_key_status(), 'The key status should be undefined without WP_SECRETS_KEY.' );
		$this->assertStringContainsString( 'not defined', $provider->get_key_source(), 'The key source should say that WP_SECRETS_KEY is not defined.' );
	}

	/**
	 * Ensure that the key source is translated.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_is_translated(): void {
		add_filter(
			'gettext',
			static function ( $translation, $text ) {
				return '%s is not defined' === $text ? '%s ist nicht definiert' : $translation;
			},
			10,
			2
		);

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( 'WP_SECRETS_KEY ist nicht definiert', $provider->get_key_source(), 'The key source should use the translated text.' );
	}

	/**
	 * Ensure that there is no site key without WP_SECRETS_KEY, whatever the salts are.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_an_undefined_wp_secrets_key_is_unavailable(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result, 'An undefined WP_SECRETS_KEY should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'An undefined WP_SECRETS_KEY should return the key unavailable error code.' );
		$this->assertStringContainsString( 'WP_SECRETS_KEY', $result->get_error_message(), 'The error message should name WP_SECRETS_KEY.' );
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
	 * Ensure that a key value is rejected when empty, non-string, the sample placeholder or equal to a key or salt.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::is_usable_key_value
	 *
	 * @dataProvider data_is_usable_key_value
	 *
	 * @param string|int|null $value       Candidate WP_SECRETS_KEY value.
	 * @param string[]        $auth_values Values of the authentication keys and salts.
	 * @param bool            $expected    Whether the value is usable as key material.
	 */
	public function test_is_usable_key_value( $value, array $auth_values, bool $expected ): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'is_usable_key_value', array( $value, $auth_values ) ), 'is_usable_key_value() should return the expected result for this value.' );
	}

	/**
	 * @return array<string, array{0: string|int|null, 1: string[], 2: bool}>
	 */
	public static function data_is_usable_key_value(): array {
		$translated = 'mettez une phrase unique ici';

		return array(
			'a real key'                    => array( 'a real key', array( 'a real salt', 'another salt' ), true ),
			'the placeholder'               => array( 'put your unique phrase here', array( 'a real salt' ), false ),
			'the value of a key or salt'    => array( 'a real salt', array( 'a real salt', 'another salt' ), false ),
			'a translated, unfilled sample' => array( $translated, array_fill( 0, 8, $translated ), false ),
			'empty'                         => array( '', array(), false ),
			'empty when trimmed'            => array( '   ', array(), false ),
			'null (undefined)'              => array( null, array(), false ),
			'not a string'                  => array( 12345, array(), false ),
		);
	}

	/**
	 * Ensure that the wp-config-sample.php placeholder is not usable as WP_SECRETS_KEY.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 * @covers WP_Secrets_Config_Key_Provider::get_key_status
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_placeholder_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', 'put your unique phrase here' );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result, 'The placeholder should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'The placeholder should return the key unavailable error code.' );
		$this->assertSame( WP_Secrets_Config_Key_Provider::KEY_STATUS_UNUSABLE, $provider->get_key_status(), 'The key status should be unusable for the placeholder.' );
	}

	/**
	 * Ensure that the value of an authentication salt is not usable as WP_SECRETS_KEY.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_value_of_a_salt_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', LOGGED_IN_SALT );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result, 'The value of LOGGED_IN_SALT should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'The value of LOGGED_IN_SALT should return the key unavailable error code.' );
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
	 * Ensure that the key status and source identify a canonical base64 WP_SECRETS_KEY.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_status
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_status_reports_base64_form(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( WP_Secrets_Config_Key_Provider::KEY_STATUS_BASE64, $provider->get_key_status(), 'The key status should identify a canonical base64 WP_SECRETS_KEY.' );
		$this->assertStringContainsString( 'base64', $provider->get_key_source(), 'The key source should identify a canonical base64 WP_SECRETS_KEY.' );
	}

	/**
	 * Ensure that the key status and source identify a WP_SECRETS_KEY using the legacy interpretation.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_key_status
	 * @covers WP_Secrets_Config_Key_Provider::get_key_source
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_status_reports_legacy_form(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( WP_Secrets_Config_Key_Provider::KEY_STATUS_LEGACY, $provider->get_key_status(), 'The key status should identify a legacy WP_SECRETS_KEY.' );
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
	 * Ensure that defining only the previous-key constant does not give the current-key provider a key.
	 *
	 * @covers WP_Secrets_Config_Key_Provider::get_site_key
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_previous_key_constant_does_not_leak_into_the_current_key_path(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( false );
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $key, 'The current-key provider should have no key without WP_SECRETS_KEY.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $key->get_error_code(), 'The current-key provider should return the key unavailable error code.' );
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
