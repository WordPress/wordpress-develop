<?php
/**
 * Polyfills for PHP 8.0 string functions.
 *
 * Implementation follows the Symfony polyfill-php80 package.
 *
 * @see https://github.com/symfony/polyfill-php80
 */

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Check if a string starts with a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-starts-with
	 *
	 * @return bool
	 */
	function str_starts_with( string $haystack, string $needle ) {
		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Check if a string contains a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-contains
	 *
	 * @return bool
	 */
	function str_contains( string $haystack, string $needle ) {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Check if a string ends with a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-ends-with
	 *
	 * @return bool
	 */
	function str_ends_with( string $haystack, string $needle ) {
		if ( '' === $needle || $needle === $haystack ) {
			return true;
		}

		if ( '' === $haystack ) {
			return false;
		}

		$needle_length = strlen( $needle );

		return $needle_length <= strlen( $haystack ) && 0 === substr_compare( $haystack, $needle, -$needle_length );
	}
}
