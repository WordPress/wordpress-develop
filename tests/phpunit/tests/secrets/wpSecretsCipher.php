<?php
/**
 * Tests for WP_Secrets_Cipher.
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretsCipher extends WP_UnitTestCase {

	const NAME = 'myplugin/api-key';

	private function invoke_private( $instance, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $instance, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $instance, $args );
	}

	private function master_key( $byte = 0x11 ) {
		return str_repeat( chr( $byte ), 32 );
	}

	public function test_round_trips_a_value() {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'sk_live_secret' );

		$this->assertIsArray( $record );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( 'sk_live_secret', $plaintext );
	}

	public function test_round_trips_an_empty_string_value() {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, '' );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( '', $plaintext );
	}

	public function test_round_trips_under_network_scope() {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'network', 0, self::NAME, WP_Secret_Version::CURRENT, 'network-secret' );

		$plaintext = $cipher->decrypt_value( $this->master_key(), 'network', 0, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertSame( 'network-secret', $plaintext );
	}

	public function test_encrypt_rejects_a_non_string_value() {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, array() );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	/**
	 * @dataProvider data_bad_master_keys
	 */
	public function test_encrypt_rejects_a_bad_master_key( $bad_key ) {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $bad_key, 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function data_bad_master_keys() {
		return array(
			'too short'    => array( str_repeat( 'a', 31 ) ),
			'too long'     => array( str_repeat( 'a', 33 ) ),
			'empty'        => array( '' ),
			'not a string' => array( 12345 ),
		);
	}

	public function test_encrypt_propagates_an_invalid_name() {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, 'Not A Valid Name', WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_NAME, $result->get_error_code() );
	}

	public function test_encrypt_reports_an_invalid_scope_as_a_wp_error() {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'bogus-scope', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function test_encrypt_reports_a_negative_site_id_as_a_wp_error() {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'site', -1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	public function test_encrypt_reports_an_invalid_slot_as_a_wp_error() {
		$cipher = new WP_Secrets_Cipher();

		$this->setExpectedIncorrectUsage( 'WP_Secrets_Cipher::encrypt_value()/decrypt_value()' );

		$result = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, 'not-a-real-slot', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_ARGUMENT, $result->get_error_code() );
	}

	/**
	 * AAD binding: a record encrypted under one context must not decrypt under a
	 * different one, for any single component of that context.
	 *
	 * @dataProvider data_mismatched_context
	 */
	public function test_decrypt_fails_when_context_does_not_match( $scope, $site_id, $name, $slot ) {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$result = $cipher->decrypt_value( $this->master_key(), $scope, $site_id, $name, $slot, $record );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code() );
	}

	public function data_mismatched_context() {
		return array(
			'different scope'   => array( 'network', 1, self::NAME, WP_Secret_Version::CURRENT ),
			'different site id' => array( 'site', 2, self::NAME, WP_Secret_Version::CURRENT ),
			'different name'    => array( 'site', 1, 'myplugin/other-key', WP_Secret_Version::CURRENT ),
			'different slot'    => array( 'site', 1, self::NAME, WP_Secret_Version::PREVIOUS ),
		);
	}

	public function test_decrypt_fails_under_a_different_master_key() {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key( 0x11 ), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$result = $cipher->decrypt_value( $this->master_key( 0x22 ), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code() );
	}

	/**
	 * @dataProvider data_tamperable_fields
	 */
	public function test_decrypt_fails_when_a_field_is_tampered( $field ) {
		$cipher = new WP_Secrets_Cipher();
		$record = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );

		$raw              = base64_decode( $record[ $field ], true );
		$last             = strlen( $raw ) - 1;
		$raw[ $last ]     = chr( ( ord( $raw[ $last ] ) + 1 ) % 256 );
		$record[ $field ] = base64_encode( $raw );

		$result = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result );
	}

	public function data_tamperable_fields() {
		return array(
			'ciphertext'       => array( 'ct' ),
			'value nonce'      => array( 'nonce' ),
			'wrapped data key' => array( 'dk' ),
			'data key nonce'   => array( 'dk_nonce' ),
		);
	}

	/**
	 * @dataProvider data_malformed_records
	 */
	public function test_decrypt_rejects_a_malformed_record( $record ) {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->decrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, $record );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code() );
	}

	public function data_malformed_records() {
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

	public function test_data_key_independence() {
		$cipher = new WP_Secrets_Cipher();

		$a = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'same-value' );
		$b = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'same-value' );

		$this->assertNotSame( $a['dk'], $b['dk'] );
		$this->assertNotSame( $a['ct'], $b['ct'] );
	}

	public function test_nonce_uniqueness_across_many_writes() {
		$cipher = new WP_Secrets_Cipher();
		$nonces = array();

		for ( $i = 0; $i < 50; $i++ ) {
			$record   = $cipher->encrypt_value( $this->master_key(), 'site', 1, self::NAME, WP_Secret_Version::CURRENT, 'value' );
			$nonces[] = $record['nonce'];
			$nonces[] = $record['dk_nonce'];
		}

		$this->assertSame( count( $nonces ), count( array_unique( $nonces ) ) );
	}

	public function test_fingerprint_is_stable_for_the_same_inputs() {
		$cipher = new WP_Secrets_Cipher();

		$this->assertSame(
			$cipher->fingerprint( $this->master_key(), 'same-value' ),
			$cipher->fingerprint( $this->master_key(), 'same-value' )
		);
	}

	public function test_fingerprint_differs_across_master_keys() {
		$cipher = new WP_Secrets_Cipher();

		$this->assertNotSame(
			$cipher->fingerprint( $this->master_key( 0x11 ), 'same-value' ),
			$cipher->fingerprint( $this->master_key( 0x22 ), 'same-value' )
		);
	}

	public function test_fingerprint_differs_across_plaintexts() {
		$cipher = new WP_Secrets_Cipher();

		$this->assertNotSame(
			$cipher->fingerprint( $this->master_key(), 'value-a' ),
			$cipher->fingerprint( $this->master_key(), 'value-b' )
		);
	}

	public function test_fingerprint_rejects_a_bad_master_key() {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->fingerprint( 'too-short', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	public function test_fingerprint_rejects_a_non_string_value() {
		$cipher = new WP_Secrets_Cipher();

		$result = $cipher->fingerprint( $this->master_key(), array() );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_INVALID_VALUE, $result->get_error_code() );
	}

	/**
	 * Known-answer vector for sodium_crypto_kdf_derive_from_key(32, 1, 'wpsecfpr', ...)
	 * followed by sodium_crypto_generichash(..., ..., 16), computed independently on
	 * both PHP 7.4 and 8.5 and pinned here so an accidental change to either
	 * primitive, the KDF subkey id, the context string, or the output length is
	 * caught rather than silently changing every existing fingerprint.
	 */
	public function test_fingerprint_known_answer_vector() {
		$cipher = new WP_Secrets_Cipher();

		$fingerprint = $cipher->fingerprint( str_repeat( chr( 0x11 ), 32 ), 'sk_live_example_value' );

		$this->assertSame( '62d94d88bf417f09171e8efb7eb30297', $fingerprint );
	}

	/**
	 * Pins the exact AAD format. A reviewer reordering these fields, or a future
	 * commit accidentally dropping the site id or slot, silently weakens the binding
	 * this whole design leans on -- this test makes that change loud instead.
	 */
	public function test_aad_format_known_answer() {
		$cipher = new WP_Secrets_Cipher();

		$aad = $this->invoke_private(
			$cipher,
			'build_aad',
			array( WP_Secrets_Cipher::AAD_VALUE, 'site', 1, self::NAME, WP_Secret_Version::CURRENT )
		);

		$this->assertSame( 'wp-secrets-value-v1|site|1|myplugin/api-key|current', $aad );
	}

	/**
	 * Raw-primitive canary, independent of this class: proves the exact
	 * key/nonce/AAD/plaintext combination below produces this exact ciphertext under
	 * the algorithm this API depends on. Encrypt_value() can't be driven through this
	 * same fixed nonce (it always generates a fresh random one, correctly), so this
	 * exists purely to catch a libsodium/sodium_compat behavior change or version
	 * mismatch that our own round-trip tests, which always encrypt and decrypt with
	 * the same implementation, would never surface.
	 */
	public function test_raw_aead_primitive_known_answer() {
		$key    = str_repeat( chr( 0x22 ), 32 );
		$nonce  = str_repeat( chr( 0x33 ), 24 );
		$aad    = 'wp-secrets-value-v1|site|1|plugin/key|current';
		$cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( 'hello world', $aad, $nonce, $key );

		$this->assertSame( 'fa9f4801aae412e9d2746dddc65d7b3d5a689f4e89461caf54975b', bin2hex( $cipher ) );
	}
}
