<?php
/**
 * HTML API: WP_CSS_Selector_Parser_Matcher class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * Base class for all CSS Selector praser/matcher classes.
 *
 * @since 6.8.0
 *
 * @access private
 */
abstract class WP_CSS_Selector_Parser_Matcher {
	const UTF8_MAX_CODEPOINT_VALUE = 0x10FFFF;
	const WHITESPACE_CHARACTERS    = " \t\r\n\f";

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	abstract public function matches( WP_HTML_Tag_Processor $processor ): bool;

	/**
	 * Parses a selector string to create a selector instance.
	 *
	 * @param string $input The selector string.
	 * @param int    $offset The offset into the string. The offset is passed by reference and
	 *                       will be updated if the parse is successful.
	 * @return static|null The selector instance, or null if the parse was unsuccessful.
	 */
	abstract public static function parse( string $input, int &$offset );

	/*
	 * ------------------------
	 * Selector partial parsing
	 * ------------------------
	 *
	 * These functions consume parts of a selector string input when successful
	 * and return meaningful values to be used by selectors.
	 */

	/**
	 * Consumes whitespace from the input string.
	 *
	 * @param string $input The selector string.
	 * @param int    $offset The offset into the string. The offset is passed by reference and will
	 *                       be update to the byte after the whitespace sequence.
	 * @return bool True if whitespace was consumed.
	 */
	final protected static function parse_whitespace( string $input, int &$offset ): bool {
		$length   = strspn( $input, self::WHITESPACE_CHARACTERS, $offset );
		$advanced = $length > 0;
		$offset  += $length;
		return $advanced;
	}

	/**
	 * Tokenization of hash tokens
	 *
	 * > U+0023 NUMBER SIGN (#)
	 * >   If the next input code point is an ident code point or the next two input code points are a valid escape, then:
	 * >     1. Create a <hash-token>.
	 * >     2. If the next 3 input code points would start an ident sequence, set the
	 * >        <hash-token>’s type flag to "id".
	 * >     3. Consume an ident sequence, and set the <hash-token>’s value to the
	 * >        returned string.
	 * >     4. Return the <hash-token>.
	 * >   Otherwise, return a <delim-token> with its value set to the current input code point.
	 *
	 * This implementation is not interested in the <delim-token>, a '#' delim token is not relevant for selectors.
	 */
	final protected static function parse_hash_token( string $input, int &$offset ): ?string {
		if ( $offset + 1 >= strlen( $input ) || '#' !== $input[ $offset ] ) {
			return null;
		}

		$updated_offset = $offset + 1;
		$result         = self::parse_ident( $input, $updated_offset );

		if ( null === $result ) {
			return null;
		}

		$offset = $updated_offset;
		return $result;
	}

	/**
	 * Parse a string token
	 *
	 * > 4.3.5. Consume a string token
	 * > This section describes how to consume a string token from a stream of code points. It returns either a <string-token> or <bad-string-token>.
	 * >
	 * > This algorithm may be called with an ending code point, which denotes the code point that ends the string. If an ending code point is not specified, the current input code point is used.
	 * >
	 * > Initially create a <string-token> with its value set to the empty string.
	 * >
	 * > Repeatedly consume the next input code point from the stream:
	 * >
	 * > ending code point
	 * >   Return the <string-token>.
	 * > EOF
	 * >   This is a parse error. Return the <string-token>.
	 * > newline
	 * >   This is a parse error. Reconsume the current input code point, create a <bad-string-token>, and return it.
	 * > U+005C REVERSE SOLIDUS (\)
	 * >   If the next input code point is EOF, do nothing.
	 * >   Otherwise, if the next input code point is a newline, consume it.
	 * >   Otherwise, (the stream starts with a valid escape) consume an escaped code point and append the returned code point to the <string-token>’s value.
	 * >
	 * > anything else
	 * >   Append the current input code point to the <string-token>’s value.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#consume-string-token
	 *
	 * This implementation will never return a <bad-string-token> because
	 * the <bad-string-token> is not a part of the selector grammar. That
	 * case is treated as failure to parse and null is returned.
	 *
	 * @return string|null
	 */
	final protected static function parse_string( string $input, int &$offset ): ?string {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		$ending_code_point = $input[ $offset ];
		if ( '"' !== $ending_code_point && "'" !== $ending_code_point ) {
			return null;
		}

		$string_token = '';

		$updated_offset     = $offset + 1;
		$anything_else_mask = "\\\n{$ending_code_point}";
		while ( $updated_offset < strlen( $input ) ) {
			$anything_else_length = strcspn( $input, $anything_else_mask, $updated_offset );
			if ( $anything_else_length > 0 ) {
				$string_token   .= substr( $input, $updated_offset, $anything_else_length );
				$updated_offset += $anything_else_length;

				if ( $updated_offset >= strlen( $input ) ) {
					break;
				}
			}

			switch ( $input[ $updated_offset ] ) {
				case '\\':
					++$updated_offset;
					if ( $updated_offset >= strlen( $input ) ) {
						break;
					}
					if ( "\n" === $input[ $updated_offset ] ) {
						++$updated_offset;
						break;
					} else {
						$string_token .= self::consume_escaped_codepoint( $input, $updated_offset );
					}
					break;

				/*
				 * This case would return a <bad-string-token>.
				 * The <bad-string-token> is not a part of the selector grammar
				 * so we do not return it and instead treat this as a
				 * failure to parse a string token.
				 */
				case "\n":
					return null;

				case $ending_code_point:
					++$updated_offset;
					break 2;
			}
		}

		$offset = $updated_offset;
		return $string_token;
	}

