<?php
/**
 * Secrets API: WP_Secrets_Key_Manager class
 *
 * @package WordPress
 * @subpackage Secrets
 * @since 7.2.0
 */

/**
 * Manages root keys and derives per-scope master keys from them.
 *
 * Owns root-key generation, storage, and rotation.
 *
 * On single-site installs, there is one root key stored via update_network_option()
 * (backed by wp_options). On multisite installs, there is one root key per network in
 * wp_sitemeta: 32 random bytes, generated once per network and wrapped by the active
 * WP_Secrets_Keyring. Site-scope master keys use the root key of the network the
 * target site belongs to, while network-scope master keys use the current network's
 * root key. It is the only value a site-key rotation ever re-wraps on a network.
 *
 * Master keys are derived from the root key on demand and never stored:
 *
 * - Site scope, blog N: sodium_crypto_kdf_derive_from_key( 32, N, 'wpsecsit', $root ).
 *   Distinct per blog, which shared network-wide salts otherwise cannot provide.
 * - Network scope: sodium_crypto_kdf_derive_from_key( 32, 0, 'wpsecnet', $root ).
 *   Subkey ID 0 is reserved for this (blog IDs start at 1) and the context string
 *   differs from the site path, so there is no collision between the two. Identical
 *   on every blog within the current network, so a network secret written on one blog
 *   reads on every other blog in that network.
 *
 * One unwrapped copy of each network's root key lives in this object for the rest of
 * the request, in memory only, never in the object cache. It is replaced whenever the
 * stored wrapped value changes – a rotation, a re-wrap, a restore – so it is never
 * stale. Callers of get_root_key() still receive a copy and must zero it themselves;
 * this object's own copy is not theirs to zero. The practical effect: a remote
 * keyring (a KMS or HSM call) is invoked once per network per request, not once per
 * secret.
 *
 * @since 7.2.0
 */
final class WP_Secrets_Key_Manager {

	/**
	 * Option name the wrapped root key is stored under.
	 *
	 * Always accessed through the *_network_option() functions directly, never through
	 * a WP_Secrets_Store. Swapping the pluggable secret store therefore never relocates
	 * the root key, and swapping the keyring never affects where ciphertext lives.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const ROOT_KEY_OPTION = '_wp_secrets_root_key';

	/**
	 * KDF context for deriving a site-scope master key. Exactly 8 bytes.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const SITE_KDF_CONTEXT = 'wpsecsit';

	/**
	 * KDF context for deriving the network-scope master key. Exactly 8 bytes.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const NETWORK_KDF_CONTEXT = 'wpsecnet';

	/**
	 * Reserved KDF subkey ID for network scope.
	 *
	 * Blog IDs start at 1, so this can never collide with a site-scope subkey ID.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	const NETWORK_SUBKEY_ID = 0;

	/**
	 * The keyring used to wrap and unwrap the root key.
	 *
	 * @since 7.2.0
	 * @var WP_Secrets_Keyring
	 */
	private $keyring;

	/**
	 * Unwrapped root keys currently cached for this request, keyed by network ID.
	 *
	 * @since 7.2.0
	 * @var array<int, string>
	 */
	private $cached_root_keys = array();

	/**
	 * Wrapped values that $cached_root_keys were unwrapped from, keyed by network ID.
	 *
	 * Used to detect that the stored wrapped value changed underneath this object
	 * (a rotation, a re-wrap, a restore) so the cache is not served stale.
	 *
	 * @since 7.2.0
	 * @var array<int, string>
	 */
	private $cached_wrapped = array();

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Secrets_Keyring|null $keyring Keyring to use. Defaults to the
	 *                                         built-in config-based provider.
	 */
	public function __construct( ?WP_Secrets_Keyring $keyring = null ) {
		$this->keyring = $keyring ? $keyring : new WP_Secrets_Config_Key_Provider();
	}

