<?php
/**
 * WP_CSS_Font_Family class.
 *
 * @package    WordPress
 * @subpackage Fonts
 * @since      7.2.0
 */

/**
 * Parses and serializes CSS `font-family` values.
 *
 * The class reads the grammar of the CSS `font-family` property and of the
 * `@font-face` `font-family` descriptor. It returns the decoded font names, so
 * that other code can compare and store a name without CSS syntax. It also
 * writes a decoded name back as a valid CSS string.
 *
 * A parsed value is a list of entries. Each entry is an array with these keys:
 *
 *     @type string $type  One of 'name', 'generic', or 'keyword'.
 *     @type string $value For 'name', the decoded font name. For 'generic' and
 *                         'keyword', the canonical CSS text.
 *
 * The parser does not accept a partial value. It returns null when the input
 * contains invalid syntax or extra tokens.
 *
 * This class is for internal core usage and is not supposed to be used by
 * extenders (plugins and/or themes).
 *
 * @since 7.2.0
 * @access private
 *
 * @link https://www.w3.org/TR/css-fonts-4/#font-family-prop
 * @link https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
 * @link https://www.w3.org/TR/cssom-1/#serialize-a-string
 */
final class WP_CSS_Font_Family {

	/**
	 * Generic font family keywords.
	 *
	 * A generic keyword is not a font name. The browser maps it to a font that
	 * the user or the system selects.
	 *
	 * @since 7.2.0
	 *
	 * @var string[]
	 */
	const GENERIC_FAMILIES = array(
		'serif',
		'sans-serif',
		'cursive',
		'fantasy',
		'monospace',
		'system-ui',
		'math',
		'ui-serif',
		'ui-sans-serif',
		'ui-monospace',
		'ui-rounded',
		'emoji',
		'fangsong',
	);

	/**
	 * Keywords that an unquoted font name cannot use.
	 *
	 * CSS reserves these keywords. A value that uses one of them alone is a
	 * keyword. A quoted value with the same text is a font name.
	 *
	 * @since 7.2.0
	 *
	 * @var string[]
	 */
	const RESERVED_KEYWORDS = array(
		'inherit',
		'initial',
		'unset',
		'revert',
		'revert-layer',
		'default',
	);

	/**
	 * Characters that the plain name compatibility path rejects.
	 *
	 * These characters start CSS syntax that a font name must not contain. The
	 * compatibility path applies only to input that font code accepted before
	 * WordPress 7.2.0, such as the plain name `O'Reilly Sans`.
	 *
	 * @since 7.2.0
	 *
	 * @var string
	 */
	const PLAIN_NAME_REJECTED_CHARACTERS = ';{}()[]@\\/*<>:!,';

