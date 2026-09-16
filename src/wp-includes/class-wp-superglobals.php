<?php
/**
 * WP_Superglobals class.
 *
 * Provides an abstraction layer over PHP superglobals ($_GET, $_POST,
 * $_REQUEST, $_COOKIE, $_SERVER) to return unslashed values.
 *
 * WordPress adds slashes to superglobals via wp_magic_quotes() for
 * historical reasons. This class wraps the live superglobal by reference
 * and transparently strips slashes on read access, eliminating the need
 * for manual wp_unslash() calls throughout the codebase.
 *
 * @package WordPress
 * @since x.x.x
 *
 * @see wp_magic_quotes()
 * @link https://core.trac.wordpress.org/ticket/22325
 */

/**
 * Core class used to provide unslashed access to PHP superglobals.
 *
 * Implements ArrayAccess, Countable, and IteratorAggregate so instances
 * can be used as drop-in replacements for superglobal arrays while
 * always returning unslashed values.
 *
 * This class is read-only. Attempts to set or unset values via array
 * access will be silently ignored, following the same pattern used by
 * WP_Theme.
 *
 * @since x.x.x
 *
 * @see ArrayAccess
 * @see Countable
 * @see IteratorAggregate
 */
class WP_Superglobals implements ArrayAccess, Countable, IteratorAggregate {

	/**
	 * Reference to the underlying PHP superglobal array.
	 *
	 * @since x.x.x
	 * @var array
	 */
	private $data;

	/**
	 * Human-readable name of the superglobal, e.g. '$_GET'.
	 *
	 * Used in _doing_it_wrong() messages.
	 *
	 * @since x.x.x
	 * @var string
	 */
	private $name;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param array  $superglobal Reference to a PHP superglobal array.
	 * @param string $name        Human-readable name for debug messages, e.g. '$_GET'.
	 */
	public function __construct( &$superglobal, $name = '' ) {
		$this->data = &$superglobal;
		$this->name = $name;
	}

	/**
	 * Retrieves an unslashed value from the superglobal.
	 *
	 * @since x.x.x
	 *
	 * @param string $key           The key to retrieve.
	 * @param mixed  $default_value Optional. Default value to return if the key does not exist.
	 *                              Default null.
	 * @return mixed The unslashed value, or `$default_value` if the key is not set.
	 */
	public function get( $key, $default_value = null ) {
		if ( ! isset( $this->data[ $key ] ) ) {
			return $default_value;
		}

		return wp_unslash( $this->data[ $key ] );
	}

	/**
	 * Checks whether a key exists in the superglobal.
	 *
	 * @since x.x.x
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( $key ) {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Retrieves all values from the superglobal, unslashed.
	 *
	 * @since x.x.x
	 *
	 * @return array All unslashed values.
	 */
	public function all() {
		return wp_unslash( $this->data );
	}

	/**
	 * Checks if a parameter is set.
	 *
	 * @since x.x.x
	 *
	 * @param string $offset Key to check.
	 * @return bool Whether the key is set.
	 */
	#[ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Retrieves an unslashed value by key.
	 *
	 * @since x.x.x
	 *
	 * @param string $offset Key to retrieve.
	 * @return mixed|null Unslashed value if set, null otherwise.
	 */
	#[ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->get( $offset );
	}

	/**
	 * Not implemented. Superglobal wrappers are read-only.
	 *
	 * This method is a no-op to maintain read-only behavior.
	 *
	 * @since x.x.x
	 *
	 * @param string $offset Key to set.
	 * @param mixed  $value  Value to set.
	 */
	#[ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {}

	/**
	 * Not implemented. Superglobal wrappers are read-only.
	 *
	 * This method is a no-op to maintain read-only behavior.
	 *
	 * @since x.x.x
	 *
	 * @param string $offset Key to unset.
	 */
	#[ReturnTypeWillChange]
	public function offsetUnset( $offset ) {}

	/**
	 * Returns the number of entries in the superglobal.
	 *
	 * @since x.x.x
	 *
	 * @return int Number of entries.
	 */
	#[ReturnTypeWillChange]
	public function count() {
		return count( $this->data );
	}

	/**
	 * Returns an iterator over all unslashed values.
	 *
	 * @since x.x.x
	 *
	 * @return ArrayIterator Iterator over unslashed key-value pairs.
	 */
	#[ReturnTypeWillChange]
	public function getIterator() {
		return new ArrayIterator( $this->all() );
	}
}
