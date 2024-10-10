<?php

/**
 * @group formatting
 *
 * @covers ::wp_check_invalid_utf8
 */
class Tests_Formatting_wpCheckInvalidUtf8 extends WP_UnitTestCase {

	/**
	 * Data provider for valid UTF-8 strings.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $value    The valid UTF-8 string.
	 *     }
	 * }
	 */
	public function data_valid_utf8_provider() {
		return array(
			array( 'Hello, world!' ),
			array( 'こんにちは' ), // Japanese for "Hello".
			array( 'Привет мир' ), // Russian for "Hello, world".
			array( 'Café' ),       // Contains an accented character.
			array( '✅' ),         // Emoji.
			array( mb_convert_encoding( 'Valid string', 'UTF-8' ) ),
		);
	}

	/**
	 * Test valid UTF-8 strings without stripping.
	 *
	 * @dataProvider data_valid_utf8_provider
	 *
	 * @param string $value The valid UTF-8 string.
	 */
	public function test_should_return_given_string_when_valid_without_stripping( $value ) {
		$this->assertSame( $value, wp_check_invalid_utf8( $value ) );
	}

	/**
	 * Data provider for invalid UTF-8 strings.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $value    The invalid UTF-8 string.
	 *     }
	 * }
	 */
	public function invalid_utf8_provider() {
		return array(
			array( "\xC3\x28" ), // Invalid byte sequence.
			array( "\xE0\xA4\xA0\xA0" ), // Improperly formed UTF-8 sequence.
			array( "\xC0\xAF" ), // Invalid continuation byte.
		);
	}

	/**
	 * Test invalid UTF-8 strings without stripping.
	 *
	 * @dataProvider invalid_utf8_provider
	 *
	 * @param string $value The invalid UTF-8 string.
	 */
	public function test_should_return_empty_string_when_invalid_without_stripping( $value ) {
		$this->assertSame( '', wp_check_invalid_utf8( $value ) );
	}

	/**
	 * Test empty string input.
	 */
	public function test_should_return_empty_string_when_input_is_empty() {
		$this->assertSame( '', wp_check_invalid_utf8( '' ) );
	}

	/**
	 * Data provider for invalid UTF-8 strings with stripping.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $value    The invalid UTF-8 string.
	 *     }
	 * }
	 */
	public function data_invalid_utf8_with_strip_provider() {
		return array(
			array( "\xE0\xA4\xA0\xA0" ), // Improperly formed UTF-8 sequence.
		);
	}

	/**
	 * Test invalid UTF-8 strings with stripping enabled.
	 *
	 * @dataProvider data_invalid_utf8_with_strip_provider
	 * @param string $value The invalid UTF-8 string.
	 */
	public function test_should_return_non_empty_string_when_invalid_with_stripping( $value ) {
		$result = wp_check_invalid_utf8( $value, true );
		// Ensure the result is not empty (indicating some normalization).
		$this->assertNotEmpty( $result );
		// Ensure that the result does not contain any invalid characters.
		$this->assertFalse( wp_check_invalid_utf8( $result ) );
	}

	/**
	 * Data provider for mixed valid and invalid UTF-8 strings.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $value    The mixed UTF-8 string.
	 *         @type string $expected  The expected result after processing with stripping.
	 *         @type string $expected_no_strip The expected result without stripping.
	 *     }
	 * }
	 */
	public function data_mixed_utf8_provider() {
		return array(
			array(
				'Valid text ' . "\xC3\x28" . ' more text', // Invalid byte sequence mixed in.
				'Valid text  more text', // Expected result with invalid byte stripped.
				'', // Expected result without stripping (empty).
			),
			array(
				'Valid 𐀀 and invalid ' . "\xE0\xA4\xA0\xA0", // Valid followed by improperly formed sequence.
				'Valid 𐀀 and invalid ', // Expected result with invalid parts stripped.
				'', // Expected result without stripping (empty).
			),
			array(
				'Valid 𐀀 mixed with invalid ' . "\xE0\xA4\xA0\xA0", // Another mixed case.
				'Valid 𐀀 mixed with invalid ', // Expected with valid preserved.
				'', // Expected result without stripping (empty).
			),
		);
	}

	/**
	 * Test mixed valid and invalid UTF-8 strings with stripping enabled.
	 *
	 * @dataProvider data_mixed_utf8_provider
	 *
	 * @param string $value The mixed UTF-8 string.
	 * @param string $expected The expected result after processing with stripping.
	 * @param string $expected_no_strip The expected result without stripping.
	 */
	public function test_should_return_valid_parts_when_mixed_with_stripping( $value, $expected, $expected_no_strip ) {
		$result = wp_check_invalid_utf8( $value, true );
		// Assert that the result matches the expected output with stripping.
		$this->assertSame( $expected, $result );

		// Optionally check that the result does not contain invalid characters.
		$this->assertFalse( wp_check_invalid_utf8( $result ) );
	}

	/**
	 * Test mixed valid and invalid UTF-8 strings without stripping.
	 *
	 * @dataProvider data_mixed_utf8_provider
	 *
	 * @param string $value The mixed UTF-8 string.
	 * @param string $expected The expected result after processing with stripping.
	 * @param string $expected_no_strip The expected result without stripping.
	 */
	public function test_should_return_empty_string_when_mixed_invalid_without_stripping( $value, $expected, $expected_no_strip ) {
		// Assert that the result is an empty string for mixed invalid input without stripping.
		$this->assertSame( $expected_no_strip, wp_check_invalid_utf8( $value ) );
	}
}
