<?php
/**
 * HTML API: WP_CSS_Complex_Selector_List class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * Core class used by the {@see WP_HTML_Processor} to parse and match CSS selectors.
 *
 * This class is designed for internal use by the HTML processor.
 *
 * For usage, see {@see WP_HTML_Processor::select()} or {@see WP_HTML_Processor::select_all()}.
 *
 * This class is instantiated via the {@see WP_CSS_Complex_Selector_List::from_selectors()} method.
 * It takes a CSS selector string and returns an instance of itself or `null` if the selector
 * is invalid or unsupported.
 *
 * A subset of the CSS selector grammar is supported. The grammar is defined in the CSS Syntax
 * specification, which is available at {@link https://www.w3.org/TR/selectors/#grammar}.
 *
 * This class is rougly analogous to the <selector-list> in the grammar. See {@see WP_CSS_Compound_Selector_List} for more details on the grammar.
 *
 * This class supports the same selector syntax as {@see WP_CSS_Compound_Selector_List} as well as:
 * - The following combinators:
 *   - Next sibling (`el + el`)
 *   - Subsequent sibling (`el ~ el`)
 *
 * @since 6.8.0
 *
 * @access private
 */
class WP_CSS_Complex_Selector_List extends WP_CSS_Compound_Selector_List {
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
		$selector = WP_CSS_Complex_Selector::parse( $input, $offset );
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
			$selector = WP_CSS_Complex_Selector::parse( $input, $offset );
			if ( null === $selector ) {
				return null;
			}
			$selectors[] = $selector;
			self::parse_whitespace( $input, $offset );
		}

		return new self( $selectors );
	}
}
