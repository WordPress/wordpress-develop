<?php
/**
 * HTML API: WP_CSS_Compound_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * CSS compound selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * A compound selector is a combination of:
 *   - An optional type selector.
 *   - Zero or more subclass selectors (ID, class, or attribute selectors).
 *   - At least one of the above.
 *
 * @since 6.8.0
 *
 * @access private
 */
final class WP_CSS_Compound_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The type selector.
	 *
	 * @var WP_CSS_Type_Selector|null
	 */
	public $type_selector;

	/**
	 * The subclass selectors.
	 *
	 * Subclass selectors are ID, class, or attribute selectors.
	 *
	 * @var (WP_CSS_ID_Selector|WP_CSS_Class_Selector|WP_CSS_Attribute_Selector)[]|null
	 */
	public $subclass_selectors;

	/**
	 * Constructor.
	 *
	 * @param WP_CSS_Type_Selector|null $type_selector The type selector or null.
	 * @param (WP_CSS_ID_Selector|WP_CSS_Class_Selector|WP_CSS_Attribute_Selector)[]|null $subclass_selectors
	 *        The array of subclass selectors or null.
	 */
	private function __construct( ?WP_CSS_Type_Selector $type_selector, ?array $subclass_selectors ) {
		$this->type_selector      = $type_selector;
		$this->subclass_selectors = array() === $subclass_selectors ? null : $subclass_selectors;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( WP_HTML_Tag_Processor $processor ): bool {
		if ( $this->type_selector && ! $this->type_selector->matches( $processor ) ) {
			return false;
		}
		if ( null !== $this->subclass_selectors ) {
			foreach ( $this->subclass_selectors as $subclass_selector ) {
				if ( ! $subclass_selector->matches( $processor ) ) {
					return false;
				}
			}
		}
		return true;
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
		$type_selector  = WP_CSS_Type_Selector::parse( $input, $updated_offset );

		$subclass_selectors            = array();
		$last_parsed_subclass_selector = self::parse_subclass_selector( $input, $updated_offset );
		while ( null !== $last_parsed_subclass_selector ) {
			$subclass_selectors[]          = $last_parsed_subclass_selector;
			$last_parsed_subclass_selector = self::parse_subclass_selector( $input, $updated_offset );
		}

		// There must be at least one selector.
		if ( null === $type_selector && array() === $subclass_selectors ) {
			return null;
		}

		$offset = $updated_offset;
		return new self( $type_selector, $subclass_selectors );
	}

	/**
	 * Parses a subclass selector.
	 *
	 * > <subclass-selector> = <id-selector> | <class-selector> | <attribute-selector>
	 *
	 * @return WP_CSS_ID_Selector|WP_CSS_Class_Selector|WP_CSS_Attribute_Selector|null
	 */
	private static function parse_subclass_selector( string $input, int &$offset ) {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		switch ( $input[ $offset ] ) {
			case '.':
				return WP_CSS_Class_Selector::parse( $input, $offset );
			case '#':
				return WP_CSS_ID_Selector::parse( $input, $offset );
			case '[':
				return WP_CSS_Attribute_Selector::parse( $input, $offset );
		}

		return null;
	}
}
