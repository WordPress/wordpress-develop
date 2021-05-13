<?php

/**
 * @group formatting
 */
class Tests_Formatting_JSEscape extends WP_UnitTestCase {

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_simple() {
		$out = esc_js( 'foo bar baz();' );
		$this->assertSame( 'foo bar baz();', $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_quotes() {
		$out = esc_js( 'foo "bar" \'baz\'' );
		// Does it make any sense to change " into &quot;?  Why not \"?
		$this->assertSame( "foo &quot;bar&quot; \'baz\'", $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_backslash() {
		$bs  = '\\';
		$out = esc_js( 'foo ' . $bs . 't bar ' . $bs . $bs . ' baz' );
		// \t becomes t - bug?
		$this->assertSame( 'foo t bar ' . $bs . $bs . ' baz', $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_amp() {
		$out = esc_js( 'foo & bar &baz; &nbsp;' );
		$this->assertSame( 'foo &amp; bar &amp;baz; &nbsp;', $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_quote_entity() {
		$out = esc_js( 'foo &#x27; bar &#39; baz &#x26;' );
		$this->assertSame( "foo \\' bar \\' baz &#x26;", $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_no_carriage_return() {
		$out = esc_js( "foo\rbar\nbaz\r" );
		// \r is stripped.
		$this->assertSame( "foobar\\nbaz", $out );
	}

	/**
	 * @covers ::esc_js
	 */
	function test_js_escape_rn() {
		$out = esc_js( "foo\r\nbar\nbaz\r\n" );
		// \r is stripped.
		$this->assertSame( "foo\\nbar\\nbaz\\n", $out );
	}
}
