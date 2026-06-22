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
	 * Ensures that adjusted foreign attributes are serialized with their namespace prefix.
	 *
	 * @ticket 65372
	 */
	public function test_serializes_adjusted_foreign_attributes_with_namespace_prefix(): void {
		$svg = '<svg><a xlink:actuate="onLoad" xlink:arcrole="arc" xlink:href="#target" xlink:role="role" xlink:show="new" xlink:title="title" xlink:type="simple" xml:lang="en" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"></a></svg>';

		$this->assertSame(
			$svg,
			WP_HTML_Processor::normalize( $svg ),
			'Should have preserved all adjusted foreign attributes when normalizing.'
		);

		$processor = WP_HTML_Processor::create_fragment( $svg );
		$this->assertTrue( $processor->next_token() );
		$this->assertSame( '<svg>', $processor->serialize_token(), 'Should serialize the opening SVG tag.' );
		$this->assertTrue( $processor->next_token() );
		$this->assertSame(
			'<a xlink:actuate="onLoad" xlink:arcrole="arc" xlink:href="#target" xlink:role="role" xlink:show="new" xlink:title="title" xlink:type="simple" xml:lang="en" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">',
			$processor->serialize_token(),
			'Should have serialized all adjusted foreign attributes with their namespace prefixes.'
		);
	}

	/**
	 * Ensures that non-adjusted foreign attributes retain their colon.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_non_adjusted_foreign_attributes_with_colon
	 *
	 * @param string $svg            SVG markup to normalize.
	 * @param string $serialized_tag Expected serialized token.
	 */
	public function test_serializes_non_adjusted_foreign_attributes_with_colon( string $svg, string $serialized_tag ): void {
		$this->assertSame(
			$svg,
			WP_HTML_Processor::normalize( $svg ),
			'Should have preserved non-adjusted colon attributes when normalizing.'
		);

		$processor = WP_HTML_Processor::create_fragment( $svg );
		$this->assertTrue( $processor->next_token() );
		$this->assertSame( '<svg>', $processor->serialize_token(), 'Should serialize the opening SVG tag.' );
		$this->assertTrue( $processor->next_token() );
		$this->assertSame(
			$serialized_tag,
			$processor->serialize_token(),
			'Should have preserved non-adjusted colon attributes when serializing the token.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_non_adjusted_foreign_attributes_with_colon(): array {
		return array(
			'xlink control' => array(
				'<svg><a xlink:author="author" xlink:href="#target"></a></svg>',
				'<a xlink:author="author" xlink:href="#target">',
			),
			'xml control'   => array(
				'<svg><a xml:id="id" xml:lang="en"></a></svg>',
				'<a xml:id="id" xml:lang="en">',
			),
			'xmlns control' => array(
				'<svg><a xmlns:foo="urn:foo" xmlns:xlink="http://www.w3.org/1999/xlink"></a></svg>',
				'<a xmlns:foo="urn:foo" xmlns:xlink="http://www.w3.org/1999/xlink">',
			),
			'source order'  => array(
				'<svg><a foo:bar="baz" xlink:href="#target"></a></svg>',
				'<a foo:bar="baz" xlink:href="#target">',
			),
		);
	}

	/**
	 * Ensures that duplicate foreign attributes are removed upon serialization.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_duplicate_foreign_attributes
	 *
	 * @param string $input    HTML containing duplicate foreign attributes.
	 * @param string $expected Expected normalized HTML.
	 */
	public function test_duplicate_foreign_attributes_are_removed( string $input, string $expected ): void {
		$this->assertSame(
			$expected,
			WP_HTML_Processor::normalize( $input ),
			'Should have removed all but the first copy of a foreign attribute when duplicates exist.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_duplicate_foreign_attributes(): array {
		return array(
			'adjusted xlink duplicate'       => array(
				'<svg><a xlink:href="#first" XLINK:HREF="#second"></a></svg>',
				'<svg><a xlink:href="#first"></a></svg>',
			),
			'adjusted xml duplicate'         => array(
				'<svg><a xml:lang="en" XML:LANG="fr"></a></svg>',
				'<svg><a xml:lang="en"></a></svg>',
			),
			'non-adjusted colon duplicate'   => array(
				'<svg><a foo:bar="one" FOO:BAR="two"></a></svg>',
				'<svg><a foo:bar="one"></a></svg>',
			),
			'adjusted and non-adjusted pair' => array(
				'<svg><a xlink:href="#target" xlink:author="author"></a></svg>',
				'<svg><a xlink:href="#target" xlink:author="author"></a></svg>',
			),
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
			'Should have removed unexpected closing tags.'
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

	/**
	 * @ticket 62396
	 *
	 * @dataProvider data_provider_serialize_doctype
	 */
	public function test_full_document_serialize_includes_doctype( string $doctype_input, string $doctype_output ) {
		$processor = WP_HTML_Processor::create_full_parser(
			"{$doctype_input}👌"
		);
		$this->assertSame(
			"{$doctype_output}<html><head></head><body>👌</body></html>",
			$processor->serialize()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_provider_serialize_doctype() {
		return array(
			'None'                       => array( '', '' ),
			'Empty'                      => array( '<!DOCTYPE>', '<!DOCTYPE>' ),
			'HTML5'                      => array( '<!DOCTYPE html>', '<!DOCTYPE html>' ),
			'Strange name'               => array( '<!DOCTYPE WordPress>', '<!DOCTYPE wordpress>' ),
			'With public'                => array( '<!DOCTYPE html PUBLIC "x">', '<!DOCTYPE html PUBLIC "x">' ),
			'With system'                => array( '<!DOCTYPE html SYSTEM "y">', '<!DOCTYPE html SYSTEM "y">' ),
			'With public and system'     => array( '<!DOCTYPE html PUBLIC "x" "y">', '<!DOCTYPE html PUBLIC "x" "y">' ),
			'Weird casing'               => array( '<!docType HtmL pubLIc\'xxx\'"yyy" all this is ignored>', '<!DOCTYPE html PUBLIC "xxx" "yyy">' ),
			'Single quotes in public ID' => array( '<!DOCTYPE html PUBLIC "\'quoted\'">', '<!DOCTYPE html PUBLIC "\'quoted\'">' ),
			'Double quotes in public ID' => array( '<!DOCTYPE html PUBLIC \'"quoted"\'\>', '<!DOCTYPE html PUBLIC \'"quoted"\'>' ),
			'Single quotes in system ID' => array( '<!DOCTYPE html SYSTEM "\'quoted\'">', '<!DOCTYPE html SYSTEM "\'quoted\'">' ),
			'Double quotes in system ID' => array( '<!DOCTYPE html SYSTEM \'"quoted"\'\>', '<!DOCTYPE html SYSTEM \'"quoted"\'>' ),
		);
	}

	/**
	 * Ensures that leading newlines in PRE, LISTING, and TEXTAREA elements are preserved upon normalization,
	 * and that normalization is idempotent in these cases.
	 *
	 * @ticket 64607
	 *
	 * @dataProvider data_provider_normalize_special_leading_newline_cases
	 *
	 * @param string $input    HTML input containing leading newlines in PRE, LISTING, or TEXTAREA elements.
	 * @param string $expected Expected output after normalization, which should preserve leading newlines.
	 */
	public function test_normalize_special_leading_newline_handling( string $input, string $expected ) {
		$normalized = WP_HTML_Processor::normalize( $input );
		$this->assertEqualHTML( $expected, $normalized );
		$normalized_twice = WP_HTML_Processor::normalize( $normalized );
		$this->assertEqualHTML( $expected, $normalized_twice );
	}

	/**
	 * Ensures that fuzzer-discovered inputs do not emit native PHP errors.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_provider_fuzzer_native_error_cases
	 *
	 * @param string      $input    HTML input.
	 * @param string|null $expected Expected normalized output, or null when unsupported.
	 */
	public function test_normalize_fuzzer_cases_do_not_emit_native_errors( string $input, ?string $expected ) {
		$errors = array();

		/*
		 * This test is checking for native PHP warnings/notices. Unsupported HTML may
		 * intentionally cause wp_trigger_error() under WP_DEBUG, which is separate
		 * from the native errors this regression test is trying to catch.
		 */
		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ) {
				$errors[] = "{$errno}: {$errstr}";
				return true;
			}
		);

		try {
			$normalized = WP_HTML_Processor::normalize( $input );
		} finally {
			restore_error_handler();
			remove_filter( 'wp_trigger_error_trigger_error', '__return_false' );
		}

		// Use assertSame() instead of assertEmpty() so PHPUnit shows captured error messages on failure.
		$this->assertSame( array(), $errors );
		$this->assertSame( $expected, $normalized, 'Should have normalized the input.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_provider_fuzzer_native_error_cases() {
		return array(
			'Unsupported active formatting' => array( '<A><I><A>', null ),
		);
	}

	/**
	 * Ensures that normalized fuzzer-discovered inputs remain supported.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_provider_normalized_fuzzer_cases_that_should_remain_supported
	 *
	 * @param string $input HTML input.
	 */
	public function test_normalized_fuzzer_cases_should_remain_supported( string $input ) {
		$errors = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ) {
				$errors[] = "{$errno}: {$errstr}";
				return true;
			}
		);

		try {
			$normalized       = WP_HTML_Processor::normalize( $input );
			$normalized_twice = is_string( $normalized ) ? WP_HTML_Processor::normalize( $normalized ) : null;
		} finally {
			restore_error_handler();
		}

		// Use assertSame() instead of assertEmpty() so PHPUnit shows captured error messages on failure.
		$this->assertSame( array(), $errors );
		$this->assertIsString( $normalized, 'Input HTML should normalize successfully.' );
		$this->assertIsString(
			$normalized_twice,
			'Normalized HTML should remain supported by the HTML Processor.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_provider_normalized_fuzzer_cases_that_should_remain_supported() {
		return array(
			'Unclosed SVG TITLE after P in EM'     => array( '<em><p><svg><title>' ),
			'Unclosed SVG TITLE after P in STRONG' => array( '<strong><p><svg ><title>' ),
		);
	}

	/**
	 * Ensures that normalized fuzzer-discovered inputs are idempotent.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_provider_normalized_fuzzer_cases_that_should_be_idempotent
	 *
	 * @param string $input HTML input.
	 */
	public function test_normalized_fuzzer_cases_should_be_idempotent( string $input ) {
		$errors = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ) {
				$errors[] = "{$errno}: {$errstr}";
				return true;
			}
		);

		try {
			$normalized       = WP_HTML_Processor::normalize( $input );
			$normalized_twice = is_string( $normalized ) ? WP_HTML_Processor::normalize( $normalized ) : null;
		} finally {
			restore_error_handler();
		}

		// Use assertSame() instead of assertEmpty() so PHPUnit shows captured error messages on failure.
		$this->assertSame( array(), $errors );
		$this->assertIsString( $normalized, 'Input HTML should normalize successfully.' );
		$this->assertSame(
			$normalized,
			$normalized_twice,
			'Normalizing already-normalized HTML should not change it.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_provider_normalized_fuzzer_cases_that_should_be_idempotent() {
		return array(
			'Malformed quoted attribute boundary'       => array( '<A "/=>' ),
			'Duplicate attribute after bare attribute'  => array( '<A V=5 R V=""=>' ),
			'Duplicate DATA-ID after numeric attribute' => array( '<E DATA-ID=1 1 DATA-ID=""=>' ),
			'Duplicate attribute before tag end'        => array( '<R V=5 R V=5 =>' ),
			'NULL byte in foreign tag name'             => array( "<SVG><L\x00 D>" ),
			'Malformed closing-looking attribute'       => array( '<a </=>' ),
			'Malformed self-closing attribute'          => array( '<a h/=>' ),
			'Duplicate ID with quote boundary'          => array( '<d ID=""" ID=""=>' ),
			'Mixed-case duplicate TITLE'                => array( "<d TITLE=\"\"' title=\"\"=>" ),
			'Colon before self-closing slash'           => array( '<e :/=>' ),
			'Duplicate class after bare attribute'      => array( "<e class=y d class=''=>" ),
			'Duplicate DATA-ID after hyphen'            => array( '<e data-id=1 - data-id="">' ),
			'Duplicate title after quotes'              => array( "<e title=''' title=\"\"=>" ),
			'FORM with SVG TITLE text edge'             => array( "<form ><svg ><title \"'></form><form>" ),
			'FORM with TABLE and SCRIPT'                => array( '<form id><table te"><script></script><td srce" ID/></form><form claslicate">' ),
			'FORM with TABLE CAPTION'                   => array( '<form><table><caption></form><form >' ),
			'Short malformed G attribute C'             => array( '<g c/=>' ),
			'Short malformed G attribute S'             => array( '<g s/=>' ),
			'Duplicate SRC boundary'                    => array( '<g src=""g src="">' ),
			'Short malformed H attribute'               => array( '<h f/=>' ),
			'Malformed SRC equals boundary'             => array( '<i src=""= src=""=">' ),
			'Malformed slash in tag opener'             => array( '<i/t/=>' ),
			'Malformed L colon attribute'               => array( '<l :/=>' ),
			'Malformed L less-than attribute'           => array( '<l/</=>' ),
			'Malformed N less-than attribute'           => array( '<n </=>' ),
			'Unclosed SVG TITLE after P'                => array( '<p><svg><title>' ),
			'Duplicate ALT boundary'                    => array( '<r alt=\'\'d alt=""=>' ),
			'NULL byte in SVG child tag'                => array( "<svg><l\x00 '>" ),
			'NULL byte before slash in SVG child tag'   => array( "<svg><l\x00/r>" ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_provider_normalize_special_leading_newline_cases() {
		return array(
			'Leading newline in PRE'             => array(
				"<pre>\nline 1\nline 2</pre>",
				"<pre>line 1\nline 2</pre>",
			),
			'Double leading newline in PRE'      => array(
				"<pre>\n\nline 2\nline 3</pre>",
				"<pre>\n\nline 2\nline 3</pre>",
			),
			'Multiple text nodes inside PRE'     => array(
				"<pre>\nline 1<!--comment--> still line 1</pre>",
				'<pre>line 1<!--comment--> still line 1</pre>',
			),
			'Multiple text nodes inside PRE with leading newlines' => array(
				"<pre>\n\nline 2<!--comment--> still line 2</pre>",
				"<pre>\n\nline 2<!--comment--> still line 2</pre>",
			),
			'Leading newline in LISTING'         => array(
				"<listing>\nline 1\nline 2</listing>",
				"<listing>line 1\nline 2</listing>",
			),
			'Double leading newline in LISTING'  => array(
				"<listing>\n\nline 2\nline 3</listing>",
				"<listing>\n\nline 2\nline 3</listing>",
			),
			'Multiple text nodes inside LISTING' => array(
				"<listing>\nline 1<!--comment--> still line 1</listing>",
				'<listing>line 1<!--comment--> still line 1</listing>',
			),
			'Multiple text nodes inside LISTING with leading newlines' => array(
				"<listing>\n\nline 2<!--comment--> still line 2</listing>",
				"<listing>\n\nline 2<!--comment--> still line 2</listing>",
			),
			'Leading newline in TEXTAREA'        => array(
				"<textarea>\nline 1\nline 2</textarea>",
				"<textarea>line 1\nline 2</textarea>",
			),
			'Double leading newline in TEXTAREA' => array(
				"<textarea>\n\nline 2\nline 3</textarea>",
				"<textarea>\n\nline 2\nline 3</textarea>",
			),
		);
	}
}
