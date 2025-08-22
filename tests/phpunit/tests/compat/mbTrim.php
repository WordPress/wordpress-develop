<?php

/**
 * @group compat
 *
 * @covers ::mb_trim
 */
class Tests_Compat_mbTrim extends WP_UnitTestCase {

	/**
	 * @ticket 63804
	 *
	 * Test that mb_trim() is always available (either from PHP or WP).
	 */
	public function test_mb_trim_availability(): void {
		$this->assertTrue( function_exists( 'mb_trim' ) );
	}

	/**
	 * @ticket 63804
	 *
	 * @dataProvider data_mb_trim
	 *
	 * @param string      $input      The input string to be trimmed.
	 * @param string      $expected   The expected trimmed result.
	 * @param string|null $characters Optional. The characters to trim. Default null (whitespace).
	 * @param string|null $encoding   Optional. The character encoding. Default null (internal encoding).
	 */
	public function test_mb_trim( $input, $expected, $characters = null, $encoding = null ): void {
		$this->assertSame(
			$expected,
			mb_trim( $input, $characters, $encoding )
		);
	}

	/**
	 * Data provider for mb_trim tests.
	 *
	 * @return array[]
	 */
	public function data_mb_trim(): array {
		return array(
			// Basic ASCII whitespace.
			array( '  hello  ', 'hello' ),
			array( "\t\n\rhello\n\r\t", 'hello' ),
			// Unicode whitespace.
			array( "\u{00A0}hello\u{00A0}", 'hello' ),
			array( "\u{3000}hello\u{3000}", 'hello' ),
			array( "\u{00A0}\u{3000} hello \u{3000}\u{00A0}", 'hello' ),
			// Custom characters.
			array( 'xxhelloxx', 'hello', 'x' ),
			array( 'xyhelloyx', 'hello', 'xy' ),
			// No trimming needed.
			array( 'hello', 'hello' ),
			// Empty string.
			array( '', '' ),
			// With encoding.
			array( '  hello  ', 'hello', null, 'UTF-8' ),
			// Null characters.
			array( "\0hello\0", 'hello' ),
			// Vertical tab and form feed.
			array( "\v\fhello\f\v", 'hello' ),
		);
	}

	/**
	 * @ticket 63804
	 *
	 * @dataProvider data_mb_trim_non_utf8
	 *
	 * @param string $input    The input string to be trimmed.
	 * @param string $expected The expected trimmed result.
	 * @param string $encoding The character encoding.
	 */
	public function test_mb_trim_non_utf8_encodings( $input, $expected, $encoding ): void {
		$this->assertSame(
			$expected,
			mb_trim( $input, null, $encoding )
		);
	}

	/**
	 * Data provider for non-UTF-8 encoding tests.
	 *
	 * @return array[]
	 */
	public function data_mb_trim_non_utf8(): array {
		// Japanese "ヒス" (HIS) in Shift_JIS, with ASCII spaces around.
		$shift_jis_str      = mb_convert_encoding( ' ヒス ', 'SJIS', 'UTF-8' );
		$shift_jis_expected = mb_convert_encoding( 'ヒス', 'SJIS', 'UTF-8' );

		// Latin1 example with spaces.
		$latin1_str      = mb_convert_encoding( ' café ', 'ISO-8859-1', 'UTF-8' );
		$latin1_expected = mb_convert_encoding( 'café', 'ISO-8859-1', 'UTF-8' );

		return array(
			array( $shift_jis_str, $shift_jis_expected, 'SJIS' ),
			array( $latin1_str, $latin1_expected, 'ISO-8859-1' ),
		);
	}
}
