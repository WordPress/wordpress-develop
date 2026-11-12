<?php
/**
 * Runs the provider conformance suite against the provider WordPress ships.
 *
 * The shipped provider passing is what makes the suite trustworthy: a conformance
 * kit nobody has run against a working implementation is a wish list. It also
 * means a host can compare their own failures against a known-good subject.
 *
 * @ticket 66187
 *
 * @group secrets
 *
 * @covers WP_Secrets_Libsodium_Provider::get
 * @covers WP_Secrets_Libsodium_Provider::set
 * @covers WP_Secrets_Libsodium_Provider::delete
 * @covers WP_Secrets_Libsodium_Provider::retire_previous
 * @covers WP_Secrets_Libsodium_Provider::list_secrets
 * @covers WP_Secrets_Libsodium_Provider::is_writable
 * @covers WP_Secrets_Libsodium_Provider::get_label
 * @covers WP_Secrets_Libsodium_Provider::get_protection_boundary
 */
class Tests_Secrets_LibsodiumProviderConformance extends WP_Secrets_Provider_Conformance {

	protected function provider() {
		return new WP_Secrets_Libsodium_Provider(
			new WP_Secrets_Option_Store(),
			new WP_Secrets_Key_Manager()
		);
	}
}
