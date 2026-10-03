<?php
/**
 * Tests for the provider seam: the outermost extension point, and what replaced
 * the store's old supports() flag.
 *
 * @group secrets
 */
class Tests_Secrets_Provider extends WP_UnitTestCase {

	/**
	 * A provider whose credentials are managed elsewhere -- a control panel, host
	 * tooling, a KMS with its own access policy. Reads work, writes are refused.
	 *
	 * @return WP_Secrets_Provider
	 */
	private function read_only_provider() {
		return new class() implements WP_Secrets_Provider {
			public function get( $name, $version, $network = false ) {
				return null;
			}

			public function set( $name, $value, $network = false, $needs_rotation = false, $action = null ) {
				return new WP_Error(
					WP_SECRETS_ERROR_PROVIDER_READ_ONLY,
					'Credentials are managed in the control panel.'
				);
			}

			public function delete( $name, $network = false ) {
				return new WP_Error( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, 'Read-only.' );
			}

			public function retire_previous( $name, $network = false ) {
				return true;
			}

			public function list_secrets( $name_prefix = '', $network = false ) {
				return array();
			}

			public function get_label() {
				return 'Example Platform (KMS)';
			}

			public function get_protection_boundary() {
				return self::BOUNDARY_PROVIDER;
			}

			public function is_writable() {
				return false;
			}
		};
	}

	// -- the shipped provider -------------------------------------------------

	public function test_the_default_provider_is_the_libsodium_one() {
		$this->assertInstanceOf( 'WP_Secrets_Libsodium_Provider', _wp_secrets_get_provider() );
	}

	public function test_the_default_provider_protects_inside_wordpress() {
		$this->assertSame(
			WP_Secrets_Provider::BOUNDARY_WORDPRESS,
			_wp_secrets_get_provider()->get_protection_boundary()
		);
	}

	public function test_the_default_provider_accepts_writes() {
		$this->assertTrue( wp_secrets_provider_is_writable() );
	}

	/**
	 * The label is what Site Health shows an operator. It must name the key source,
	 * because "libsodium" alone does not tell anyone whether their root key is
	 * derived from wp-config.php salts or held in a KMS.
	 */
	public function test_the_default_provider_label_names_the_key_source() {
		$label = wp_secrets_provider_label();

		$this->assertStringContainsString( 'libsodium', $label );
		$this->assertStringContainsString(
			_wp_secrets_get_key_manager()->get_keyring()->get_key_source(),
			$label
		);
	}

	// -- a platform provider, which is what the seam exists for ----------------

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_drop_in_provider_replaces_the_default_entirely() {
		$GLOBALS['wp_secrets_provider'] = $this->read_only_provider();

		$this->assertSame( 'Example Platform (KMS)', wp_secrets_provider_label() );
		$this->assertSame(
			WP_Secrets_Provider::BOUNDARY_PROVIDER,
			_wp_secrets_get_provider()->get_protection_boundary()
		);
	}

	/**
	 * The case Pantheon and Altis both asked for: a provider that serves reads and
	 * refuses writes, declaring it up front so a settings screen can disable its
	 * save control rather than discovering the refusal after the fact.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_read_only_provider_refuses_writes_and_declares_it() {
		$GLOBALS['wp_secrets_provider'] = $this->read_only_provider();

		$this->assertFalse( wp_secrets_provider_is_writable() );

		$result = wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, $result->get_error_code() );
	}

	/**
	 * Reads still work on a read-only provider -- "read-only" is about writes, not
	 * about being broken. Absence is still absence.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_read_only_provider_still_serves_reads() {
		$GLOBALS['wp_secrets_provider'] = $this->read_only_provider();

		$this->assertNull( wp_get_secret( 'myplugin/api-key' ) );
	}

	// -- fail closed -----------------------------------------------------------

	/**
	 * A broken drop-in must not quietly revert to the default provider: that would
	 * downgrade a site's protection at the exact moment nobody is watching.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_fails_closed_rather_than_reverting_to_the_default() {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$provider = _wp_secrets_get_provider();

		$this->assertInstanceOf( 'WP_Secrets_Broken_Provider', $provider );
		$this->assertNotInstanceOf( 'WP_Secrets_Libsodium_Provider', $provider );
		$this->assertFalse( $provider->is_writable() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_makes_every_operation_a_wp_error() {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertWPError( wp_set_secret( 'myplugin/api-key', 'value' ) );
		$this->assertWPError( wp_get_secret( 'myplugin/api-key' ) );
		$this->assertWPError( wp_delete_secret( 'myplugin/api-key' ) );
		$this->assertWPError( wp_list_secrets() );
		$this->assertWPError( wp_retire_secret_version( 'myplugin/api-key' ) );
	}

	/**
	 * Specifically not null: a misconfigured credential backend must never look
	 * like a working site that happens to have no secrets in it yet.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_read_is_an_error_not_an_absence() {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertNotNull( wp_get_secret( 'myplugin/api-key' ) );
	}

	/**
	 * A provider global set to something that is not a provider fails closed, the
	 * same posture the store and keyring globals take -- for those two,
	 * wp_load_secrets_dropin() marks the drop-in broken on a type mismatch and
	 * both getters return their Broken_* sentinel.
	 *
	 * Falling through to the default provider instead would be the worst available
	 * outcome: a platform drop-in that misnames its class would have every read
	 * served from wp_options, find nothing there, and report a site's credentials
	 * absent when they are merely unreachable.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_non_provider_global_fails_closed() {
		$GLOBALS['wp_secrets_provider'] = new stdClass();

		$this->assertInstanceOf( 'WP_Secrets_Broken_Provider', _wp_secrets_get_provider() );
	}

	/**
	 * And it is an error rather than an absence, which is the property that
	 * actually protects the operator.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_non_provider_global_reads_as_an_error_not_an_absence() {
		$GLOBALS['wp_secrets_provider'] = new stdClass();

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertWPError( $result );
		$this->assertNotNull( $result );
	}

	/**
	 * The counterpart, and the case the routing docs must not misdescribe: a drop-in
	 * that overrides only the keyring registers no provider at all, and that is a
	 * supported arrangement rather than a broken one. It gets the shipped libsodium
	 * provider, composed with the keyring it did supply.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_keyring_only_dropin_still_gets_the_default_provider() {
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertInstanceOf( 'WP_Secrets_Libsodium_Provider', _wp_secrets_get_provider() );
	}
}
