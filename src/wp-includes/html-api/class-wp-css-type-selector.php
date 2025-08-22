<?php
/**
 * HTML API: WP_CSS_Type_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * CSS type selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * @since 6.8.0
 *
 * @access private
 */
final class WP_CSS_Type_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The element type (tag name) to match or '*' to match any element.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Constructor.
	 *
	 * @param string $type The element type (tag name) to match or '*' to match any element.
	 */
	private function __construct( string $type ) {
		$this->type = $type;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( WP_HTML_Tag_Processor $processor ): bool {
		$tag_name = $processor->get_tag();
		if ( null === $tag_name ) {
			return false;
		}
		return $this->matches_tag( $tag_name );
	}

	/**
	 * Checks whether the selector matches the provided tag name.
	 *
	 * @param string $tag_name
	 * @return bool
	 */
	public function matches_tag( string $tag_name ): bool {
		if ( '*' === $this->type ) {
			return true;
		}
		return 0 === strcasecmp( $tag_name, $this->type );
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

		if ( '*' === $input[ $offset ] ) {
			++$offset;
			return new WP_CSS_Type_Selector( '*' );
		}

		$result = self::parse_ident( $input, $offset );
		if ( null === $result ) {
			return null;
		}

		return new self( $result );
	}
}
