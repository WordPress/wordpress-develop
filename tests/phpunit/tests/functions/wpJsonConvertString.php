<?php

/**
 * @group functions
 *
 * @covers ::_wp_json_convert_string
 */
class Tests_Functions_WpJsonConvertString extends WP_UnitTestCase {

	/**
	 * Test valid UTF-8 strings.
	 *
	 * @dataProvider data_should_return_given_string_when_valid
	 *
	 * @param string $value The valid UTF-8 string.
	 */
	public function test_should_return_given_string_when_valid( $value ) {
		$this->assertSame( $value, _wp_json_convert_string( $value ) );
	}

	/**
	 * Data provider for valid UTF-8 strings.
	 *
	 * @return array[]
	 */
	public function data_should_return_given_string_when_valid() {
		return array(
			'standard greeting'     => array( 'Hello, World!' ),
			'japanese greeting'     => array( 'こんにちは' ),
			'russian greeting'      => array( 'Привет мир' ),
			'accented character'    => array( 'Café' ),
			'emoji'                 => array( '✅' ),
			'valid string encoding' => array( mb_convert_encoding( 'Valid string', 'UTF-8' ) ),
		);
	}

	/**
	 * Test empty string input.
	 */
	public function test_should_return_empty_string_when_input_is_empty() {
		$this->assertSame( '', _wp_json_convert_string( '' ) );
	}

	/**
	 * Test mixed valid and invalid UTF-8 strings.
	 *
	 * @dataProvider data_should_return_mixed_valid_invalid_utf8
	 *
	 * @param string $value The mixed UTF-8 string.
	 * @param string $expected The expected result after processing.
	 */
	public function test_should_return_result_when_mixed( $value, $expected ) {
		$this->assertSame( $expected, _wp_json_convert_string( $value ) );
	}

	/**
	 * Data provider for mixed valid and invalid UTF-8 strings.
	 *
	 * @return array[]
	 */
	public function data_should_return_mixed_valid_invalid_utf8() {
		return array(
			'mixed invalid byte sequence'      => array(
				'Valid text ' . "\xC3\x28" . ' more text',
				'Valid text ?( more text',
			),
			'mixed improperly formed sequence' => array(
				'Valid 𐀀 and invalid ' . "\xE0\xA4\xA0\xA0",
				'Valid 𐀀 and invalid ठ?',
			),
			'mixed another invalid sequence'   => array(
				'Valid 𐀀 mixed with invalid ' . "\xC0\xAF",
				'Valid 𐀀 mixed with invalid ??',
			),
		);
	}
}
