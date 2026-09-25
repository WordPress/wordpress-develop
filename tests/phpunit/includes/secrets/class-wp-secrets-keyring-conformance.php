<?php
/**
 * Conformance suite for WP_Secrets_Keyring implementations.
 *
 * Extend this, return a keyring from keyring(), and the contract every keyring is
 * held to gets checked against it. "implements WP_Secrets_Keyring" is a claim PHP
 * can verify about method names and nothing else -- it cannot tell you that
 * wrap() is non-deterministic, that unwrap() rejects tampering instead of
 * silently returning garbage bytes, or that failure is reported as WP_Error
 * rather than an exception or a falsy string. Those are the properties that
 * actually matter to WP_Secrets_Key_Manager, the only caller of a keyring.
 *
 * @package SecretsAPI
 */
abstract class WP_Secrets_Keyring_Conformance extends WP_UnitTestCase {

	/**
	 * The keyring under test. Fresh per test.
	 *
	 * @return WP_Secrets_Keyring
	 */
	abstract protected function keyring();

	/**
	 * Wrap() followed by unwrap() has to return exactly the bytes that went in --
	 * WP_Secrets_Key_Manager derives the root key from this round trip, so any
	 * corruption here is a corrupted root key for every secret on the site.
	 */
	public function test_wrap_returns_a_non_empty_string_that_unwraps_to_the_same_bytes() {
		$keyring      = $this->keyring();
		$key_material = random_bytes( 32 );

		$wrapped = $keyring->wrap( $key_material );

		$this->assertIsString( $wrapped );
		$this->assertNotSame( '', $wrapped );

		$unwrapped = $keyring->unwrap( $wrapped );

		$this->assertSame( $key_material, $unwrapped );
	}

	/**
	 * A deterministic wrap() leaks, via ciphertext comparison, whether two wrapped
	 * values protect the same key material -- something nothing outside the
	 * keyring is entitled to learn. A fresh nonce (or equivalent) per call is what
	 * WP_Secrets_Key_Manager relies on to keep that comparison unavailable.
	 */
	public function test_two_wraps_of_the_same_bytes_return_different_strings() {
		$keyring      = $this->keyring();
		$key_material = random_bytes( 32 );

		$first  = $keyring->wrap( $key_material );
		$second = $keyring->wrap( $key_material );

		$this->assertNotSame( $first, $second );
	}

	/**
	 * Unwrap() never throws and never returns a plausible-looking string for input
	 * it did not produce -- WP_Secrets_Key_Manager treats anything other than
	 * WP_Error as usable key material, so a keyring that returns garbage bytes on
	 * garbage input hands a wrong root key downstream instead of failing.
	 */
	public function test_unwrap_of_garbage_is_a_wp_error() {
		$keyring = $this->keyring();
		$garbage = 'garbage-' . bin2hex( random_bytes( 16 ) );

		$this->assertWPError( $keyring->unwrap( $garbage ) );
	}

	/**
	 * A truncated wrapped value must fail closed rather than decode to a short,
	 * wrong key -- WP_Secrets_Key_Manager has no way to tell a merely-short key
	 * from a correctly-derived one except by trusting unwrap()'s success.
	 */
	public function test_unwrap_of_a_truncated_value_is_a_wp_error() {
		$keyring = $this->keyring();
		$wrapped = $keyring->wrap( random_bytes( 32 ) );

		$truncated = substr( $wrapped, 0, intdiv( strlen( $wrapped ), 2 ) );

		$this->assertWPError( $keyring->unwrap( $truncated ) );
	}

	/**
	 * A single flipped bit anywhere in the wrapped value has to be caught --
	 * that is the entire point of authenticated wrapping. Silently accepting it
	 * would let a corrupted or tampered wrapped root key through as genuine.
	 */
	public function test_unwrap_of_a_value_with_one_flipped_byte_is_a_wp_error() {
		$keyring = $this->keyring();
		$wrapped = $keyring->wrap( random_bytes( 32 ) );

		$flip_at             = intdiv( strlen( $wrapped ), 2 );
		$flipped             = $wrapped;
		$flipped[ $flip_at ] = chr( ord( $wrapped[ $flip_at ] ) ^ 0x01 );

		$this->assertWPError( $keyring->unwrap( $flipped ) );
	}

	/**
	 * Site Health renders get_key_source() directly; an empty or non-string value
	 * there is a blank line on a diagnostics page an operator is depending on.
	 */
	public function test_get_key_source_returns_a_non_empty_string() {
		$source = $this->keyring()->get_key_source();

		$this->assertIsString( $source );
		$this->assertNotSame( '', trim( $source ) );
	}
}
