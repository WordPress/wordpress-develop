<?php
/**
 * And against a read-only provider, which is the shape Pantheon and Altis
 * described: reads served, writes refused by the platform's own tooling.
 *
 * Included so the suite is exercised on both sides of every is_writable() branch.
 * A conformance kit that has only ever run against a writable provider would not
 * have proven that its own skips work.
 *
 * @group secrets
 */
class Tests_Secrets_ReadOnlyProviderConformance extends WP_Secrets_Provider_Conformance {

	/**
	 * The provider under test: one that serves reads and refuses every write.
	 *
	 * @return WP_Secrets_Provider
	 */
	protected function provider() {
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
					'Credentials are managed in the platform control panel.'
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
			 * @return WP_Error Always: this provider is read-only.
			 */
			public function retire_previous( string $name, bool $network = false ) {
				return new WP_Error( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, 'Read-only.' );
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
				return 'Example Platform (control panel)';
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
}
