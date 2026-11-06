<?php
/**
 * Runs the keyring conformance suite against the keyring WordPress ships.
 *
 * The shipped keyring passing is what makes the suite trustworthy: a conformance
 * kit nobody has run against a working implementation is a wish list. This is the
 * known-good subject a host writing a KMS- or HSM-backed keyring can compare
 * their own failures against.
 *
 * @ticket 66187
 *
 * @group secrets
 *
 * @covers WP_Secrets_Config_Key_Provider::wrap
 * @covers WP_Secrets_Config_Key_Provider::unwrap
 * @covers WP_Secrets_Config_Key_Provider::get_key_source
 */
class Tests_Secrets_ConfigKeyringConformance extends WP_Secrets_Keyring_Conformance {

	protected function keyring() {
		return new WP_Secrets_Config_Key_Provider();
	}
}
