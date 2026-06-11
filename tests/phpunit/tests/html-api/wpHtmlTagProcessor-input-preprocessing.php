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
