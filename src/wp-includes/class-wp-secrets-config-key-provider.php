<?php
/**
 * Secrets API: WP_Secrets_Config_Key_Provider class
 *
 * @package WordPress
 * @subpackage Secrets
 * @since 7.2.0
 */

/**
 * The default keyring.
 *
 * Derives a site key from wp-config.php and uses it to wrap and unwrap the root key
 * with authenticated encryption.
 *
 * Site key derivation, in priority order:
 *
 * 1. WP_SECRETS_KEY is defined and base64-decodes (strictly) to exactly 32 bytes:
 *    those decoded bytes are used raw. This is the documented, recommended form;
 *    `wp secret generate-key` emits it.
 * 2. WP_SECRETS_KEY is defined in any other shape: the literal constant string is
 *    hashed with a keyed BLAKE2b to 32 bytes. This is the legacy interpretation --
 *    sites arriving from a prior plugin with a constant of arbitrary shape are not
 *    locked out of their own credentials by a hard failure here.
 * 3. WP_SECRETS_KEY is undefined: LOGGED_IN_KEY . LOGGED_IN_SALT is hashed the same
 *    way. Deliberately byte-identical to the legacy interpretation's hashing, not a
 *    coincidence -- it is what makes salt-fallback sites migrate with zero
 *    credential re-entry.
 *
 * WP_SECRETS_KEY_PREVIOUS follows the same three rules and exists only so a site-key
 * rotation can unwrap under the old key before wrapping under the new one.
 *
 * @since 7.2.0
 */
final class WP_Secrets_Config_Key_Provider implements WP_Secrets_Keyring {

	/**
	 * AAD binding the root key's wrapped form to this purpose.
	 *
	 * Prevents the wrapped root key from being replayed as some other wrapped value.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const AAD = 'wp-secrets-root-key-v1';

	/**
	 * The exact text wp-config-sample.php ships for every auth and salt constant.
	 *
	 * A site running with this literal value never ran the secret-key generator.
	 * Treating it as usable would derive a key that any attacker can also derive.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const KNOWN_PLACEHOLDER = 'put your unique phrase here';

	/**
	 * Whether to derive from WP_SECRETS_KEY_PREVIOUS instead of WP_SECRETS_KEY.
	 *
	 * @since 7.2.0
	 * @var bool
	 */
	private $use_previous_key;

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 *
	 * @param bool $use_previous_key Derive from WP_SECRETS_KEY_PREVIOUS rather than
	 *                               WP_SECRETS_KEY. Used only during a site-key
	 *                               rotation, to unwrap under the outgoing key.
	 */
	public function __construct( $use_previous_key = false ) {
		$this->use_previous_key = (bool) $use_previous_key;
	}

