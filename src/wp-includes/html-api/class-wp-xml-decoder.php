<?php

/**
 * XML API: WP_XML_Decoder class
 *
 * Decodes spans of raw text found inside XML content.
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
	 *     '©' = WP_XML_Decoder::decode( 'data', '&copy;' );
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

			// Use the `attribute` context to disable ambiguous follower detection.
			$character_reference = WP_HTML_Decoder::read_character_reference( 'attribute', $text, $next_character_reference_at, $token_length );
			if ( ! isset( $character_reference ) ) {
				/*
				 * > The following are forbidden, and constitute fatal errors:
				 * > * the appearance of a reference to an unparsed entity, except in the EntityValue in an entity declaration.
				 *
				 * See https://www.w3.org/TR/xml/#forbidden
				 */
				return null;
			}

			if ( '�' === $character_reference ) {
				/*
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
				return null;
			}

			if ( ';' !== $text[ $at + $token_length - 1 ] ) {
				/*
				 * In XML, all character references must end with a semicolon.
				 */
				return null;
			}

			$at       = $next_character_reference_at;
			$decoded .= substr( $text, $was_at, $at - $was_at );
			$decoded .= $character_reference;
			$at      += $token_length;
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
}
