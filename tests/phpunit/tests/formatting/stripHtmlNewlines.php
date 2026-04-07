<?php

/**
 * Tests for strip_html_newlines().
 *
 * @group formatting
 *
 * @covers ::strip_html_newlines
 */
class Tests_Formatting_StripHtmlNewlines extends WP_UnitTestCase {
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
}
