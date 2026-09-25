<?php
/**
 * Configurable WP_Secrets_Keyring test double. Not real cryptography -- a
 * non-deterministic transform with an integrity tag, just enough to stand in for
 * a real keyring under the conformance suite without needing that code to also
 * exercise libsodium.
 */
class Mock_Keyring implements WP_Secrets_Keyring {

	const MARKER = 'mock-wrapped:';

	private $fail_wrap    = false;
	private $fail_unwrap  = false;
	private $wrap_calls   = 0;
	private $unwrap_calls = 0;

	public function wrap( $key_material ) {
		++$this->wrap_calls;

		if ( $this->fail_wrap ) {
			return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'Mock_Keyring: wrap() configured to fail.' );
		}

		$nonce = random_bytes( 8 );
		$tag   = hash( 'sha256', $nonce . $key_material, true );

		return self::MARKER . base64_encode( $nonce . $key_material . $tag );
	}

	public function unwrap( $wrapped ) {
		++$this->unwrap_calls;

		if ( $this->fail_unwrap ) {
			return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'Mock_Keyring: unwrap() configured to fail.' );
		}

		if ( ! is_string( $wrapped ) || 0 !== strpos( $wrapped, self::MARKER ) ) {
			return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'Mock_Keyring: not a value this keyring wrapped.' );
		}

		$decoded = base64_decode( substr( $wrapped, strlen( self::MARKER ) ), true );

		if ( false === $decoded || strlen( $decoded ) < 41 ) {
			return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'Mock_Keyring: wrapped value is malformed.' );
		}

		$nonce        = substr( $decoded, 0, 8 );
		$key_material = substr( $decoded, 8, -32 );
		$tag          = substr( $decoded, -32 );

		if ( ! hash_equals( hash( 'sha256', $nonce . $key_material, true ), $tag ) ) {
			return new WP_Error( WP_SECRETS_ERROR_KEY_UNAVAILABLE, 'Mock_Keyring: integrity tag mismatch.' );
		}

		return $key_material;
	}

	public function get_key_source() {
		return 'mock keyring';
	}

	/**
	 * @param bool $fail Whether wrap() should return WP_Error.
	 *
	 * @return $this
	 */
	public function configure_fail_wrap( $fail = true ) {
		$this->fail_wrap = $fail;

		return $this;
	}

	/**
	 * @param bool $fail Whether unwrap() should return WP_Error.
	 *
	 * @return $this
	 */
	public function configure_fail_unwrap( $fail = true ) {
		$this->fail_unwrap = $fail;

		return $this;
	}

	/**
	 * @return int Number of times wrap() has been called.
	 */
	public function wrap_call_count() {
		return $this->wrap_calls;
	}

	/**
	 * @return int Number of times unwrap() has been called.
	 */
	public function unwrap_call_count() {
		return $this->unwrap_calls;
	}
}