	/**
	 * Parses a CSS `font-family` property value.
	 *
	 * The parser requires valid CSS. It consumes the complete value and
	 * rejects a value with extra tokens.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value CSS `font-family` value.
	 * @return array[]|null List of parsed entries, or null if the value is invalid.
	 */
	public static function parse_list( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '//u', $value ) ) {
			// Reject invalid UTF-8 rather than replace characters in a name.
			return null;
		}

		// Apply the CSS input preprocessing rules. See https://www.w3.org/TR/css-syntax-3/#input-preprocessing.
		$value = str_replace( array( "\r\n", "\r", "\f" ), "\n", $value );
		$value = str_replace( "\0", "\u{FFFD}", $value );

		$length  = strlen( $value );
		$offset  = 0;
		$entries = array();

		while ( true ) {
			if ( ! self::skip_whitespace_and_comments( $value, $offset, $length ) ) {
				return null;
			}

			$entry = self::consume_family_name( $value, $offset, $length );
			if ( null === $entry ) {
				return null;
			}

			$entries[] = $entry;

			if ( ! self::skip_whitespace_and_comments( $value, $offset, $length ) ) {
				return null;
			}

			if ( $offset >= $length ) {
				break;
			}

			if ( ',' !== $value[ $offset ] ) {
				return null;
			}

			++$offset;
		}

		// A reserved keyword is valid only as the single value of the property.
		if ( count( $entries ) > 1 && in_array( 'keyword', array_column( $entries, 'type' ), true ) ) {
			return null;
		}

		return $entries;
	}

	/**
	 * Parses a CSS `font-family` value and accepts an established plain name.
	 *
	 * Use this method at font input boundaries, such as the REST API, theme
	 * settings, and direct calls to {@see wp_print_font_faces()}. It first
	 * reads the value as CSS. If that fails, it reads each comma separated
	 * part as a plain name, which earlier WordPress versions accepted.
	 *
	 * The plain name path rejects a part that contains CSS syntax characters,
	 * such as a semicolon or a parenthesis. Use
	 * {@see WP_CSS_Font_Family::parse_list()} where the input must be valid CSS.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value CSS `font-family` value, or a plain font name.
	 * @return array[]|null List of parsed entries, or null if the value is invalid.
	 */
	public static function parse_list_with_plain_names( $value ) {
		$entries = self::parse_list( $value );
		if ( null !== $entries ) {
			return $entries;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '//u', $value ) ) {
			return null;
		}

		$entries = array();

		foreach ( explode( ',', $value ) as $part ) {
			// A part without a comma parses to one entry, such as a generic family.
			$parsed = self::parse_list( $part );

			if ( null !== $parsed && 'keyword' !== $parsed[0]['type'] ) {
				$entries[] = $parsed[0];
				continue;
			}

			$name = self::parse_plain_name( $part );
			if ( null === $name ) {
				return null;
			}

			$entries[] = array(
				'type'  => 'name',
				'value' => $name,
			);
		}

		return $entries;
	}

	/**
	 * Parses the font name for an `@font-face` `font-family` descriptor.
	 *
	 * The descriptor names one font family. It cannot hold a fallback list.
	 * For compatibility with existing data, this method selects the first
	 * entry of a list and returns its name.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value CSS `font-family` value, or a plain font name.
	 * @return string|null The decoded font name, or null if the value is invalid.
	 */
	public static function parse_descriptor_name( $value ) {
		$entries = self::parse_list_with_plain_names( $value );

		if ( null === $entries || 'keyword' === $entries[0]['type'] ) {
			return null;
		}

		return $entries[0]['value'];
	}

	/**
	 * Serializes a decoded font name as a CSS string.
	 *
	 * The method always adds quotes. It escapes the quote character, the
	 * backslash, and the control characters. It also escapes the characters
	 * that HTML reads, so that the name survives HTML output and the KSES
	 * post filters without a change.
	 *
	 * A hexadecimal escape uses the shortest digit sequence and always ends
	 * with one space. A leading zero is not possible, and the backslash also
	 * uses a hexadecimal escape, because {@see wp_kses_no_null()} removes a
	 * backslash that zeros follow.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name Decoded font name.
	 * @return string The name as a quoted CSS string.
	 */
	public static function serialize_name( $name ) {
		return '"' . preg_replace_callback(
			'/[\x00-\x1f\x7f"\\\\<>&]/',
			static function ( $matches ) {
				if ( "\0" === $matches[0] ) {
					return "\u{FFFD}";
				}
				if ( '"' === $matches[0] ) {
					return '\\"';
				}

				return sprintf( '\\%x ', ord( $matches[0] ) );
			},
			(string) $name
		) . '"';
	}

	/**
	 * Serializes a list of parsed entries as a CSS `font-family` value.
	 *
	 * @since 7.2.0
	 *
	 * @param array[] $entries List of parsed entries.
	 * @return string The CSS `font-family` value.
	 */
	public static function serialize_list( $entries ) {
		$parts = array();

		foreach ( $entries as $entry ) {
			if ( 'name' === $entry['type'] ) {
				$parts[] = self::serialize_name( $entry['value'] );
			} else {
				$parts[] = $entry['value'];
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Skips whitespace and comments.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset. Passed by reference.
	 * @param int    $length Input length.
	 * @return bool True on success, false if a comment does not terminate.
	 */
	private static function skip_whitespace_and_comments( $value, &$offset, $length ) {
		while ( $offset < $length ) {
			$character = $value[ $offset ];

			if ( ' ' === $character || "\t" === $character || "\n" === $character ) {
				++$offset;
				continue;
			}

			if ( '/' === $character && $offset + 1 < $length && '*' === $value[ $offset + 1 ] ) {
				$end = strpos( $value, '*/', $offset + 2 );
				if ( false === $end ) {
					return false;
				}
				$offset = $end + 2;
				continue;
			}

			break;
		}

		return true;
	}

	/**
	 * Consumes one family name.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset. Passed by reference.
	 * @param int    $length Input length.
	 * @return array|null The parsed entry, or null if the input is invalid.
	 */
	private static function consume_family_name( $value, &$offset, $length ) {
		if ( $offset >= $length ) {
			return null;
		}

		$character = $value[ $offset ];

		if ( '"' === $character || "'" === $character ) {
			$name = self::consume_string( $value, $offset, $length );
			if ( null === $name ) {
				return null;
			}

			return array(
				'type'  => 'name',
				'value' => $name,
			);
		}

		$identifiers = array();

		while ( self::starts_identifier( $value, $offset, $length ) ) {
			$identifiers[] = self::consume_identifier( $value, $offset, $length );

			/*
			 * The `generic()` function names a generic family. It is only valid
			 * as the complete family name.
			 */
			if ( 1 === count( $identifiers ) && 'generic' === strtolower( $identifiers[0] ) && $offset < $length && '(' === $value[ $offset ] ) {
				return self::consume_generic_function( $value, $offset, $length );
			}

			// Whitespace and comments can separate the identifiers of one name.
			if ( ! self::skip_whitespace_and_comments( $value, $offset, $length ) ) {
				return null;
			}
		}

		if ( empty( $identifiers ) ) {
			return null;
		}

		$name = implode( ' ', $identifiers );
		$type = 'name';

		if ( 1 === count( $identifiers ) ) {
			$lowercase = strtolower( $name );

			if ( in_array( $lowercase, self::GENERIC_FAMILIES, true ) ) {
				$type = 'generic';
			} elseif ( in_array( $lowercase, self::RESERVED_KEYWORDS, true ) ) {
				$type = 'keyword';
			}
		}

		return array(
			'type'  => $type,
			'value' => 'name' === $type ? $name : $lowercase,
		);
	}

	/**
	 * Consumes a `generic()` function.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset, at the opening parenthesis. Passed by reference.
	 * @param int    $length Input length.
	 * @return array|null The parsed entry, or null if the input is invalid.
	 */
	private static function consume_generic_function( $value, &$offset, $length ) {
		++$offset;

		if ( ! self::skip_whitespace_and_comments( $value, $offset, $length ) ) {
			return null;
		}

		if ( ! self::starts_identifier( $value, $offset, $length ) ) {
			return null;
		}

		$identifier = strtolower( self::consume_identifier( $value, $offset, $length ) );

		// Only defined generic arguments can enter CSS without quotes or escapes.
		if ( ! in_array( $identifier, array( 'kai', 'fangsong', 'khmer-mul', 'nastaliq' ), true ) ) {
			return null;
		}

		if ( ! self::skip_whitespace_and_comments( $value, $offset, $length ) ) {
			return null;
		}

		if ( $offset >= $length || ')' !== $value[ $offset ] ) {
			return null;
		}

		++$offset;

		return array(
			'type'  => 'generic',
			'value' => 'generic(' . $identifier . ')',
		);
	}

	/**
	 * Consumes a quoted string and returns its decoded text.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset, at the opening quote. Passed by reference.
	 * @param int    $length Input length.
	 * @return string|null The decoded text, or null if the string does not terminate.
	 */
	private static function consume_string( $value, &$offset, $length ) {
		$quote = $value[ $offset ];
		++$offset;
		$result = '';

		while ( $offset < $length ) {
			$character = $value[ $offset ];

			if ( $character === $quote ) {
				++$offset;
				return $result;
			}

			if ( "\n" === $character ) {
				// A newline ends the string and makes it invalid.
				return null;
			}

			if ( '\\' === $character ) {
				if ( $offset + 1 >= $length ) {
					// The string does not terminate.
					return null;
				}

				++$offset;

				if ( "\n" === $value[ $offset ] ) {
					// An escaped newline continues the string.
					++$offset;
					continue;
				}

				$result .= self::consume_escape( $value, $offset, $length );
				continue;
			}

			$result .= $character;
			++$offset;
		}

		return null;
	}

	/**
	 * Consumes an identifier and returns its decoded text.
	 *
	 * The offset must point at the start of an identifier. See
	 * {@see WP_CSS_Font_Family::starts_identifier()}.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset. Passed by reference.
	 * @param int    $length Input length.
	 * @return string The decoded text.
	 */
	private static function consume_identifier( $value, &$offset, $length ) {
		$result = '';

		while ( $offset < $length ) {
			$character = $value[ $offset ];

			if ( '\\' === $character ) {
				if ( ! self::is_valid_escape( $value, $offset, $length ) ) {
					break;
				}

				++$offset;
				$result .= self::consume_escape( $value, $offset, $length );
				continue;
			}

			// Copy the literal bytes up to the next escape or token boundary.
			if ( ! preg_match( '/\G[-_a-zA-Z0-9\x80-\xff]+/', $value, $matches, 0, $offset ) ) {
				break;
			}

			$result .= $matches[0];
			$offset += strlen( $matches[0] );
		}

		return $result;
	}

	/**
	 * Consumes an escape sequence and returns the code point it encodes.
	 *
	 * The offset must point at the character after the backslash, and that
	 * character must exist.
	 *
	 * @since 7.2.0
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset. Passed by reference.
	 * @param int    $length Input length.
	 * @return string The decoded text.
	 */
	private static function consume_escape( $value, &$offset, $length ) {
		if ( ! ctype_xdigit( $value[ $offset ] ) ) {
			// The escape encodes the next code point. Copy its complete UTF-8 sequence.
			$size = 1;
			while ( $offset + $size < $length && 0x80 === ( ord( $value[ $offset + $size ] ) & 0xC0 ) ) {
				++$size;
			}

			$result  = substr( $value, $offset, $size );
			$offset += $size;
			return $result;
		}

		$size       = strspn( $value, '0123456789abcdefABCDEF', $offset, 6 );
		$code_point = (int) hexdec( substr( $value, $offset, $size ) );
		$offset    += $size;

		// One whitespace character ends the hexadecimal escape.
		if ( $offset < $length && in_array( $value[ $offset ], array( ' ', "\t", "\n" ), true ) ) {
			++$offset;
		}

		// Zero, a surrogate, and a code point above U+10FFFF decode to the replacement character.
		$character = 0 === $code_point ? false : mb_chr( $code_point, 'UTF-8' );

		return false === $character ? "\u{FFFD}" : $character;
	}

	/**
	 * Checks whether the input at the offset starts an identifier.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset.
	 * @param int    $length Input length.
	 * @return bool True if an identifier starts at the offset.
	 */
	private static function starts_identifier( $value, $offset, $length ) {
		// Match two hyphens, or an optional hyphen before a name start or valid escape.
		return $offset < $length && 1 === preg_match( '/\G(?:--|-?(?:[_a-zA-Z\x80-\xff]|\\\\[^\n]))/', $value, $matches, 0, $offset );
	}

	/**
	 * Checks whether a backslash at the offset starts a valid escape.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset, at the backslash.
	 * @param int    $length Input length.
	 * @return bool True if the backslash starts a valid escape.
	 */
	private static function is_valid_escape( $value, $offset, $length ) {
		return $offset + 1 < $length && '\\' === $value[ $offset ] && "\n" !== $value[ $offset + 1 ];
	}

	/**
	 * Reads one part of a value as an established plain font name.
	 *
	 * @since 7.2.0
	 *
	 * @param string $part One comma separated part of the input.
	 * @return string|null The plain name, or null if the part is not a plain name.
	 */
	private static function parse_plain_name( $part ) {
		$name = trim( $part, " \t\n\r\f" );

		if ( '' === $name ) {
			return null;
		}

		/*
		 * A value that starts with a quote is CSS, and the CSS parser already
		 * rejected it. A quote inside the value is part of the plain name. This
		 * accepts the names `O'Reilly Sans` and `O"Reilly Sans`.
		 */
		if ( "'" === $name[0] || '"' === $name[0] ) {
			return null;
		}

		if ( strcspn( $name, self::PLAIN_NAME_REJECTED_CHARACTERS ) !== strlen( $name ) ) {
			return null;
		}

		// Reject the remaining control characters.
		if ( 1 === preg_match( '/[\x00-\x1f\x7f]/', $name ) ) {
			return null;
		}

		return $name;
	}
}