	/**
	 * Wraps raw key material under the derived site key.
	 *
	 * @since 7.2.0
	 *
	 * @param string $key_material Raw key material to protect.
	 *
	 * @return string|WP_Error
	 */
	public function wrap( $key_material ) {
		if ( ! is_string( $key_material ) || '' === $key_material ) {
			return new WP_Error(
				WP_SECRETS_ERROR_INVALID_VALUE,
				__( 'Key material to wrap must be a non-empty string.', 'default' )
			);
		}

		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE,
				__( 'No libsodium implementation is available.', 'default' )
			);
		}

		$site_key = $this->get_site_key();

		if ( is_wp_error( $site_key ) ) {
			return $site_key;
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $key_material, self::AAD, $nonce, $site_key );

		wp_secrets_memzero( $site_key );

		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Unwraps key material previously wrapped by wrap().
	 *
	 * @since 7.2.0
	 *
	 * @param string $wrapped An opaque value previously returned by wrap().
	 *
	 * @return string|WP_Error
	 */
	public function unwrap( $wrapped ) {
		if ( ! is_string( $wrapped ) || '' === $wrapped ) {
			return new WP_Error(
				WP_SECRETS_ERROR_KEY_UNAVAILABLE,
				__( 'Nothing to unwrap.', 'default' )
			);
		}

		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE,
				__( 'No libsodium implementation is available.', 'default' )
			);
		}

		$raw          = base64_decode( $wrapped, true );
		$nonce_length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		if ( false === $raw || strlen( $raw ) <= $nonce_length ) {
			return new WP_Error(
				WP_SECRETS_ERROR_KEY_UNAVAILABLE,
				__( 'The wrapped key material is malformed.', 'default' )
			);
		}

		$nonce      = substr( $raw, 0, $nonce_length );
		$ciphertext = substr( $raw, $nonce_length );

		$site_key = $this->get_site_key();

		if ( is_wp_error( $site_key ) ) {
			return $site_key;
		}

		$key_material = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, self::AAD, $nonce, $site_key );

		wp_secrets_memzero( $site_key );

		if ( false === $key_material ) {
			return new WP_Error(
				WP_SECRETS_ERROR_KEY_UNAVAILABLE,
				__( 'The wrapped key material could not be decrypted with the configured site key.', 'default' )
			);
		}

		return $key_material;
	}

	/**
	 * Describes the active key source, for Site Health.
	 *
	 * @since 7.2.0
	 *
	 * @return string
	 */
	public function get_key_source() {
		if ( defined( 'WP_SECRETS_KEY' ) ) {
			return $this->is_canonical_base64_32( WP_SECRETS_KEY )
				? 'WP_SECRETS_KEY (base64-encoded 32 bytes)'
				: 'WP_SECRETS_KEY (legacy interpretation -- hashed as an opaque string)';
		}

		return 'derived from LOGGED_IN_KEY and LOGGED_IN_SALT';
	}

	/**
	 * Derives the 32-byte site key per the priority rules documented on this class.
	 *
	 * @since 7.2.0
	 *
	 * @return string|WP_Error
	 */
	private function get_site_key() {
		$constant_name = $this->use_previous_key ? 'WP_SECRETS_KEY_PREVIOUS' : 'WP_SECRETS_KEY';

		if ( defined( $constant_name ) ) {
			$raw_constant = constant( $constant_name );

			if ( ! is_string( $raw_constant ) || '' === $raw_constant ) {
				return new WP_Error(
					WP_SECRETS_ERROR_KEY_UNAVAILABLE,
					sprintf(
						/* translators: %s: PHP constant name. */
						__( '%s is defined but is not a usable string.', 'default' ),
						$constant_name
					)
				);
			}

			if ( $this->is_canonical_base64_32( $raw_constant ) ) {
				return base64_decode( $raw_constant, true );
			}

			return sodium_crypto_generichash( $raw_constant, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES );
		}

		if ( $this->use_previous_key ) {
			return new WP_Error(
				WP_SECRETS_ERROR_KEY_UNAVAILABLE,
				__( 'WP_SECRETS_KEY_PREVIOUS is not defined.', 'default' )
			);
		}

		$logged_in_key  = defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : null;
		$logged_in_salt = defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : null;

		if ( ! $this->are_usable_salt_values( $logged_in_key, $logged_in_salt ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_KEY_UNAVAILABLE,
				__( 'WP_SECRETS_KEY is not defined, and LOGGED_IN_KEY/LOGGED_IN_SALT are not usable (undefined, empty, or left at the wp-config-sample.php placeholder).', 'default' )
			);
		}

		return sodium_crypto_generichash( $logged_in_key . $logged_in_salt, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES );
	}

	/**
	 * Whether a pair of candidate salt values are usable as key material.
	 *
	 * Both must be non-empty strings, and neither may be the literal
	 * wp-config-sample.php placeholder -- which every unconfigured install shares,
	 * making it a publicly known value rather than a secret.
	 *
	 * Takes explicit arguments rather than reading LOGGED_IN_KEY/LOGGED_IN_SALT
	 * directly so the decision logic is testable without touching real constants,
	 * which -- once WordPress has bootstrapped once in a process -- can never be
	 * redefined to a different value for a test.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $logged_in_key  Candidate value of LOGGED_IN_KEY, or null if undefined.
	 * @param mixed $logged_in_salt Candidate value of LOGGED_IN_SALT, or null if undefined.
	 *
	 * @return bool
	 */
	private function are_usable_salt_values( $logged_in_key, $logged_in_salt ) {
		foreach ( array( $logged_in_key, $logged_in_salt ) as $value ) {
			if ( ! is_string( $value ) || '' === $value || self::KNOWN_PLACEHOLDER === $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether $value is the canonical base64 encoding of exactly 32 bytes.
	 *
	 * Uses strict-mode decoding plus a re-encode comparison, rather than a bare
	 * base64_decode(), so that a malformed or non-canonically-padded constant falls
	 * through to the legacy (hashed) interpretation instead of being silently
	 * accepted as 32 bytes of something else.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Candidate value.
	 *
	 * @return bool
	 */
	private function is_canonical_base64_32( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded || SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen( $decoded ) ) {
			return false;
		}

		return base64_encode( $decoded ) === $value;
	}
}
