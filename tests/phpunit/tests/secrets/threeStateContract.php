<?php
/**
 * Dedicated coverage for wp_get_secret()'s three-state return contract: a WP_Secret
 * if it exists and decrypts, null if it does not exist, WP_Error if it exists but
 * could not be retrieved. The whole design rests on never collapsing these, so every
 * broken case here explicitly asserts `null !== $result` as well as WP_Error.
 *
 * @group secrets
 */
class Tests_Secrets_ThreeStateContract extends WP_UnitTestCase {

	public function test_absent_is_null() {
		$result = wp_get_secret( 'myplugin/never-set' );

		$this->assertNull( $result );
	}

	public function test_previous_slot_absent_on_an_existing_secret_is_also_null() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$result = wp_get_secret( 'myplugin/api-key', WP_Secret_Version::PREVIOUS );

		$this->assertNull( $result );
	}

	public function test_malformed_record_is_wp_error_not_null() {
		update_option( '_wp_secret_myplugin/api-key', 'this is a string, not a record array', false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code() );
	}

	public function test_record_missing_the_current_slot_is_wp_error_not_null() {
		update_option( '_wp_secret_myplugin/api-key', array( 'v' => 1 ), false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_MALFORMED, $result->get_error_code() );
	}

	public function test_unsupported_record_version_is_wp_error_not_null() {
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

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_RECORD_UNSUPPORTED_VERSION, $result->get_error_code() );
	}

	/**
	 * A record's AAD binds it to the specific name it was encrypted under. Storing a
	 * legitimately-encrypted record under a different name's option row -- as if it
	 * had been copied or corrupted -- must fail decryption, not silently succeed
	 * under the wrong context or crash.
	 */
	public function test_aad_mismatch_from_a_copied_record_is_wp_error_not_null() {
		wp_set_secret( 'myplugin/original-name', 'value' );
		$copied_record = get_option( '_wp_secret_myplugin/original-name' );

		update_option( '_wp_secret_myplugin/different-name', $copied_record, false );

		$result = wp_get_secret( 'myplugin/different-name' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_DECRYPTION_FAILED, $result->get_error_code() );
	}

	/**
	 * An operator sets the constant wrong after secrets exist: WP_SECRETS_KEY is
	 * defined with a value that cannot serve as a usable key once secrets already
	 * exist under the site's real key. wp_get_secret() must fail with WP_Error, not
	 * silently return null as if the secret never existed.
	 *
	 * The write goes through a hand-built WP_Secrets_Libsodium_Provider rather than
	 * wp_set_secret() so it bypasses the static _wp_secrets_get_key_manager(), whose
	 * request-scoped root-key cache (ADR 0009) would otherwise keep serving the
	 * already-unwrapped key and mask the misconfiguration this test defines below.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_unavailable_is_wp_error_not_null() {
		if ( 'put your unique phrase here' === LOGGED_IN_KEY || 'put your unique phrase here' === LOGGED_IN_SALT ) {
			$this->markTestSkipped( 'Needs real LOGGED_IN_KEY and LOGGED_IN_SALT values; wp-tests-config-sample.php ships placeholders.' );
		}

		$provider = new WP_Secrets_Libsodium_Provider(
			new WP_Secrets_Option_Store(),
			new WP_Secrets_Key_Manager( new WP_Secrets_Config_Key_Provider() )
		);
		$provider->set( 'myplugin/api-key', 'value' );

		define( 'WP_SECRETS_KEY', 424242 );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * Written under the ambient salt-fallback key, then read back after the stored
	 * wrapped root key has been corrupted -- simulating the option row being
	 * damaged after secrets already exist. get_master_key() must fail before
	 * decryption is ever attempted, since a usable root key was never obtained.
	 */
	public function test_a_corrupted_wrapped_root_key_is_wp_error_not_null() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		update_site_option( WP_Secrets_Key_Manager::ROOT_KEY_OPTION, 'not-a-valid-wrapped-value' );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_KEY_UNAVAILABLE, $result->get_error_code() );
	}

	/**
	 * A record whose crypto fields are intact but whose unauthenticated
	 * 'fingerprint' field has been removed still decrypts, and must return a
	 * WP_Secret rather than throwing. Regression test: this previously handed an
	 * empty fingerprint to WP_Secret's constructor, which rejects one, producing an
	 * uncaught InvalidArgumentException -- a fatal, and a fourth state the contract
	 * does not allow.
	 */
	public function test_record_missing_its_fingerprint_field_still_returns_a_secret() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$record = get_option( '_wp_secret_myplugin/api-key' );
		unset( $record['current']['fingerprint'] );
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertInstanceOf( WP_Secret::class, $result );
		$this->assertSame( 'value', $result->reveal() );
		$this->assertNotSame( '', $result->fingerprint() );
	}

	/**
	 * The 'fingerprint' field is outside the AAD, so anyone who can write to the
	 * store can change it freely. WP_Secret::fingerprint() must report the value
	 * recomputed from the decrypted plaintext, not whatever the record claims.
	 */
	public function test_fingerprint_is_recomputed_not_read_from_the_record() {
		wp_set_secret( 'myplugin/api-key', 'value' );
		$genuine = wp_get_secret( 'myplugin/api-key' )->fingerprint();

		$record                           = get_option( '_wp_secret_myplugin/api-key' );
		$record['current']['fingerprint'] = 'deadbeefdeadbeefdeadbeefdeadbeef';
		update_option( '_wp_secret_myplugin/api-key', $record, false );

		$this->assertSame( $genuine, wp_get_secret( 'myplugin/api-key' )->fingerprint() );
	}

	public function test_exists_and_decrypts_is_a_wp_secret() {
		wp_set_secret( 'myplugin/api-key', 'value' );

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertNotWPError( $result );
		$this->assertInstanceOf( WP_Secret::class, $result );
	}
}
