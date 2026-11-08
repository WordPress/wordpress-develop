<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor input-stream preprocessing
 * at its read boundaries.
 *
 * The HTML specification's "preprocessing the input stream" step (newline
 * normalization) and the tokenizer's U+0000 NULL replacements are deferred
 * by the Tag Processor while scanning and must be applied wherever parsed
 * values are read out of the input document.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 7.1.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlTagProcessor_InputPreprocessing extends WP_UnitTestCase {
	/**
	 * Ensures that `get_attribute()` applies input-stream preprocessing and
	 * tokenizer replacements to attribute values found in the input document.
	 *
	 * Newlines are normalized (CRLF → LF, CR → LF) and U+0000 NULL is replaced
	 * with U+FFFD before character references decode, so `&#13;` produces a
	 * real carriage return and `&#0;` produces U+FFFD. Browser-verified.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 *
	 * @dataProvider data_attribute_values_with_preprocessing
	 *
	 * @param string $html     HTML containing a tag with attribute `a`.
	 * @param string $expected Expected attribute value after preprocessing and decoding.
	 */
	public function test_get_attribute_applies_input_preprocessing( string $html, string $expected ): void {
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( $expected, $processor->get_attribute( 'a' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function data_attribute_values_with_preprocessing(): array {
		return array(
			'Raw CR'                      => array( "<div a='x\ry'>", "x\ny" ),
			'Raw CRLF'                    => array( "<div a='x\r\ny'>", "x\ny" ),
			'Raw CR then CRLF'            => array( "<div a='x\r\r\ny'>", "x\n\ny" ),
			'Double-quoted raw CR'        => array( "<div a=\"x\ry\">", "x\ny" ),
			'NULL byte'                   => array( "<div a='x\x00y'>", "x\u{FFFD}y" ),
			'NULL byte unquoted'          => array( "<div a=x\x00y>", "x\u{FFFD}y" ),
			'Encoded CR is preserved'     => array( "<div a='x&#13;y'>", "x\ry" ),
			'Encoded NULL becomes U+FFFD' => array( "<div a='x&#0;y'>", "x\u{FFFD}y" ),
			'Raw CR before encoded CR'    => array( "<div a='x\r&#13;y'>", "x\n\ry" ),
			'Raw CR and NULL byte'        => array( "<div a='x\r\x00y'>", "x\n\u{FFFD}y" ),
			'Named reference before NULL' => array( "<div a='x&amp\x00;y'>", "x&\u{FFFD};y" ),
			'Named reference before CR'   => array( "<div a='x&amp\ry'>", "x&\ny" ),
		);
	}

	/**
	 * Ensures that values enqueued through `set_attribute()` are returned verbatim.
	 *
	 * Input-stream preprocessing applies only to the input document. API-supplied
	 * values are plaintext, equivalent to DOM `setAttribute()`, which performs
	 * no replacements. Browser-verified.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 *
	 * @dataProvider data_enqueued_attribute_values
	 *
	 * @param string $value Plaintext attribute value to set and expect back unchanged.
	 */
	public function test_get_attribute_returns_enqueued_values_verbatim( string $value ): void {
		$processor = new WP_HTML_Tag_Processor( '<div a="original">' );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->set_attribute( 'a', $value ), 'Should have enqueued the attribute update.' );
		$this->assertSame( $value, $processor->get_attribute( 'a' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_enqueued_attribute_values(): array {
		return array(
			'Carriage return' => array( "x\ry" ),
			'CRLF'            => array( "x\r\ny" ),
			'NULL byte'       => array( "x\x00y" ),
		);
	}

	/**
	 * Ensures the existing class attribute value is preprocessed when enqueued
	 * class updates are flushed into an attribute update.
	 *
	 * @ticket 65372
	 *
	 * @covers ::add_class
	 *
	 * @dataProvider data_class_updates_with_preprocessing
	 *
	 * @param string $html          HTML containing a tag with a class attribute.
	 * @param string $expected_html Expected document after adding a class.
	 */
	public function test_class_updates_apply_input_preprocessing_to_existing_value( string $html, string $expected_html ): void {
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->add_class( 'added' ), 'Should have enqueued the class addition.' );
		$this->assertSame( $expected_html, $processor->get_updated_html() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function data_class_updates_with_preprocessing(): array {
		return array(
			'Raw CR'                      => array( "<div class='a\rb'>", "<div class=\"a\nb added\">" ),
			'Raw CRLF'                    => array( "<div class='a\r\nb'>", "<div class=\"a\nb added\">" ),
			'NULL byte'                   => array( "<div class='a\x00b'>", "<div class=\"a\u{FFFD}b added\">" ),
			'Named reference before NULL' => array( "<div class='&not\x00x'>", "<div class=\"\u{AC}\u{FFFD}x added\">" ),
		);
	}

	/**
	 * Ensures attribute names containing NULL bytes are exposed with U+FFFD and
	 * are addressable only by their replaced name, as browsers expose them.
	 *
	 * Browser-verified: `getAttribute("da\u{FFFD}ta")` finds the attribute
	 * parsed from `da\x00ta`; `getAttribute("da\x00ta")` does not.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 * @covers ::get_attribute_names_with_prefix
	 */
	public function test_attribute_names_replace_null_bytes(): void {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta='1'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( array( "da\u{FFFD}ta" ), $processor->get_attribute_names_with_prefix( '' ) );
		$this->assertSame( '1', $processor->get_attribute( "da\u{FFFD}ta" ), 'Should have found the attribute by its replaced name.' );
		$this->assertNull( $processor->get_attribute( "da\x00ta" ), 'Should not have found the attribute by its raw source name.' );

		$processor = new WP_HTML_Tag_Processor( "<div DA\x00TA='1'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( array( "da\u{FFFD}ta" ), $processor->get_attribute_names_with_prefix( '' ), 'Should have lowercased the name around the replacement character.' );
	}

	/**
	 * Ensures attribute names which collapse to the same name after NULL-byte
	 * replacement are duplicates of one attribute: the first in document order
	 * provides the value and removal removes every collapsed copy.
	 *
	 * Browser-verified: `<div da\x00ta="1" da\u{FFFD}ta="2">` produces a single
	 * attribute `da\u{FFFD}ta` with value "1".
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 * @covers ::remove_attribute
	 */
	public function test_attribute_names_collapsing_after_null_replacement_are_duplicates(): void {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta='1' da\u{FFFD}ta='2'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( array( "da\u{FFFD}ta" ), $processor->get_attribute_names_with_prefix( '' ) );
		$this->assertSame( '1', $processor->get_attribute( "da\u{FFFD}ta" ), 'First duplicate should provide the value.' );

		$this->assertTrue( $processor->remove_attribute( "da\u{FFFD}ta" ), 'Should have removed the attribute.' );
		$this->assertSame( '<div  >', $processor->get_updated_html(), 'Should have removed all duplicates of the attribute.' );
	}

	/**
	 * Ensures setting an attribute by its U+FFFD-replaced name updates the
	 * source attribute whose raw name contains a NULL byte instead of adding
	 * a second attribute.
	 *
	 * @ticket 65372
	 *
	 * @covers ::set_attribute
	 */
	public function test_set_attribute_updates_attribute_with_null_byte_in_source_name(): void {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta='old'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->set_attribute( "da\u{FFFD}ta", 'new' ), 'Should have set the attribute.' );
		$this->assertSame( "<div da\u{FFFD}ta=\"new\">", $processor->get_updated_html() );
	}

	/**
	 * Ensures tag names containing NULL bytes are exposed with U+FFFD,
	 * matching the tokenizer's tag-name-state replacement in browsers.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_tag
	 * @covers ::get_token_name
	 */
	public function test_get_tag_replaces_null_bytes(): void {
		$processor = new WP_HTML_Tag_Processor( "<di\x00v>x</di\x00v>" );

		$this->assertTrue( $processor->next_token(), 'Should have found the tag opener.' );
		$this->assertSame( "DI\u{FFFD}V", $processor->get_tag() );
		$this->assertSame( "DI\u{FFFD}V", $processor->get_token_name() );

		$this->assertTrue( $processor->next_token(), 'Should have found the text node.' );
		$this->assertSame( 'x', $processor->get_modifiable_text() );

		$this->assertTrue( $processor->next_token(), 'Should have found the tag closer.' );
		$this->assertTrue( $processor->is_tag_closer(), 'Should have matched the tag closer.' );
		$this->assertSame( "DI\u{FFFD}V", $processor->get_tag() );
	}

	/**
	 * Ensures NULL bytes in tag names do not affect special-element detection:
	 * `<scr\x00ipt>` is not SCRIPT and does not switch into rawtext parsing,
	 * in browsers or here. Internal identification uses raw bytes.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_tag
	 */
	public function test_null_byte_in_tag_name_does_not_select_rawtext_parsing(): void {
		$processor = new WP_HTML_Tag_Processor( "<scr\x00ipt><b></b></scr\x00ipt>" );

		$this->assertTrue( $processor->next_token(), 'Should have found the tag opener.' );
		$this->assertSame( "SCR\u{FFFD}IPT", $processor->get_tag() );

		$this->assertTrue( $processor->next_token(), 'Should have found the B tag, not raw text.' );
		$this->assertSame( 'B', $processor->get_tag() );
	}

	/**
	 * Ensures NULL bytes cannot appear in PI-lookalike comment tag names,
	 * whose targets are restricted to ASCII name characters.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_tag
	 */
	public function test_pi_lookalike_target_stops_before_null_byte(): void {
		$processor = new WP_HTML_Tag_Processor( "<?px\x00rest ?>" );

		$this->assertTrue( $processor->next_token(), 'Should have found the comment.' );
		$this->assertSame( WP_HTML_Tag_Processor::COMMENT_AS_PI_NODE_LOOKALIKE, $processor->get_comment_type() );
		$this->assertSame( 'px', $processor->get_tag() );
	}

	/**
	 * Ensures tag-name queries match in the same replaced alphabet that
	 * `get_tag()` exposes: a sought name containing U+FFFD matches source
	 * names whose raw bytes contain NULL in its place, a sought name
	 * containing a raw NULL byte matches nothing, and the value returned
	 * by `get_tag()` round-trips into a successful query.
	 *
	 * This is also how WP_HTML_Processor::next_tag() matches, since it
	 * compares sought names against the token name.
	 *
	 * @ticket 65372
	 *
	 * @covers ::next_tag
	 */
	public function test_tag_name_queries_match_replaced_names(): void {
		$processor = new WP_HTML_Tag_Processor( "<di\x00v>" );
		$this->assertTrue( $processor->next_tag( "DI\u{FFFD}V" ), 'Should have matched the tag by its replaced name.' );

		$processor = new WP_HTML_Tag_Processor( "<di\x00v>" );
		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$tag_name  = $processor->get_tag();
		$processor = new WP_HTML_Tag_Processor( "<di\x00v>" );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => $tag_name ) ), 'The name returned by get_tag() should match in a query.' );

		$processor = new WP_HTML_Tag_Processor( "<di\x00v>" );
		$this->assertFalse( $processor->next_tag( "DI\x00V" ), 'Should not have matched the tag by its raw source name.' );

		$processor = new WP_HTML_Tag_Processor( "<di\u{FFFD}v>" );
		$this->assertTrue( $processor->next_tag( "DI\u{FFFD}V" ), 'Should have matched a raw U+FFFD name.' );

		$processor = WP_HTML_Processor::create_full_parser( "<body><di\x00v>" );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => "DI\u{FFFD}V" ) ), 'The HTML Processor should match the replaced name.' );

		$processor = WP_HTML_Processor::create_full_parser( "<body><di\x00v>" );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => "DI\x00V" ) ), 'The HTML Processor should not match the raw source name.' );
	}

	/**
	 * Ensures class_list does not replace NULL bytes in API-supplied values.
	 *
	 * Browser-verified: `setAttribute('class', "a\x00b")` then reading
	 * `classList` yields the token "a\x00b" with the NULL byte preserved;
	 * U+0000 replacement happens only in the tokenizer, and values from the
	 * input document already receive it through `get_attribute()`.
	 *
	 * @ticket 65372
	 *
	 * @covers ::class_list
	 * @covers ::has_class
	 */
	public function test_class_list_preserves_null_bytes_in_enqueued_values(): void {
		$processor = new WP_HTML_Tag_Processor( '<div>' );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->set_attribute( 'class', "a\x00b c\u{FFFD}d" ), 'Should have set the class attribute.' );
		$this->assertSame( array( "a\x00b", "c\u{FFFD}d" ), iterator_to_array( $processor->class_list(), false ), 'Should have preserved the NULL byte in the API-supplied class.' );
		$this->assertTrue( $processor->has_class( "a\x00b" ) );
	}

	/**
	 * Ensures the class helpers operate on the replaced source value:
	 * a class containing a NULL byte in the document is exposed, matched,
	 * and queried by its U+FFFD spelling only.
	 *
	 * @ticket 65372
	 *
	 * @covers ::class_list
	 * @covers ::has_class
	 * @covers ::next_tag
	 */
	public function test_class_helpers_use_replaced_source_values(): void {
		$processor = new WP_HTML_Tag_Processor( "<div class='a\x00b'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( array( "a\u{FFFD}b" ), iterator_to_array( $processor->class_list(), false ), 'Should have exposed the replaced class name.' );
		$this->assertTrue( $processor->has_class( "a\u{FFFD}b" ), 'Should have matched the replaced class name.' );
		$this->assertFalse( $processor->has_class( "a\x00b" ), 'Should not have matched the raw source class name.' );

		$processor = new WP_HTML_Tag_Processor( "<div class='a\x00b'>" );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => "a\u{FFFD}b" ) ), 'Should have matched a class_name query by the replaced name.' );
	}

	/**
	 * Ensures boolean attributes whose names contain NULL bytes are
	 * addressable by their replaced name.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 */
	public function test_boolean_attribute_with_null_byte_in_name(): void {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->get_attribute( "da\u{FFFD}ta" ), 'Should have reported the boolean attribute by its replaced name.' );
	}

	/**
	 * Ensures attribute-name prefixes are matched verbatim against the
	 * replaced names: a prefix spelled with U+FFFD matches, and a prefix
	 * containing a raw NULL byte matches nothing.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute_names_with_prefix
	 */
	public function test_attribute_name_prefixes_match_replaced_names(): void {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta='1'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( array( "da\u{FFFD}ta" ), $processor->get_attribute_names_with_prefix( "da\u{FFFD}" ), 'A replaced-name prefix should match.' );
		$this->assertSame( array(), $processor->get_attribute_names_with_prefix( "da\x00" ), 'A raw NULL prefix should match nothing.' );
	}

	/**
	 * Ensures the replaced tag names flow through HTML Processor tree
	 * construction: an end tag spelled with U+FFFD closes an element
	 * whose start tag was spelled with a raw NULL byte, as in browsers,
	 * where both spellings tokenize to the same name.
	 *
	 * @ticket 65372
	 */
	public function test_html_processor_matches_end_tags_across_null_byte_spellings(): void {
		$this->assertSame(
			"<di\u{FFFD}v>x</di\u{FFFD}v>y",
			WP_HTML_Processor::normalize( "<di\x00v>x</di\u{FFFD}v>y" ),
			'The U+FFFD-spelled end tag should have closed the NULL-spelled element.'
		);

		$processor = WP_HTML_Processor::create_full_parser( "<body><di\x00v>x</di\u{FFFD}v>y" );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => "DI\u{FFFD}V" ) ), 'Should have found the element by its replaced name.' );
		$this->assertSame( array( 'HTML', 'BODY', "DI\u{FFFD}V" ), $processor->get_breadcrumbs(), 'Should have built breadcrumbs from replaced names.' );
	}

	/**
	 * Ensures pending class updates are flushed for any case spelling of
	 * the "class" attribute name, since attribute names are matched
	 * ASCII-case-insensitively.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_attribute
	 */
	public function test_get_attribute_flushes_class_updates_case_insensitively(): void {
		$processor = new WP_HTML_Tag_Processor( '<div class="a">' );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->add_class( 'b' ), 'Should have enqueued the class addition.' );
		$this->assertSame( 'a b', $processor->get_attribute( 'CLASS' ), 'Should have included pending class updates for an uppercase lookup.' );
	}

	/**
	 * Ensures numeric character references for U+0000 decode to U+FFFD in text.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_modifiable_text
	 */
	public function test_encoded_null_in_text_node_decodes_to_replacement_character(): void {
		$processor = new WP_HTML_Tag_Processor( 'a&#0;b' );

		$this->assertTrue( $processor->next_token(), 'Should have found the text node.' );
		$this->assertSame( "a\u{FFFD}b", $processor->get_modifiable_text() );
	}
}
