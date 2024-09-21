<?php
/**
 * Unit tests covering WP_HTML_Processor serialization functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.7.0
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor_Serialize extends WP_UnitTestCase {
	/**
	 * Ensures that basic text is properly encoded when serialized.
	 *
	 * @ticket 62036
	 */
	public function test_properly_encodes_text() {
		$this->assertSame(
			WP_HTML_Processor::normalize( "apples > or\x00anges" ),
			'apples &gt; oranges',
			'Should have returned an HTML string with applicable characters properly encoded.'
		);
	}

	/**
	 * Ensures that unclosed elements are explicitly closed to ensure proper HTML isolation.
	 *
	 * When thinking about embedding HTML fragments into others, it's important that unclosed
	 * elements aren't left dangling, otherwise a snippet of HTML may "swallow" parts of the
	 * document that follow it.
	 *
	 * @ticket 62036
	 */
	public function test_closes_unclosed_elements_at_end() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<div>' ),
			'<div></div>',
			'Should have provided the explicit closer to the un-closed DIV element.'
		);
	}

	/**
	 * Ensures that boolean attributes remain boolean and do not gain values.
	 *
	 * @ticket 62036
	 */
	public function test_boolean_attributes_remain_boolean() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<input disabled>' ),
			'<input disabled>',
			'Should have preserved the boolean attribute upon serialization.'
		);
	}

	/**
	 * Ensures that attributes with values result in double-quoted attribute values.
	 *
	 * @ticket 62036
	 */
	public function test_attributes_are_double_quoted() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<p id=3></p>' ),
			'<p id="3"></p>',
			'Should double-quote all attribute values.'
		);
	}

	/**
	 * Ensures that self-closing flags on HTML void elements are not serialized, to
	 * prevent risk of conflating the flag with unquoted attribute values.
	 *
	 * Example:
	 *
	 *     BR element with "class" attribute having value "clear"
	 *     <br class="clear"/>
	 *
	 *     BR element with "class" attribute having value "clear"
	 *     <br class=clear />
	 *
	 *     BR element with "class" attribute having value "clear/"
	 *     <br class=clear/>
	 *
	 * @ticket 62036
	 */
	public function test_void_elements_get_no_dangerous_self_closing_flag() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<br class="clear"/>' ),
			'<br class="clear">',
			'Should have removed dangerous self-closing flag on HTML void element.'
		);
	}

	/**
	 * Ensures that duplicate attributes are removed upon serialization.
	 *
	 * @ticket 62036
	 */
	public function test_duplicate_attributes_are_removed() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<div one=1 one="one" one=\'won\' one>' ),
			'<div one="1"></div>',
			'Should have removed all but the first copy of an attribute when duplicates exist.'
		);
	}

	/**
	 * Ensures that SCRIPT contents are not escaped, as they are not parsed like text nodes are.
	 *
	 * @ticket 62036
	 */
	public function test_script_contents_are_not_escaped() {
		$this->assertSame(
			WP_HTML_Processor::normalize( "<script>apples > or\x00anges</script>" ),
			"<script>apples > or\u{FFFD}anges</script>",
			'Should have preserved text inside a SCRIPT element, except for replacing NULL bytes.'
		);
	}

	/**
	 * Ensures that STYLE contents are not escaped, as they are not parsed like text nodes are.
	 *
	 * @ticket 62036
	 */
	public function test_style_contents_are_not_escaped() {
		$this->assertSame(
			WP_HTML_Processor::normalize( "<style>apples > or\x00anges</style>" ),
			"<style>apples > or\u{FFFD}anges</style>",
			'Should have preserved text inside a STYLE element, except for replacing NULL bytes.'
		);
	}

	public function test_unexpected_closing_tags_are_removed() {
		$this->assertSame(
			WP_HTML_Processor::normalize( 'one</div>two</span>three' ),
			'onetwothree',
			'Should have removed unpected closing tags.'
		);
	}

	/**
	 * Ensures that self-closing elements in foreign content retain their self-closing flag.
	 *
	 * @ticket 62036
	 */
	public function test_self_closing_foreign_elements_retain_their_self_closing_flag() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<svg><g><g /></svg>' ),
			'<svg><g><g /></g></svg>',
			'Should have closed unclosed G element, but preserved the self-closing nature of the other G element.'
		);
	}

	/**
	 * Ensures that incomplete syntax elements at the end of an HTML string are removed from
	 * the serialization, since these are often vectors of exploits for the successive HTML.
	 *
	 * @ticket 62036
	 *
	 * @dataProvider data_incomplete_syntax_tokens
	 *
	 * @param string $incomplete_token An incomplete HTML syntax token.
	 */
	public function test_should_remove_incomplete_input_from_end( string $incomplete_token ) {
		$this->assertSame(
			WP_HTML_Processor::normalize( "content{$incomplete_token}" ),
			'content',
			'Should have removed the incomplete token from the end of the input.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_syntax_tokens() {
		return array(
			'Comment opener'       => array( '<!--' ),
			'Bogus comment opener' => array( '<![sneaky[' ),
			'Incomplete tag'       => array( '<my-custom status="pending"' ),
			'SCRIPT opening tag'   => array( '<script>' ),
		);
	}

	/**
	 * Ensures that presumptuous tag openers are treated as plaintext.
	 *
	 * @ticket 62036
	 */
	public function test_encodes_presumptuous_opening_tags() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '<>' ),
			'&lt;&gt;',
			'Should have encoded the invalid presumptuous opening tag as plaintext.'
		);
	}

	/**
	 * Ensures that presumptuous tag closers are skipped in serialization.
	 *
	 * @ticket 62036
	 */
	public function test_skips_presumptuous_closing_tags() {
		$this->assertSame(
			WP_HTML_Processor::normalize( '</>' ),
			'',
			'Should have completely ignored the presumptuous tag closer.'
		);
	}

	/**
	 * Ensures that invalid or "bogus" comments in HTML are normalized to their proper normative form.
	 *
	 * @ticket 62036
	 *
	 * @dataProvider data_bogus_comments
	 *
	 * @param string $opening      Start of bogus comment, e.g. "<!".
	 * @param string $comment_text Comment content, as reported in a browser.
	 * @param string $closing      End of bogus comment, e.g. ">".
	 */
	public function test_normalizes_bogus_comment_forms( string $opening, string $comment_text, string $closing ) {
		$this->assertSame(
			WP_HTML_Processor::normalize( "{$opening}{$comment_text}{$closing}" ),
			"<!--{$comment_text}-->",
			'Should have replaced the invalid comment syntax with normative syntax.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_bogus_comments() {
		return array(
			'False DOCTYPE'                         => array( '<!', 'html', '>' ),
			'CDATA look-alike'                      => array( '<!', '[CDATA[inside]]', '>' ),
			'Immediately-closed markup instruction' => array( '<!', '?', '>' ),
			'Warning Symbol'                        => array( '<!', '', '>' ),
			'PHP block look-alike'                  => array( '<', '?php foo(); ?', '>' ),
			'Funky comment'                         => array( '</', '%display-name', '>' ),
			'XML Processing Instruction look-alike' => array( '<', '?xml foo ', '>' ),
		);
	}

	/**
	 * Ensures that NULL bytes are properly handled.
	 *
	 * @ticket 62036
	 *
	 * @dataProvider data_tokens_with_null_bytes
	 *
	 * @param string $html_with_nulls HTML token containing NULL bytes in various places.
	 * @param string $expected_output Expected parse of HTML after handling NULL bytes.
	 */
	public function test_replaces_null_bytes_appropriately( string $html_with_nulls, string $expected_output ) {
		$this->assertSame(
			WP_HTML_Processor::normalize( $html_with_nulls ),
			$expected_output,
			'Should have properly replaced or removed NULL bytes.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tokens_with_null_bytes() {
		return array(
			'Tag name'             => array( "<img\x00id=5>", "<img\u{FFFD}id=5></img\u{FFFD}id=5>" ),
			'Attribute name'       => array( "<img/\x00id=5>", "<img \u{FFFD}id=\"5\">" ),
			'Attribute value'      => array( "<img id='5\x00'>", "<img id=\"5\u{FFFD}\">" ),
			'Body text'            => array( "one\x00two", 'onetwo' ),
			'Foreign content text' => array( "<svg>one\x00two</svg>", "<svg>one\u{FFFD}two</svg>" ),
			'SCRIPT content'       => array( "<script>alert(\x00)</script>", "<script>alert(\u{FFFD})</script>" ),
			'STYLE content'        => array( "<style>\x00 {}</style>", "<style>\u{FFFD} {}</style>" ),
			'Comment text'         => array( "<!-- \x00 -->", "<!-- \u{FFFD} -->" ),
		);
	}
}
