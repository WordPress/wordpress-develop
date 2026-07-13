<?php

/**
 * @group formatting
 *
 * @covers ::js_trim
 */
class Tests_Formatting_JsTrim extends WP_UnitTestCase {

	/**
	 * @ticket 63804
	 *
	 * Test that js_trim() is always available (either from PHP or WP).
	 */
	public function test_js_trim_availability(): void {
		$this->assertTrue( function_exists( 'js_trim' ) );
	}

	/**
	 * @ticket 63804
	 *
	 * @dataProvider data_js_trim
	 *
	 * @param string $input    The input string to be trimmed.
	 * @param string $expected The expected trimmed result.
	 */
	public function test_js_trim( $input, $expected ): void {
		$this->assertSame( $expected, js_trim( $input ) );
	}

	/**
	 * Data provider for js_trim tests.
	 *
	 * @return array[]
	 */
	public function data_js_trim(): array {
		return array(
			// Basic ASCII whitespace.
			array( '  hello  ', 'hello' ),
			array( "\t\n\rhello\n\r\t", 'hello' ),
			// Unicode whitespace.
			array( "\u{00A0}hello\u{00A0}", 'hello' ),
			array( "\u{3000}hello\u{3000}", 'hello' ),
			array( "\u{00A0}\u{3000} hello \u{3000}\u{00A0}", 'hello' ),
			// Null characters should not be trimmed by js_trim().
			array( "\0hello\0", "\0hello\0" ),
			// Vertical tab and form feed are trimmed.
			array( "\v\fhello\f\v", 'hello' ),
			// No trimming needed.
			array( 'hello', 'hello' ),
			// Empty string.
			array( '', '' ),
		);
	}
}
