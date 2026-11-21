<?php

/**
 * Tests for valid_unicode().
 *
 * These tests document the helper's historical accept/reject ranges for the
 * XML Char-style check. See the function docblock for caller guidance.
 *
 * @group kses
 *
 * @covers ::valid_unicode
 */
class Tests_Kses_ValidUnicode extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_indicates_if_codepoint_is_valid_xml_char
	 *
	 * @param int|string $codepoint   Code point under the XML Char-style check.
	 * @param bool       $is_xml_char Whether valid_unicode() accepts it as an XML Char.
	 */
	public function test_indicates_if_codepoint_is_valid_xml_char( $codepoint, $is_xml_char ) {
		$this->assertSame( $is_xml_char, valid_unicode( $codepoint ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_indicates_if_codepoint_is_valid_xml_char() {
		return array(
			'horizontal tab'               => array( 0x9, true ),
			'line feed'                    => array( 0xA, true ),
			'carriage return'              => array( 0xD, true ),
			'space'                        => array( 0x20, true ),
			'bmp letter A'                 => array( 0x41, true ),
			'end of bmp before surrogates' => array( 0xD7FF, true ),
			'private use area start'       => array( 0xE000, true ),
			'near end of bmp'              => array( 0xFFFD, true ),
			'supplementary plane start'    => array( 0x10000, true ),
			'max unicode'                  => array( 0x10FFFF, true ),
			'backspace control'            => array( 0x8, false ),
			'vertical tab'                 => array( 0xB, false ),
			'unit separator control'       => array( 0x1F, false ),
			'null'                         => array( 0x0, false ),
			'surrogate start'              => array( 0xD800, false ),
			'surrogate end'                => array( 0xDFFF, false ),
			'noncharacter FFFE'            => array( 0xFFFE, false ),
			'noncharacter FFFF'            => array( 0xFFFF, false ),
			'above unicode max'            => array( 0x110000, false ),
			'negative'                     => array( -1, false ),
			'string codepoint casts'       => array( '65', true ),
		);
	}
}
