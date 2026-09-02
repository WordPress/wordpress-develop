<?php
/**
 * HTML API: WP_CSS_Complex_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since {WP_VERSION}
 */

/**
 * CSS complex selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Processor}.
 *
 * A complex selector is at least a single compound selector. There may be additional selectors
 * with combinators.
 *
 * @since {WP_VERSION}
 *
 * @access private
 */
final class WP_CSS_Complex_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * Child combinator.
	 */
	const COMBINATOR_CHILD = '>';

	/**
	 * Descendant combinator.
	 */
	const COMBINATOR_DESCENDANT = ' ';

	/**
	 * Next sibling combinator.
	 *
	 * This combinator is not currently supported.
	 */
	const COMBINATOR_NEXT_SIBLING = '+';

	/**
	 * Subsequent sibling combinator.
	 *
	 * This combinator is not currently supported.
	 */
	const COMBINATOR_SUBSEQUENT_SIBLING = '~';

	/**
	 * The "self selector" is the last element in a complex selector, it corresponds to the
	 * selected element.
	 *
	 * Example:
	 *
	 *                   $self_selector
	 *                   ┏━━━━┻━━━━┓
	 *     .heading h1 > el.selected
	 *
	 * @readonly
	 * @var WP_CSS_Compound_Selector
	 */
	public $self_selector;

	/**
	 * The "context selectors" are zero or more elements that provide additional constraints for
	 * the "self selector."
	 *
	 * These selectors are represented as 2-tuples where the element at index 0 is the selector and
	 * the element at index 1 is the combinator string constant from this class,
	 * e.g. `WP_CSS_Complex_Selector::COMBINATOR_CHILD`.
	 *
	 * In the example selector below, an element like `<strong class="selected">` matches iff:
	 *   - it is a child of an `H1` element
	 *   - that `H1` element is a descendant of a `SECTION` element.
	 *
	 * The `section` and `h1` parts of this selector and their combinators are the
	 * "context selectors." Note that this terminology does not correspond to language in the
	 * specification texts.
	 *
	 *     $context_selectors
	 *     ┏━━━━━┻━━━━┓
	 *     section h1 > strong.selected
	 *
	 * The example would have the following context selectors:
	 *
	 *     // Pseudo-code
	 *     array(
	 *       array( WP_CSS_Type_Selector( 'type'=>'h1' ), '>' ),
	 *       array( WP_CSS_Type_Selector( 'type'=>'section' ), ' ' ),
	 *     )
	 *
	 * Context selectors are ordered from right to left in the selector text. The selectors closest
	 * to the target appear at the start of the `context_selectors` array.
	 *
	 * @readonly
	 * @var array{WP_CSS_Type_Selector, string}[]|null
	 */
	public $context_selectors;

	/**
	 * Constructor.
	 *
	 * @param WP_CSS_Compound_Selector $self_selector The selector in the final position.
	 * @param array{WP_CSS_Type_Selector, string}[]|null $context_selectors The context selectors.
	 */
	private function __construct(
		WP_CSS_Compound_Selector $self_selector,
		?array $context_selectors
	) {
		$this->self_selector     = $self_selector;
		$this->context_selectors = $context_selectors;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( $processor ): bool {
		// First selector must match this location.
		if ( ! $this->self_selector->matches( $processor ) ) {
			return false;
		}

		if ( null === $this->context_selectors || array() === $this->context_selectors ) {
			return true;
		}

		$breadcrumbs = array_slice( array_reverse( $processor->get_breadcrumbs() ), 1 );
		return $this->explore_matches( $this->context_selectors, $breadcrumbs );
	}

	/**
	 * Checks for matches by recursively comparing context selectors with breadcrumbs.
	 *
	 * @param array{WP_CSS_Type_Selector, string}[] $selectors Selectors to match.
	 * @param string[] $breadcrumbs Breadcrumbs.
	 * @return bool True if a match is found, otherwise false.
	 */
	private function explore_matches( array $selectors, array $breadcrumbs ): bool {
		if ( array() === $selectors ) {
			return true;
		}
		if ( array() === $breadcrumbs ) {
			return false;
		}

		$selector   = $selectors[0][0];
		$combinator = $selectors[0][1];

		switch ( $combinator ) {
			case self::COMBINATOR_CHILD:
				if ( $selector->matches_tag( $breadcrumbs[0] ) ) {
					return $this->explore_matches( array_slice( $selectors, 1 ), array_slice( $breadcrumbs, 1 ) );
				}
				return false;

			case self::COMBINATOR_DESCENDANT:
				// Find _all_ the breadcrumbs that match and recurse from each of them.
				for ( $i = 0; $i < count( $breadcrumbs ); $i++ ) {
					if ( $selector->matches_tag( $breadcrumbs[ $i ] ) ) {
						$next_breadcrumbs = array_slice( $breadcrumbs, $i + 1 );
						if ( $this->explore_matches( array_slice( $selectors, 1 ), $next_breadcrumbs ) ) {
							return true;
						}
					}
				}
				return false;

			default:
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// translators: %s: A CSS selector combinator like ">" or "+".
						__( 'Unsupported combinator "%s" found.' ),
						$combinator
					),
					'{WP_VERSION}'
				);
				return false;
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
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		$updated_offset = $offset;
		$self_selector  = WP_CSS_Compound_Selector::parse( $input, $updated_offset );
		if ( null === $self_selector ) {
			return null;
		}
		/** @var array{WP_CSS_Compound_Selector, string}[] */
		$selectors = array();

		$found_whitespace = self::parse_whitespace( $input, $updated_offset );
		while ( $updated_offset < strlen( $input ) ) {
			$combinator    = null;
			$next_selector = null;

			// Sibling (`+` and `~`) combinators are not supported at this time.
			if (
				WP_CSS_Complex_Selector::COMBINATOR_NEXT_SIBLING === $input[ $updated_offset ] ||
				WP_CSS_Complex_Selector::COMBINATOR_SUBSEQUENT_SIBLING === $input[ $updated_offset ]
			) {
				return null;
			} elseif (
				WP_CSS_Complex_Selector::COMBINATOR_CHILD === $input[ $updated_offset ]
			) {
				$combinator = $input[ $updated_offset ];
				++$updated_offset;
				self::parse_whitespace( $input, $updated_offset );

				// A combinator has been found, failure to find a selector here is a parse error.
				$next_selector = WP_CSS_Compound_Selector::parse( $input, $updated_offset );
				if ( null === $next_selector ) {
					return null;
				}
			} elseif ( $found_whitespace ) {
				/*
				* Whitespace is ambiguous, it could be a descendant combinator or
				* insignificant whitespace.
				*/
				$next_selector = WP_CSS_Compound_Selector::parse( $input, $updated_offset );
				if ( null !== $next_selector ) {
					$combinator = WP_CSS_Complex_Selector::COMBINATOR_DESCENDANT;
				}
			}

			if ( null === $next_selector ) {
				break;
			}

			// $self_selector will pass to a relative selector where only the type selector is allowed.
			if ( null !== $self_selector->subclass_selectors || null === $self_selector->type_selector ) {
				return null;
			}

			/** @var array{WP_CSS_Type_Selector, string} */
			$selector_pair = array( $self_selector->type_selector, $combinator );
			$selectors[]   = $selector_pair;
			$self_selector = $next_selector;

			$found_whitespace = self::parse_whitespace( $input, $updated_offset );
		}
		$offset = $updated_offset;

		return new self( $self_selector, array_reverse( $selectors ) );
	}
}
