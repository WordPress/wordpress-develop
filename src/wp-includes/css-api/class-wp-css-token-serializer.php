<?php
/**
 * CSS API: WP_CSS_Token_Serializer class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since 7.2.0
 */

/**
 * Serializes CSS tokens and values as CSS text.
 *
 * @since 7.2.0
 */
class WP_CSS_Token_Serializer {
	/**
	 * Serializes a plain PHP string as CSS identifier text.
	 *
	 * Characters not valid in CSS identifiers are hex-escaped. This uses
	 * the same safety escaping as {@see WP_CSS_Token_Serializer::serialize_string()} for HTML
	 * and CSS-sensitive characters, plus escaping of whitespace and other
	 * characters not permitted in idents.
	 *
	 * An empty string serializes to an empty string. Callers that require an
	 * ident token must reject an empty value.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 * @see https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded string value to encode as a CSS identifier.
	 * @return string Serialized CSS identifier text.
	 */
	public static function serialize_ident( string $value ): string {
		$value  = wp_scrub_utf8( $value );
		$result = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			// NULL → U+FFFD REPLACEMENT CHARACTER.
			if ( 0x00 === $byte ) {
				$result .= "\u{FFFD}";
				continue;
			}

			// Non-ASCII bytes (≥ 0x80): valid in idents, pass through.
			if ( $byte >= 0x80 ) {
				$result .= $value[ $i ];
				continue;
			}

			// ASCII letters and underscore: always valid in idents.
			if (
				( $byte >= 0x41 && $byte <= 0x5A ) || // A-Z
				( $byte >= 0x61 && $byte <= 0x7A ) || // a-z
				0x5F === $byte                         // _
			) {
				$result .= $value[ $i ];
				continue;
			}

			// Hyphen: valid in idents, but check for hyphen-digit at start.
			if ( 0x2D === $byte ) {
				// A hyphen standing alone must be escaped to form an ident token.
				if ( 0 === $i && 1 === $length ) {
					$result .= '\\2D ';
					continue;
				}

				// Hyphen at position 0 followed by a digit at position 1: escape the digit.
				if ( 0 === $i && $i + 1 < $length && ord( $value[ $i + 1 ] ) >= 0x30 && ord( $value[ $i + 1 ] ) <= 0x39 ) {
					$result .= '-';
					++$i;
					$result .= sprintf( '\\%X ', ord( $value[ $i ] ) );
					continue;
				}
				$result .= '-';
				continue;
			}

			// Digits: valid except at position 0.
			if ( $byte >= 0x30 && $byte <= 0x39 ) {
				if ( 0 === $i ) {
					$result .= sprintf( '\\%X ', $byte );
				} else {
					$result .= $value[ $i ];
				}
				continue;
			}

			// Everything else: hex-escape.
			$result .= sprintf( '\\%X ', $byte );
		}

		return $result;
	}

	/**
	 * Creates a quoted CSS string from a plain PHP string value.
	 *
	 * Example:
	 *     $value = 'CSS & a "<style>" tag\'s strings';
	 *     $css_string = WP_CSS_Token_Serializer::serialize_string( $value );
	 *     echo "<style>*::before { content: {$css_string}; }</style>";
	 *
	 * CSS strings are quoted, and many characters that are problematic in HTML
	 * or may be complicated for rudimentary CSS or HTML processors to handle
	 * are encoded using Unicode escape sequences.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded string value to encode as a quoted CSS string.
	 * @return string Serialized quoted CSS string text.
	 */
	public static function serialize_string( string $value ): string {
		$value   = wp_scrub_utf8( $value );
		$escaped = strtr(
			$value,
			array(
				// Escape existing backslashes to prevent unintentional escapes in result.
				'\\'   => '\\5C ',

				// Pre-processing replaces NULLs and some newlines. Replace and escape as necessary.
				"\0"   => "\u{FFFD}",

				// Normalize and replace newlines. https://www.w3.org/TR/css-syntax-3/#input-preprocessing
				"\r\n" => '\\A ',
				"\r"   => '\\A ',
				"\f"   => '\\A ',

				// Newlines must be escaped in CSS strings.
				"\n"   => '\\A ',

				// Arbitrary characters for Unicode escaping:

				// HTML syntax may be problematic.
				'<'    => '\\3C ',
				'>'    => '\\3E ',
				'&'    => '\\26 ',

				// CSS syntax may be problematic.
				','    => '\\2C ',
				';'    => '\\3B ',
				'{'    => '\\7B ',
				'}'    => '\\7D ',
				'"'    => '\\22 ',
				"'"    => '\\27 ',
			)
		);
		return "\"{$escaped}\"";
	}
}
