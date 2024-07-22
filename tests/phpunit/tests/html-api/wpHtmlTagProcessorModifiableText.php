<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor modifiable text functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlTagProcessorModifiableText extends WP_UnitTestCase {
	/**
	 * Ensures that calls to `get_modifiable_text()` don't change the
	 * parser state in a way that would corrupt repeated calls.
	 *
	 * @ticket 61576
	 */
	public function test_get_modifiable_text_is_idempotent() {
		$processor = new WP_HTML_Tag_Processor( "<pre>\nFirst newline ignored.</pre>" );

		// Find the text node in the middle.
		while ( '#text' !== $processor->get_token_name() && $processor->next_token() ) {
			continue;
		}

		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			'Failed to find text node under test: check test setup.'
		);

		// The count of 5 isn't important; but calling this multiple times is.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame(
				'First newline ignored.',
				$processor->get_modifiable_text(),
				'Should have returned the same modifiable text regardless of how many times it was called.'
			);
		}
	}
}
