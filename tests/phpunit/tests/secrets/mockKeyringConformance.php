<?php
/**
 * Runs the keyring conformance suite against Mock_Keyring.
 *
 * Every other test in this suite treats Mock_Keyring as a stand-in for a real
 * keyring; this proves that stand-in is never weaker than the contract it
 * substitutes for, so a test that passes with Mock_Keyring in place is a test
 * that would also have to reckon with a conforming real keyring.
 *
 * @group secrets
 */
class Tests_Secrets_MockKeyringConformance extends WP_Secrets_Keyring_Conformance {

	protected function keyring() {
		return new Mock_Keyring();
	}
}
