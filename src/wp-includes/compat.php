<?php
/**
 * WordPress implementation for PHP functions either missing from older PHP versions or not included by default.
 *
 * This file is loaded extremely early and the functions can be relied upon by drop-ins.
 * Ergo, please ensure you do not rely on external functions when writing code for this file.
 * Only use functions built into PHP or are defined in this file and have adequate testing
 * and error suppression to ensure the file will run correctly and not break websites.
 *
 * @package PHP
 * @access private
 */

// If gettext isn't available.
if ( ! function_exists( '_' ) ) {
	/**
	 * Compat function to mimic _(), an alias of gettext().
	 *
	 * @since 0.71
	 *
	 * @see https://php.net/manual/en/function.gettext.php
	 *
	 * @param string $message The message being translated.
	 * @return string
	 */
	function _( $message ) {
		return $message;
	}
}

/**
 * Returns whether PCRE/u (PCRE_UTF8 modifier) is available for use.
 *
 * @ignore
 * @since 4.2.2
 * @access private
 *
 * @param bool $set - Used for testing only
 *             null   : default - get PCRE/u capability
 *             false  : Used for testing - return false for future calls to this function
 *             'reset': Used for testing - restore default behavior of this function
 */
function _wp_can_use_pcre_u( $set = null ) {
	static $utf8_pcre = 'reset';

	if ( null !== $set ) {
		$utf8_pcre = $set;
	}

	if ( 'reset' === $utf8_pcre ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional error generated to detect PCRE/u support.
		$utf8_pcre = @preg_match( '/^./u', 'a' );
	}

	return $utf8_pcre;
}

/**
 * Indicates if a given slug for a character set represents the UTF-8 text encoding.
 *
 * A charset is considered to represent UTF-8 if it is a case-insensitive match
 * of "UTF-8" with or without the hyphen.
 *
 * Example:
 *
 *     true  === _is_utf8_charset( 'UTF-8' );
 *     true  === _is_utf8_charset( 'utf8' );
 *     false === _is_utf8_charset( 'latin1' );
 *     false === _is_utf8_charset( 'UTF 8' );
 *
 *     // Only strings match.
 *     false === _is_utf8_charset( [ 'charset' => 'utf-8' ] );
 *
 * `is_utf8_charset` should be used outside of this file.
 *
 * @ignore
 * @since 6.6.1
 *
 * @param string $charset_slug Slug representing a text character encoding, or "charset".
 *                             E.g. "UTF-8", "Windows-1252", "ISO-8859-1", "SJIS".
 *
 * @return bool Whether the slug represents the UTF-8 encoding.
 */
function _is_utf8_charset( $charset_slug ) {
	if ( ! is_string( $charset_slug ) ) {
		return false;
	}

	return (
		0 === strcasecmp( 'UTF-8', $charset_slug ) ||
		0 === strcasecmp( 'UTF8', $charset_slug )
	);
}

if ( ! function_exists( 'mb_substr' ) ) :
	/**
	 * Compat function to mimic mb_substr().
	 *
	 * @ignore
	 * @since 3.2.0
	 *
	 * @see _mb_substr()
	 *
	 * @param string      $string   The string to extract the substring from.
	 * @param int         $start    Position to being extraction from in `$string`.
	 * @param int|null    $length   Optional. Maximum number of characters to extract from `$string`.
	 *                              Default null.
	 * @param string|null $encoding Optional. Character encoding to use. Default null.
	 * @return string Extracted substring.
	 */
	function mb_substr( $string, $start, $length = null, $encoding = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
		return _mb_substr( $string, $start, $length, $encoding );
	}
endif;

