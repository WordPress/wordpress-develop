<?php
/**
 * Tests for WP_Secrets_Cipher.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsCipher extends WP_UnitTestCase {

	const NAME = 'myplugin/api-key';

	/**
	 * Invokes a private method through reflection.
	 *
	 * @param object            $instance Object to invoke the method on.
	 * @param string            $method   Method name.
	 * @param array<int, mixed> $args     Positional arguments.
	 * @return mixed Whatever the method returns.
	 */
	private function invoke_private( $instance, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $instance, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $instance, $args );
	}

	/**
	 * Builds a 32-byte master key out of one repeated byte.
	 *
	 * @param int $byte Byte value to repeat.
	 * @return string
	 */
	private function master_key( $byte = 0x11 ) {
		return str_repeat( chr( $byte ), 32 );
	}

	/**
	 * Ensure that a value encrypted by WP_Secrets_Cipher::encrypt_value() decrypts back unchanged.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 * @covers WP_Secrets_Cipher::decrypt_value
	 */
	public function test_round_trips_a_value(): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'sk_live_secret' );

		$this->assertIsArray( $record, 'Encrypting a value should return a record array.' );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( 'sk_live_secret', $plaintext, 'The record should decrypt to the original plaintext.' );
	}

	/**
	 * Ensure that an empty string encrypts and decrypts back to an empty string.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 * @covers WP_Secrets_Cipher::decrypt_value
	 */
	public function test_round_trips_an_empty_string_value(): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, '' );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( '', $plaintext, 'An empty string should round-trip to an empty string.' );
	}

	/**
	 * Ensure that a value encrypted under network scope with site ID 0 decrypts back unchanged.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 * @covers WP_Secrets_Cipher::decrypt_value
	 */
	public function test_round_trips_under_network_scope(): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'network', 0, self::NAME, WP_Secret_Version::CURRENT, 'network-secret' );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'network', 0, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( 'network-secret', $plaintext, 'A network-scope record should decrypt to the original plaintext.' );
	}

	/**
	 * Ensure that WP_Secrets_Cipher::encrypt_value() refuses to encrypt a non-string plaintext.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_encrypt_rejects_a_non_string_value(): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, array() ); // @phpstan-ignore argument.type (Intentionally passing a non-string plaintext.)

		$this->assertWPError( $result, 'Encrypting a non-string value should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'A non-string value should be reported as an invalid value.' );
	}

	/**
	 * Ensure that WP_Secrets_Cipher::encrypt_value() refuses a master key that is not 32 bytes.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 *
	 * @dataProvider data_encrypt_rejects_a_bad_master_key
	 *
	 * @param string $bad_key Master key the cipher must refuse.
	 */
	public function test_encrypt_rejects_a_bad_master_key( $bad_key ): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $bad_key, 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result, 'Encrypting with a bad master key should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'A bad master key should be reported as key unavailable.' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function data_encrypt_rejects_a_bad_master_key() {
		return array(
			'too short' => array( str_repeat( 'a', 31 ) ),
			'too long'  => array( str_repeat( 'a', 33 ) ),
			'empty'     => array( '' ),
		);
	}

	/**
	 * Ensure that WP_Secrets_Cipher::encrypt_value() returns the name validation error for a bad name.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_encrypt_propagates_an_invalid_name(): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, 'Not A Valid Name', WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result, 'Encrypting under an invalid name should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code(), 'An invalid name should be reported with the invalid name error code.' );
	}

	/**
	 * Ensure that an unknown scope triggers a _doing_it_wrong() notice and returns a WP_Error.
	 *
	 * Scope, site ID and slot are only ever supplied by core itself, so a bad value is a
	 * programming error: it is flagged to the developer but still reported as a WP_Error
	 * rather than thrown.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_encrypt_reports_an_invalid_scope_as_a_wp_error(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'bogus-scope', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'An invalid scope should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid scope should be reported as an invalid argument.' );
	}

	/**
	 * Ensure that a negative site ID triggers a _doing_it_wrong() notice and returns a WP_Error.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_encrypt_reports_a_negative_site_id_as_a_wp_error(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'site', -1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result, 'A negative site ID should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'A negative site ID should be reported as an invalid argument.' );
	}

	/**
	 * Ensure that a slot other than a WP_Secret_Version constant is reported as a caller error.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_encrypt_reports_an_invalid_slot_as_a_wp_error(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, 'not-a-real-slot', 'value' ); // @phpstan-ignore argument.type (Intentionally passing an invalid value.)

		$this->assertWPError( $result, 'An invalid slot should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code(), 'An invalid slot should be reported as an invalid argument.' );
	}

	/**
	 * AAD binding: a record encrypted under one context must not decrypt under a
	 * different one, for any single component of that context.
	 *
	 * @covers WP_Secrets_Cipher::decrypt_value
	 *
	 * @dataProvider data_decrypt_fails_when_context_does_not_match
	 *
	 * @param string $scope   Scope to decrypt under.
	 * @param int    $site_id Site id to decrypt under.
	 * @param string $name    Secret name to decrypt under.
	 * @param string $slot    Version slot to decrypt under.
	 *
	 * @phpstan-param 'site'|'network' $scope
	 * @phpstan-param WP_Secret_Version::CURRENT|WP_Secret_Version::PREVIOUS $slot
	 */
	public function test_decrypt_fails_when_context_does_not_match( $scope, $site_id, $name, $slot ): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$result = $cipher->decrypt_value( $this->master_key(), $scope, $site_id, $name, $slot, $record );

		$this->assertWPError( $result, 'Decrypting under a mismatched context should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code(), 'A mismatched context should be reported as a decryption failure.' );
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
	 */
	public static function data_decrypt_fails_when_context_does_not_match() {
		return array(
			'different scope'   => array( 'network', 1, self::NAME, WP_Secret_Version::CURRENT ),
			'different site id' => array( 'site', 2, self::NAME, WP_Secret_Version::CURRENT ),
			'different name'    => array( 'site', 1, 'myplugin/other-key', WP_Secret_Version::CURRENT ),
			'different slot'    => array( 'site', 1, self::NAME, WP_Secret_Version::PREVIOUS ),
		);
	}

	/**
	 * Ensure that a record cannot be decrypted with a master key other than the one that encrypted it.
	 *
	 * @covers WP_Secrets_Cipher::decrypt_value
	 */
	public function test_decrypt_fails_under_a_different_master_key(): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key( 0x11 ), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$result = $cipher->decrypt_value( $this->master_key( 0x22 ), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result, 'Decrypting under a different master key should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code(), 'A different master key should be reported as a decryption failure.' );
	}

	/**
	 * Ensure that decryption fails when a single byte of any encrypted field is altered.
	 *
	 * Every field feeds an authenticated decryption, so corrupting one must be reported
	 * as an error rather than yielding different plaintext.
	 *
	 * @covers WP_Secrets_Cipher::decrypt_value
	 *
	 * @dataProvider data_decrypt_fails_when_a_field_is_tampered
	 *
	 * @param string $field Record field to corrupt.
	 */
	public function test_decrypt_fails_when_a_field_is_tampered( $field ): void {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertIsArray( $record, 'Encrypting a value should return a record array.' );
		$this->assertArrayHasKey( $field, $record, 'The record should contain the field being tampered with.' );

		$raw = base64_decode( $record[ $field ], true );

		$this->assertIsString( $raw, 'The record field should be valid base64.' );

		$last             = strlen( $raw ) - 1;
		$raw[ $last ]     = chr( ( ord( $raw[ $last ] ) + 1 ) % 256 );
		$record[ $field ] = base64_encode( $raw );

		$result = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result, 'Decrypting a tampered record should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code(), 'A tampered field should be reported as a decryption failure.' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function data_decrypt_fails_when_a_field_is_tampered() {
		return array(
			'ciphertext'       => array( 'ct' ),
			'value nonce'      => array( 'nonce' ),
			'wrapped data key' => array( 'dk' ),
			'data key nonce'   => array( 'dk_nonce' ),
		);
	}

	/**
	 * Ensure that a record with a missing, non-base64 or wrongly sized field is reported as malformed.
	 *
	 * @covers WP_Secrets_Cipher::decrypt_value
	 *
	 * @dataProvider data_decrypt_rejects_a_malformed_record
	 *
	 * @param mixed $record Stored record the cipher must refuse.
	 */
	public function test_decrypt_rejects_a_malformed_record( $record ): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result, 'Decrypting a malformed record should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'A malformed record should be reported as record malformed.' );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function data_decrypt_rejects_a_malformed_record() {
		return array(
			'not an array'       => array( 'just a string' ),
			'missing ct'         => array(
				array(
					'dk'       => 'x',
					'dk_nonce' => 'x',
					'nonce'    => 'x',
				),
			),
			'ct not base64'      => array(
				array(
					'dk'       => base64_encode( 'x' ),
					'dk_nonce' => base64_encode( str_repeat( 'x', 24 ) ),
					'ct'       => 'not base64!!',
					'nonce'    => base64_encode( str_repeat( 'x', 24 ) ),
				),
			),
			'nonce wrong length' => array(
				array(
					'dk'       => base64_encode( 'x' ),
					'dk_nonce' => base64_encode( str_repeat( 'x', 24 ) ),
					'ct'       => base64_encode( 'x' ),
					'nonce'    => base64_encode( 'short' ),
				),
			),
		);
	}

	/**
	 * Ensure that encrypting the same value twice produces a different data key and ciphertext.
	 *
	 * Each write must generate a fresh data key, so identical plaintexts are never
	 * recognizable from their stored records.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_data_key_independence(): void {
		$cipher = new WP_Secrets_Cipher();

		$a = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'same-value' );
		$b = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'same-value' );

		$this->assertIsArray( $a, 'The first encryption should return a record array.' );
		$this->assertIsArray( $b, 'The second encryption should return a record array.' );

		$this->assertNotSame( $a['dk'], $b['dk'], 'Each encryption should use a freshly wrapped data key.' );
		$this->assertNotSame( $a['ct'], $b['ct'], 'Encrypting the same value twice should produce different ciphertexts.' );
	}

	/**
	 * Ensure that no value or data key nonce is reused across repeated encryptions.
	 *
	 * @covers WP_Secrets_Cipher::encrypt_value
	 */
	public function test_nonce_uniqueness_across_many_writes(): void {
		$cipher = new WP_Secrets_Cipher();
		$nonces = array();

		for ( $i = 0; $i < 50; $i++ ) {
			$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

			$this->assertIsArray( $record, 'Each encryption should return a record array.' );

			$nonces[] = $record['nonce'];
			$nonces[] = $record['dk_nonce'];
		}

		$this->assertSame( count( $nonces ), count( array_unique( $nonces ) ), 'No value or data key nonce should be reused across writes.' );
	}

	/**
	 * Ensure that the same master key and plaintext always produce the same fingerprint.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_is_stable_for_the_same_inputs(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->assertSame(
			$cipher->fingerprint( $this->master_key(), 'same-value' ),
			$cipher->fingerprint( $this->master_key(), 'same-value' ),
			'The same master key and plaintext should produce the same fingerprint.'
		);
	}

	/**
	 * Ensure that the same plaintext fingerprints differently under a different master key.
	 *
	 * Keying the fingerprint stops it acting as a cross-site lookup oracle for a known value.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_differs_across_master_keys(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->assertNotSame(
			$cipher->fingerprint( $this->master_key( 0x11 ), 'same-value' ),
			$cipher->fingerprint( $this->master_key( 0x22 ), 'same-value' ),
			'The same plaintext should fingerprint differently under different master keys.'
		);
	}

	/**
	 * Ensure that different plaintexts produce different fingerprints under the same master key.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_differs_across_plaintexts(): void {
		$cipher = new WP_Secrets_Cipher();

		$this->assertNotSame(
			$cipher->fingerprint( $this->master_key(), 'value-a' ),
			$cipher->fingerprint( $this->master_key(), 'value-b' ),
			'Different plaintexts should produce different fingerprints.'
		);
	}

	/**
	 * Ensure that WP_Secrets_Cipher::fingerprint() refuses a master key that is not 32 bytes.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_rejects_a_bad_master_key(): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->fingerprint( 'too-short', 'value' );

		$this->assertWPError( $result, 'Fingerprinting with a bad master key should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'A bad master key should be reported as key unavailable.' );
	}

	/**
	 * Ensure that WP_Secrets_Cipher::fingerprint() refuses a non-string plaintext.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_rejects_a_non_string_value(): void {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->fingerprint( $this->master_key(), array() ); // @phpstan-ignore argument.type (Intentionally passing a non-string plaintext.)

		$this->assertWPError( $result, 'Fingerprinting a non-string value should return a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code(), 'A non-string value should be reported as an invalid value.' );
	}

	/**
	 * Known-answer vector for sodium_crypto_kdf_derive_from_key(32, 1, 'wpsecfpr', ...)
	 * followed by sodium_crypto_generichash(..., ..., 16), computed independently on
	 * both PHP 7.4 and 8.5 and pinned here so an accidental change to either
	 * primitive, the KDF subkey id, the context string, or the output length is
	 * caught rather than silently changing every existing fingerprint.
	 *
	 * @covers WP_Secrets_Cipher::fingerprint
	 */
	public function test_fingerprint_known_answer_vector(): void {
		$cipher = new WP_Secrets_Cipher();

		$fingerprint = $cipher->fingerprint( str_repeat( chr( 0x11 ), 32 ), 'sk_live_example_value' );

		$this->assertSame( '62d94d88bf417f09171e8efb7eb30297', $fingerprint, 'The fingerprint should match the pinned known-answer vector.' );
	}

	/**
	 * Pins the exact AAD format. A reviewer reordering these fields, or a future
	 * commit accidentally dropping the site id or slot, silently weakens the binding
	 * this whole design leans on – this test makes that change loud instead.
	 *
	 * @covers WP_Secrets_Cipher::build_aad
	 */
	public function test_aad_format_known_answer(): void {
		$cipher = new WP_Secrets_Cipher();

		$aad = $this->invoke_private(
			$cipher,
			'build_aad',
			array( WP_Secrets_Cipher::AAD_VALUE, 'site', 1, self::NAME, WP_Secret_Version::CURRENT )
		);

		$this->assertSame( 'wp-secrets-value-v1|site|1|myplugin/api-key|current', $aad, 'The AAD should match the pinned format.' );
	}

	/**
	 * Raw-primitive canary, independent of this class: proves the exact
	 * key/nonce/AAD/plaintext combination below produces this exact ciphertext under
	 * the algorithm this API depends on. Encrypt_value() can't be driven through this
	 * same fixed nonce (it always generates a fresh random one, correctly), so this
	 * exists purely to catch a libsodium/sodium_compat behavior change or version
	 * mismatch that our own round-trip tests, which always encrypt and decrypt with
	 * the same implementation, would never surface.
	 *
	 * @coversNothing
	 */
	public function test_raw_aead_primitive_known_answer(): void {
		$key    = str_repeat( chr( 0x22 ), 32 );
		$nonce  = str_repeat( chr( 0x33 ), 24 );
		$aad    = 'wp-secrets-value-v1|site|1|plugin/key|current';
		$cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( 'hello world', $aad, $nonce, $key );

		$this->assertSame( 'fa9f4801aae412e9d2746dddc65d7b3d5a689f4e89461caf54975b', bin2hex( $cipher ), 'The raw AEAD ciphertext should match the pinned known-answer vector.' );
	}
}
