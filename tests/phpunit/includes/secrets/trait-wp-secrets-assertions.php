<?php
/**
 * Shared assertions for the Secrets API test suite.
 *
 * Kept in one place so a change to what "never contains plaintext" means is a single
 * edit rather than a search across the suite.
 *
 * @package SecretsAPI
 */

/**
 * Assertions shared across the Secrets API test suite.
 */
trait WP_Secrets_Assertions {

	/**
	 * Assert that a value is a WP_Secret, and optionally that it reveals a specific
	 * plaintext.
	 *
	 * Checks WP_Error explicitly first so a failure reports the actual error code
	 * rather than the far less useful "WP_Error is not an instance of WP_Secret".
	 *
	 * @param mixed       $maybe_secret Value under test.
	 * @param string|null $expected     Expected plaintext, or null to skip that check.
	 * @param string      $message      Optional failure message.
	 */
	public function assertIsSecret( $maybe_secret, $expected = null, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit assertion naming, like assertWPError().
		$context = '' !== $message ? $message . ' ' : '';

		if ( is_wp_error( $maybe_secret ) ) {
			$this->fail( $context . 'Expected a WP_Secret, got WP_Error: ' . $maybe_secret->get_error_code() . ' -- ' . $maybe_secret->get_error_message() );
		}

		$this->assertInstanceOf(
			WP_Secret::class,
			$maybe_secret,
			$context . 'Expected a WP_Secret, got ' . ( is_object( $maybe_secret ) ? get_class( $maybe_secret ) : gettype( $maybe_secret ) ) . '.'
		);

		if ( null !== $expected ) {
			$this->assertSame( $expected, $maybe_secret->reveal(), $context . 'WP_Secret revealed the wrong plaintext.' );
		}
	}

	/**
	 * Assert that a stored record's slot decrypts to an expected plaintext, going
	 * through the public retrieval path.
	 *
	 * @param string $name     The secret's namespaced name.
	 * @param string $slot     A WP_Secret_Version constant.
	 * @param string $expected Expected plaintext.
	 * @param bool   $network  Whether this is a network-scope secret.
	 */
	public function assertRecordSlotDecryptsTo( $name, $slot, $expected, $network = false ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit assertion naming, like assertWPError().
		$secret = $network ? wp_get_network_secret( $name, $slot ) : wp_get_secret( $name, $slot );

		$this->assertIsSecret( $secret, $expected, sprintf( 'Slot "%s" of "%s":', $slot, $name ) );
	}

	/**
	 * Assert that a haystack of any shape contains no trace of a plaintext value.
	 *
	 * Arrays and objects are walked recursively and serialized defensively, because
	 * the failure mode this guards against is a plaintext surviving somewhere nested
	 * that a shallow string comparison would miss.
	 *
	 * @param string $plaintext Value that must not appear.
	 * @param mixed  $haystack  Structure to search.
	 * @param string $message   Optional failure message.
	 */
	public function assertNeverContainsPlaintext( $plaintext, $haystack, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit assertion naming, like assertWPError().
		$this->assertNotSame( '', $plaintext, 'Refusing to search for an empty plaintext; the assertion would be vacuous.' );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Flattening arbitrary structures is the point of this assertion; print_r reaches nested values that a shallow comparison would miss.
		$flattened = is_scalar( $haystack ) ? (string) $haystack : print_r( $haystack, true );

		$this->assertStringNotContainsString(
			$plaintext,
			$flattened,
			'' !== $message ? $message : 'Plaintext leaked into a structure that must never contain it.'
		);
	}
}
