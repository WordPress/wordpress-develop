<?php
/**
 * HTML API: WP_CSS_Compound_Selector_List class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since {WP_VERSION}
 */

/**
 * Core class used by the {@see WP_HTML_Tag_Processor} to parse and match CSS selectors.
 *
 * This class is designed for internal use by the HTML Tag Processor.
 *
 * For usage, see {@see WP_HTML_Tag_Processor::select()}.
 *
 * This class is instantiated via the {@see WP_CSS_Compound_Selector_List::from_selectors()} method.
 * It takes a CSS selector string and returns an instance of itself or `null` if the selector
 * is invalid or unsupported.
 *
 * ### Text Encoding
 *
 * Selector strings are UTF-8 text. Ill-formed byte sequences in a selector string are
 * replaced with U+FFFD REPLACEMENT CHARACTER (visually "�"), one per maximal subpart,
 * before parsing — following the byte-decoding step CSS Syntax specifies for input
 * byte streams (via the WHATWG Encoding Standard's UTF-8 decoder), not a browser's
 * `querySelector`, which receives already-decoded strings and can never see ill-formed
 * input. The replacement also triggers a `_doing_it_wrong()` notice, since a selector
 * containing ill-formed bytes is almost always a developer error and, once replaced,
 * matches only literal U+FFFD characters in the document.
 *
 * Note that the document side is byte-oriented and unscrubbed: the Tag Processor
 * reports attribute values and class names with their raw bytes intact (see the
 * "Text Encoding" section of {@see WP_HTML_Tag_Processor}). A selector containing
 * ill-formed bytes therefore never matches those same raw bytes in a document.
 * Selectors for non-UTF-8 ( but ASCII-compatible ) documents can only reliably match
 * non-ASCII values by converting the selector and document to UTF-8 beforehand.
 *
 * @link https://www.w3.org/TR/css-syntax-3/#input-byte-stream
 *
 * A subset of the CSS selector grammar is supported. The grammar is defined in the CSS Syntax
 * specification, which is available at {@link https://www.w3.org/TR/selectors/#grammar}.
 *
 * This class is analogous to <compound-selector-list> in the grammar. The supported grammar is:
 *
 *     <selector-list> = <complex-selector-list>
 *     <complex-selector-list> = <complex-selector>#
 *     <compound-selector-list> = <compound-selector>#
 *     <complex-selector> = [ <type-selector> <combinator>? ]* <compound-selector>
 *     <compound-selector> = [ <type-selector>? <subclass-selector>* ]!
 *     <combinator> = '>' | [ '|' '|' ]
 *     <type-selector> = <ident-token> | '*'
 *     <subclass-selector> = <id-selector> | <class-selector> | <attribute-selector>
 *     <id-selector> = <hash-token>
 *     <class-selector> = '.' <ident-token>
 *     <attribute-selector> = '[' <ident-token> ']' |
 *                            '[' <ident-token> <attr-matcher> [ <string-token> | <ident-token> ] <attr-modifier>? ']'
 *     <attr-matcher> = [ '~' | '|' | '^' | '$' | '*' ]? '='
 *     <attr-modifier> = i | s
 *
 * @link https://www.w3.org/TR/selectors/#grammar Refer to the grammar for more details.
 *
 * This class of selectors does not support "complex" selectors. That is any selector with a
 * combinator such as descendant (`.ancestor .descendant`) or child (`.parent > .child`).
 * See {@see WP_CSS_Complex_Selector_List} for support of some combinators.
 *
 * Note that this grammar has been adapted and does not support the full CSS selector grammar.
 * Supported selector syntax:
 * - Type selectors (tag names, e.g. `div`)
 * - Class selectors (e.g. `.class-name`)
 * - ID selectors (e.g. `#unique-id`)
 * - Attribute selectors (e.g. `[attribute-name]` or `[attribute-name="value"]`)
 * - Comma-separated selector lists (e.g. `.selector-1, .selector-2`)
 * - Compound selectors (e.g. `div.class-name#id[attr]`)
 *
 * Unsupported selector syntax:
 * - Pseudo-element selectors (`::before`)
 * - Pseudo-class selectors (`:hover` or `:nth-child(2)`)
 * - Namespace prefixes (`svg|title` or `[xlink|href]`)
 * - Combinators are not supported by this class (descendant, child, next sibling,
 *   subsequent sibling). See {@see WP_CSS_Complex_Selector_List} for combinator support.
 *
 * Future ideas:
 * - Namespace type selectors could be implemented with select namespaces in order to
 *   select elements from a namespace, for example:
 *   - `svg|*` to select all SVG elements
 *   - `html|title` to select only HTML TITLE elements.
 *
 * @since {WP_VERSION}
 *
 * @access private
 *
 * @link https://www.w3.org/TR/css-syntax-3/
 * @link https://www.w3.org/tr/selectors/
 * @link https://www.w3.org/TR/selectors-api2/
 * @link https://www.w3.org/TR/selectors-4/
 */
class WP_CSS_Compound_Selector_List extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( $processor ): bool {
		if ( $processor->get_token_type() !== '#tag' ) {
			return false;
		}

		foreach ( $this->selectors as $selector ) {
			if ( $selector->matches( $processor ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Array of selectors.
	 *
	 * @var array
	 */
	private $selectors;

	/**
	 * Constructor.
	 *
	 * @param array $selectors Array of selectors.
	 */
	protected function __construct( array $selectors ) {
		$this->selectors = $selectors;
	}

	/**
	 * Takes a CSS selector string and returns an instance of itself or `null` if the selector
	 * string is invalid or unsupported.
	 *
	 * The selector string must be UTF-8: ill-formed byte sequences are replaced with
	 * U+FFFD per maximal subpart before parsing and reported with `_doing_it_wrong()`.
	 * See the "Text Encoding" section of the class documentation.
	 *
	 * @param string $input CSS selectors.
	 * @return static|null
	 */
	public static function from_selectors( string $input ) {
		$input = self::normalize_selector_input( $input );

		if ( '' === $input ) {
			return null;
		}

		$offset = 0;
		return static::parse( $input, $offset );
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
		$selector = WP_CSS_Compound_Selector::parse( $input, $offset );
		if ( null === $selector ) {
			return null;
		}
		self::parse_whitespace( $input, $offset );

		$selectors = array( $selector );
		while ( $offset < strlen( $input ) ) {
			// Each loop should stop on a `,` selector list delimiter.
			if ( ',' !== $input[ $offset ] ) {
				return null;
			}
			++$offset;
			self::parse_whitespace( $input, $offset );
			$selector = WP_CSS_Compound_Selector::parse( $input, $offset );
			if ( null === $selector ) {
				return null;
			}
			$selectors[] = $selector;
			self::parse_whitespace( $input, $offset );
		}

		return new self( $selectors );
	}
}
