<?php

/**
 * @group formatting
 *
 * @covers ::wp_check_invalid_utf8
 */
class Tests_Formatting_wpCheckInvalidUtf8 extends WP_UnitTestCase {

	/**
	 * Test valid UTF-8 strings without stripping.
	 *
	 * @dataProvider data_should_return_given_string_when_valid_without_stripping
	 *
	 * @param string $value The valid UTF-8 string.
	 */
	public function test_should_return_given_string_when_valid_without_stripping( $value ) {
		$this->assertSame( $value, wp_check_invalid_utf8( $value ) );
	}

	/**
	 * Data provider for valid UTF-8 strings.
	 *
	 * @return array[]
	 */
	public function data_should_return_given_string_when_valid_without_stripping() {
		return array(
			'standard greeting'     => array( 'Hello, world!' ),
			'japanese greeting'     => array( 'こんにちは' ),
			'russian greeting'      => array( 'Привет мир' ),
			'accented character'    => array( 'Café' ),
			'emoji'                 => array( '✅' ),
			'valid string encoding' => array( mb_convert_encoding( 'Valid string', 'UTF-8' ) ),
		);
	}

	/**
	 * Test invalid UTF-8 strings without stripping.
	 *
	 * @dataProvider data_should_return_empty_string_when_invalid_without_stripping
	 *
	 * @param string $value The invalid UTF-8 string.
	 */
	public function test_should_return_empty_string_when_invalid_without_stripping( $value ) {
		$this->assertSame( '', wp_check_invalid_utf8( $value ) );
	}

	/**
	 * Data provider for invalid UTF-8 strings.
	 *
	 * @return array[]
	 */
	public function data_should_return_empty_string_when_invalid_without_stripping() {
		return array(
			'invalid byte sequence'      => array( "\xC3\x28" ),
			'improperly formed sequence' => array( "\xE0\xA4\xA0\xA0" ),
			'invalid continuation byte'  => array( "\xC0\xAF" ),
		);
	}

	/**
	 * Test empty string input.
	 */
	public function test_should_return_empty_string_when_input_is_empty() {
		$this->assertSame( '', wp_check_invalid_utf8( '' ) );
	}

	/**
	 * Test mixed valid and invalid UTF-8 strings with stripping enabled.
	 *
	 * @dataProvider data_should_return_mixed_valid_invalid_utf8
	 *
	 * @param string $value The mixed UTF-8 string.
	 * @param string $expected The expected result after processing with stripping.
	 * @param string $expected_no_strip The expected result without stripping.
	 */
	public function test_should_return_notice_when_mixed_with_stripping( $value, $expected, $expected_no_strip ) {
		$this->expectNotice();
		$result = wp_check_invalid_utf8( $value, true );
		$this->expectNoticeMessage( 'iconv(): Detected an illegal character in input string' );
	}

	/**
	 * Test mixed valid and invalid UTF-8 strings without stripping.
	 *
	 * @dataProvider data_should_return_mixed_valid_invalid_utf8
	 *
	 * @param string $value The mixed UTF-8 string.
	 * @param string $expected The expected result after processing with stripping.
	 * @param string $expected_no_strip The expected result without stripping.
	 */
	public function test_should_return_empty_string_when_mixed_invalid_without_stripping( $value, $expected, $expected_no_strip ) {
		$this->assertSame( $expected_no_strip, wp_check_invalid_utf8( $value ) );
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
				'Valid text  more text',
				'',
			),
			'mixed improperly formed sequence' => array(
				'Valid 𐀀 and invalid ' . "\xE0\xA4\xA0\xA0",
				'Valid 𐀀 and invalid ',
				'',
			),
			'mixed another invalid sequence'   => array(
				'Valid 𐀀 mixed with invalid ' . "\xE0\xA4\xA0\xA0",
				'Valid 𐀀 mixed with invalid ',
				'',
			),
		);
	}

	/**
	 * Test invalid UTF-8 strings with stripping enabled.
	 *
	 * @dataProvider data_should_return_notice_when_invalid_with_stripping
	 * @param string $value The invalid UTF-8 string.
	 */
	public function test_should_return_notice_when_invalid_with_stripping( $value ) {
		$this->expectNotice();
		$result = wp_check_invalid_utf8( $value, true );
		$this->expectNoticeMessage( 'iconv(): Detected an illegal character in input string' );
	}

	/**
	 * Data provider for invalid UTF-8 strings with stripping.
	 *
	 * @return array[]
	 */
	public function data_should_return_notice_when_invalid_with_stripping() {
		return array(
			'improperly formed sequence' => array( "\xE0\xA4\xA0\xA0" ),
		);
	}
}
