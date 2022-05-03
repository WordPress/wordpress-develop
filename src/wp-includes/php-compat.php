<?php

define( "IS_32_BIT_SYSTEM", (int) 2147483648 !== 2147483648 );
defined( "PHP_INT_MAX" ) || define("PHP_INT_MAX", IS_32_BIT_SYSTEM ? 2147483647 : 9223372036854775807 );
defined( "PHP_INT_MIN" ) || define("PHP_INT_MIN", IS_32_BIT_SYSTEM ? -2147483648 : -9223372036854775808 );

/**
 * Returns byte size represented in a numeric php.ini directive.
 *
 * @param false|int|string $value
 * @return int
 */
function wp_ini_bytes( $value ) {
	//  A missing value is an implicit lack of limit, thus we return `0`, meaning "no limit."
	if ( ! wp_ini_bytes_is_present( $value ) ) {
		return 0;
	}

	/*
	 * If passed an already-parsed value return it directly. This makes
	 * it cheap to pass values that may have already been parsed and
	 * removes the need to track whether that has already happened.
	 */
	if ( is_int( $value ) ) {
		return $value;
	}

	/*
	 * Non-string inputs "fail" to no limit, because there's
	 * no limit we could ascribe to this invalid value.
	 */
	if ( ! is_string( $value ) ) {
		return 0;
	}

	return php_compat_ini_bytes( $value );
}

function wp_ini_bytes_is_present( $value ) {
	return -1 !== $value && false !== $value;
}

/**
 * Returns byte size represented in a numeric php.ini directive.
 *
 * php.ini directives may use a string representation of a number of bytes
 * or a "shorthand" byte size to reference larger values. Multiple numeric
 * php.ini directive use these shorthands even when they don't refer to bytes.
 *
 * Example:
 *
 *     php_compat_ini_bytes( "1m" ) == 1048576
 *     php_compat_ini_bytes( "2K" ) == 2048 // 2 * 1024
 *     php_compat_ini_bytes( "0.5g" ) == 0
 *     php_compat_ini_bytes( "14.6e-13g" ) == 15032385536 // 14 * 1024^3
 *     php_compat_ini_bytes( "-813k" ) == 0;
 *     php_compat_ini_bytes( "boat" ) == 0;
 *
 *     // This gives an answer, but it's _wrong_ because
 *     // the underlying mechanism in PHP overflowed and
 *     // the real return value depends on whether PHP
 *     // was built with 64-bit support.
 *     php_compat_ini_bytes( "9223372036854775807g" ) == ??
 *
 * Notes:
 *  - Suffix units are case-insensitive and are always determined
 *    by looking at the last character in the input string.
 *  - Suffix units k/m/g report powers of 1024. PHP and the IEC disagree
 *    on the meaning of "kilobyte," "megabyte," and "gigabyte."
 *  - This function will not fail; it stops parsing after finding
 *    the last consecutive digit at the front of the trimmed string.
 *  - Invalid string representations return a value of 0.
 *  - As noted in the PHP documentation, any numeric value that overflows
 *    an integer for the platform on which PHP is built will break.
 *
 * @since 6.1.0
 *
 * @link https://www.php.net/manual/en/function.ini-get.php
 * @link https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 * @link https://en.wikipedia.org/wiki/Byte#Multiple-byte_units
 *
 * @param string $value A numeric php.ini directive's byte value,
 *                      either shorthand or ordinary, as returned
 *                      by a call to `ini_get()`.
 * @return int          Parsed numeric value represented by given string.
 */