/**
 * Internal compat function to mimic mb_substr().
 *
 * Only understands UTF-8 and 8bit. All other character sets will be treated as 8bit.
 * For `$encoding === UTF-8`, the `$str` input is expected to be a valid UTF-8 byte
 * sequence. The behavior of this function for invalid inputs is undefined.
 *
 * @ignore
 * @since 3.2.0
 *
 * @param string      $str      The string to extract the substring from.
 * @param int         $start    Position to being extraction from in `$str`.
 * @param int|null    $length   Optional. Maximum number of characters to extract from `$str`.
 *                              Default null.
 * @param string|null $encoding Optional. Character encoding to use. Default null.
 * @return string Extracted substring.
 */
function _mb_substr( $str, $start, $length = null, $encoding = null ) {
	if ( null === $str ) {
		return '';
	}

	if ( null === $encoding ) {
		$encoding = get_option( 'blog_charset' );
	}

	/*
	 * The solution below works only for UTF-8, so in case of a different
	 * charset just use built-in substr().
	 */
	if ( ! _is_utf8_charset( $encoding ) ) {
		return is_null( $length ) ? substr( $str, $start ) : substr( $str, $start, $length );
	}

	if ( _wp_can_use_pcre_u() ) {
		// Use the regex unicode support to separate the UTF-8 characters into an array.
		preg_match_all( '/./us', $str, $match );
		$chars = is_null( $length ) ? array_slice( $match[0], $start ) : array_slice( $match[0], $start, $length );
		return implode( '', $chars );
	}

	$regex = '/(
		[\x00-\x7F]                  # single-byte sequences   0xxxxxxx
		| [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
		| \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		| [\xE1-\xEC][\x80-\xBF]{2}
		| \xED[\x80-\x9F][\x80-\xBF]
		| [\xEE-\xEF][\x80-\xBF]{2}
		| \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
		| [\xF1-\xF3][\x80-\xBF]{3}
		| \xF4[\x80-\x8F][\x80-\xBF]{2}
	)/x';

	// Start with 1 element instead of 0 since the first thing we do is pop.
	$chars = array( '' );

	do {
		// We had some string left over from the last round, but we counted it in that last round.
		array_pop( $chars );

		/*
		 * Split by UTF-8 character, limit to 1000 characters (last array element will contain
		 * the rest of the string).
		 */
		$pieces = preg_split( $regex, $str, 1000, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		$chars = array_merge( $chars, $pieces );

		// If there's anything left over, repeat the loop.
	} while ( count( $pieces ) > 1 && $str = array_pop( $pieces ) );

	return implode( '', array_slice( $chars, $start, $length ) );
}

if ( ! function_exists( 'mb_strlen' ) ) :
	/**
	 * Compat function to mimic mb_strlen().
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @see _mb_strlen()
	 *
	 * @param string      $string   The string to retrieve the character length from.
	 * @param string|null $encoding Optional. Character encoding to use. Default null.
	 * @return int String length of `$string`.
	 */
	function mb_strlen( $string, $encoding = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
		return _mb_strlen( $string, $encoding );
	}
endif;

/**
 * Internal compat function to mimic mb_strlen().
 *
 * Only understands UTF-8 and 8bit. All other character sets will be treated as 8bit.
 * For `$encoding === UTF-8`, the `$str` input is expected to be a valid UTF-8 byte
 * sequence. The behavior of this function for invalid inputs is undefined.
 *
 * @ignore
 * @since 4.2.0
 *
 * @param string      $str      The string to retrieve the character length from.
 * @param string|null $encoding Optional. Character encoding to use. Default null.
 * @return int String length of `$str`.
 */
function _mb_strlen( $str, $encoding = null ) {
	if ( null === $encoding ) {
		$encoding = get_option( 'blog_charset' );
	}

	/*
	 * The solution below works only for UTF-8, so in case of a different charset
	 * just use built-in strlen().
	 */
	if ( ! _is_utf8_charset( $encoding ) ) {
		return strlen( $str );
	}

	if ( _wp_can_use_pcre_u() ) {
		// Use the regex unicode support to separate the UTF-8 characters into an array.
		preg_match_all( '/./us', $str, $match );
		return count( $match[0] );
	}

	$regex = '/(?:
		[\x00-\x7F]                  # single-byte sequences   0xxxxxxx
		| [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
		| \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		| [\xE1-\xEC][\x80-\xBF]{2}
		| \xED[\x80-\x9F][\x80-\xBF]
		| [\xEE-\xEF][\x80-\xBF]{2}
		| \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
		| [\xF1-\xF3][\x80-\xBF]{3}
		| \xF4[\x80-\x8F][\x80-\xBF]{2}
	)/x';

	// Start at 1 instead of 0 since the first thing we do is decrement.
	$count = 1;

	do {
		// We had some string left over from the last round, but we counted it in that last round.
		--$count;

		/*
		 * Split by UTF-8 character, limit to 1000 characters (last array element will contain
		 * the rest of the string).
		 */
		$pieces = preg_split( $regex, $str, 1000 );

		// Increment.
		$count += count( $pieces );

		// If there's anything left over, repeat the loop.
	} while ( $str = array_pop( $pieces ) );

	// Fencepost: preg_split() always returns one extra item in the array.
	return --$count;
}

// sodium_crypto_box() was introduced in PHP 7.2.
if ( ! function_exists( 'sodium_crypto_box' ) ) {
	require ABSPATH . WPINC . '/sodium_compat/autoload.php';
}

if ( ! function_exists( 'is_countable' ) ) {
	/**
	 * Polyfill for is_countable() function added in PHP 7.3.
	 *
	 * Verify that the content of a variable is an array or an object
	 * implementing the Countable interface.
	 *
	 * @since 4.9.6
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if `$value` is countable, false otherwise.
	 */
	function is_countable( $value ) {
		return ( is_array( $value )
			|| $value instanceof Countable
			|| $value instanceof SimpleXMLElement
			|| $value instanceof ResourceBundle
		);
	}
}

if ( ! function_exists( 'array_key_first' ) ) {
	/**
	 * Polyfill for array_key_first() function added in PHP 7.3.
	 *
	 * Get the first key of the given array without affecting
	 * the internal array pointer.
	 *
	 * @since 5.9.0
	 *
	 * @param array $array An array.
	 * @return string|int|null The first key of array if the array
	 *                         is not empty; `null` otherwise.
	 */
	function array_key_first( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		foreach ( $array as $key => $value ) {
			return $key;
		}
	}
}

if ( ! function_exists( 'array_key_last' ) ) {
	/**
	 * Polyfill for `array_key_last()` function added in PHP 7.3.
	 *
	 * Get the last key of the given array without affecting the
	 * internal array pointer.
	 *
	 * @since 5.9.0
	 *
	 * @param array $array An array.
	 * @return string|int|null The last key of array if the array
	 *.                        is not empty; `null` otherwise.
	 */
	function array_key_last( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		end( $array );

		return key( $array );
	}
}

if ( ! function_exists( 'array_is_list' ) ) {
	/**
	 * Polyfill for `array_is_list()` function added in PHP 8.1.
	 *
	 * Determines if the given array is a list.
	 *
	 * An array is considered a list if its keys consist of consecutive numbers from 0 to count($array)-1.
	 *
	 * @see https://github.com/symfony/polyfill-php81/tree/main
	 *
	 * @since 6.5.0
	 *
	 * @param array<mixed> $arr The array being evaluated.
	 * @return bool True if array is a list, false otherwise.
	 */
	function array_is_list( $arr ) {
		if ( ( array() === $arr ) || ( array_values( $arr ) === $arr ) ) {
			return true;
		}

		$next_key = -1;

		foreach ( $arr as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for `str_contains()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if needle is
	 * contained in haystack.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$needle` is in `$haystack`, otherwise false.
	 */
	function str_contains( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for `str_starts_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack begins with needle.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` starts with `$needle`, otherwise false.
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack ) {
			return '' === $needle;
		}

		$len = strlen( $needle );

		return substr( $haystack, -$len, $len ) === $needle;
	}
}

if ( ! function_exists( 'array_find' ) ) {
	/**
	 * Polyfill for `array_find()` function added in PHP 8.4.
	 *
	 * Searches an array for the first element that passes a given callback.
	 *
	 * @since 6.8.0
	 *
	 * @param array    $array    The array to search.
	 * @param callable $callback The callback to run for each element.
	 * @return mixed|null The first element in the array that passes the `$callback`, otherwise null.
	 */
	function array_find( array $array, callable $callback ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'array_find_key' ) ) {
	/**
	 * Polyfill for `array_find_key()` function added in PHP 8.4.
	 *
	 * Searches an array for the first key that passes a given callback.
	 *
	 * @since 6.8.0
	 *
	 * @param array    $array    The array to search.
	 * @param callable $callback The callback to run for each element.
	 * @return int|string|null The first key in the array that passes the `$callback`, otherwise null.
	 */
	function array_find_key( array $array, callable $callback ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $key;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'array_any' ) ) {
	/**
	 * Polyfill for `array_any()` function added in PHP 8.4.
	 *
	 * Checks if any element of an array passes a given callback.
	 *
	 * @since 6.8.0
	 *
	 * @param array    $array    The array to check.
	 * @param callable $callback The callback to run for each element.
	 * @return bool True if any element in the array passes the `$callback`, otherwise false.
	 */
	function array_any( array $array, callable $callback ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'array_all' ) ) {
	/**
	 * Polyfill for `array_all()` function added in PHP 8.4.
	 *
	 * Checks if all elements of an array pass a given callback.
	 *
	 * @since 6.8.0
	 *
	 * @param array    $array    The array to check.
	 * @param callable $callback The callback to run for each element.
	 * @return bool True if all elements in the array pass the `$callback`, otherwise false.
	 */
	function array_all( array $array, callable $callback ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		foreach ( $array as $key => $value ) {
			if ( ! $callback( $value, $key ) ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'array_first' ) ) {
	/**
	 * Polyfill for `array_first()` function added in PHP 8.5.
	 *
	 * Returns the first element of an array.
	 *
	 * @since 6.9.0
	 *
	 * @param array $array The array to get the first element from.
	 * @return mixed|null The first element of the array, or null if the array is empty.
	 */
	function array_first( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		foreach ( $array as $value ) {
			return $value;
		}
	}
}

if ( ! function_exists( 'array_last' ) ) {
	/**
	 * Polyfill for `array_last()` function added in PHP 8.5.
	 *
	 * Returns the last element of an array.
	 *
	 * @since 6.9.0
	 *
	 * @param array $array The array to get the last element from.
	 * @return mixed|null The last element of the array, or null if the array is empty.
	 */
	function array_last( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		return $array[ array_key_last( $array ) ];
	}
}

if ( ! function_exists( 'mb_trim' ) ) {
	/**
	 * Polyfill for `mb_trim()` function added in PHP 8.4.
	 *
	 * Trims whitespace from the beginning and end of a string.
	 *
	 * @since 6.9.0
	 *
	 * @param string      $string     The string to trim.
	 * @param string|null $characters Optional. The characters to trim from the string.
	 *                                Without the second parameter, mb_trim() will strip these characters:
	 *                                - " " (Unicode U+0020), an ordinary space.
	 *                                - "\t" (Unicode U+0009), a tab.
	 *                                - "\n" (Unicode U+000A), a new line (line feed).
	 *                                - "\r" (Unicode U+000D), a carriage return.
	 *                                - "\0" (Unicode U+0000), the NUL-byte.
	 *                                - "\v" (Unicode U+000B), a vertical tab.
	 *                                - "\f" (Unicode U+000C), a form feed.
	 *                                - "\u00A0" (Unicode U+00A0), a NO-BREAK SPACE.
	 *                                - "\u1680" (Unicode U+1680), an OGHAM SPACE MARK.
	 *                                - "\u2000" (Unicode U+2000), an EN QUAD.
	 *                                - "\u2001" (Unicode U+2001), an EM QUAD.
	 *                                - "\u2002" (Unicode U+2002), an EN SPACE.
	 *                                - "\u2003" (Unicode U+2003), an EM SPACE.
	 *                                - "\u2004" (Unicode U+2004), a THREE-PER-EM SPACE.
	 *                                - "\u2005" (Unicode U+2005), a FOUR-PER-EM SPACE.
	 *                                - "\u2006" (Unicode U+2006), a SIX-PER-EM SPACE.
	 *                                - "\u2007" (Unicode U+2007), a FIGURE SPACE.
	 *                                - "\u2008" (Unicode U+2008), a PUNCTUATION SPACE.
	 *                                - "\u2009" (Unicode U+2009), a THIN SPACE.
	 *                                - "\u200A" (Unicode U+200A), a HAIR SPACE.
	 *                                - "\u2028" (Unicode U+2028), a LINE SEPARATOR.
	 *                                - "\u2029" (Unicode U+2029), a PARAGRAPH SEPARATOR.
	 *                                - "\u202F" (Unicode U+202F), a NARROW NO-BREAK SPACE.
	 *                                - "\u205F" (Unicode U+205F), a MEDIUM MATHEMATICAL SPACE.
	 *                                - "\u3000" (Unicode U+3000), an IDEOGRAPHIC SPACE.
	 *                                - "\u0085" (Unicode U+0085), a NEXT LINE (NEL).
	 *                                - "\u180E" (Unicode U+180E), a MONGOLIAN VOWEL SEPARATOR.
	 * @param string|null $encoding  Optional. The encoding parameter is the character encoding. If it is omitted or null, the internal character encoding value will be used.
	 * @return string The trimmed string.
	 */
	function mb_trim( string $str, ?string $characters = null, ?string $encoding = null ) {
		if ( is_null( $characters ) ) {
			$characters = " \t\n\r\0\v\f\u{00A0}\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}\u{0085}\u180E";
		}

		if ( is_null( $encoding ) ) {
			$encoding = mb_internal_encoding();
		}

		if ( ! mb_check_encoding( '', $encoding ) ) {
			return $str; // If the encoding is invalid, return the original string.
		}

		if ( '' === $characters ) {
			return $str;
		}

		if ( 'UTF-8' !== $encoding ) {
			$characters = mb_convert_encoding( $characters, 'UTF-8', $encoding );
			$str        = mb_convert_encoding( $str, 'UTF-8', $encoding );
		}

		// Use preg_replace to trim the characters from both ends of the string.
		$pattern        = '/^[' . preg_quote( $characters, '/' ) . ']+|[' . preg_quote( $characters, '/' ) . ']+$/uD';
		$trimmed_string = preg_replace( $pattern, '', $str );

		if ( false === $trimmed_string ) {
			return $str; // If preg_replace fails, return the original string.
		}

		// Convert back to the original encoding if it was not UTF-8.
		if ( 'UTF-8' !== $encoding ) {
			$trimmed_string = mb_convert_encoding( $trimmed_string, $encoding, 'UTF-8' );
		}

		return $trimmed_string;
	}
}

// IMAGETYPE_AVIF constant is only defined in PHP 8.x or later.
if ( ! defined( 'IMAGETYPE_AVIF' ) ) {
	define( 'IMAGETYPE_AVIF', 19 );
}

// IMG_AVIF constant is only defined in PHP 8.x or later.
if ( ! defined( 'IMG_AVIF' ) ) {
	define( 'IMG_AVIF', IMAGETYPE_AVIF );
}

// IMAGETYPE_HEIC constant is not yet defined in PHP as of PHP 8.3.
if ( ! defined( 'IMAGETYPE_HEIC' ) ) {
	define( 'IMAGETYPE_HEIC', 99 );
}