	/**
	 * Consume an escaped code point.
	 *
	 * > 4.3.7. Consume an escaped code point
	 * > This section describes how to consume an escaped code point. It assumes that the U+005C
	 * > REVERSE SOLIDUS (\) has already been consumed and that the next input code point has
	 * > already been verified to be part of a valid escape. It will return a code point.
	 * >
	 * > Consume the next input code point.
	 * >
	 * > hex digit
	 * >   Consume as many hex digits as possible, but no more than 5. Note that this means 1-6
	 * >   hex digits have been consumed in total. If the next input code point is whitespace,
	 * >   consume it as well. Interpret the hex digits as a hexadecimal number. If this number is
	 * >   zero, or is for a surrogate, or is greater than the maximum allowed code point, return
	 * >   U+FFFD REPLACEMENT CHARACTER (�). Otherwise, return the code point with that value.
	 * > EOF
	 * >   This is a parse error. Return U+FFFD REPLACEMENT CHARACTER (�).
	 * > anything else
	 * >   Return the current input code point.
	 *
	 * @param string $input
	 * @param int $offset
	 * @return string
	 */
	final protected static function consume_escaped_codepoint( $input, &$offset ): string {
		$hex_length = strspn( $input, '0123456789abcdefABCDEF', $offset, 6 );
		if ( $hex_length > 0 ) {
			/**
			 * The 6-character hex string has a maximum value of 0xFFFFFF.
			 * It is likely to fit in an int value and not be a float.
			 *
			 * @var int
			 */
			$codepoint_value = hexdec( substr( $input, $offset, $hex_length ) );

			/*
			 * > A surrogate is a leading surrogate or a trailing surrogate.
			 * > A leading surrogate is a code point that is in the range U+D800 to U+DBFF, inclusive.
			 * > A trailing surrogate is a code point that is in the range U+DC00 to U+DFFF, inclusive.
			 *
			 * The surrogate ranges are adjacent, so the complete range is 0xD800 to 0xDFFF, inclusive.
			 */
			$codepoint_char = (
				0 === $codepoint_value ||
				$codepoint_value > self::UTF8_MAX_CODEPOINT_VALUE ||
				( 0xD800 <= $codepoint_value && $codepoint_value <= 0xDFFF )
			)
				? "\u{FFFD}"
				: mb_chr( $codepoint_value, 'UTF-8' );

			$offset += $hex_length;

			// If the next input code point is whitespace, consume it as well.
			if (
				strlen( $input ) > $offset &&
				(
					"\n" === $input[ $offset ] ||
					"\t" === $input[ $offset ] ||
					' ' === $input[ $offset ]
				)
			) {
				++$offset;
			}
			return $codepoint_char;
		}

		$codepoint_char = mb_substr( $input, $offset, 1, 'UTF-8' );
		$offset        += strlen( $codepoint_char );
		return $codepoint_char;
	}