function php_compat_ini_bytes( $value ) {
	/** @var int Number of bytes in input string; we're only assessing 7-bit ASCII/Unicode characters. */
	$strlen = strlen( $value );

	/** @var int|float Count (of bytes) represented by value string. */
	$scalar = 0;

	/** @var int Sign of number represented by input, either positive (1) or negative (-1). */
	$sign = 1;

	/** @var int Numeric base of digits; determined by string prefix (e.g. "0x" or "0"). */
	$base = 10;

	/** @var int Index into input string as we walk through it and analyze each character. */
	$i = 0;

	/*
	 * Trim leading whitespace.
	 *
	 * We could also do this with `ltrim()` but that adds a needless
	 * string copy and makes it appear like we could add `+` to the
	 * list of values to trim, which we cannot, because that
	 * results in the wrong parse for strings with multiple
	 * `+` or `-` characters in a row.
	 */
	for ( ; $i < $strlen; $i++ ) {
		switch ( $value[ $i ] ) {
			case ' ':
			case "\t":
			case "\r":
			case "\v":
			case "\f":
				break;

			default:
				break 2;
		}
	}

	// Handle optional sign indicator.
	switch ( $value[ $i ] ) {
		case '+':
			$i++;
			break;

		case '-':
			$sign = -1;
			$i++;
			break;
	}

	// Determine base for digit conversion, if not decimal.
	$base_a = $i < $strlen ? $value[ $i ] : '';
	$base_b = $i + 1 < $strlen ? $value[ $i + 1 ] : '';

	if ( '0' === $base_a && ( 'x' === $base_b || 'X' === $base_b ) ) {
		$base = 16;
		$i += 2;
	} else if ( '0' === $base_a && ctype_digit( $base_b ) ) {
		$base = 8;
		$i += 1;
	}

	// Trim leading zeros.
	for ( ; $i < $strlen; $i++ ) {
		if ( '0' !== $value[ $i ] ) {
			break;
		}
	}

	/**
	 * Numeric values for scanned digits.
	 *
	 * These are used both to determine the decimal value the digit
	 * represents as well as whether it's an allowed character in
	 * the given base system. It's allowed if its value is less
	 * than the base: e.g. '7' is allowed in octal, "base 8"
	 * but '8' and '9' aren't because they are above it.
	 *
	 * @var array
	 */
	$digits = array(
		'0' => 0,
		'1' => 1,
		'2' => 2,
		'3' => 3,
		'4' => 4,
		'5' => 5,
		'6' => 6,
		'7' => 7,
		'8' => 8,
		'9' => 9,
		'A' => 10,
		'a' => 10,
		'B' => 11,
		'b' => 11,
		'C' => 12,
		'c' => 12,
		'D' => 13,
		'd' => 13,
		'E' => 14,
		'e' => 14,
		'F' => 15,
		'f' => 15,
	);

	/*
	 * Build the scalar value by eating the next sequence of contiguous digits.
	 */
	for ( ; $i < $strlen; $i++ ) {
		$c = $value[ $i ];

		/*
		 * Only digits recognized in this base system can be used.
		 * Once we find an unrecognized digit we abort and move
		 * on to the next step in parsing the size suffix.
		 */
		if ( ! isset( $digits[ $c ] ) || $digits[ $c ] >= $base ) {
			break;
		}

		$scalar = $scalar * $base + $digits[ $c ];

		// Stop processing if we're already at the max value.
		if (
			( $sign > 0 && $scalar > PHP_INT_MAX ) ||
			( $sign < 0 && $scalar > -PHP_INT_MIN )
		) {
			break;
		}
	}

	// Clamp the parsed digits to an integer value as PHP does internally.
	if ( $sign > 0 && $scalar >= PHP_INT_MAX ) {
		$scalar = PHP_INT_MAX;
	} else if ( $sign < 0 && $scalar >= -PHP_INT_MIN ) {
		$scalar = PHP_INT_MIN;
	} else if ( $sign < 0 ) {
		$scalar = -$scalar;
	}

	/*
	 * Do not use WP constants here (GB_IN_BYTES, MB_IN_BYTES, KB_IN_BYTES)
	 * since they are re-definable; PHP shorthand values are hard-coded
	 * in PHP itself and stay the same regardless of these constants.
	 *
	 * Note that we can overflow here, as happens in PHP itself.
	 * Overflow results will likely not match PHP's value, but
	 * will likely break in most cases anyway and so leaving
	 * this loose is the best we can do until and unless PHP
	 * makes a more concrete choice on how to handle overflow.
	 */
	switch ( $value[ $strlen - 1 ] ) {
		case 'g':
		case 'G':
			$scalar *= 1073741824; // 1024^3
			break;

		case 'm':
		case 'M':
			$scalar *= 1048576; // 1024^2
			break;

		case 'k':
		case 'K':
			$scalar *= 1024;
			break;
	}

	return (int) $scalar;
}
