<?php

/**
 * @group formatting
 *
 *
 * @covers ::wp_check_invalid_utf8
 */
class Tests_Formatting_wpCheckInvalidUtf8 extends WP_UnitTestCase {

	/**
	 * Test valid UTF-8 strings without stripping.
	 */
	public function test_valid_utf8() {
		$valid_strings = array(
			'Hello, world!',
			'こんにちは', // Japanese for "Hello"
			'Привет мир', // Russian for "Hello, world"
			'Café',       // Contains an accented character
			'✅',         // Emoji
			mb_convert_encoding( 'Valid string', 'UTF-8' ),
		);

		foreach ( $valid_strings as $string ) {
			$this->assertEquals( $string, wp_check_invalid_utf8( $string ) );
		}
	}

	/**
	 * Test invalid UTF-8 strings without stripping.
	 */
	public function test_invalid_utf8_without_strip() {
		$invalid_strings = array(
			"\xC3\x28", // Invalid byte sequence
			"\xE0\xA4\xA0\xA0", // Improperly formed UTF-8 sequence
			"\xC0\xAF", // Invalid continuation byte
		);

		foreach ( $invalid_strings as $string ) {
			print_r( "String - {$string}" );
			$this->assertEquals( '', wp_check_invalid_utf8( $string ) );
		}
	}

	/**
	 * Test empty string input.
	 */
	public function test_empty_string() {
		$this->assertEquals( '', wp_check_invalid_utf8( '' ) );
	}
}
