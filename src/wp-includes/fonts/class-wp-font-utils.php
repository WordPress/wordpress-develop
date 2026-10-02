<?php
/**
 * Font Utils class.
 *
 * Provides utility functions for working with font families.
 *
 * @package    WordPress
 * @subpackage Fonts
 * @since      6.5.0
 */

/**
 * A class of utilities for working with the Font Library.
 *
 * These utilities may change or be removed in the future and are intended for internal use only.
 *
 * @since 6.5.0
 * @access private
 */
class WP_Font_Utils {
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
	const GENERIC_FONT_FAMILIES = array(
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
	const RESERVED_FONT_FAMILY_KEYWORDS = array(
		'inherit',
		'initial',
		'unset',
		'revert',
		'revert-layer',
		'default',
	);

	/**
	 * Sanitizes and formats font family names.
	 *
	 * The method reads the value with the CSS `font-family` grammar and writes
	 * it back in a canonical form. It writes each named family as a quoted CSS
	 * string and keeps each generic family as a keyword. The decoded name does
	 * not change, so a name can contain a comma, an apostrophe, a quotation
	 * mark, or a CSS escape.
	 *
	 * For compatibility, the method also accepts a plain font name that is not
	 * valid CSS, such as `O'Reilly Sans`. It rejects a value that contains CSS
	 * syntax outside a quoted name, such as `"A"; color:red`.
	 *
	 * It follows the recommendations from the CSS Fonts Module Level 4.
	 * @link https://www.w3.org/TR/css-fonts-4/#font-family-prop
	 *
	 * @since 6.5.0
	 * @since 7.2.0 Parses the value with the CSS font family grammar to keep the font name. Names are
	 *              always quoted, and an invalid value returns an empty string.
	 * @access private
	 *
	 * @see WP_Font_Utils::parse_font_family_list_with_plain_names()
	 *
	 * @param string $font_family Font family name(s), comma-separated.
	 * @return string Sanitized and formatted font family name(s), or an empty
	 *                string if the value is invalid.
	 */
	public static function sanitize_font_family( $font_family ) {
		$entries = self::parse_font_family_list_with_plain_names( $font_family );

		if ( null === $entries ) {
			return '';
		}

		return self::serialize_font_family_list( $entries );
	}

	/**
	 * Generates a slug from font face properties, e.g. `open sans;normal;400;100%;U+0-10FFFF`
	 *
	 * Used for comparison with other font faces in the same family, to prevent duplicates
	 * that would both match according the CSS font matching spec. Uses only simple case-insensitive
	 * matching for fontFamily and unicodeRange, so does not handle overlapping font-family lists or
	 * unicode ranges.
	 *
	 * The font family part uses the decoded font names, so two values that
	 * write the same name with different CSS escapes produce the same slug.
	 *
	 * @since 6.5.0
	 * @since 7.2.0 Compares decoded font names instead of raw CSS text.
	 * @access private
	 *
	 * @link https://drafts.csswg.org/css-fonts/#font-style-matching
	 *
	 * @param array $settings {
	 *     Font face settings.
	 *
	 *     @type string $fontFamily   Font family name.
	 *     @type string $fontStyle    Optional font style, defaults to 'normal'.
	 *     @type string $fontWeight   Optional font weight, defaults to 400.
	 *     @type string $fontStretch  Optional font stretch, defaults to '100%'.
	 *     @type string $unicodeRange Optional unicode range, defaults to 'U+0-10FFFF'.
	 * }
	 * @return string Font face slug.
	 */
	public static function get_font_face_slug( $settings ) {
		$defaults    = array(
			'fontFamily'   => '',
			'fontStyle'    => 'normal',
			'fontWeight'   => '400',
			'fontStretch'  => '100%',
			'unicodeRange' => 'U+0-10FFFF',
		);
		$settings    = wp_parse_args( $settings, $defaults );
		$font_family = self::get_font_family_comparison_key( $settings['fontFamily'] );
		if ( function_exists( 'mb_strtolower' ) ) {
			$font_family = mb_strtolower( $font_family );
		} else {
			$font_family = strtolower( $font_family );
		}
		$font_style    = strtolower( $settings['fontStyle'] );
		$font_weight   = strtolower( $settings['fontWeight'] );
		$font_stretch  = strtolower( $settings['fontStretch'] );
		$unicode_range = strtoupper( $settings['unicodeRange'] );

		// Convert weight keywords to numeric strings.
		$font_weight = str_replace( array( 'normal', 'bold' ), array( '400', '700' ), $font_weight );

		// Convert stretch keywords to numeric strings.
		$font_stretch_map = array(
			'ultra-condensed' => '50%',
			'extra-condensed' => '62.5%',
			'condensed'       => '75%',
			'semi-condensed'  => '87.5%',
			'normal'          => '100%',
			'semi-expanded'   => '112.5%',
			'expanded'        => '125%',
			'extra-expanded'  => '150%',
			'ultra-expanded'  => '200%',
		);
		$font_stretch     = str_replace( array_keys( $font_stretch_map ), array_values( $font_stretch_map ), $font_stretch );

		$slug_elements = array( $font_style, $font_weight, $font_stretch, $unicode_range );

		$slug_elements = array_map(
			function ( $elem ) {
				// Remove quotes to normalize the values, and ';' to use as a separator.
				$elem = trim( str_replace( array( '"', "'", ';' ), '', $elem ) );

				// Normalize comma separated lists by removing whitespace in between items.
				// CSS spec for whitespace includes: U+000A LINE FEED, U+0009 CHARACTER TABULATION, or U+0020 SPACE,
				// which by default are all matched by \s in PHP.
				return preg_replace( '/,\s+/', ',', $elem );
			},
			$slug_elements
		);

		// The font family part keeps its own characters, so add it after the sanitization.
		return $font_family . ';' . sanitize_text_field( implode( ';', $slug_elements ) );
	}

	/**
	 * Builds the font family part of a font face slug.
	 *
	 * The method returns the decoded font names, separated by commas. As in
	 * WordPress 6.5.0, it removes the quotation marks and the apostrophes from
	 * each name, so that the slug of an existing font face does not change.
	 * Each name then replaces a small set of characters with a percent sequence:
	 *
	 * - `;` and `,` cannot change the field boundaries of the slug.
	 * - `&`, `<`, and `>` cannot change when KSES filters the `post_title` of
	 *   the font face post for a user without the `unfiltered_html` capability.
	 * - `\` cannot disappear when {@see WP_Query} removes slashes from its
	 *   `title` query parameter.
	 * - `%` keeps the replacement reversible.
	 *
	 * If the value is not a font family value that the parser accepts, the
	 * method uses the text normalization of WordPress 6.5.0.
	 *
	 * @since 7.2.0
	 *
	 * @param string $font_family Font family value.
	 * @return string The font family comparison key.
	 */
	private static function get_font_family_comparison_key( $font_family ) {
		$entries = self::parse_font_family_list_with_plain_names( $font_family );

		if ( null === $entries ) {
			// Keep the WordPress 6.5.0 behavior for a value that the parser rejects.
			$key = trim( str_replace( array( '"', "'", ';' ), '', (string) $font_family ) );
			return sanitize_text_field( preg_replace( '/,\s+/', ',', $key ) );
		}

		// Remove the quotes first. Replace '%' next, so that the replacement stays reversible.
		$search  = array( '"', "'", '%', '\\', ';', ',', '&', '<', '>' );
		$replace = array( '', '', '%25', '%5c', '%3b', '%2c', '%26', '%3c', '%3e' );

		$keys = array();
		foreach ( $entries as $entry ) {
			$keys[] = str_replace( $search, $replace, $entry['value'] );
		}

		return implode( ',', $keys );
	}

	/**
	 * Sanitizes a tree of data using a schema.
	 *
	 * The schema structure should mirror the data tree. Each value provided in the
	 * schema should be a callable that will be applied to sanitize the corresponding
	 * value in the data tree. Keys that are in the data tree, but not present in the
	 * schema, will be removed in the sanitized data. Nested arrays are traversed recursively.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @param array $tree   The data to sanitize.
	 * @param array $schema The schema used for sanitization.
	 * @return array The sanitized data.
	 */
	public static function sanitize_from_schema( $tree, $schema ) {
		if ( ! is_array( $tree ) || ! is_array( $schema ) ) {
			return array();
		}

		foreach ( $tree as $key => $value ) {
			// Remove keys not in the schema or with null/empty values.
			if ( ! array_key_exists( $key, $schema ) ) {
				unset( $tree[ $key ] );
				continue;
			}

			$is_value_array  = is_array( $value );
			$is_schema_array = is_array( $schema[ $key ] ) && ! is_callable( $schema[ $key ] );

			if ( $is_value_array && $is_schema_array ) {
				if ( wp_is_numeric_array( $value ) ) {
					// If indexed, process each item in the array.
					foreach ( $value as $item_key => $item_value ) {
						$tree[ $key ][ $item_key ] = isset( $schema[ $key ][0] ) && is_array( $schema[ $key ][0] )
							? self::sanitize_from_schema( $item_value, $schema[ $key ][0] )
							: self::apply_sanitizer( $item_value, $schema[ $key ][0] );
					}
				} else {
					// If it is an associative or indexed array, process as a single object.
					$tree[ $key ] = self::sanitize_from_schema( $value, $schema[ $key ] );
				}
			} elseif ( ! $is_value_array && $is_schema_array ) {
				// If the value is not an array but the schema is, remove the key.
				unset( $tree[ $key ] );
			} elseif ( ! $is_schema_array ) {
				// If the schema is not an array, apply the sanitizer to the value.
				$tree[ $key ] = self::apply_sanitizer( $value, $schema[ $key ] );
			}

			// Remove keys with null/empty values.
			if ( empty( $tree[ $key ] ) ) {
				unset( $tree[ $key ] );
			}
		}

		return $tree;
	}

	/**
	 * Applies a sanitizer function to a value.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed    $value     The value to sanitize.
	 * @param callable $sanitizer The sanitizer function to apply.
	 * @return mixed The sanitized value.
	 */
	private static function apply_sanitizer( $value, $sanitizer ) {
		if ( null === $sanitizer ) {
			return $value;

		}
		return call_user_func( $sanitizer, $value );
	}

	/**
	 * Returns the expected mime-type values for font files, depending on PHP version.
	 *
	 * This is needed because font mime types vary by PHP version, so checking the PHP version
	 * is necessary until a list of valid mime-types for each file extension can be provided to
	 * the 'upload_mimes' filter.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @return string[] A collection of mime types keyed by file extension.
	 */
	public static function get_allowed_font_mime_types() {
		$php_7_ttf_mime_type = PHP_VERSION_ID >= 70300 ? 'application/font-sfnt' : 'application/x-font-ttf';

		return array(
			'otf'   => 'application/vnd.ms-opentype',
			'ttf'   => PHP_VERSION_ID >= 70400 ? 'font/sfnt' : $php_7_ttf_mime_type,
			'woff'  => PHP_VERSION_ID >= 80112 ? 'font/woff' : 'application/font-woff',
			'woff2' => PHP_VERSION_ID >= 80112 ? 'font/woff2' : 'application/font-woff2',
		);
	}

	/**
	 * Parses a CSS `font-family` property value.
	 *
	 * The parser requires valid CSS. It consumes the complete value and
	 * rejects a value with extra tokens. It returns the decoded font names, so
	 * that other code can compare and store a name without CSS syntax.
	 *
	 * A parsed value is a list of entries. Each entry is an array with these keys:
	 *
	 *     @type string $type  One of 'name', 'generic', or 'keyword'.
	 *     @type string $value For 'name', the decoded font name. For 'generic' and
	 *                         'keyword', the canonical CSS text.
	 *
	 * @since 7.2.0
	 *
	 * @link https://www.w3.org/TR/css-fonts-4/#font-family-prop
	 * @link https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
	 *
	 * @param string $value CSS `font-family` value.
	 * @return array[]|null List of parsed entries, or null if the value is invalid.
	 */
	public static function parse_font_family_list( $value ) {
		return self::parse_font_family_entries( $value, false );
	}

	/**
	 * Parses a CSS `font-family` value and accepts an established plain name.
	 *
	 * Use this method at font input boundaries, such as the REST API, theme
	 * settings, and direct calls to {@see wp_print_font_faces()}. It reads each
	 * entry of the list as CSS. If an entry is not valid CSS, it reads the text
	 * up to the next comma as a plain name, which earlier WordPress versions
	 * accepted. It ignores an empty entry, such as the one after a trailing comma.
	 *
	 * The plain name path rejects an entry that contains CSS syntax characters,
	 * such as a semicolon or a parenthesis. Use
	 * {@see WP_Font_Utils::parse_font_family_list()} where the input must be valid CSS.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value CSS `font-family` value, or a plain font name.
	 * @return array[]|null List of parsed entries, or null if the value is invalid.
	 */
	public static function parse_font_family_list_with_plain_names( $value ) {
		return self::parse_font_family_entries( $value, true );
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
	public static function parse_font_family_descriptor_name( $value ) {
		$entries = self::parse_font_family_list_with_plain_names( $value );

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
	 * post filters without a change. It escapes the semicolon, because
	 * {@see safecss_filter_attr()} splits declarations at each semicolon.
	 *
	 * A hexadecimal escape uses the shortest digit sequence and always ends
	 * with one space. A leading zero is not possible, and the backslash also
	 * uses a hexadecimal escape, because {@see wp_kses_no_null()} removes a
	 * backslash that zeros follow.
	 *
	 * @since 7.2.0
	 *
	 * @link https://www.w3.org/TR/cssom-1/#serialize-a-string
	 *
	 * @param string $name Decoded font name.
	 * @return string The name as a quoted CSS string.
	 */
	public static function serialize_font_family_name( $name ) {
		return '"' . preg_replace_callback(
			'/[\x00-\x1f\x7f"\\\\<>&;]/',
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
	 * A name that is one identifier of letters and hyphens stays unquoted, such
	 * as `Arial` or `-apple-system`. Some browsers read a system font keyword,
	 * such as `-apple-system`, only when it has no quotes. Each other name is a
	 * quoted CSS string.
	 *
	 * @since 7.2.0
	 *
	 * @param array[] $entries List of parsed entries.
	 * @return string The CSS `font-family` value.
	 */
	public static function serialize_font_family_list( $entries ) {
		$parts = array();

		foreach ( $entries as $entry ) {
			if ( 'name' !== $entry['type'] || self::is_unquoted_font_family_name( $entry['value'] ) ) {
				$parts[] = $entry['value'];
			} else {
				$parts[] = self::serialize_font_family_name( $entry['value'] );
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Parses a CSS `font-family` value into a list of entries.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value             CSS `font-family` value.
	 * @param bool   $allow_plain_names Whether to read an entry that is not valid CSS as a plain name.
	 * @return array[]|null List of parsed entries, or null if the value is invalid.
	 */
	private static function parse_font_family_entries( $value, $allow_plain_names ) {
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
			$start = $offset;
			$entry = null;

			if ( self::skip_css_whitespace_and_comments( $value, $offset, $length ) ) {
				$entry = self::consume_css_family_name( $value, $offset, $length );
			}

			// The entry must end at a comma or at the end of the value.
			if (
				null !== $entry &&
				( ! self::skip_css_whitespace_and_comments( $value, $offset, $length ) ||
					( $offset < $length && ',' !== $value[ $offset ] ) )
			) {
				$entry = null;
			}

			// A reserved keyword is valid only as the single value of the property.
			if ( null !== $entry && 'keyword' === $entry['type'] && ( $entries || $offset < $length ) ) {
				$entry = null;
			}

			if ( null === $entry ) {
				if ( ! $allow_plain_names ) {
					return null;
				}

				$end    = strpos( $value, ',', $start );
				$offset = false === $end ? $length : $end;
				$part   = substr( $value, $start, $offset - $start );

				if ( '' !== trim( $part, " \t\n" ) ) {
					$name = self::parse_plain_font_family_name( $part );
					if ( null === $name ) {
						return null;
					}

					$entry = array(
						'type'  => 'name',
						'value' => $name,
					);
				}
			}

			if ( null !== $entry ) {
				$entries[] = $entry;
			}

			if ( $offset >= $length ) {
				break;
			}

			// Skip the comma.
			++$offset;
		}

		return $entries ? $entries : null;
	}

	/**
	 * Checks whether a font name can be written as an unquoted identifier.
	 *
	 * The name must be one identifier of ASCII letters and hyphens. It must not
	 * be a generic family or a reserved keyword, because without quotes the
	 * name would have a different meaning.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name Decoded font name.
	 * @return bool True if the name can be written without quotes.
	 */
	private static function is_unquoted_font_family_name( $name ) {
		if ( 1 !== preg_match( '/^-?[a-zA-Z][a-zA-Z-]*$/', $name ) ) {
			return false;
		}

		$lowercase = strtolower( $name );

		return ! in_array( $lowercase, self::GENERIC_FONT_FAMILIES, true )
			&& ! in_array( $lowercase, self::RESERVED_FONT_FAMILY_KEYWORDS, true );
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
	private static function skip_css_whitespace_and_comments( $value, &$offset, $length ) {
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
	private static function consume_css_family_name( $value, &$offset, $length ) {
		if ( $offset >= $length ) {
			return null;
		}

		$character = $value[ $offset ];

		if ( '"' === $character || "'" === $character ) {
			$name = self::consume_css_string( $value, $offset, $length );
			if ( null === $name ) {
				return null;
			}

			return array(
				'type'  => 'name',
				'value' => $name,
			);
		}

		$identifiers = array();

		while ( self::starts_css_identifier( $value, $offset, $length ) ) {
			$identifiers[] = self::consume_css_identifier( $value, $offset, $length );

			/*
			 * The `generic()` function names a generic family. It is only valid
			 * as the complete family name.
			 */
			if ( 1 === count( $identifiers ) && 'generic' === strtolower( $identifiers[0] ) && $offset < $length && '(' === $value[ $offset ] ) {
				return self::consume_css_generic_function( $value, $offset, $length );
			}

			// Whitespace and comments can separate the identifiers of one name.
			if ( ! self::skip_css_whitespace_and_comments( $value, $offset, $length ) ) {
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

			if ( in_array( $lowercase, self::GENERIC_FONT_FAMILIES, true ) ) {
				$type = 'generic';
			} elseif ( in_array( $lowercase, self::RESERVED_FONT_FAMILY_KEYWORDS, true ) ) {
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
	private static function consume_css_generic_function( $value, &$offset, $length ) {
		++$offset;

		if ( ! self::skip_css_whitespace_and_comments( $value, $offset, $length ) ) {
			return null;
		}

		if ( ! self::starts_css_identifier( $value, $offset, $length ) ) {
			return null;
		}

		$identifier = strtolower( self::consume_css_identifier( $value, $offset, $length ) );

		// Only defined generic arguments can enter CSS without quotes or escapes.
		if ( ! in_array( $identifier, array( 'kai', 'fangsong', 'khmer-mul', 'nastaliq' ), true ) ) {
			return null;
		}

		if ( ! self::skip_css_whitespace_and_comments( $value, $offset, $length ) ) {
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
	private static function consume_css_string( $value, &$offset, $length ) {
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

				$result .= self::consume_css_escape( $value, $offset, $length );
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
	 * {@see WP_Font_Utils::starts_css_identifier()}.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value  Preprocessed input.
	 * @param int    $offset Current offset. Passed by reference.
	 * @param int    $length Input length.
	 * @return string The decoded text.
	 */
	private static function consume_css_identifier( $value, &$offset, $length ) {
		$result = '';

		while ( $offset < $length ) {
			$character = $value[ $offset ];

			if ( '\\' === $character ) {
				// A backslash at the end of the input or before a newline is not an escape.
				if ( $offset + 1 >= $length || "\n" === $value[ $offset + 1 ] ) {
					break;
				}

				++$offset;
				$result .= self::consume_css_escape( $value, $offset, $length );
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
	private static function consume_css_escape( $value, &$offset, $length ) {
		if ( ! ctype_xdigit( $value[ $offset ] ) ) {
			// The escape encodes the next code point. The input is valid UTF-8.
			preg_match( '/\G./su', $value, $matches, 0, $offset );
			$offset += strlen( $matches[0] );
			return $matches[0];
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
	private static function starts_css_identifier( $value, $offset, $length ) {
		// Match two hyphens, or an optional hyphen before a name start or valid escape.
		return $offset < $length && 1 === preg_match( '/\G(?:--|-?(?:[_a-zA-Z\x80-\xff]|\\\\[^\n]))/', $value, $matches, 0, $offset );
	}

	/**
	 * Reads one part of a value as an established plain font name.
	 *
	 * @since 7.2.0
	 *
	 * @param string $part One comma separated part of the input.
	 * @return string|null The plain name, or null if the part is not a plain name.
	 */
	private static function parse_plain_font_family_name( $part ) {
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

		/*
		 * Reject the characters that start CSS syntax, and the control characters.
		 * A font name must not contain them.
		 */
		if ( 1 === preg_match( '#[;{}()\[\]@\\\\/*<>:!\x00-\x1f\x7f]#', $name ) ) {
			return null;
		}

		return $name;
	}
}
