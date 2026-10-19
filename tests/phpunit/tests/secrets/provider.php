<?php
/**
 * Tests for the provider seam: the outermost extension point, and what replaced
 * the store's old supports() flag.
 *
 * @group secrets
 */
class Tests_Secrets_Provider extends WP_UnitTestCase {

	/**
	 * A provider whose credentials are managed elsewhere – a control panel, host
	 * tooling, a KMS with its own access policy. Reads work, writes are refused.
	 *
	 * @return WP_Secrets_Provider
	 */
	private function read_only_provider() {
		return new class() implements WP_Secrets_Provider {
			/**
			 * @param string $name    The secret's name.
			 * @param string $version A WP_Secret_Version constant.
			 * @param bool   $network Whether this is a network-scope secret.
			 * @return WP_Secret|null|WP_Error
			 */
			public function get( string $name, string $version, bool $network = false ) {
				return null;
			}

			/**
			 * @param string      $name           The secret's name.
			 * @param string      $value          The plaintext value.
			 * @param bool        $network        Whether this is a network-scope secret.
			 * @param bool        $needs_rotation Mark the stored secret as needing rotation.
			 * @param string|null $action         Overrides the action reported to `wp_secret_changed`.
			 * @return WP_Error Always: this provider is read-only.
			 */
			public function set( string $name, $value, bool $network = false, bool $needs_rotation = false, ?string $action = null ) {
				return new WP_Error(
					WP_SECRETS_ERROR_PROVIDER_READ_ONLY,
					'Credentials are managed in the control panel.'
				);
			}

			/**
			 * @param string $name    The secret's name.
			 * @param bool   $network Whether this is a network-scope secret.
			 * @return WP_Error Always: this provider is read-only.
			 */
			public function delete( string $name, bool $network = false ) {
				return new WP_Error( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, 'Read-only.' );
			}

			/**
			 * @param string $name    The secret's name.
			 * @param bool   $network Whether this is a network-scope secret.
			 * @return true Always: this provider keeps no version history.
			 */
			public function retire_previous( string $name, bool $network = false ) {
				return true;
			}

			/**
			 * @param string $name_prefix Restrict to names beginning with this prefix.
			 * @param bool   $network     Whether to list network-scope secrets.
			 * @return array Always empty: this provider lists nothing.
			 *
			 * @phpstan-return list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}>
			 */
			public function list_secrets( string $name_prefix = '', bool $network = false ) {
				return array();
			}

			/**
			 * @return string
			 */
			public function get_label(): string {
				return 'Example Platform (KMS)';
			}

			/**
			 * @return string
			 */
			public function get_protection_boundary(): string {
				return self::BOUNDARY_PROVIDER;
			}

			/**
			 * @return bool
			 */
			public function is_writable(): bool {
				return false;
			}
		};
	}

	// – the shipped provider -------------------------------------------------

	public function test_the_default_provider_is_the_libsodium_one(): void {
		$this->assertInstanceOf( 'WP_Secrets_Libsodium_Provider', _wp_secrets_get_provider() );
	}

	public function test_the_default_provider_protects_inside_wordpress(): void {
		$this->assertSame(
			WP_Secrets_Provider::BOUNDARY_WORDPRESS,
			_wp_secrets_get_provider()->get_protection_boundary()
		);
	}

	public function test_the_default_provider_accepts_writes(): void {
		$this->assertTrue( wp_secrets_provider_is_writable() );
	}

	/**
	 * The label is what Site Health shows an operator. It must name the key source,
	 * because "libsodium" alone does not tell anyone whether their root key is
	 * derived from wp-config.php salts or held in a KMS.
	 */
	public function test_the_default_provider_label_names_the_key_source(): void {
		$label = wp_secrets_provider_label();

		$this->assertStringContainsString( 'libsodium', $label );
		$this->assertStringContainsString(
			_wp_secrets_get_key_manager()->get_keyring()->get_key_source(),
			$label
		);
	}

	// – a platform provider, which is what the seam exists for ----------------

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_drop_in_provider_replaces_the_default_entirely(): void {
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
	public function test_a_read_only_provider_refuses_writes_and_declares_it(): void {
		$GLOBALS['wp_secrets_provider'] = $this->read_only_provider();

		$this->assertFalse( wp_secrets_provider_is_writable() );

		$result = wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $result );
		$this->assertSame( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, $result->get_error_code() );
	}

	/**
	 * Reads still work on a read-only provider – "read-only" is about writes, not
	 * about being broken. Absence is still absence.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_read_only_provider_still_serves_reads(): void {
		$GLOBALS['wp_secrets_provider'] = $this->read_only_provider();

		$this->assertNull( wp_get_secret( 'myplugin/api-key' ) );
	}

	// – fail closed -----------------------------------------------------------

	/**
	 * A broken drop-in must not quietly revert to the default provider: that would
	 * downgrade a site's protection at the exact moment nobody is watching.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_fails_closed_rather_than_reverting_to_the_default(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$provider = _wp_secrets_get_provider();

		$this->assertNotInstanceOf( 'WP_Secrets_Libsodium_Provider', $provider );
		$this->assertInstanceOf( 'WP_Secrets_Broken_Provider', $provider );
		$this->assertFalse( $provider->is_writable() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_broken_dropin_makes_every_operation_a_wp_error(): void {
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
	public function test_a_broken_dropin_read_is_an_error_not_an_absence(): void {
		$GLOBALS['wp_secrets_dropin_broken'] = true;

		$this->assertNotNull( wp_get_secret( 'myplugin/api-key' ) );
	}

	/**
	 * A provider global set to something that is not a provider fails closed, the
	 * same posture the store and keyring globals take – for those two,
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
	public function test_a_non_provider_global_fails_closed(): void {
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
	public function test_a_non_provider_global_reads_as_an_error_not_an_absence(): void {
		$GLOBALS['wp_secrets_provider'] = new stdClass();

		$result = wp_get_secret( 'myplugin/api-key' );

		$this->assertNotNull( $result );
		$this->assertWPError( $result );
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
	public function test_a_keyring_only_dropin_still_gets_the_default_provider(): void {
		$GLOBALS['wp_secrets_keyring'] = new Mock_Keyring();

		$this->assertInstanceOf( 'WP_Secrets_Libsodium_Provider', _wp_secrets_get_provider() );
	}
}
