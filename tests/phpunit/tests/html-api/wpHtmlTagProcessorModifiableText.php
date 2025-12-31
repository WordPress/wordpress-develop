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
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_modifiable_text_replacements() {
		return array(
			'shorter'     => array( 'just some text', 'shorter text' ),
			'same length' => array( 'just some text', 'different text' ),
			'longer'      => array( 'just some text', 'a bit longer text' ),
		);
	}

	/**
	 * Ensures that `get_modifiable_text()` reads enqueued updates when read
	 * from after writing; guarantees consistency through writes.
	 *
	 * @ticket 61617
	 * @ticket 62241
	 *
	 * @dataProvider data_get_modifiable_text_replacements
	 *
	 * @param string $initial     Initial text.
	 * @param string $replacement Replacement text.
	 */
	public function test_get_modifiable_text_is_consistent_after_writes( $initial, $replacement ) {
		$processor = new WP_HTML_Tag_Processor( $initial );
		$processor->next_token();

		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			"Should have found text node but found '{$processor->get_token_name()}' instead: check test setup."
		);

		$this->assertSame(
			$initial,
			$processor->get_modifiable_text(),
			'Should have found initial test text: check test setup.'
		);

		$processor->set_modifiable_text( $replacement );
		$this->assertSame(
			$replacement,
			$processor->get_modifiable_text(),
			'Should have found enqueued updated text.'
		);
		$this->assertSame(
			$replacement,
			$processor->get_updated_html(),
			'Should match updated HTML.'
		);
		$this->assertSame(
			$replacement,
			$processor->get_modifiable_text(),
			'Should have found updated text.'
		);
	}

	/**
	 * Ensures that `get_modifiable_text()` reads enqueued updates when read from
	 * after writing; guarantees consistency through writes after closed tag element.
	 *
	 * @ticket 62241
	 *
	 * @dataProvider data_get_modifiable_text_replacements
	 *
	 * @param string $initial     Initial text.
	 * @param string $replacement Replacement text.
	 */
	public function test_get_modifiable_text_is_consistent_after_writes_when_text_after_closed_tag_element( $initial, $replacement ) {
		$html_before = '<p>some content</p>';
		$processor   = new WP_HTML_Tag_Processor( $html_before . $initial );
		// Move to the text node after the closing p tag.
		$processor->next_token();
		$processor->next_token();
		$processor->next_token();
		$processor->next_token();

		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			"Should have found text node but found '{$processor->get_token_name()}' instead: check test setup."
		);

		$this->assertSame(
			$initial,
			$processor->get_modifiable_text(),
			'Should have found initial test text: check test setup.'
		);

		$processor->set_modifiable_text( $replacement );
		$this->assertSame(
			$replacement,
			$processor->get_modifiable_text(),
			'Should have found enqueued updated text.'
		);

		$this->assertSame(
			$html_before . $replacement,
			$processor->get_updated_html(),
			'Should match updated HTML.'
		);

		$this->assertSame(
			$replacement,
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
	 * For example, a modifiable text update that would change the structure of the HTML
	 * document is not allowed, like attempting to set `-->` within a comment or `</script>`
	 * within a text/plain SCRIPT tag.
	 *
	 * @ticket 61617
	 * @ticket 62797
	 *
	 * @dataProvider data_unallowed_modifiable_text_updates
	 *
	 * @param string $html_with_nonempty_modifiable_text Will be used to find the test element.
	 * @param string $invalid_update                     Update containing possibly-compromising text.
	 */
	public function test_rejects_dangerous_updates( string $html_with_nonempty_modifiable_text, string $invalid_update ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_nonempty_modifiable_text );

		while ( '' === $processor->get_modifiable_text() && $processor->next_token() ) {
			continue;
		}

		$original_text = $processor->get_modifiable_text();
		$this->assertNotEmpty( $original_text, 'Should have found non-empty text: check test setup.' );

		$this->assertFalse(
			$processor->set_modifiable_text( $invalid_update ),
			'Should have rejected possibly-compromising modifiable text update.'
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
			'Comment with -->'                        => array( '<!-- this is a comment -->', 'Comments end in -->' ),
			'Comment with --!>'                       => array( '<!-- this is a comment -->', 'Invalid but legitimate comments end in --!>' ),
			'Non-JS SCRIPT with <script>'             => array( '<script type="text/html">Replace me</script>', '<!-- Just a <script>' ),
			'Non-JS SCRIPT with </script>'            => array( '<script type="text/plain">Replace me</script>', 'Just a </script>' ),
			'Non-JS SCRIPT with <script attributes>'  => array( '<script language="text">Replace me</script>', '<!-- <script sneaky>after' ),
			'Non-JS SCRIPT with </script attributes>' => array( '<script language="text">Replace me</script>', 'before</script sneaky>after' ),
		);
	}

	/**
	 * Ensures that JavaScript script tag contents are safely updated.
	 *
	 * @ticket 62797
	 *
	 * @dataProvider data_script_tag_text_updates
	 *
	 * @param string $html     HTML containing a SCRIPT tag to be modified.
	 * @param string $update   Update containing possibly-compromising text.
	 * @param string $expected Expected result.
	 */
	public function test_safely_updates_script_tag_contents( string $html, string $update, string $expected ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( 'SCRIPT' ) );
		$this->assertTrue( $processor->set_modifiable_text( $update ) );
		$this->assertSame( $expected, $processor->get_updated_html() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_script_tag_text_updates(): array {
		return array(
			'Simple update'                         => array( '<script></script>', '{}', '<script>{}</script>' ),
			'Needs no replacement'                  => array( '<script></script>', '<!--<scriptish>', '<script><!--<scriptish></script>' ),
			'var script;1<script>0'                 => array( '<script></script>', 'var script;1<script>0', '<script>var script;1<\u0073cript>0</script>' ),
			'1</script>/'                           => array( '<script></script>', '1</script>/', '<script>1</\u0073cript>/</script>' ),
			'var SCRIPT;1<SCRIPT>0'                 => array( '<script></script>', 'var SCRIPT;1<SCRIPT>0', '<script>var SCRIPT;1<\u0053CRIPT>0</script>' ),
			'1</SCRIPT>/'                           => array( '<script></script>', '1</SCRIPT>/', '<script>1</\u0053CRIPT>/</script>' ),
			'"</script>"'                           => array( '<script></script>', '"</script>"', '<script>"</\u0073cript>"</script>' ),
			'"</ScRiPt>"'                           => array( '<script></script>', '"</ScRiPt>"', '<script>"</\u0053cRiPt>"</script>' ),
			'Tricky script open tag with \r'        => array( '<script></script>', "<!-- <script\r>", "<script><!-- <\\u0073cript\r></script>" ),
			'Tricky script open tag with \r\n'      => array( '<script></script>', "<!-- <script\r\n>", "<script><!-- <\\u0073cript\r\n></script>" ),
			'Tricky script close tag with \r'       => array( '<script></script>', "// </script\r>", "<script>// </\\u0073cript\r></script>" ),
			'Tricky script close tag with \r\n'     => array( '<script></script>', "// </script\r\n>", "<script>// </\\u0073cript\r\n></script>" ),
			'Module tag'                            => array( '<script type="module"></script>', '"<script>"', '<script type="module">"<\u0073cript>"</script>' ),
			'Tag with type'                         => array( '<script type="text/javascript"></script>', '"<script>"', '<script type="text/javascript">"<\u0073cript>"</script>' ),
			'Tag with language'                     => array( '<script language="javascript"></script>', '"<script>"', '<script language="javascript">"<\u0073cript>"</script>' ),
			'Non-JS script, save HTML-like content' => array( '<script type="text/html"></script>', '<h1>This & that</h1>', '<script type="text/html"><h1>This & that</h1></script>' ),
		);
	}

	/**
	 * @ticket 64419
	 */
	public function test_complex_javascript_and_json_auto_escaping() {
		$processor = new WP_HTML_Tag_Processor( "<script></script>\n<script></script>\n<hr>" );
		$processor->next_tag( 'SCRIPT' );
		$processor->set_attribute( 'type', 'importmap' );
		$importmap_data = array(
			'imports' => array(
				'</SCRIPT>\\<!--\\<script>' => './script',
			),
		);

		$importmap = json_encode(
			$importmap_data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS
		);

		$processor->set_modifiable_text( "\n{$importmap}\n" );
		$decoded_importmap = json_decode( $processor->get_modifiable_text(), true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'JSON failed to decode correctly.' );
		$this->assertEquals( $importmap_data, $decoded_importmap );
		$processor->next_tag( 'SCRIPT' );
		$processor->set_attribute( 'type', 'module' );
		$javascript = <<<'JS'
import '</SCRIPT>\\<!--\\<script>';
JS;
		$processor->set_modifiable_text( "\n{$javascript}\n" );

		$expected = <<<'HTML'
<script type="importmap">
{"imports":{"</\u0053CRIPT>\\<!--\\<\u0073cript>":"./script"}}
</script>
<script type="module">
import '</\u0053CRIPT>\\<!--\\<\u0073cript>';
</script>
<hr>
HTML;

		$updated_html = $processor->get_updated_html();
		$this->assertEqualHTML( $expected, $updated_html );

		// Reprocess to ensure JSON survives HTML round-trip:
		$processor = new WP_HTML_Tag_Processor( $updated_html );
		$processor->next_tag( 'SCRIPT' );
		$this->assertSame( 'importmap', $processor->get_attribute( 'type' ) );
		$importmap_json    = $processor->get_modifiable_text();
		$decoded_importmap = json_decode( $importmap_json, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'Importmap JSON failed to decode.' );
		$this->assertEquals(
			$importmap_data,
			$decoded_importmap,
			'JSON was not equal after re-processing updated HTML.'
		);
	}

	/**
	 * @ticket 64419
	 */
	public function test_json_auto_escaping() {
		// This is not a typical JSON encoding or escaping, but it is valid.
		$json_text             = '"Escaped BS: \\\\; Escaped BS+LT: \\\\<; Unescaped LT: <; Script closer: </script>"';
		$expected_decoded_json = 'Escaped BS: \\; Escaped BS+LT: \\<; Unescaped LT: <; Script closer: </script>';
		$decoded_json          = json_decode( $json_text );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'JSON failed to decode.' );
		$this->assertSame(
			$expected_decoded_json,
			$decoded_json,
			'Decoded JSON did not match expected value.'
		);

		$processor = new WP_HTML_Tag_Processor( '<script type="application/json"></script>' );
		$processor->next_tag( 'SCRIPT' );

		$processor->set_modifiable_text( "\n{$json_text}\n" );

		$expected = <<<'HTML'
<script type="application/json">
"Escaped BS: \\; Escaped BS+LT: \\<; Unescaped LT: <; Script closer: </\u0073cript>"
</script>
HTML;

		$updated_html = $processor->get_updated_html();
		$this->assertEqualHTML( $expected, $updated_html );

		// Reprocess to ensure JSON value survives HTML round-trip:
		$processor = new WP_HTML_Tag_Processor( $updated_html );
		$processor->next_tag( 'SCRIPT' );
		$decoded_json_from_html = json_decode( $processor->get_modifiable_text(), true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'JSON failed to decode.' );
		$this->assertEquals(
			$expected_decoded_json,
			$decoded_json_from_html
		);
	}
}