	/**
	 * Destructor.
	 *
	 * Clears cached unwrapped root keys from memory when the manager is destroyed.
	 *
	 * @since 7.2.0
	 */
	public function __destruct() {
		foreach ( $this->cached_root_keys as &$root_key ) {
			if ( is_string( $root_key ) ) {
				wp_secrets_memzero( $root_key );
			}
		}
		unset( $root_key );

		$this->cached_root_keys = array();
	}

	/**
	 * Returns the keyring in use.
	 *
	 * Used by Site Health and by `wp secret dropin` and `wp secret health`, which all
	 * need to describe the active keyring without duplicating the logic that resolves
	 * it.
	 *
	 * @since 7.2.0
	 *
	 * @return WP_Secrets_Keyring
	 */
	public function get_keyring(): WP_Secrets_Keyring {
		return $this->keyring;
	}

	/**
	 * Derives a scope's master key from a root key.
	 *
	 * On multisite, site-scope master keys are derived from the root key of the
	 * network that $site_id belongs to (`get_site( $site_id )->network_id`), while
	 * network-scope master keys are derived from the current network's root key.
	 *
	 * @since 7.2.0
	 *
	 * @param string   $scope           'site' or 'network'.
	 * @param int|null $site_id         Blog ID for site scope. Defaults to the current blog.
	 *                                  Ignored for network scope.
	 * @param bool     $create_root_key Optional. Whether to generate and store a root key
	 *                                  if none exists. Pass false when only reading
	 *                                  secrets. Default true.
	 * @return string|WP_Error 32-byte master key on success. WP_Error on failure,
	 *                         including when a caller passes an invalid scope or
	 *                         site ID – see WP_Secrets_Cipher::validate_common()
	 *                         for why that is a WP_Error and not an exception.
	 *
	 * @phpstan-param 'site'|'network' $scope
	 */
	public function get_master_key( string $scope, ?int $site_id = null, bool $create_root_key = true ) {
		if ( ! in_array( $scope, array( 'site', 'network' ), true ) ) {
			$message = __( 'The scope must be "site" or "network".' );

			_doing_it_wrong( __METHOD__, $message, '7.2.0' );

			return new WP_Error( WP_SECRETS_ERROR_INVALID_ARGUMENT, $message );
		}

		if ( 'network' === $scope ) {
			$subkey_id  = self::NETWORK_SUBKEY_ID;
			$context    = self::NETWORK_KDF_CONTEXT;
			$network_id = get_current_network_id();
		} else {
			$subkey_id = $site_id ?? get_current_blog_id();

			if ( $subkey_id < 1 ) {
				$message = __( 'The site id must be a positive integer.' );

				_doing_it_wrong( __METHOD__, $message, '7.2.0' );

				return new WP_Error( WP_SECRETS_ERROR_INVALID_ARGUMENT, $message );
			}

			$context    = self::SITE_KDF_CONTEXT;
			$site       = is_multisite() ? get_site( $subkey_id ) : null;
			$network_id = $site instanceof WP_Site ? (int) $site->network_id : get_current_network_id();
		}

		$root_key = $this->get_root_key( $create_root_key, $network_id );

		if ( is_wp_error( $root_key ) ) {
			return $root_key;
		}

		if ( ! function_exists( 'sodium_crypto_kdf_derive_from_key' ) ) {
			wp_secrets_memzero( $root_key );

			return new WP_Error(
				WP_SECRETS_ERROR_CRYPTO_UNAVAILABLE,
				__( 'No libsodium implementation is available.' )
			);
		}

		$master_key = sodium_crypto_kdf_derive_from_key( 32, $subkey_id, $context, $root_key );

		wp_secrets_memzero( $root_key );

		return $master_key;
	}

