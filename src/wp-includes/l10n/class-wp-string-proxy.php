<?php
/**
 * String proxy class.
 *
 * @package WordPress
 * @subpackage L10n
 * @since 6.5.0
 */

/**
 * Class WP_String_Proxy.
 *
 * Implements `JsonSerializable` interface so that it gets converted to a
 * translated string on `json_encode()`.
 *
 * Implements the `ArrayAccess` interface so that you can directly access
 * individual characters in the translated string.
 *
 * @since 6.5.0
 */
abstract class WP_String_Proxy implements JsonSerializable, ArrayAccess {

	private static $count = 0;

	protected $cache_id;

	/**
	 * Store a copy of the result when the string is modified through `offsetSet`.
	 *
	 * @var string|null
	 */
	private $modified;

	/**
	 * WP_String_Proxy constructor.
	 */
	public function __construct() {
		$this->cache_id = ++ self::$count;
	}

	/**
	 * Return the string representation of the proxy object.
	 *
	 * @since 6.5.0
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->get_modified_or_result();
	}

	/**
	 * Return the JSON representation of the proxy object.
	 *
	 * @since 6.5.0
	 *
	 * @return string
	 */
	#[ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->get_modified_or_result();
	}

	/**
	 * Lazily evaluate the result the first time it is being requested.
	 *
	 * @since 6.5.0
	 *
	 * @return string
	 */
	abstract protected function result();

	/**
	 * Check whether an offset into the array exists.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $offset Offset to check for.
	 *
	 * @return bool
	 */
	#[ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return mb_strlen( $this->get_modified_or_result() ) > $offset;
	}

	/**
	 * Retrieve a specific offset into the array.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return mixed
	 */
	#[ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		$result = $this->get_modified_or_result();

		return $result[ $offset ];
	}

	/**
	 * Set a specific offset in the array.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value  The value to set the offset to.
	 */
	#[ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {

		if ( null === $this->modified ) {
			$this->modified = $this->result();
		}

		$this->modified[ $offset ] = $value;
	}

	/**
	 * Unset a specific offset in the array.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $offset The offset to unset.
	 */
	#[ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		trigger_error( 'Cannot unset string offset', E_USER_ERROR );
	}

	/**
	 * Get the modified string or call the result function.
	 *
	 * @since 6.5.0
	 *
	 * @return string
	 */
	private function get_modified_or_result() {
		return null === $this->modified ? $this->result() : $this->modified;
	}
}
