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

	/**
	 * Selector strings are UTF-8 text: invalid byte sequences are replaced
	 * with U+FFFD per maximal subpart (CSS Syntax §3.2 via the WHATWG
	 * Encoding Standard) before parsing, so the selector parses rather than
	 * being rejected. The replacement is almost certainly not what the
	 * developer meant, so it also triggers `_doing_it_wrong()`.
	 *
	 * @expectedIncorrectUsage WP_CSS_Compound_Selector_List::from_selectors
	 */
	public function test_invalid_utf8_is_scrubbed_to_replacement_character_and_notifies() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( ".B\xFCcher" );
		$this->assertNotNull( $result, 'Selector with invalid UTF-8 should parse after scrubbing.' );
	}

	/**
	 * Valid UTF-8 — including a literal U+FFFD — must parse without any
	 * incorrect-usage notice: scrubbing is the identity function on valid
	 * input.
	 */
	public function test_valid_utf8_with_literal_replacement_character_is_not_notified() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( ".B\u{FFFD}cher" );
		$this->assertNotNull( $result, 'Selector containing a literal U+FFFD should parse.' );
	}

	/**
	 * The whole input is scrubbed uniformly, so a selector list with invalid
	 * bytes in one of several selectors still parses as a list.
	 *
	 * @expectedIncorrectUsage WP_CSS_Compound_Selector_List::from_selectors
	 */
	public function test_invalid_utf8_in_selector_list_is_scrubbed() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( ".ok, .B\xE2\x8Ccher" );
		$this->assertNotNull( $result, 'Selector list with invalid UTF-8 should parse after scrubbing.' );
	}

	/**
	 * A selector consisting of nothing but an invalid byte parses: it scrubs
	 * to U+FFFD, which is an ident-start code point and therefore a valid
	 * type selector. Surprising, but it follows from the scrub running
	 * before tokenization — the parser never sees the invalid byte.
	 *
	 * @expectedIncorrectUsage WP_CSS_Compound_Selector_List::from_selectors
	 */
	public function test_lone_invalid_byte_parses_as_replacement_character_type_selector() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( "\x80" );
		$this->assertNotNull( $result, 'A lone invalid byte should parse as a U+FFFD type selector.' );
	}

	/**
	 * The scrub notice reports the byte replacement, which happens before
	 * parsing — it fires even when the scrubbed selector is then rejected
	 * by the grammar.
	 *
	 * @expectedIncorrectUsage WP_CSS_Compound_Selector_List::from_selectors
	 */
	public function test_invalid_utf8_notice_fires_even_when_selector_is_rejected() {
		$result = WP_CSS_Compound_Selector_List::from_selectors( "\x80 div" );
		$this->assertNull( $result, 'Descendant combinators are unsupported by the compound list; the scrubbed selector should still be rejected.' );
	}
}
