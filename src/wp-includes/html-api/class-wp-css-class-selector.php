<?php
/**
 * HTML API: WP_CSS_Class_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * CSS class selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * @since 6.8.0
 *
 * @access private
 */
final class WP_CSS_Class_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The class name to match.
	 *
	 * @var string
	 */
	public $class_name;

	/**
	 * Constructor.
	 *
	 * @param string $class_name The class name to match.
	 */
	private function __construct( string $class_name ) {
		$this->class_name = $class_name;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( WP_HTML_Tag_Processor $processor ): bool {
		return (bool) $processor->has_class( $this->class_name );
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
		if ( $offset + 1 >= strlen( $input ) || '.' !== $input[ $offset ] ) {
			return null;
		}

		$updated_offset = $offset + 1;
		$result         = self::parse_ident( $input, $updated_offset );

		if ( null === $result ) {
			return null;
		}

		$offset = $updated_offset;
		return new self( $result );
	}
}
