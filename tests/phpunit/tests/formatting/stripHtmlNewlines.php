<?php

/**
 * Tests for strip_html_newlines().
 *
 * @group formatting
 *
 * @covers ::strip_html_newlines
 */
class Tests_Formatting_StripHtmlNewlines extends WP_UnitTestCase {

	/**
	 * Verifies that newlines and carriage returns in text nodes are replaced
	 * with spaces, including across inline elements like anchors.
	 *
	 * @ticket 5678
	 */
	public function test_strips_newlines_from_text_nodes() {
		$this->assertSame( '', strip_html_newlines( '' ), 'Empty string should be returned as-is.' );
		$this->assertSame( '<p>No newlines here.</p>', strip_html_newlines( '<p>No newlines here.</p>' ), 'Text without newlines should be returned as-is.' );
		$this->assertSame( '<p>Line one Line two Line three</p>', strip_html_newlines( "<p>Line one\n\nLine two\r\nLine three</p>" ), 'Multiple newlines and carriage returns should be collapsed to a single space.' );
		$this->assertSame(
			'<p>This is a paragraph in which the wpautop() <a href="#elsewhere">wrapping will happen in the middle</a> of an anchor, which is an inline element.</p>',
			strip_html_newlines( "<p>This is a paragraph in which the\nwpautop() <a href=\"#elsewhere\">wrapping will\nhappen in the middle</a> of an\nanchor, which is an inline element.</p>" ),
			'Newlines within and around inline elements should be stripped.'
		);
	}

	/**
	 * @ticket 5678
	 */
	public function test_preserves_newlines_in_preformatted_elements() {
		$input  = "<p>Normal\ntext</p>\n<pre>\nPreformatted\nlines\n</pre>\n<p>More\ntext</p>";
		$result = strip_html_newlines( $input );

		$this->assertStringContainsString( 'Normal text', $result, 'Newlines in normal text should be stripped.' );
		$this->assertStringContainsString( 'More text', $result, 'Newlines in trailing paragraph should be stripped.' );
		$this->assertStringContainsString( "\nPreformatted\nlines\n", $result, 'Newlines inside <pre> should be preserved.' );

		$preserved_cases = array(
			'code'   => "<p>A\nB</p><code>x\ny</code>",
			'kbd'    => "<p>A\nB</p><kbd>x\ny</kbd>",
			'script' => "<p>A\nB</p><script>x\ny</script>",
			'style'  => "<p>A\nB</p><style>x\ny</style>",
		);

		foreach ( $preserved_cases as $tag => $html ) {
			$out = strip_html_newlines( $html );
			$this->assertStringContainsString( 'A B', $out, "Text node newline should be stripped around <{$tag}>." );
			$this->assertStringContainsString( "x\ny", $out, "Newline inside <{$tag}> should be preserved." );
		}
	}

	/**
	 * @ticket 5678
	 */
	public function test_does_not_preserve_newlines_in_tags_prefixed_with_preserved_name() {
		$cases = array(
			'preload'   => "<preload>some\ncontent</preload><p>normal\ntext</p>",
			'codeblock' => "<codeblock>x\ny</codeblock>",
			'keyboard'  => "<keyboard>a\nb</keyboard>",
		);

		foreach ( $cases as $tag => $html ) {
			$out = strip_html_newlines( $html );
			$this->assertStringNotContainsString( "\n", $out, "Newlines inside <{$tag}> should be stripped (not a preserved element)." );
		}
	}
}
