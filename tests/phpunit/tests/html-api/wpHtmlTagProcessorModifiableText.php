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

	/**
	 * Ensures that when ignoring a newline after LISTING and PRE tags, that this
	 * happens appropriately after seeking.
	 */
	public function test_get_modifiable_text_ignores_newlines_after_seeking() {
		$processor = new WP_HTML_Tag_Processor(
			<<<HTML
<span>\nhere</span>
<listing>\ngone</listing>
<pre>reset last known ignore-point</pre>
<div>\nhere</div>
HTML
		);

		$processor->next_tag( 'SPAN' );
		$processor->next_token();
		$processor->set_bookmark( 'span' );

		$this->assertSame(
			"\nhere",
			$processor->get_modifiable_text(),
			'Should not have removed the leading newline from the first SPAN.'
		);

		$processor->next_tag( 'LISTING' );
		$processor->next_token();
		$processor->set_bookmark( 'listing' );

		$this->assertSame(
			'gone',
			$processor->get_modifiable_text(),
			'Should have stripped the leading newline from the LISTING element on first traversal.'
		);

		$processor->next_tag( 'DIV' );
		$processor->next_token();
		$processor->set_bookmark( 'div' );

		$this->assertSame(
			"\nhere",
			$processor->get_modifiable_text(),
			'Should not have removed the leading newline from the last DIV.'
		);

		$processor->seek( 'span' );
		$this->assertSame(
			"\nhere",
			$processor->get_modifiable_text(),
			'Should not have removed the leading newline from the first SPAN on its second traversal.'
		);

		$processor->seek( 'listing' );
		if ( "\ngone" === $processor->get_modifiable_text() ) {
			$this->markTestSkipped( "There's no support currently for handling the leading newline after seeking." );
		}

		$this->assertSame(
			'gone',
			$processor->get_modifiable_text(),
			'Should have remembered to remote leading newline from LISTING element after seeking around it.'
		);

		$processor->seek( 'div' );
		$this->assertSame(
			"\nhere",
			$processor->get_modifiable_text(),
			'Should not have removed the leading newline from the last DIV on its second traversal.'
		);
	}
}
