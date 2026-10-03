<?php
/**
 * Tests for WP_Secrets_Config_Key_Provider.
 *
 * Constants (WP_SECRETS_KEY, WP_SECRETS_KEY_PREVIOUS) that this class reads can only
 * be defined once per PHP process, so any test that needs a specific value for them
 * runs @runInSeparateProcess. LOGGED_IN_KEY/LOGGED_IN_SALT are already defined by the
 * WordPress test suite's own bootstrap before any test of ours can run, in every
 * process including an isolated one, so the salt-fallback path is exercised against
 * whatever the ambient harness defines them as rather than a value this suite picks.
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsConfigKeyProvider extends WP_UnitTestCase {

	private function invoke_private( $instance, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $instance, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $instance, $args );
	}

	public function test_implements_the_keyring_interface() {
		$this->assertInstanceOf( WP_Secrets_Keyring::class, new WP_Secrets_Config_Key_Provider() );
	}

	public function test_wrap_then_unwrap_round_trips() {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$wrapped = $provider->wrap( $material );
		$this->assertIsString( $wrapped );

		$unwrapped = $provider->unwrap( $wrapped );
		$this->assertSame( $material, $unwrapped );
	}

	public function test_wrap_rejects_a_non_string() {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->wrap( 12345 );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_wrap_rejects_an_empty_string() {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->wrap( '' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	public function test_two_wraps_of_the_same_material_produce_different_output() {
		$provider = new WP_Secrets_Config_Key_Provider();
		$material = random_bytes( 32 );

		$this->assertNotSame( $provider->wrap( $material ), $provider->wrap( $material ) );
	}

	public function test_unwrap_rejects_a_non_string() {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( array() );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_garbage_base64() {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( 'not valid base64 at all!!!' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_data_too_short_to_contain_a_nonce() {
		$provider = new WP_Secrets_Config_Key_Provider();

		$result = $provider->unwrap( base64_encode( 'x' ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_unwrap_rejects_a_tampered_ciphertext() {
		$provider = new WP_Secrets_Config_Key_Provider();
		$wrapped  = $provider->wrap( random_bytes( 32 ) );

		$raw                        = base64_decode( $wrapped, true );
		$tampered_raw               = $raw;
		$last_byte                  = strlen( $tampered_raw ) - 1;
		$tampered_raw[ $last_byte ] = chr( ( ord( $tampered_raw[ $last_byte ] ) + 1 ) % 256 );

		$result = $provider->unwrap( base64_encode( $tampered_raw ) );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_get_key_source_reports_the_salt_fallback_when_wp_secrets_key_is_unset() {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'LOGGED_IN_KEY', $provider->get_key_source() );
	}

	/**
	 * @dataProvider data_canonical_base64_32
	 */
	public function test_is_canonical_base64_32( $value, $expected ) {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'is_canonical_base64_32', array( $value ) ) );
	}

	public function data_canonical_base64_32() {
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
	 * @dataProvider data_usable_salt_values
	 */
	public function test_are_usable_salt_values( $key, $salt, $expected ) {
		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertSame( $expected, $this->invoke_private( $provider, 'are_usable_salt_values', array( $key, $salt ) ) );
	}

	public function data_usable_salt_values() {
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
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_derives_raw_bytes_when_wp_secrets_key_is_canonical_base64_32() {
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
	public function test_derives_a_known_answer_via_the_legacy_interpretation() {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();
		$key      = $this->invoke_private( $provider, 'get_site_key' );

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
	public function test_wrong_length_base64_falls_through_to_legacy_interpretation() {
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
	public function test_non_string_wp_secrets_key_is_unavailable() {
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
	public function test_get_key_source_reports_base64_form() {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'A', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'base64', $provider->get_key_source() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_key_source_reports_legacy_form() {
		define( 'WP_SECRETS_KEY', 'not-base64-32!!' );

		$provider = new WP_Secrets_Config_Key_Provider();

		$this->assertStringContainsString( 'legacy', $provider->get_key_source() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_reads_the_previous_constant() {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( true );

		$this->assertSame( str_repeat( 'C', 32 ), $this->invoke_private( $provider, 'get_site_key' ) );
	}

	/**
	 * Defining only the previous-key constant must not affect the current-key
	 * provider: it should still fall through to the salt fallback.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_previous_key_constant_does_not_leak_into_the_current_key_path() {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'C', 32 ) ) );

		$provider = new WP_Secrets_Config_Key_Provider( false );
		$key      = $this->invoke_private( $provider, 'get_site_key' );

		$this->assertNotWPError( $key );
		$this->assertNotSame( str_repeat( 'C', 32 ), $key );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_use_previous_key_without_the_constant_defined_is_unavailable() {
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
	public function test_old_and_new_key_providers_are_not_interchangeable() {
		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'D', 32 ) ) );
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'E', 32 ) ) );

		$old_provider = new WP_Secrets_Config_Key_Provider( true );
		$new_provider = new WP_Secrets_Config_Key_Provider( false );

		$wrapped_under_old = $old_provider->wrap( random_bytes( 32 ) );

		$this->assertWPError( $new_provider->unwrap( $wrapped_under_old ) );
		$this->assertIsString( $old_provider->unwrap( $wrapped_under_old ) );
	}
}
