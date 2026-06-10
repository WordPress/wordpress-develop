<?php
/**
 * HTML API: WP_CSS_Attribute_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since {WP_VERSION}
 */

/**
 * CSS attribute selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * @since {WP_VERSION}
 *
 * @access private
 */
final class WP_CSS_Attribute_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The attribute value is matched exactly.
	 *
	 * @example
	 *
	 *     [attr=val]
	 */
	const MATCH_EXACT = 'exact';

	/**
	 * The attribute value matches any value in a whitespace separated list of words exactly.
	 *
	 * @example
	 *
	 *     [attr~=value]
	 */
	const MATCH_ONE_OF_EXACT = 'one-of';

	/**
	 * The attribute value is matched exactly or matches the beginning of the attribute
	 * immediately followed by a hyphen.
	 *
	 * @example
	 *
	 *     [attr|=value]
	 */
	const MATCH_EXACT_OR_HYPHEN_SUFFIXED = 'exact-or-hyphen-suffixed';

	/**
	 * The attribute value matches the start of the attribute.
	 *
	 * @example
	 *
	 *     [attr^=value]
	 */
	const MATCH_PREFIXED_BY = 'prefixed';

	/**
	 * The attribute value matches the end of the attribute.
	 *
	 * @example
	 *
	 *     [attr$=value]
	 */
	const MATCH_SUFFIXED_BY = 'suffixed';

	/**
	 * The attribute value is contained in the attribute.
	 *
	 * @example
	 *
	 *     [attr*=value]
	 */
	const MATCH_CONTAINS = 'contains';

	/**
	 * Modifier for case sensitive matching.
	 *
	 * @example
	 *
	 *     [attr=value s]
	 */
	const MODIFIER_CASE_SENSITIVE = 'case-sensitive';

	/**
	 * Modifier for case insensitive matching.
	 *
	 * @example
	 *
	 *     [attr=value i]
	 */
	const MODIFIER_CASE_INSENSITIVE = 'case-insensitive';

	/**
	 * The attributes whose values HTML defines as ASCII case-insensitive
	 * for attribute selectors on an HTML element, when the selector has no
	 * `i`/`s` modifier. An explicit `s` modifier forces case-sensitive
	 * matching even for these attributes; elements in other namespaces
	 * (SVG, MathML) are unaffected.
	 *
	 * The names are stored as array keys for constant-time lookup.
	 *
	 * @see https://html.spec.whatwg.org/multipage/semantics-other.html#case-sensitivity-of-selectors
	 */
	const HTML_CASE_INSENSITIVE_ATTRIBUTE_VALUES = array(
		'accept'         => true,
		'accept-charset' => true,
		'align'          => true,
		'alink'          => true,
		'axis'           => true,
		'bgcolor'        => true,
		'charset'        => true,
		'checked'        => true,
		'clear'          => true,
		'codetype'       => true,
		'color'          => true,
		'compact'        => true,
		'declare'        => true,
		'defer'          => true,
		'dir'            => true,
		'direction'      => true,
		'disabled'       => true,
		'enctype'        => true,
		'face'           => true,
		'frame'          => true,
		'hreflang'       => true,
		'http-equiv'     => true,
		'lang'           => true,
		'language'       => true,
		'link'           => true,
		'media'          => true,
		'method'         => true,
		'multiple'       => true,
		'nohref'         => true,
		'noresize'       => true,
		'noshade'        => true,
		'nowrap'         => true,
		'readonly'       => true,
		'rel'            => true,
		'rev'            => true,
		'rules'          => true,
		'scope'          => true,
		'scrolling'      => true,
		'selected'       => true,
		'shape'          => true,
		'target'         => true,
		'text'           => true,
		'type'           => true,
		'valign'         => true,
		'valuetype'      => true,
		'vlink'          => true,
	);

	/**
	 * The name of the attribute to match.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The attribute matcher.
	 *
	 * Allowed string values are the class constants:
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_EXACT}
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT}
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_EXACT_OR_HYPHEN_SUFFIXED}
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_PREFIXED_BY}
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_SUFFIXED_BY}
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_CONTAINS}
	 *
	 * @var string|null
	 */
	public $matcher;

	/**
	 * The attribute value to match.
	 *
	 * @var string|null
	 */
	public $value;

	/**
	 * The attribute modifier.
	 *
	 * Allowed string values are the class constants:
	 *   - {@see WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE}
	 *   - {@see WP_CSS_Attribute_Selector::MODIFIER_CASE_INSENSITIVE}
	 *
	 * @var string|null
	 */
	public $modifier;

	/**
	 * Constructor.
	 *
	 * @param string $name The attribute name.
	 * @param string|null $matcher The attribute matcher.
	 *        Must be one of the class MATCH_* constants or null.
	 * @param string|null $value The attribute value to match.
	 * @param string|null $modifier The attribute case modifier.
	 *        Must be one of the class MODIFIER_* constants or null.
	 */
	private function __construct( string $name, ?string $matcher = null, ?string $value = null, ?string $modifier = null ) {
		$this->name     = $name;
		$this->matcher  = $matcher;
		$this->value    = $value;
		$this->modifier = $modifier;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( WP_HTML_Tag_Processor $processor ): bool {
		$attr_value = $processor->get_attribute( $this->name );
		if ( null === $attr_value ) {
			return false;
		}

		if ( null === $this->value ) {
			return true;
		}

		/*
		 * The substring matchers match nothing when the value is empty:
		 *
		 * > If "val" is the empty string then the selector does not represent anything.
		 *
		 * https://www.w3.org/TR/selectors-4/#attribute-substrings
		 */
		if (
			'' === $this->value &&
			(
				self::MATCH_PREFIXED_BY === $this->matcher ||
				self::MATCH_SUFFIXED_BY === $this->matcher ||
				self::MATCH_CONTAINS === $this->matcher
			)
		) {
			return false;
		}

		if ( true === $attr_value ) {
			$attr_value = '';
		}

		/*
		 * Without an explicit modifier, HTML defines some attributes' values
		 * as ASCII case-insensitive on HTML elements. An explicit `s`
		 * modifier forces case-sensitive matching even for those.
		 */
		$case_insensitive = self::MODIFIER_CASE_INSENSITIVE === $this->modifier || (
			null === $this->modifier &&
			'html' === $processor->get_namespace() &&
			isset( self::HTML_CASE_INSENSITIVE_ATTRIBUTE_VALUES[ strtolower( $this->name ) ] )
		);

		switch ( $this->matcher ) {
			case self::MATCH_EXACT:
				return $case_insensitive
					? 0 === strcasecmp( $attr_value, $this->value )
					: $attr_value === $this->value;

			case self::MATCH_ONE_OF_EXACT:
				foreach ( $this->whitespace_delimited_list( $attr_value ) as $val ) {
					if (
						$case_insensitive
							? 0 === strcasecmp( $val, $this->value )
							: $val === $this->value
					) {
						return true;
					}
				}
				return false;

			case self::MATCH_EXACT_OR_HYPHEN_SUFFIXED:
				$exact_length   = strlen( $this->value );
				$matches_prefix = substr_compare( $attr_value, $this->value, 0, $exact_length, $case_insensitive );
				return (
					0 === $matches_prefix &&
					( strlen( $attr_value ) === $exact_length || '-' === $attr_value[ $exact_length ] )
				);

			case self::MATCH_PREFIXED_BY:
				return 0 === substr_compare( $attr_value, $this->value, 0, strlen( $this->value ), $case_insensitive );

			case self::MATCH_SUFFIXED_BY:
				return 0 === substr_compare( $attr_value, $this->value, -strlen( $this->value ), null, $case_insensitive );

			case self::MATCH_CONTAINS:
				return false !== (
					$case_insensitive
						? stripos( $attr_value, $this->value )
						: strpos( $attr_value, $this->value )
				);
		}
	}

	/**
	 * Splits a string into a list of whitespace delimited values.
	 *
	 * This is useful for the {@see WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT} matcher.
	 *
	 * @param string $input
	 *
	 * @return Generator<string> Yields each whitespace-delimited value from the input string.
	 */
	private function whitespace_delimited_list( string $input ): Generator {
		// Start by skipping whitespace.
		$offset = strspn( $input, self::WHITESPACE_CHARACTERS );

		while ( $offset < strlen( $input ) ) {
			// Find the byte length until the next boundary.
			$length = strcspn( $input, self::WHITESPACE_CHARACTERS, $offset );
			$value  = substr( $input, $offset, $length );

			// Move past trailing whitespace.
			$offset += $length + strspn( $input, self::WHITESPACE_CHARACTERS, $offset + $length );

			yield $value;
		}
	}

	/**
	 * Parses a selector string to create a selector instance.
	 *
	 * To create an instance of this class, use the {@see WP_CSS_Compound_Selector_List::from_selectors()} method.
	 *
	 * The end of input acts like a closing `]`: tokenization auto-closes
	 * unterminated simple blocks (and unterminated strings) at EOF, so
	 * `[att=val` is the same selector as `[att=val]`. Truncation inside the
	 * selector grammar itself (e.g. `[` or `[att=`) is still invalid.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#consume-simple-block
	 *
	 * @param string $input The selector string.
	 * @param int    $offset The offset into the string. The offset is passed by reference and
	 *                       will be updated if the parse is successful.
	 * @return static|null The selector instance, or null if the parse was unsuccessful.
	 */
	public static function parse( string $input, int &$offset ) {
		// Need at least 2 bytes `[x`; the closing `]` may be supplied by the end of input.
		if ( $offset + 1 >= strlen( $input ) ) {
			return null;
		}

		$updated_offset = $offset;

		if ( '[' !== $input[ $updated_offset ] ) {
			return null;
		}
		++$updated_offset;

		self::parse_whitespace( $input, $updated_offset );
		$attr_name = self::parse_ident( $input, $updated_offset );
		if ( null === $attr_name ) {
			return null;
		}
		self::parse_whitespace( $input, $updated_offset );

		// The end of input auto-closes the attribute selector.
		if ( $updated_offset >= strlen( $input ) ) {
			$offset = $updated_offset;
			return new WP_CSS_Attribute_Selector( $attr_name );
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new WP_CSS_Attribute_Selector( $attr_name );
		}

		if ( '=' === $input[ $updated_offset ] ) {
			++$updated_offset;
			$attr_matcher = WP_CSS_Attribute_Selector::MATCH_EXACT;
		} elseif ( $updated_offset + 1 < strlen( $input ) && '=' === $input[ $updated_offset + 1 ] ) {
			switch ( $input[ $updated_offset ] ) {
				case '~':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT;
					$updated_offset += 2;
					break;
				case '|':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_EXACT_OR_HYPHEN_SUFFIXED;
					$updated_offset += 2;
					break;
				case '^':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_PREFIXED_BY;
					$updated_offset += 2;
					break;
				case '$':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_SUFFIXED_BY;
					$updated_offset += 2;
					break;
				case '*':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_CONTAINS;
					$updated_offset += 2;
					break;
				default:
					return null;
			}
		} else {
			return null;
		}

		self::parse_whitespace( $input, $updated_offset );
		$attr_val =
			self::parse_string( $input, $updated_offset ) ??
			self::parse_ident( $input, $updated_offset );

		if ( null === $attr_val ) {
			return null;
		}

		self::parse_whitespace( $input, $updated_offset );

		$attr_modifier = null;
		if ( $updated_offset < strlen( $input ) ) {
			switch ( $input[ $updated_offset ] ) {
				case 'i':
				case 'I':
					$attr_modifier = WP_CSS_Attribute_Selector::MODIFIER_CASE_INSENSITIVE;
					++$updated_offset;
					break;

				case 's':
				case 'S':
					$attr_modifier = WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE;
					++$updated_offset;
					break;
			}

			if ( null !== $attr_modifier ) {
				self::parse_whitespace( $input, $updated_offset );
			}
		}

		// The end of input auto-closes the attribute selector.
		if ( $updated_offset >= strlen( $input ) ) {
			$offset = $updated_offset;
			return new self( $attr_name, $attr_matcher, $attr_val, $attr_modifier );
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new self( $attr_name, $attr_matcher, $attr_val, $attr_modifier );
		}

		return null;
	}
}
