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
	public function test_get_attribute_applies_input_preprocessing( string $html, string $expected ) {
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertSame( $expected, $processor->get_attribute( 'a' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attribute_values_with_preprocessing() {
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
	public function test_get_attribute_returns_enqueued_values_verbatim( string $value ) {
		$processor = new WP_HTML_Tag_Processor( '<div a="original">' );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->set_attribute( 'a', $value ), 'Should have enqueued the attribute update.' );
		$this->assertSame( $value, $processor->get_attribute( 'a' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_enqueued_attribute_values() {
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
	public function test_class_updates_apply_input_preprocessing_to_existing_value( string $html, string $expected_html ) {
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->add_class( 'added' ), 'Should have enqueued the class addition.' );
		$this->assertSame( $expected_html, $processor->get_updated_html() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_class_updates_with_preprocessing() {
		return array(
			'Raw CR'    => array( "<div class='a\rb'>", "<div class=\"a\nb added\">" ),
			'Raw CRLF'  => array( "<div class='a\r\nb'>", "<div class=\"a\nb added\">" ),
			'NULL byte' => array( "<div class='a\x00b'>", "<div class=\"a\u{FFFD}b added\">" ),
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
	public function test_attribute_names_replace_null_bytes() {
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
	public function test_attribute_names_collapsing_after_null_replacement_are_duplicates() {
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
	public function test_set_attribute_updates_attribute_with_null_byte_in_source_name() {
		$processor = new WP_HTML_Tag_Processor( "<div da\x00ta='old'>" );

		$this->assertTrue( $processor->next_tag(), 'Should have found the tag.' );
		$this->assertTrue( $processor->set_attribute( "da\u{FFFD}ta", 'new' ), 'Should have set the attribute.' );
		$this->assertSame( "<div da\u{FFFD}ta=\"new\">", $processor->get_updated_html() );
	}

	/**
	 * Ensures numeric character references for U+0000 decode to U+FFFD in text.
	 *
	 * @ticket 65372
	 *
	 * @covers ::get_modifiable_text
	 */
	public function test_encoded_null_in_text_node_decodes_to_replacement_character() {
		$processor = new WP_HTML_Tag_Processor( 'a&#0;b' );

		$this->assertTrue( $processor->next_token(), 'Should have found the text node.' );
		$this->assertSame( "a\u{FFFD}b", $processor->get_modifiable_text() );
	}
}