	/**
	 * Parse an ident token
	 *
	 * CAUTION: This method is _not_ for parsing and ID selector!
	 *
	 * > 4.3.11. Consume an ident sequence
	 * > This section describes how to consume an ident sequence from a stream of code points. It returns a string containing the largest name that can be formed from adjacent code points in the stream, starting from the first.
	 * >
	 * > Note: This algorithm does not do the verification of the first few code points that are necessary to ensure the returned code points would constitute an <ident-token>. If that is the intended use, ensure that the stream starts with an ident sequence before calling this algorithm.
	 * >
	 * > Let result initially be an empty string.
	 * >
	 * > Repeatedly consume the next input code point from the stream:
	 * >
	 * > ident code point
	 * >   Append the code point to result.
	 * > the stream starts with a valid escape
	 * >   Consume an escaped code point. Append the returned code point to result.
	 * > anything else
	 * >   Reconsume the current input code point. Return result.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#consume-name
	 *
	 * @return string|null
	 */
	final protected static function parse_ident( string $input, int &$offset ): ?string {
		if ( ! self::check_if_three_code_points_would_start_an_ident_sequence( $input, $offset ) ) {
			return null;
		}

		$ident = '';

		while ( $offset < strlen( $input ) ) {
			if ( self::next_two_are_valid_escape( $input, $offset ) ) {
				// Move past the `\` character.
				++$offset;
				$ident .= self::consume_escaped_codepoint( $input, $offset );
				continue;
			} elseif ( self::is_ident_codepoint( $input, $offset ) ) {
				$ident .= $input[ $offset ];
				++$offset;
				continue;
			}
			break;
		}

		return $ident;
	}

	/*
	 * --------------------------
	 * Selector parsing utilities
	 * --------------------------
	 *
	 * The following functions are used for parsing but do not consume any input.
	 */

	/**
	 * Checks for two valid escape codepoints.
	 *
	 * > 4.3.8. Check if two code points are a valid escape
	 * > This section describes how to check if two code points are a valid escape. The algorithm described here can be called explicitly with two code points, or can be called with the input stream itself. In the latter case, the two code points in question are the current input code point and the next input code point, in that order.
	 * >
	 * > Note: This algorithm will not consume any additional code point.
	 * >
	 * > If the first code point is not U+005C REVERSE SOLIDUS (\), return false.
	 * >
	 * > Otherwise, if the second code point is a newline, return false.
	 * >
	 * > Otherwise, return true.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#starts-with-a-valid-escape
	 *
	 * @todo this does not check whether the second codepoint is valid.
	 *
	 * @param string $input The input string.
	 * @param int $offset The byte offset in the string.
	 * @return bool True if the next two codepoints are a valid escape, otherwise false.
	 */
	final protected static function next_two_are_valid_escape( string $input, int $offset ): bool {
		if ( $offset + 1 >= strlen( $input ) ) {
			return false;
		}
		return '\\' === $input[ $offset ] && "\n" !== $input[ $offset + 1 ];
	}

	/**
	 * Checks if the next code point is an "ident start code point."
	 *
	 * Caution! This method does not do any bounds checking, it should not be passed
	 * a string with an offset that is out of bounds.
	 *
	 * > ident-start code point
	 * >   A letter, a non-ASCII code point, or U+005F LOW LINE (_).
	 * > uppercase letter
	 * >   A code point between U+0041 LATIN CAPITAL LETTER A (A) and U+005A LATIN CAPITAL LETTER Z (Z) inclusive.
	 * > lowercase letter
	 * >   A code point between U+0061 LATIN SMALL LETTER A (a) and U+007A LATIN SMALL LETTER Z (z) inclusive.
	 * > letter
	 * >   An uppercase letter or a lowercase letter.
	 * > non-ASCII code point
	 * >   A code point with a value equal to or greater than U+0080 <control>.
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#ident-start-code-point
	 *
	 * @param string $input The input string.
	 * @param int $offset The byte offset in the string.
	 * @return bool True if the next codepoint is an ident start code point, otherwise false.
	 */
	final protected static function is_ident_start_codepoint( string $input, int $offset ): bool {
		return (
			'_' === $input[ $offset ] ||
			( 'a' <= $input[ $offset ] && $input[ $offset ] <= 'z' ) ||
			( 'A' <= $input[ $offset ] && $input[ $offset ] <= 'Z' ) ||
			ord( $input[ $offset ] ) > 0x7F
		);
	}

