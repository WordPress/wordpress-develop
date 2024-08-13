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
	 * Ensures that `get_modifiable_text()` reads enqueued updates when read
	 * from after writing; guarantees consistency through writes.
	 *
	 * @ticket 61617
	 */
	public function test_get_modifiable_text_is_consistent_after_writes() {
		$before    = 'just some text';
		$after     = 'different text';
		$processor = new WP_HTML_Tag_Processor( $before );
		$processor->next_token();

		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			"Should have found text node but found '{$processor->get_token_name()}' instead: check test setup."
		);

		$this->assertSame(
			$before,
			$processor->get_modifiable_text(),
			'Should have found initial test text: check test setup.'
		);

		$processor->set_modifiable_text( $after );
		$this->assertSame(
			$after,
			$processor->get_modifiable_text(),
			'Should have found enqueued updated text.'
		);

		$processor->get_updated_html();
		$this->assertSame(
			$after,
			$processor->get_modifiable_text(),
			'Should have found updated text.'
		);
	}

	/**
	 * Ensures that `get_modifiable_text()` reads enqueued updates when read from after
	 * writing when starting from an empty text; guarantees consistency through writes.
	 *
	 * @ticket 61617
	 */
	public function test_get_modifiable_text_is_consistent_after_writes_to_empty_text() {
		$after     = 'different text';
		$processor = new WP_HTML_Tag_Processor( '<script></script>' );
		$processor->next_token();

		$this->assertSame(
			'SCRIPT',
			$processor->get_token_name(),
			"Should have found text node but found '{$processor->get_token_name()}' instead: check test setup."
		);

		$this->assertSame(
			'',
			$processor->get_modifiable_text(),
			'Should have found initial test text: check test setup.'
		);

		$processor->set_modifiable_text( $after );
		$this->assertSame(
			$after,
			$processor->get_modifiable_text(),
			'Should have found enqueued updated text.'
		);

		$processor->get_updated_html();
		$this->assertSame(
			$after,
			$processor->get_modifiable_text(),
			'Should have found updated text.'
		);
	}

	/**
	 * Ensures that updates to modifiable text that are shorter than the
	 * original text do not cause the parser to lose its orientation.
	 *
	 * @ticket 61617
	 */
	public function test_setting_shorter_modifiable_text() {
		$processor = new WP_HTML_Tag_Processor( '<div><textarea>very long text</textarea><div id="not a <span>">' );

		// Find the test node in the middle.
		while ( 'TEXTAREA' !== $processor->get_token_name() && $processor->next_token() ) {
			continue;
		}

		$this->assertSame(
			'TEXTAREA',
			$processor->get_token_name(),
			'Failed to find the test TEXTAREA node; check the test setup.'
		);

		$processor->set_modifiable_text( 'short' );
		$processor->get_updated_html();
		$this->assertSame(
			'short',
			$processor->get_modifiable_text(),
			'Should have updated modifiable text to something shorter than the original.'
		);

		$this->assertTrue(
			$processor->next_token(),
			'Should have advanced to the last token in the input.'
		);

		$this->assertSame(
			'DIV',
			$processor->get_token_name(),
			'Should have recognized the final DIV in the input.'
		);

		$this->assertSame(
			'not a <span>',
			$processor->get_attribute( 'id' ),
			'Should have read in the id from the last DIV as "not a <span>"'
		);
	}

	/**
	 * Ensures that reads to modifiable text after setting it reads the updated
	 * enqueued values, and not the original value.
	 *
	 * @ticket 61617
	 */
	public function test_modifiable_text_reads_updates_after_setting() {
		$processor = new WP_HTML_Tag_Processor( 'This is text<!-- this is not -->' );

		$processor->next_token();
		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			'Failed to find first text node: check test setup.'
		);

		$update = 'This is new text';
		$processor->set_modifiable_text( $update );
		$this->assertSame(
			$update,
			$processor->get_modifiable_text(),
			'Failed to read updated enqueued value of text node.'
		);

		$processor->next_token();
		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			'Failed to advance to comment: check test setup.'
		);

		$this->assertSame(
			' this is not ',
			$processor->get_modifiable_text(),
			'Failed to read modifiable text for next token; did it read the old enqueued value from the previous token?'
		);
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

	/**
	 * Ensures that modifiable text updates are not applied where they aren't supported.
	 *
	 * @ticket 61617
	 *
	 * @dataProvider data_tokens_not_supporting_modifiable_text_updates
	 *
	 * @param string $html             Contains HTML with a token not supporting modifiable text updates.
	 * @param int    $advance_n_tokens Count of times to run `next_token()` before reaching target node.
	 */
	public function test_rejects_updates_on_unsupported_match_locations( string $html, int $advance_n_tokens ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( --$advance_n_tokens >= 0 ) {
			$processor->next_token();
		}

		$this->assertFalse(
			$processor->set_modifiable_text( 'Bazinga!' ),
			'Should have prevented modifying the text at the target node.'
		);

		$this->assertSame(
			$html,
			$processor->get_updated_html(),
			'Should not have modified the input document in any way.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tokens_not_supporting_modifiable_text_updates() {
		return array(
			'Before parsing'               => array( 'nothing to see here', 0 ),
			'After parsing'                => array( 'nothing here either', 2 ),
			'Incomplete document'          => array( '<tag without="an end', 1 ),
			'Presumptuous closer'          => array( 'before</>after', 2 ),
			'Invalid (CDATA)'              => array( '<![CDATA[this is a comment]]>', 1 ),
			'Invalid (shortest comment)'   => array( '<!-->', 1 ),
			'Invalid (shorter comment)'    => array( '<!--->', 1 ),
			'Invalid (markup declaration)' => array( '<!run>', 1 ),
			'Invalid (PI-like node)'       => array( '<?xml is not html ?>', 1 ),
		);
	}

	/**
	 * Ensures that modifiable text updates are applied as expected to supported nodes.
	 *
	 * @ticket 61617
	 *
	 * @dataProvider data_tokens_with_basic_modifiable_text_updates
	 *
	 * @param string $html             Contains HTML with a token supporting modifiable text updates.
	 * @param int    $advance_n_tokens Count of times to run `next_token()` before reaching target node.
	 * @param string $raw_replacement  This should be escaped properly when replaced as modifiable text.
	 * @param string $transformed      Expected output after updating modifiable text.
	 */
	public function test_updates_basic_modifiable_text_on_supported_nodes( string $html, int $advance_n_tokens, string $raw_replacement, string $transformed ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( --$advance_n_tokens >= 0 ) {
			$processor->next_token();
		}

		$this->assertTrue(
			$processor->set_modifiable_text( $raw_replacement ),
			'Should have modified the text at the target node.'
		);

		$this->assertSame(
			$transformed,
			$processor->get_updated_html(),
			"Should have transformed the HTML as expected why modifying the target node's modifiable text."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tokens_with_basic_modifiable_text_updates() {
		return array(
			'Text node (start)'       => array( 'Text', 1, 'Blubber', 'Blubber' ),
			'Text node (middle)'      => array( '<em>Bold move</em>', 2, 'yo', '<em>yo</em>' ),
			'Text node (end)'         => array( '<img>of a dog', 2, 'of a cat', '<img>of a cat' ),
			'Encoded text node'       => array( '<figcaption>birds and dogs</figcaption>', 2, '<birds> & <dogs>', '<figcaption>&lt;birds&gt; &amp; &lt;dogs&gt;</figcaption>' ),
			'SCRIPT tag'              => array( 'before<script></script>after', 2, 'const img = "<img> & <br>";', 'before<script>const img = "<img> & <br>";</script>after' ),
			'STYLE tag'               => array( '<style></style>', 1, 'p::before { content: "<img> & </style>"; }', '<style>p::before { content: "<img> & \3c\2fstyle>"; }</style>' ),
			'TEXTAREA tag'            => array( 'a<textarea>has no need to escape</textarea>b', 2, "so it <doesn't>", "a<textarea>so it <doesn't></textarea>b" ),
			'TEXTAREA (escape)'       => array( 'a<textarea>has no need to escape</textarea>b', 2, 'but it does for </textarea>', 'a<textarea>but it does for &lt;/textarea></textarea>b' ),
			'TEXTAREA (escape+attrs)' => array( 'a<textarea>has no need to escape</textarea>b', 2, 'but it does for </textarea not an="attribute">', 'a<textarea>but it does for &lt;/textarea not an="attribute"></textarea>b' ),
			'TITLE tag'               => array( 'a<title>has no need to escape</title>b', 2, "so it <doesn't>", "a<title>so it <doesn't></title>b" ),
			'TITLE (escape)'          => array( 'a<title>has no need to escape</title>b', 2, 'but it does for </title>', 'a<title>but it does for &lt;/title></title>b' ),
			'TITLE (escape+attrs)'    => array( 'a<title>has no need to escape</title>b', 2, 'but it does for </title not an="attribute">', 'a<title>but it does for &lt;/title not an="attribute"></title>b' ),
		);
	}

	/**
	 * Ensures that updates with potentially-compromising values aren't accepted.
	 *
	 * For example, a modifiable text update should be allowed which would break
	 * the structure of the containing element, such as in a script or comment.
	 *
	 * @ticket 61617
	 *
	 * @dataProvider data_unallowed_modifiable_text_updates
	 *
	 * @param string $html_with_nonempty_modifiable_text Will be used to find the test element.
	 * @param string $invalid_update                     Update containing possibly-compromising text.
	 */
	public function test_rejects_updates_with_unallowed_substrings( string $html_with_nonempty_modifiable_text, string $invalid_update ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_nonempty_modifiable_text );

		while ( '' === $processor->get_modifiable_text() && $processor->next_token() ) {
			continue;
		}

		$original_text = $processor->get_modifiable_text();
		$this->assertNotEmpty( $original_text, 'Should have found non-empty text: check test setup.' );

		$this->assertFalse(
			$processor->set_modifiable_text( $invalid_update ),
			'Should have reject possibly-compromising modifiable text update.'
		);

		// Flush updates.
		$processor->get_updated_html();

		$this->assertSame(
			$original_text,
			$processor->get_modifiable_text(),
			'Should have preserved the original modifiable text before the rejected update.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unallowed_modifiable_text_updates() {
		return array(
			'Comment with -->'                 => array( '<!-- this is a comment -->', 'Comments end in -->' ),
			'Comment with --!>'                => array( '<!-- this is a comment -->', 'Invalid but legitimate comments end in --!>' ),
			'SCRIPT with </script>'            => array( '<script>Replace me</script>', 'Just a </script>' ),
			'SCRIPT with </script attributes>' => array( '<script>Replace me</script>', 'before</script id=sneak>after' ),
		);
	}
}
