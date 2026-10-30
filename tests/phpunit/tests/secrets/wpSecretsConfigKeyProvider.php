<?php
/**
 * Tests for WP_Secrets_Config_Key_Provider.
 *
 * Constants (WP_SECRETS_KEY, WP_SECRETS_KEY_PREVIOUS) that this class reads can only
 * be defined once per PHP process, so any test that needs a specific value for them,
 * or needs them undefined, runs @runInSeparateProcess. The test bootstrap defines
 * WP_SECRETS_KEY only outside isolated processes.
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

	public function test_implements_the_keyring_interface(): void {
		$this->assertInstanceOf( WP_Secrets_Keyring::class, new WP_Secrets_Config_Key_Provider() ); // @phpstan-ignore method.alreadyNarrowedType (Pins the interface the class declares, so removing it fails a test.)
	}

	public function test_wrap_then_unwrap_round_trips(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$wrapped = $provider->wrap( $material );
		$this->assertIsString( $wrapped );

		$unwrapped = $provider->unwrap( $wrapped );
		$this->assertSame( $material, $unwrapped );
	}

	public function test_wrap_rejects_an_empty_string(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->wrap( '' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_two_wraps_of_the_same_material_produce_different_output(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$this->assertNotSame( $provider->wrap( $material ), $provider->wrap( $material ) );
	}

	public function test_unwrap_rejects_an_empty_string(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( '' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_garbage_base64(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( 'not valid base64 at all!!!' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_data_too_short_to_contain_a_nonce(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( base64_encode( 'x' ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_a_tampered_ciphertext(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$wrapped  = $provider->wrap( random_bytes( 32 ) );
		$this->assertIsString( $wrapped );

		$raw = base64_decode( $wrapped, true );
		$this->assertIsString( $raw );

		$tampered_raw               = $raw;
		$last_byte                  = strlen( $tampered_raw ) - 1;
		$tampered_raw[ $last_byte ] = chr( ( ord( $tampered_raw[ $last_byte ] ) + 1 ) % 256 );

		$result = $provider->unwrap( base64_encode( $tampered_raw ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_an_undefined_key(): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'not defined', $provider->get_key_source() );
	}

	/**
	 * Without WP_SECRETS_KEY there is no key, whatever the salts are.
	 *
	 * @ticket 66187
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_an_undefined_wp_secrets_key_is_unavailable(): void {
		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
		$this->assertStringContainsString( 'WP_SECRETS_KEY', $result->get_error_message() );
	}

	/**
	 * @dataProvider data_canonical_base64_32
	 *
	 * @param string|int $value    Candidate value.
	 * @param bool       $expected Whether it is the canonical base64 encoding of 32 bytes.
	 */
	public function test_is_canonical_base64_32( $value, $expected ): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'is_canonical_base64_32', array( $value ) ) );
	}

	/**
	 * @return array<string, array{0: string|int, 1: bool}>
	 */
	public static function data_canonical_base64_32(): array {
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
	 * @ticket 66187
	 *
	 * @dataProvider data_usable_key_values
	 *
	 * @param string|int|null $value       Candidate WP_SECRETS_KEY value.
	 * @param string[]        $auth_values Values of the authentication keys and salts.
	 * @param bool            $expected    Whether the value is usable as key material.
	 */
	public function test_is_usable_key_value( $value, array $auth_values, bool $expected ): void {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'is_usable_key_value', array( $value, $auth_values ) ) );
	}

	/**
	 * @return array<string, array{0: string|int|null, 1: string[], 2: bool}>
	 */
	public static function data_usable_key_values(): array {
		$translated = 'mettez une phrase unique ici';

		return array(
			'a real key'                    => array( 'a real key', array( 'a real salt', 'another salt' ), true ),
			'the placeholder'               => array( 'put your unique phrase here', array( 'a real salt' ), false ),
			'the value of a key or salt'    => array( 'a real salt', array( 'a real salt', 'another salt' ), false ),
			'a translated, unfilled sample' => array( $translated, array_fill( 0, 8, $translated ), false ),
			'empty'                         => array( '', array(), false ),
			'null (undefined)'              => array( null, array(), false ),
			'not a string'                  => array( 12345, array(), false ),
		);
	}

	/**
	 * @ticket 66187
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_placeholder_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', 'put your unique phrase here' );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
		$this->assertStringContainsString( 'not usable', $provider->get_key_source() );
	}

	/**
	 * @ticket 66187
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_value_of_a_salt_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', LOGGED_IN_SALT );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_derives_raw_bytes_when_wp_secrets_key_is_canonical_base64_32(): void {
		$raw = str_repeat( 'A', 32 );
		define( 'WP_SECRETS_KEY', base64_encode( $raw ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $raw, $this->invoke_private( $provider, 'get_site_key' ) );
	}

	/**
	 * Known-answer vector: sodium_crypto_generichash( 'not-base64-32!!', '', 32 ),
	 * computed independently and pinned here so an accidental change to the
	 * algorithm, its output length, or its inputs is caught rather than silently
	 * changing what key every existing secret is wrapped under.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_derives_a_known_answer_via_the_legacy_interpretation(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertIsString( $key );
		$this->assertSame(
			'db0aa0426b1dfaecc1878c103914462b0066eca0cdd050654a179588fed74b21',
			bin2hex( $key )
		);
	}

	/**
	 * A base64 string that decodes cleanly but not to 32 bytes must fall through to
	 * the legacy (hashed) interpretation rather than being treated as raw key
	 * material of the wrong length.
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
			$key
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_non_string_wp_secrets_key_is_unavailable(): void {
		define( 'WP_SECRETS_KEY', 424242 );

		$provider = new WP_Secrets_Config_Key_Provider();
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_base64_form(): void {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'base64', $provider->get_key_source() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_legacy_form(): void {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'legacy', $provider->get_key_source() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_reads_the_previous_constant(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( true );

		$this->assertSame( str_repeat( 'C', 32 ), $this->invoke_private( $provider, 'get_site_key' ) );
	}

	/**
	 * Defining only the previous-key constant must not affect the current-key
	 * provider: without WP_SECRETS_KEY it has no key.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_previous_key_constant_does_not_leak_into_the_current_key_path(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( false );
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $key );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $key->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_without_the_constant_defined_is_unavailable(): void {
		$provider = new WP_Secrets_Config_Key_Provider( true );
		$result   = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * A key rotation unwraps under the outgoing key and wraps under the incoming
	 * one. This proves the two providers actually disagree when they should.
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
		$this->assertIsString( $wrapped_under_old );

		$this->assertWPError( $new_provider->unwrap( $wrapped_under_old ) );
		$this->assertIsString( $old_provider->unwrap( $wrapped_under_old ) );
	}
}