	/**
	 * Checks if the next code point is an "ident code point."
	 *
	 * Caution! This method does not do any bounds checking, it should not be passed
	 * a string with an offset that is out of bounds.
	 *
	 * > ident code point
	 * >   An ident-start code point, a digit, or U+002D HYPHEN-MINUS (-).
	 * > digit
	 * >   A code point between U+0030 DIGIT ZERO (0) and U+0039 DIGIT NINE (9) inclusive.
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#ident-code-point
	 *
	 * @param string $input The input string.
	 * @param int $offset The byte offset in the string.
	 * @return bool True if the next codepoint is an ident code point, otherwise false.
	 */
	final protected static function is_ident_codepoint( string $input, int $offset ): bool {
		return '-' === $input[ $offset ] ||
			( '0' <= $input[ $offset ] && $input[ $offset ] <= '9' ) ||
			self::is_ident_start_codepoint( $input, $offset );
	}

	/**
	 * Checks if three code points would start an ident sequence.
	 *
	 * > 4.3.9. Check if three code points would start an ident sequence
	 * > This section describes how to check if three code points would start an ident sequence. The algorithm described here can be called explicitly with three code points, or can be called with the input stream itself. In the latter case, the three code points in question are the current input code point and the next two input code points, in that order.
	 * >
	 * > Note: This algorithm will not consume any additional code points.
	 * >
	 * > Look at the first code point:
	 * >
	 * > U+002D HYPHEN-MINUS
	 * >   If the second code point is an ident-start code point or a U+002D HYPHEN-MINUS, or the second and third code points are a valid escape, return true. Otherwise, return false.
	 * > ident-start code point
	 * >   Return true.
	 * > U+005C REVERSE SOLIDUS (\)
	 * >   If the first and second code points are a valid escape, return true. Otherwise, return false.
	 * > anything else
	 * >   Return false.
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 *
	 * @param string $input The input string.
	 * @param int $offset The byte offset in the string.
	 * @return bool True if the next three codepoints would start an ident sequence, otherwise false.
	 */
	final protected static function check_if_three_code_points_would_start_an_ident_sequence( string $input, int $offset ): bool {
		if ( $offset >= strlen( $input ) ) {
			return false;
		}

		// > U+005C REVERSE SOLIDUS (\)
		if ( '\\' === $input[ $offset ] ) {
			return self::next_two_are_valid_escape( $input, $offset );
		}

		// > U+002D HYPHEN-MINUS
		if ( '-' === $input[ $offset ] ) {
			$after_initial_hyphen_minus_offset = $offset + 1;
			if ( $after_initial_hyphen_minus_offset >= strlen( $input ) ) {
				return false;
			}

			// > If the second code point is… U+002D HYPHEN-MINUS… return true
			if ( '-' === $input[ $after_initial_hyphen_minus_offset ] ) {
				return true;
			}

			// > If the second and third code points are a valid escape… return true.
			if ( self::next_two_are_valid_escape( $input, $after_initial_hyphen_minus_offset ) ) {
				return true;
			}

			// > If the second code point is an ident-start code point… return true.
			if ( self::is_ident_start_codepoint( $input, $after_initial_hyphen_minus_offset ) ) {
				return true;
			}

			// > Otherwise, return false.
			return false;
		}

		// > ident-start code point
		// >   Return true.
		// > anything else
		// >   Return false.
		return self::is_ident_start_codepoint( $input, $offset );
	}

	/**
	 * Normalizes selector input for processing.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#input-preprocessing
	 *
	 * @param string $input The selector string.
	 * @return string The normalized selector string.
	 */
	final protected static function normalize_selector_input( string $input ): string {
		/*
		 * > A selector string is a list of one or more complex selectors ([SELECTORS4], section 3.1) that may be surrounded by whitespace…
		 *
		 * This list includes \f.
		 * A later step would normalize it to a known whitespace character, but it can be trimmed here as well.
		 */
		$input = trim( $input, " \t\r\n\r\f" );

		/*
		 * > The input stream consists of the filtered code points pushed into it as the input byte stream is decoded.
		 * >
		 * > To filter code points from a stream of (unfiltered) code points input:
		 * > Replace any U+000D CARRIAGE RETURN (CR) code points, U+000C FORM FEED (FF) code points, or pairs of U+000D CARRIAGE RETURN (CR) followed by U+000A LINE FEED (LF) in input by a single U+000A LINE FEED (LF) code point.
		 * > Replace any U+0000 NULL or surrogate code points in input with U+FFFD REPLACEMENT CHARACTER (�).
		 *
		 * https://www.w3.org/TR/css-syntax-3/#input-preprocessing
		 */
		$input = str_replace( array( "\r\n" ), "\n", $input );
		$input = str_replace( array( "\r", "\f" ), "\n", $input );
		$input = str_replace( "\0", "\u{FFFD}", $input );

		return $input;
	}
}
