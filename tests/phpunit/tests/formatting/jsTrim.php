<?php

/**
 * @group formatting
 *
 * @covers ::js_trim
 */
class Tests_Formatting_JsTrim extends WP_UnitTestCase {
	public function test_trims_ascii_whitespace() {
		$this->assertSame( 'hello', js_trim( "  hello  " ) );
		$this->assertSame( 'hello', js_trim( "\t\n\rhello\n\r\t" ) );
	}

	public function test_trims_unicode_whitespace() {
		// NO-BREAK SPACE (U+00A0)
		$this->assertSame( 'hello', js_trim( "\u{00A0}hello\u{00A0}" ) );
		// IDEOGRAPHIC SPACE (U+3000)
		$this->assertSame( 'hello', js_trim( "\u{3000}hello\u{3000}" ) );
		// MIXED
		$this->assertSame( 'hello', js_trim( "\u{00A0}\u{3000} hello \u{3000}\u{00A0}" ) );
	}

	public function test_trims_null_and_control_chars() {
		$this->assertSame( "\0hello\0", js_trim( "\0hello\0" ) );
		$this->assertSame( 'hello', js_trim( "\v\fhello\f\v" ) );
	}

	public function test_no_trimming_needed() {
		$this->assertSame( 'hello', js_trim( 'hello' ) );
	}

	public function test_empty_string_returns_empty() {
		$this->assertSame( '', js_trim( '' ) );
	}
}
