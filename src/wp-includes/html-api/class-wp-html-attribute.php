<?php

class WP_HTML_Attribute {
	/**
	 * Parses and returns an unordered set of space-separated tokens.
	 *
	 * Tokens in the returned array appear in the same order as they are uniquely
	 * found in the given attribute value string. When case-insensitive, output
	 * tokens will all be ASCII lowercase.
	 *
	 * Example:
	 *
	 *     array( 'a', 'b', 'c' ) === WP_HTML_Attribute::from_unordered_set_of_space_separated_tokens( "a b   a\t\nc" );
	 *
	 * > A set of space-separated tokens is a string containing zero or more
	 * > words (known as tokens) separated by one or more ASCII whitespace,
	 * > where words consist of any string of one or more characters, none
	 * > of which are ASCII whitespace.
	 *
	 * > An unordered set of unique space-separated tokens is a set of
	 * > space-separated tokens where none of the tokens are duplicated.
	 *
	 * > How tokens in a set of space-separated tokens are to be compared
	 * > (e.g. case-sensitively or not) is defined on a per-set basis.
	 *
	 * @see https://html.spec.whatwg.org/#unordered-set-of-unique-space-separated-tokens
	 *
	 * @since {WP_VERSION}
	 *
	 * @param string $attribute_value HTML-decoded attribute value to parse.
	 * @param string $case_sensitivity Optional. Constrain uniqueness with 'case-sensitive'
	 *                                 or 'case-insensitive'. Default 'case-sensitive'.
	 * @return string[] Set of unique tokens parsed from attribute value.
	 */
	public static function from_unordered_set_of_space_separated_tokens( $attribute_value, $case_sensitivity = 'case-sensitive' ) {
		if ( empty( $attribute_value ) ) {
			return array();
		}

		if ( 'case-insensitive' === $case_sensitivity ) {
			$attribute_value = strtolower( $attribute_value );
		}

		$tokens  = array();
		$uniques = ' ';
		$at      = 0;
		$end     = strlen( $attribute_value );
		while ( $at < $end ) {
			$at += strspn( $attribute_value, " \t\f\r\n", $at );

			$word_length = strcspn( $attribute_value, " \t\f\r\n", $at );
			$word        = substr( $attribute_value, $at, $word_length );

			if ( 0 < $word_length && ! str_contains( $uniques, " {$word} " ) ) {
				$uniques .= "{$word} ";
				$tokens[] = $word;
			}

			$at += $word_length;
		}

		return $tokens;
	}
}
