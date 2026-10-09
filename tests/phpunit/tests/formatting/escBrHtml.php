<?php

/**
 * @group formatting
 *
 * @covers ::esc_br_html
 */
class Tests_Formatting_EscBrHtml extends WP_UnitTestCase {

	public function test_esc_br_html_basics() {
		// Simple string (no line breaks).
		$html = 'The quick brown fox.';
		$this->assertSame( $html, esc_br_html( $html ) );

		// String with a single line break.
		$html    = "The quick brown fox.\nJumps over the lazy dog.";
		$escaped = 'The quick brown fox.<br />Jumps over the lazy dog.';
		$this->assertSame( $escaped, esc_br_html( $html ) );

		// String with multiple line breaks.
		$html    = "First line.\nSecond line.\nThird line.";
		$escaped = 'First line.<br />Second line.<br />Third line.';
		$this->assertSame( $escaped, esc_br_html( $html ) );
	}

	public function test_escapes_ampersands_with_line_breaks() {
		$html    = "penn & teller\nat&t";
		$escaped = 'penn &amp; teller<br />at&amp;t';
		$this->assertSame( $escaped, esc_br_html( $html ) );
	}

	public function test_escapes_greater_and_less_than_with_line_breaks() {
		$html    = "this > that\nthat <randomhtml />";
		$escaped = 'this &gt; that<br />that &lt;randomhtml /&gt;';
		$this->assertSame( $escaped, esc_br_html( $html ) );
	}

	public function test_preserves_existing_entities_with_line_breaks() {
		$html    = "Line one: &#038;\nLine two: &#x00A3;\nLine three: &amp;";
		$escaped = 'Line one: &#038;<br />Line two: &#xA3;<br />Line three: &amp;';
		$this->assertSame( $escaped, esc_br_html( $html ) );
	}

	public function test_ignores_empty_strings() {
		$this->assertSame( '', esc_br_html( '' ) );
	}

	public function test_handles_multiple_consecutive_line_breaks() {
		$html    = "First line.\n\n\nSecond line.";
		$escaped = 'First line.<br /><br /><br />Second line.';
		$this->assertSame( $escaped, esc_br_html( $html ) );
	}
}