	/**
	 * Returns the root key for a network, generating and persisting one on first use.
	 *
	 * get_network_option() also returns false when its query fails, so a false result
	 * is checked against the database before a new root key is generated. Generating
	 * one while another is stored would make every existing secret undecryptable.
	 *
	 * @since 7.2.0
	 *
	 * @param bool     $create     Optional. Whether to generate and store a root key if none
	 *                             exists. Default true.
	 * @param int|null $network_id Optional. Network ID on multisite. Defaults to
	 *                             get_current_network_id(). Ignored on single-site.
	 * @return string|WP_Error 32 raw bytes on success. WP_Error on failure.
	 */
	public function get_root_key( bool $create = true, ?int $network_id = null ) {
		if ( null !== $network_id && $network_id < 1 ) {
			$message = __( 'Network ID must be a positive integer.' );

			_doing_it_wrong( __METHOD__, $message, '7.2.0' );

			return new WP_Error(
				WP_SECRETS_ERROR_INVALID_ARGUMENT,
				__( 'The network ID for root key lookup must be a positive integer.' )
			);
		}

		$network_id = is_multisite() && null !== $network_id ? $network_id : get_current_network_id();
		$wrapped    = $this->get_wrapped_root_key( $network_id );

		if ( false === $wrapped ) {
			$wrapped = $this->read_stored_root_key( $network_id );

			if ( is_wp_error( $wrapped ) ) {
				return $wrapped;
			}

			if ( false === $wrapped ) {
				if ( ! $create ) {
					return new WP_Error(
						WP_SECRETS_ERROR_KEY_UNAVAILABLE,
						__( 'No root key is stored.' )
					);
				}

				return $this->generate_root_key( $network_id );
			}
		}

		if ( ! is_string( $wrapped ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_RECORD_MALFORMED,
				__( 'The stored root key is not a string.' )
			);
		}

		if (
			isset( $this->cached_root_keys[ $network_id ], $this->cached_wrapped[ $network_id ] ) &&
			$wrapped === $this->cached_wrapped[ $network_id ]
		) {
			return $this->cached_root_keys[ $network_id ];
		}

		return $this->unwrap_and_cache( $wrapped, $network_id );
	}

	/**
	 * Re-wraps the root key under a new keyring, without touching any stored secret.
	 *
	 * Every derived master key is unchanged by this: they come from the root key's
	 * raw bytes, which rotation never alters, only its wrapping. No secret value is
	 * ever re-encrypted as a result of a site-key rotation.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Secrets_Keyring $old_keyring Unwraps the current wrapped root key.
	 * @param WP_Secrets_Keyring $new_keyring Wraps it again for storage.
	 * @return true|WP_Error
	 */
	public function rotate_site_key( WP_Secrets_Keyring $old_keyring, WP_Secrets_Keyring $new_keyring ) {
		$network_id = get_current_network_id();
		$wrapped    = $this->get_wrapped_root_key( $network_id );

		if ( ! is_string( $wrapped ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'No root key exists to rotate.' )
			);
		}

		if (
			$old_keyring === $this->keyring &&
			isset( $this->cached_root_keys[ $network_id ], $this->cached_wrapped[ $network_id ] ) &&
			$wrapped === $this->cached_wrapped[ $network_id ]
		) {
			$root_key = $this->cached_root_keys[ $network_id ];
		} else {
			$root_key = $old_keyring->unwrap( $wrapped );
		}

		if ( is_wp_error( $root_key ) ) {
			return $root_key;
		}

		$rewrapped = $new_keyring->wrap( $root_key );

		if ( is_wp_error( $rewrapped ) ) {
			wp_secrets_memzero( $root_key );

			return $rewrapped;
		}

