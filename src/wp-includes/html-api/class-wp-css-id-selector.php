<?php
/**
 * HTML API: WP_CSS_ID_Selector class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.8.0
 */

/**
 * CSS ID selector.
 *
 * This class is used to test for matching HTML tags in a {@see WP_HTML_Tag_Processor}.
 *
 * @since 6.8.0
 *
 * @access private
 */
final class WP_CSS_ID_Selector extends WP_CSS_Selector_Parser_Matcher {
	/**
	 * The ID to match.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Constructor.
	 *
	 * @param string $id The ID to match.
	 */
	private function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Determines if the processor's current position matches the selector.
	 *
	 * @param WP_HTML_Tag_Processor $processor The processor.
	 * @return bool True if the processor's current position matches the selector.
	 */
	public function matches( WP_HTML_Tag_Processor $processor ): bool {
		$id = $processor->get_attribute( 'id' );
		if ( ! is_string( $id ) ) {
			return false;
		}

		$case_insensitive = $processor->is_quirks_mode();

		return $case_insensitive
		? 0 === strcasecmp( $id, $this->id )
		: $processor->get_attribute( 'id' ) === $this->id;
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
		$ident = self::parse_hash_token( $input, $offset );
		if ( null === $ident ) {
			return null;
		}
		return new self( $ident );
	}
}
