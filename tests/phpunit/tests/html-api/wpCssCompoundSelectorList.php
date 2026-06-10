<?php
/**
 * Unit tests covering WP_CSS_Compound_Selector_List functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since {WP_VERSION}
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_Compound_Selector_List
 */
class Tests_HtmlApi_WpCssCompoundSelectorList extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 */
	public function test_parse_selector_list() {
		$input  = 'el1, el2, el.foo#bar[baz=quux]';
		$result = WP_CSS_Compound_Selector_List::from_selectors( $input );
		$this->assertNotNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_invalid_selector_list() {
		$input  = 'el,,';
		$result = WP_CSS_Compound_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_invalid_selector_list2() {
		$input  = 'el!';
		$result = WP_CSS_Compound_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}

	/**
	 * An escaped whitespace code point at the end of input belongs to the
	 * ident and must survive input normalization: `.foo\ ` is the valid
	 * class `foo ` (with a space), not a backslash at the end of input.
	 *
	 * @ticket 62653
	 */
	public function test_parse_escaped_whitespace_at_end_of_input() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( '.foo\\ ' );
		$this->assertNotNull( $result );
	}

	/**
	 * A backslash before a newline is not a valid escape; at the end of
	 * input it must not be mistaken for trimmable trailing whitespace.
	 *
	 * @ticket 62653
	 */
	public function test_parse_escape_before_newline_at_end_of_input_is_invalid() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( ".foo\\\n" );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_empty_selector_list() {
		$input  = " \t   \t\n\r\f";
		$result = WP_CSS_Compound_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_unsupported_complex_selector() {
		$input  = 'ancestor descendant';
		$result = WP_CSS_Compound_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}
}
