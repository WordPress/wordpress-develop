<?php

/**
 * XML API: WP_XML_Decoder class
 *
 * Decodes spans of raw text found inside XML content,
 * whether found in an attribute or in a text node.
 *
 * Do not use this function on the contents of a CDATA section,
 * as those sections are not encoded with the XML rules unless
 * they are embedded XML content.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since WP_VERSION
 */
class WP_XML_Decoder {
	/**
	 * Decodes a span of XML text.
	 *
	 * Example:
	 *
	 *     '&' = WP_XML_Decoder::decode( '&amp;' );
	 *     '…' = WP_XML_Decoder::decode( '&#x2026;' );
	 *
	 * @todo Add examples of parse failures, and decide if it should fail or not.
	 *
	 * @since WP_VERSION
	 *
	 * @access private
	 *
	 * @param string $text    Text document containing span of text to decode.
	 * @return string Decoded UTF-8 string.
	 */
	public static function decode( $text ) {
		$decoded = '';
		$end     = strlen( $text );
		$at      = 0;
		$was_at  = 0;

		while ( $at < $end ) {
			$next_character_reference_at = strpos( $text, '&', $at );
			if ( false === $next_character_reference_at || $next_character_reference_at >= $end ) {
				break;
			}

			$start_of_potential_reference_at = $next_character_reference_at + 1;
			if ( $start_of_potential_reference_at >= $end ) {
				// @todo This is an error. The document ended too early; consume the rest as plaintext, which is wrong.
				break;
			}

			/**
			 * First character after the opening `&`.
			 */
			$start_of_potential_reference = $text[ $start_of_potential_reference_at ];

			/*
			 * If it's a named character reference, it will be one of the five mandated references.
			 *
			 *  - `&amp;`
			 *  - `&apos;`
			 *  - `&gt;`
			 *  - `&lt;`
			 *  - `&quot;`
			 *
			 * These all must be found within the five successive characters from the `&`.
			 *
			 * Example:
			 *
			 *              ╭ ampersand at 9 = $end - 6
			 *     &apos;XML&apos; ($end = 15)
			 *               ╰───┴─ this length must be at least 5 long,
			 *                      which is $end - 5.
			 */
			if (
				$next_character_reference_at < $end - 5 &&
				(
					'a' === $start_of_potential_reference ||
					'g' === $start_of_potential_reference ||
					'l' === $start_of_potential_reference ||
					'q' === $start_of_potential_reference
				)
			) {
				foreach ( array(
					'amp;'  => '&',
					'apos;' => "'",
					'lt;'   => '<',
					'gt;'   => '>',
					'quot;' => '"',
				) as $name => $substitution ) {
					if ( 0 === substr_compare( $text, $name, $next_character_reference_at, strlen( $name ) ) ) {
						$decoded .= substr( $text, $was_at, $next_character_reference_at - $was_at ) . $substitution;
						$at       = $start_of_potential_reference_at + strlen( $name );
						$was_at   = $at;
						continue 2;
					}
				}

				// @todo This is an invalid document. It should be communicated. Treat as plaintext and continue.
				++$at;
				continue;
			}

			/*
			 * The shortest numerical character reference is four characters.
			 *
			 * Example:
			 *
			 *     &#9;
			 */
			if ( '#' !== $start_of_potential_reference || $next_character_reference_at + 4 >= $end ) {
				// @todo This is an error. This ampersand _must_ be encoded. Treat as plaintext and move on.
				++$at;
				continue;
			}

			$is_hex = 'x' === $text[ $start_of_potential_reference_at + 1 ];
			if ( $is_hex ) {
				$zeros_at    = $start_of_potential_reference_at + 2;
				$base        = 16;
				$digit_chars = '0123456789abcdefABCDEF';
				$max_digits  = 6; // `&#x10FFFF;`
			} else {
				$zeros_at    = $start_of_potential_reference_at + 1;
				$base        = 10;
				$digit_chars = '0123456789';
				$max_digits  = 7; // `&#1114111;`
			}

			$zero_count  = strspn( $text, '0', $zeros_at );
			$digits_at   = $zeros_at + $zero_count;
			$digit_count = strspn( $text, $digit_chars, $digits_at, $max_digits );
			$semi_at     = $digits_at + $digit_count;

			if ( $digit_count === 0 || $semi_at >= $end || ';' !== $text[ $semi_at ] ) {
				// @todo This is an error. Treat as plaintext and move on.
				++$at;
				continue;
			}

			$code_point          = intval( substr( $text, $digits_at, $digit_count ), $base );
			$character_reference = WP_HTML_Decoder::code_point_to_utf8_bytes( $code_point );
			if ( '�' === $character_reference && 0xFFFD !== $code_point ) {
				/*
				 * Stop processing if we got an invalid character AND the reference does not
				 * specifically refer code point FFFD (�).
				 *
				 * > It is a fatal error when an XML processor encounters an entity with an
				 * > encoding that it is unable to process. It is a fatal error if an XML entity
				 * > is determined (via default, encoding declaration, or higher-level protocol)
				 * > to be in a certain encoding but contains byte sequences that are not legal
				 * > in that encoding. Specifically, it is a fatal error if an entity encoded in
				 * >  UTF-8 contains any ill-formed code unit sequences, as defined in section
				 * > 3.9 of Unicode [Unicode]. Unless an encoding is determined by a higher-level
				 * > protocol, it is also a fatal error if an XML entity contains no encoding
				 * > declaration and its content is not legal UTF-8 or UTF-16.
				 *
				 * See https://www.w3.org/TR/xml/#charencoding
				 */
				// @todo This is an error. Treat as plaintext and continue, which is wrong.
				++$at;
				continue;
			}

			$decoded .= substr( $text, $was_at, $at - $was_at );
			$decoded .= $character_reference;
			$at       = $semi_at + 1;
			$was_at   = $at;
		}

		if ( 0 === $was_at ) {
			return $text;
		}

		if ( $was_at < $end ) {
			$decoded .= substr( $text, $was_at, $end - $was_at );
		}

		return $decoded;
	}

	/**
	 * Finds and parses the next entity in a given text starting after the
	 * given byte offset, and being entirely found within the given max length.
	 *
	 * @since {WP_VERSION}
	 *
	 * // @todo Implement this function.
	 *
	 * @param string   $text                 Text in which to search for an XML entity.
	 * @param int      $starting_byte_offset Start looking after this byte offset.
	 * @param int      $ending_byte_offset   Stop looking if entity is not fully contained before this byte offset.
	 * @param int|null $entity_at            Optional. If provided, will be set to byte offset where entity was
	 *                                       found, if found. Otherwise, will not be set.
	 *
	 * @return string|null Parsed entity, if parsed, otherwise `null`.
	 */
	public static function next_entity( string $text, int $starting_byte_offset, int $ending_byte_offset, int &$entity_at = null ): ?string {
		$at  = $starting_byte_offset;
		$end = $ending_byte_offset;

		while ( $at < $end ) {
			$remaining = $end - $at;
			$amp_after = strcspn( $text, '&', $at, $remaining );

			// There are no more possible entities.
			if ( $amp_after === $remaining ) {
				return null;
			}

			/*
			 * @todo Move the decoding logic from `decode()` above into here,
			 *       then call this function in a loop from `decode()`.
			 */

			++$at;
		}

		return null;
	}
}
