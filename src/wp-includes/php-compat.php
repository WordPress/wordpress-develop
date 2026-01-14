<?php

defined( 'IS_32_BIT_SYSTEM' ) || define( 'IS_32_BIT_SYSTEM', 2147483647 === PHP_INT_MAX );

/**
 * Returns the numeric byte size value for numeric php.ini directives.
 *
 * Generally this can be used in combination with {@see \ini_get()}
 * for values that accept a numeric "byte" size, such as `post_max_size`.
 * It will return the value which PHP interprets from the "shorthand"
 * syntax, such as "128m" being 128 MiB and "128mb" being 128 B.
 *
 * @see \ini_parse_quantity()
 *
 * @since 7.0.0
 *
 * @param false|int|string $value
 * @return int
 */
function wp_ini_parse_quantity( $value ) {
	// A missing value is an implicit lack of limit, thus we return `0`, meaning "no limit."
	if ( false === $value ) {
		return 0;
	}

	/*
	 * Directly return pre-parsed values so we can repeatedly call
	 * this without tracking if we've already parsed a given value.
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

	return function_exists( 'ini_parse_quantity' )
		? ini_parse_quantity( $value )
		: ini_parse_quantity_fallback( $value );
}

/**
 * Returns larger of two php.ini directive quantity values.
 *
 * Example:
 *     wp_ini_greater_quantity( '256m', -1 ) === -1
 *     wp_ini_greater_quantity( '64K', '64') === '64K'
 *     wp_ini_greater_quantity( 1000, 2000 ) === 2000
 *
 * @param int|string|false $a Quantity value.
 * @param int|string|false $b Quantity value.
 * @return int|string|false   Larger quantity value.
 */
function wp_ini_greater_quantity( $a, $b ) {
	return wp_ini_quantity_cmp( $a, $b ) >= 0 ? $a : $b;
}

/**
 * Returns smaller of two php.ini directive quantity values.
 *
 * Example:
 *     wp_ini_lesser_quantity( '256m', -1 ) === '256m'
 *     wp_ini_lesser_quantity( '64K', '64') === '64'
 *     wp_ini_lesser_quantity( 1000, 2000 ) === 1000
 *
 * @param int|string|false $a Quantity value.
 * @param int|string|false $b Quantity value.
 * @return int|string|false   Smaller quantity value.
 */
function wp_ini_lesser_quantity( $a, $b ) {
	return wp_ini_quantity_cmp( $a, $b ) <= 0 ? $a : $b;
}

/**
 * Comparator for php.ini quantity values, can be used
 * as the callback for functions such as `usort()`.
 *
 * Example:
 *     $a  <  $b => -1
 *     $a === $b =>  0
 *     $a  >  $b =>  1
 *
 * @param int|string|false $a Quantity being compared.
 * @param int|string|false $b Quantity against which $a is compared.
 * @return int
 */
function wp_ini_quantity_cmp( $a, $b ) {
	$a_scalar = wp_ini_parse_quantity( $a );
	$b_scalar = wp_ini_parse_quantity( $b );

	if ( $a_scalar === $b_scalar ) {
		return 0;
	}

	// No limit on $a means it's at least as large as any $b value.
	if ( $a_scalar <= 0 ) {
		return 1;
	}

	// No limit on $b means it's at least as large as any $a value.
	if ( $b_scalar <= 0 ) {
		return -1;
	}

	return $a_scalar > $b_scalar ? 1 : -1;
}

/**
 * Returns quantity represented by a php.ini directive's "byte size shorthand."
 *
 * php.ini directives may use a string representation of a number of bytes
 * or a "shorthand" byte size to reference larger values. Multiple numeric
 * php.ini directive use these shorthands even when they don't refer to bytes.
 *
 * Example:
 *
 *     ini_parse_quantity( "1m" ) == 1048576
 *     ini_parse_quantity( "2K" ) == 2048 // 2 * 1024
 *     ini_parse_quantity( "0.5g" ) == 0
 *     ini_parse_quantity( "14.6e-13g" ) == 15032385536 // 14 * 1024^3
 *     ini_parse_quantity( "-813k" ) == -832512; // -813 * 1024
 *     ini_parse_quantity( "boat" ) == 0;
 *
 *     // This gives an answer, but it's _wrong_ because
 *     // the underlying mechanism in PHP overflowed and
 *     // the real return value depends on whether PHP
 *     // was built with 64-bit support.
 *     ini_parse_quantity( "9223372036854775807g" ) == ??
 *
 * Notes:
 *  - Suffixes are specifically _the last character_ and case-insensitive.
 *  - Suffixes k/m/g intentionally report powers of 1024 to agree with PHP.
 *  - This function does not fail on invalid input; it returns `0` in such cses.
 *  - As noted in the PHP documentation, overflow behavior is unspecified and
 *    platform-dependant. Values that trigger overflow are likely wrong
 *  - In PHP 8.2+ this function may return an invalid count for shorthand values
 *    parsed with the new unsigned parser. Currently only affects "memory_limit"
 *    and only when the value overflows an unsigned integer on the platform.
 *
 * @since 7.0.0
 *
 * @link https://www.php.net/manual/en/function.ini-get.php
 * @link https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes

 * @param string $value Numeric string value possibly in "shorthand notation."
 * @return int          Parsed numeric value represented by given string.
 */
function ini_parse_quantity_fallback( $value ) {
	/**
	 * Number of bytes in input string; because we're only assessing 7-bit
	 * ASCII/Unicode characters we can safely count bytes vs. needing to
	 * worry about code units, code points, or grapheme clusters.
	 */
	$end = strlen( $value );

	/** @var int|float $scalar Numeric quantity represented by value string. */
	$scalar = 0;

	/** Sign of numeric quantity, either positive (1) or negative (-1). */
	$sign = 1;

	/**
	 * Numeric base of digits determined by string prefix (e.g. "0x" or "0").
	 * Must be 8 for octal, 10 for decimal, or 16 for hexadecimal.
	 */
	$base = 10;

	/** Byte index into input being processed. */
	$at = 0;

	// Trim leading whitespace from the value.
	$at += strspn( $value, " \t\r\v\f", $at );
	if ( $at >= $end ) {
		return $scalar;
	}

	// Handle optional sign indicator.
	switch ( $value[ $at ] ) {
		case '+':
			$at++;
			break;

		case '-':
			$sign = -1;
			$at++;
			break;
	}

	// Determine base for digit conversion, if not decimal.
	$base_a = $value[ $at ] ?? '';
	$base_b = $value[ $at + 1 ] ?? '';

	if ( '0' === $base_a && ( 'x' === $base_b || 'X' === $base_b ) ) {
		$base = 16;
		$at += 2;
	} else if ( '0' === $base_a && '0' <= $base_b && $base_b <= '9' ) {
		$base = 8;
		$at += 1;
	}

	// Trim leading zeros from the amount.
	$at += strspn( $value, '0', $at );

	/**
	 * Numeric values for scanned digits.
	 *
	 * These are used to determine the decimal value the digit
	 * represents and whether it's an allowed character in
	 * the given base. It's allowed if its value is less
	 * than the base: e.g. '7' is allowed in octal (base 8)
	 * but '8' and '9' aren't because they are greater than 8.
	 *
	 * @var array<string, int> $digits
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
	 * Build the scalar value by consuming the next sequence of contiguous digits.
	 * It looks like this could be replaced with native functions to parse digits,
	 * but the behavior mimics peculiarities internal to PHP when parsing overflowed
	 * digits, inasmuch as is possible from user-space PHP code.
	 */
	for ( ; $at < $end; $at++ ) {
		$c = $value[ $at ];

		/*
		 * Only digits recognized in this base system can be used.
		 * Once any unrecognized digits are found, abort and move
		 * on to the next step in parsing the size suffix.
		 */
		if ( ! isset( $digits[ $c ] ) || $digits[ $c ] >= $base ) {
			break;
		}

		/*
		 * This is the step that computes the integer as new digits arrive.
		 *
		 * Example:
		 *      4   = (0 * 10) + 4
		 *      45  = ((0 * 10 + 4) * 10) + 5
		 *      458 = ((0 * 10 + 4) * 10 + 5) * 10 + 8
		 */
		$scalar = $scalar * $base + $digits[ $c ];

		/*
		 * There’s nothing to do one having reached the maximum
		 * storable size, so bail. This comparison works because
		 * the int value is cast into a float once having crossed
		 * beyond the maximum integer.
		 */
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

	// @todo Any values above these, when multiplied, will overflow.
	$max_int_g = floor( PHP_INT_MAX / 1073741824 );
	$max_int_m = floor( PHP_INT_MAX / 1048576 );
	$max_int_k = floor( PHP_INT_MAX / 1024 );

	/*
	 * Do not use WP constants here (GB_IN_BYTES, MB_IN_BYTES, KB_IN_BYTES)
	 * since they are re-definable; PHP shorthand values are hard-coded
	 * in PHP itself and stay the same regardless of these constants.
	 *
	 * Note that it’s possible to overflow here, as happens in PHP itself.
	 * Overflow results will likely not match PHP’s value, but will likely
	 * break in most cases anyway and so leaving this loose is the best
	 * that can be done without PHP reporting the internal values.
	 *
	 * @todo Is is possible to detect overflow here?
	 */
	switch ( $value[ $end - 1 ] ) {
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
