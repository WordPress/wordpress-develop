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

	protected function provider() {
		return new class() implements WP_Secrets_Provider {
			public function get( $name, $version, $network = false ) {
				return null;
			}

			public function set( $name, $value, $network = false, $needs_rotation = false, $action = null ) {
				return new WP_Error(
					WP_SECRETS_ERROR_PROVIDER_READ_ONLY,
					'Credentials are managed in the platform control panel.'
				);
			}

			public function delete( $name, $network = false ) {
				return new WP_Error( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, 'Read-only.' );
			}

			public function retire_previous( $name, $network = false ) {
				return new WP_Error( WP_SECRETS_ERROR_PROVIDER_READ_ONLY, 'Read-only.' );
			}

			public function list_secrets( $name_prefix = '', $network = false ) {
				return array();
			}

			public function get_label() {
				return 'Example Platform (control panel)';
			}

			public function get_protection_boundary() {
				return self::BOUNDARY_PROVIDER;
			}

			public function is_writable() {
				return false;
			}
		};
	}
}
