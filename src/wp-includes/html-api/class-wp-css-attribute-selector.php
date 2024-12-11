<?php
/**
 * HTML API: WP_CSS_Attribute_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * CSS attribute selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * @since 6.8.0
 *
 * @access private
 */
final class WP_CSS_Attribute_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The attribute value is matched exactly.
	 *
	 * @example
	 *
	 *     [att=val]
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
	const MATCH_EXACT_OR_HYPHEN_PREFIXED = 'exact-or-hyphen-prefixed';

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
	 *   - {@see WP_CSS_Attribute_Selector::MATCH_EXACT_OR_HYPHEN_PREFIXED}
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
		$att_value = $processor->get_attribute( $this->name );
		if ( null === $att_value ) {
			return false;
		}

		if ( null === $this->value ) {
			return true;
		}

		if ( true === $att_value ) {
			$att_value = '';
		}

		$case_insensitive = self::MODIFIER_CASE_INSENSITIVE === $this->modifier;

		switch ( $this->matcher ) {
			case self::MATCH_EXACT:
				return $case_insensitive
					? 0 === strcasecmp( $att_value, $this->value )
					: $att_value === $this->value;

			case self::MATCH_ONE_OF_EXACT:
				foreach ( $this->whitespace_delimited_list( $att_value ) as $val ) {
					if (
						$case_insensitive
							? 0 === strcasecmp( $val, $this->value )
							: $val === $this->value
					) {
						return true;
					}
				}
				return false;

			case self::MATCH_EXACT_OR_HYPHEN_PREFIXED:
				// Attempt the full match first
				if (
					$case_insensitive
						? 0 === strcasecmp( $att_value, $this->value )
						: $att_value === $this->value
				) {
					return true;
				}

				// Partial match
				if ( strlen( $att_value ) < strlen( $this->value ) + 1 ) {
					return false;
				}

				$starts_with = "{$this->value}-";
				return 0 === substr_compare( $att_value, $starts_with, 0, strlen( $starts_with ), $case_insensitive );

			case self::MATCH_PREFIXED_BY:
				return 0 === substr_compare( $att_value, $this->value, 0, strlen( $this->value ), $case_insensitive );

			case self::MATCH_SUFFIXED_BY:
				return 0 === substr_compare( $att_value, $this->value, -strlen( $this->value ), null, $case_insensitive );

			case self::MATCH_CONTAINS:
				return false !== (
					$case_insensitive
						? stripos( $att_value, $this->value )
						: strpos( $att_value, $this->value )
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
	 * @return Generator<string>
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
	 * @param string $input The selector string.
	 * @param int    $offset The offset into the string. The offset is passed by reference and
	 *                       will be updated if the parse is successful.
	 * @return static|null The selector instance, or null if the parse was unsuccessful.
	 */
	public static function parse( string $input, int &$offset ) {
		// Need at least 3 bytes [x]
		if ( $offset + 2 >= strlen( $input ) ) {
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

		if ( $updated_offset >= strlen( $input ) ) {
			return null;
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new WP_CSS_Attribute_Selector( $attr_name );
		}

		// need to match at least `=x]` at this point
		if ( $updated_offset + 3 >= strlen( $input ) ) {
			return null;
		}

		if ( '=' === $input[ $updated_offset ] ) {
			++$updated_offset;
			$attr_matcher = WP_CSS_Attribute_Selector::MATCH_EXACT;
		} elseif ( '=' === $input[ $updated_offset + 1 ] ) {
			switch ( $input[ $updated_offset ] ) {
				case '~':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT;
					$updated_offset += 2;
					break;
				case '|':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_EXACT_OR_HYPHEN_PREFIXED;
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
		if ( $updated_offset >= strlen( $input ) ) {
			return null;
		}

		$attr_modifier = null;
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
			if ( $updated_offset >= strlen( $input ) ) {
				return null;
			}
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new self( $attr_name, $attr_matcher, $attr_val, $attr_modifier );
		}

		return null;
	}
}
