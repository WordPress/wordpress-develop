<?php
/**
 * Dedicated coverage for wp_get_secret()'s three-state return contract: a WP_Secret
 * if it exists and decrypts, null if it does not exist, WP_Error if it exists but
 * could not be retrieved. The whole design rests on never collapsing these, so every
 * broken case here explicitly asserts `null !== $result` as well as WP_Error.
 *
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_ThreeStateContract extends WP_UnitTestCase {

	/**
	 * Ensure that wp_get_secret() returns null for a secret that was never set.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_absent_is_null(): void {
		$result = wp_get_secret( 'myplugin/never-set' );

		$this->assertNull( $result, 'A secret that was never set should be null.' );
	}

	/**
	 * Ensure that wp_get_secret() returns null, not an error, for a missing previous version.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_previous_slot_absent_on_an_existing_secret_is_also_null(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );

		$this->assertNull( $result, 'A missing previous version should be null.' );
	}

	/**
	 * Ensure that wp_get_secret() returns a WP_Error, not null, when the stored record is not an array.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_malformed_record_is_wp_error_not_null(): void {
		update_option( '_wp_secret_myplugin/api-key', 'this is a string, not a record array', false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'A malformed record should not be reported as null.' );
		$this->assertWPError( $result, 'A malformed record should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'A non-array record should produce the malformed record error code.' );
	}

	/**
	 * Ensure that wp_get_secret() returns a WP_Error, not null, for a record with no current slot.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_record_missing_the_current_slot_is_wp_error_not_null(): void {
		update_option( '_wp_secret_myplugin/api-key', array( 'v' => 1 ), false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'A record without a current slot should not be reported as null.' );
		$this->assertWPError( $result, 'A record without a current slot should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code(), 'A record without a current slot should produce the malformed record error code.' );
	}

	/**
	 * Ensure that wp_get_secret() returns a WP_Error, not null, for a record of an unknown format version.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_unsupported_record_version_is_wp_error_not_null(): void {
		update_option(
			'_wp_secret_myplugin/api-key',
			array(
				'v'       => 2,
				'current' => array(
					'dk'          => 'x',
					'dk_nonce'    => 'x',
					'ct'          => 'x',
					'nonce'       => 'x',
					'fingerprint' => 'x',
				),
			),
			false
		);

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'An unsupported record version should not be reported as null.' );
		$this->assertWPError( $result, 'An unsupported record version should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_UNSUPPORTED_VERSION, $result->get_error_code(), 'An unsupported record version should produce the unsupported version error code.' );
	}

	/**
	 * A record's AAD binds it to the specific name it was encrypted under. Storing a
	 * legitimately-encrypted record under a different name's option row – as if it
	 * had been copied or corrupted – must fail decryption, not silently succeed
	 * under the wrong context or crash.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_aad_mismatch_from_a_copied_record_is_wp_error_not_null(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/original-name', 'value' ), 'Setting myplugin/original-name to value should succeed.' );
		$copied_record = get_option( '_wp_secret_myplugin/original-name' );

		update_option( '_wp_secret_myplugin/different-name', $copied_record, false );

		$result = wp_get_secret( 'myplugin/different-name' );

		$this->assertNotNull( $result, 'A record copied under another name should not be reported as null.' );
		$this->assertWPError( $result, 'A record copied under another name should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code(), 'A record copied under another name should fail decryption.' );
	}

	/**
	 * An operator defines WP_SECRETS_KEY with a value that is not a usable string,
	 * here an integer, after secrets already exist. The key is rejected as unavailable
	 * before it is ever tried, and wp_get_secret() must report that as a WP_Error, not
	 * silently return null as if the secret never existed.
	 *
	 * The write goes through a hand-built WP_Secrets_Libsodium_Provider rather than
	 * wp_set_secret() so it bypasses the static _wp_secrets_get_key_manager(), whose
	 * request-scoped root-key cache would otherwise keep serving the
	 * already-unwrapped key and mask the misconfiguration this test defines below.
	 *
	 * @covers ::wp_get_secret
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_unavailable_is_wp_error_not_null(): void {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		$provider = new WP_Secrets_Libsodium_Provider(
			new WP_Secrets_Option_Store(),
			new WP_Secrets_Key_Manager( new WP_Secrets_Config_Key_Provider() )
		);
		$this->assertTrue( $provider->set( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		define( 'WP_SECRETS_KEY', 424242 );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'A secret with an unusable WP_SECRETS_KEY should not be reported as null.' );
		$this->assertWPError( $result, 'A secret with an unusable WP_SECRETS_KEY should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'An unusable WP_SECRETS_KEY should produce the key unavailable error code.' );
	}

	/**
	 * Ensure that a different valid WP_SECRETS_KEY makes wp_get_secret() return a WP_Error, not null.
	 *
	 * This is the realistic misconfiguration: a well-formed key that is not the one the
	 * stored root key was wrapped under, such as a regenerated key or one copied from
	 * another site. The secret is written through a keyring that reads
	 * WP_SECRETS_KEY_PREVIOUS, so this process can then define a different
	 * WP_SECRETS_KEY for the read.
	 *
	 * @covers ::wp_get_secret
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_different_valid_key_is_wp_error_not_null(): void {
		define( 'WP_SECRETS_KEY_PREVIOUS', base64_encode( str_repeat( 'a', 32 ) ) );

		$provider = new WP_Secrets_Libsodium_Provider(
			new WP_Secrets_Option_Store(),
			new WP_Secrets_Key_Manager( new WP_Secrets_Config_Key_Provider( true ) )
		);
		$this->assertTrue( $provider->set( 'myplugin/api-key', 'value' ), 'Writing the secret under the original key should succeed.' );

		define( 'WP_SECRETS_KEY', base64_encode( str_repeat( 'b', 32 ) ) );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'A secret read under a different valid WP_SECRETS_KEY should not be reported as null.' );
		$this->assertWPError( $result, 'A secret read under a different valid WP_SECRETS_KEY should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'A different valid WP_SECRETS_KEY should produce the key unavailable error code.' );
		$this->assertStringContainsString( 'could not be decrypted', $result->get_error_message(), 'The error should come from failing to decrypt the root key, not from rejecting the key format.' );
	}

	/**
	 * Written under the ambient salt-fallback key, then read back after the stored
	 * wrapped root key has been corrupted – simulating the option row being
	 * damaged after secrets already exist. get_master_key() must fail before
	 * decryption is ever attempted, since a usable root key was never obtained.
	 *
	 * @covers ::wp_get_secret
	 * @covers WP_Secrets_Key_Manager::get_master_key
	 */
	public function test_a_corrupted_wrapped_root_key_is_wp_error_not_null(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, 'not-a-valid-wrapped-value' );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'A corrupted wrapped root key should not be reported as null.' );
		$this->assertWPError( $result, 'A corrupted wrapped root key should be reported as a WP_Error.' );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code(), 'A corrupted wrapped root key should produce the key unavailable error code.' );
	}

	/**
	 * A record whose crypto fields are intact but whose unauthenticated
	 * 'fingerprint' field has been removed still decrypts, and must return a
	 * WP_Secret rather than throwing. Regression test: this previously handed an
	 * empty fingerprint to WP_Secret's constructor, which rejects one, producing an
	 * uncaught InvalidArgumentException – a fatal, and a fourth state the contract
	 * does not allow.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_record_missing_its_fingerprint_field_still_returns_a_secret(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		unset( $record['current']['fingerprint'] );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $result, 'A record without a fingerprint field should still return a WP_Secret.' );
		$this->assertSame( 'value', $result->reveal(), 'A record without a fingerprint field should still decrypt to the stored value.' );
		$this->assertNotSame( '', $result->fingerprint(), 'The fingerprint should be recomputed when the record has none.' );
	}

	/**
	 * The 'fingerprint' field is outside the AAD, so anyone who can write to the
	 * store can change it freely. WP_Secret::fingerprint() must report the value
	 * recomputed from the decrypted plaintext, not whatever the record claims.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_fingerprint_is_recomputed_not_read_from_the_record(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );
		$before = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $before, 'The secret should be a WP_Secret before the fingerprint is tampered with.' );
		$genuine = $before->fingerprint();

		$record = get_option( '_wp_secret_myplugin/api-key' );

		$this->assertIsArray( $record, 'The stored record should be an array.' );
		$this->assertArrayHasKey( 'current', $record, 'The stored record should have a current slot.' );
		$this->assertIsArray( $record['current'], 'The current slot should be an array.' );

		$record['current']['fingerprint'] = 'deadbeefdeadbeefdeadbeefdeadbeef';
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$after = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $after, 'The secret should be a WP_Secret after the fingerprint is tampered with.' );
		$this->assertSame( $genuine, $after->fingerprint(), 'The fingerprint should be recomputed rather than read from the record.' );
	}

	/**
	 * Ensure that wp_get_secret() returns a WP_Secret for a secret that exists and decrypts.
	 *
	 * @covers ::wp_get_secret
	 */
	public function test_exists_and_decrypts_is_a_wp_secret(): void {
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'Setting myplugin/api-key to value should succeed.' );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result, 'An existing secret should not be reported as null.' );
		$this->assertNotWPError( $result, 'An existing secret should not be reported as a WP_Error.' );
		$this->assertInstanceOf( WP_Secret::class, $result, 'An existing secret should be returned as a WP_Secret.' );
	}
}
