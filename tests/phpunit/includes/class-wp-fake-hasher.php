<?php
/**
 * WP_Fake_Hasher for testing
 *
 * @package WordPress
 * @since 6.8.0
 */

/**
 * Test class.
 *
 * @since 6.8.0
 */
class WP_Fake_Hasher {
	private $hash = '';

	public function __construct() {
		$this->hash = str_repeat( 'a', 36 );
	}

	/**
	 * Hashes a password.
	 *
	 * @param string $password Password to hash.
	 * @return string Hashed password.
	 */
	public function HashPassword( string $password ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->hash;
	}

	/**
	 * Checks the password hash.
	 *
	 * @param string $password Password to check.
	 * @param string $hash     Hash to check against.
	 * @return bool Whether the password hash is valid.
	 */
	public function CheckPassword( string $password, string $hash ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $hash === $this->hash;
	}
}
