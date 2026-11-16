<?php
/**
 * Secrets API: WP_Secrets_Store class
 *
 * @package WordPress
 * @subpackage Secrets
 * @since 7.2.0
 */

/**
 * Where a secret's encrypted record lives.
 *
 * An implementation is never handed a plaintext secret, only the record array
 * produced by WP_Secrets_Cipher and assembled by the functions in secrets.php, and
 * cannot turn encryption off; there is no method here that accepts or returns
 * anything but ciphertext-bearing structures.
 *
 * A platform store that is read-only from the application, with credentials managed
 * by a separate CLI or dashboard, returns WP_Error from set() rather than pretending
 * the write succeeded. Providers declare writability up front through
 * WP_Secrets_Provider::is_writable().
 *
 * Records must be kept apart by site and by network. A site-scope record belongs to
 * the current site, get_current_blog_id(), and a network-scope record to the current
 * network, get_current_network_id(). Neither is passed to these methods, and
 * switch_to_blog() changes the current site within a request, so an implementation
 * reads them on every call. The default WP_Secrets_Option_Store gets this from the
 * options tables, which follow the current site and network. A store backed by a
 * service outside the database has to include them in every key it uses.
 *
 * Implementations extend this class. It is abstract rather than an interface so
 * that a later release can add a method with a default implementation, and every
 * existing subclass keeps working.
 *
 * @since 7.2.0
 */
abstract class WP_Secrets_Store {

	/**
	 * Reads a secret's stored record.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return array<mixed>|null|WP_Error The record array if it exists. Null if it does not.
	 *                             WP_Error if it could not be determined which.
	 */
	abstract public function get( string $name, bool $network = false );

	/**
	 * Writes a secret's record.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $name    The secret's namespaced name.
	 * @param array<mixed> $record  The record to store.
	 * @param bool         $network Whether this is a network-scope secret.
	 * @return bool|WP_Error True on success. WP_Error on failure, including when the
	 *                       store does not accept writes.
	 */
	abstract public function set( string $name, array $record, bool $network = false );

	/**
	 * Deletes a secret's record.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return bool|WP_Error True on success (including if it did not exist).
	 *                       WP_Error on failure.
	 */
	abstract public function delete( string $name, bool $network = false );

	/**
	 * Lists the names of every secret in this store, for this scope. Never a value.
	 *
	 * @since 7.2.0
	 *
	 * @param bool $network Whether to list network-scope secrets.
	 * @return string[]|WP_Error Array of secret names on success. WP_Error on failure.
	 */
	abstract public function list_names( bool $network = false );
}