		/*
		 * update_network_option() returns false both on genuine failure and when the
		 * new value equals the old one – a documented ambiguity shared with
		 * update_option(). In practice this never collides here: wrap() draws a
		 * fresh random nonce every call, so the re-wrapped value is only equal to
		 * the old one if this call somehow re-wrapped under the exact same nonce,
		 * astronomically unlikely.
		 */
		if ( ! update_network_option( $network_id, self::ROOT_KEY_OPTION, $rewrapped ) ) {
			wp_secrets_memzero( $root_key );

			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'Could not store the re-wrapped root key.' )
			);
		}

		if ( isset( $this->cached_root_keys[ $network_id ] ) ) {
			wp_secrets_memzero( $this->cached_root_keys[ $network_id ] );
		}

		$this->cached_wrapped[ $network_id ]   = $rewrapped;
		$this->cached_root_keys[ $network_id ] = $root_key;

		wp_secrets_memzero( $root_key );

		return true;
	}

	/**
	 * Returns the wrapped root key for a network, adopting a pre-conversion copy if needed.
	 *
	 * A single site converted to multisite keeps its wrapped root key in its own
	 * (now main site's) options row: `update_network_option()` only starts writing to
	 * sitemeta once `is_multisite()` is true, and the conversion process does not
	 * move existing option rows. Without this, the main network's first read would
	 * find no sitemeta row and mint a brand new root key, stranding every secret written
	 * before conversion.
	 *
	 * @since 7.2.0
	 *
	 * @param int $network_id Network ID to look up.
	 * @return mixed The wrapped root key, or false if none exists anywhere. Anything
	 *               but a string or false is a corrupt row, which the caller reports.
	 */
	private function get_wrapped_root_key( int $network_id ) {
		$wrapped = get_network_option( $network_id, self::ROOT_KEY_OPTION );

		if ( false !== $wrapped || ! is_multisite() || get_main_network_id() !== $network_id ) {
			return $wrapped;
		}

		$main_site_id = get_main_site_id( $network_id );
		$legacy       = get_blog_option( $main_site_id, self::ROOT_KEY_OPTION );

		if ( ! is_string( $legacy ) ) {
			return false;
		}

		if ( add_network_option( $network_id, self::ROOT_KEY_OPTION, $legacy ) ) {
			delete_blog_option( $main_site_id, self::ROOT_KEY_OPTION );
		}

		// Re-read: handles a concurrent request that already adopted it.
		return get_network_option( $network_id, self::ROOT_KEY_OPTION );
	}

	/**
	 * Generates a new root key and persists it for the given network.
	 *
	 * Handles the race where two requests both find no root key at the same time.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $network_id Network ID to store the root key for.
	 * @return string|WP_Error
	 */
	private function generate_root_key( int $network_id ) {
		$candidate = random_bytes( 32 );

		$wrapped = $this->keyring->wrap( $candidate );

		if ( is_wp_error( $wrapped ) ) {
			wp_secrets_memzero( $candidate );

			return $wrapped;
		}

		$inserted_row_id = $this->insert_root_key( $wrapped, $network_id );

		// Use whichever root key is stored now: this one, or one another request stored first.
		$existing = $this->read_stored_root_key( $network_id );

		if ( is_string( $existing ) && hash_equals( $existing, $wrapped ) ) {
			if ( isset( $this->cached_root_keys[ $network_id ] ) ) {
				wp_secrets_memzero( $this->cached_root_keys[ $network_id ] );
			}

			$this->cached_wrapped[ $network_id ]   = $wrapped;
			$this->cached_root_keys[ $network_id ] = $candidate;

			return $candidate;
		}

		// Lost the race: another request created one first. Use theirs, not ours.
		wp_secrets_memzero( $candidate );

		if ( $inserted_row_id ) {
			global $wpdb;
			$wpdb->delete( $wpdb->sitemeta, array( 'meta_id' => $inserted_row_id ) );
		}

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( false === $existing ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'Could not store the root key.' )
			);
		}

		return $this->unwrap_and_cache( $existing, $network_id );
	}

	/**
	 * Unwraps a wrapped root key and caches it for the given network.
	 *
	 * @since 7.2.0
	 *
	 * @param string $wrapped    The wrapped root key.
	 * @param int    $network_id Network ID to cache under.
	 * @return string|WP_Error Unwrapped root key bytes on success, WP_Error on failure.
	 */
	private function unwrap_and_cache( string $wrapped, int $network_id ) {
		$root_key = $this->keyring->unwrap( $wrapped );

		if ( is_string( $root_key ) ) {
			if ( isset( $this->cached_root_keys[ $network_id ] ) ) {
				wp_secrets_memzero( $this->cached_root_keys[ $network_id ] );
			}

			$this->cached_wrapped[ $network_id ]   = $wrapped;
			$this->cached_root_keys[ $network_id ] = $root_key;
		}

		return $root_key;
	}

	/**
	 * Stores a new wrapped root key for a network unless one is already stored.
	 *
	 * This does not use add_network_option(). On a single site that is add_option(), which
	 * skips its existence check for an option cached as missing and then overwrites the
	 * stored row, so a failed read followed by add_option() would replace the root key.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $wrapped    The wrapped root key.
	 * @param int    $network_id Network ID on multisite.
	 * @return int ID of the inserted wp_sitemeta row on multisite, 0 otherwise.
	 */
	private function insert_root_key( string $wrapped, int $network_id ): int {
		global $wpdb;

		$value = maybe_serialize( $wrapped );

		if ( ! is_multisite() ) {
			// The unique key on option_name makes this a no-op when a root key exists.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'off')",
					self::ROOT_KEY_OPTION,
					$value
				)
			);

			return 0;
		}

		/*
		 * wp_sitemeta has no unique key on meta_key, so two requests can both insert a
		 * row. read_stored_root_key() returns the oldest row, so both end up with the
		 * same root key, and the request that lost deletes its own row.
		 */
		$inserted = $wpdb->insert(
			$wpdb->sitemeta,
			array(
				'site_id'    => $network_id,
				'meta_key'   => self::ROOT_KEY_OPTION,
				'meta_value' => $value,
			)
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Reads a network's wrapped root key from the database, bypassing the options cache.
	 *
	 * get_network_option() returns false both when the root key does not exist and when
	 * its query failed, and caches the option as missing either way. This read tells
	 * the two apart. When the row exists, it also clears that cache entry.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $network_id Network ID to query on multisite.
	 * @return string|false|WP_Error The wrapped root key, false if none is stored, or
	 *                               WP_Error if the read failed or the row is malformed.
	 */
	private function read_stored_root_key( int $network_id ) {
		global $wpdb;

		if ( is_multisite() ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = %s AND site_id = %d ORDER BY meta_id ASC LIMIT 1",
					self::ROOT_KEY_OPTION,
					$network_id
				)
			);
		} else {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
					self::ROOT_KEY_OPTION
				)
			);
		}

		if ( '' !== $wpdb->last_error ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'Could not read the root key.' )
			);
		}

		if ( null === $value ) {
			return false;
		}

		$this->forget_missing_root_key( $network_id );

		$value = maybe_unserialize( $value );

		if ( ! is_string( $value ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_RECORD_MALFORMED,
				__( 'The stored root key is not a string.' )
			);
		}

		return $value;
	}

	/**
	 * Removes the root key from the cache of options that do not exist.
	 *
	 * @since 7.2.0
	 *
	 * @param int $network_id Network ID whose site-options cache should be cleared on multisite.
	 */
	private function forget_missing_root_key( int $network_id ): void {
		if ( is_multisite() ) {
			$group          = 'site-options';
			$notoptions_key = "$network_id:notoptions";

			wp_cache_delete( "$network_id:" . self::ROOT_KEY_OPTION, $group );
		} else {
			$group          = 'options';
			$notoptions_key = 'notoptions';

			wp_cache_delete( self::ROOT_KEY_OPTION, $group );
		}

		$notoptions = wp_cache_get( $notoptions_key, $group );

		if ( is_array( $notoptions ) && isset( $notoptions[ self::ROOT_KEY_OPTION ] ) ) {
			unset( $notoptions[ self::ROOT_KEY_OPTION ] );
			wp_cache_set( $notoptions_key, $notoptions, $group );
		}
	}
}
