<?php
/**
 * @group formatting
 * @ticket 58805
 *
 * @covers ::excerpt_remove_footnotes
 */

class Tests_Formatting_ExcerptRemoveFootnotes extends WP_UnitTestCase {
	/**
	 * @ticket 58805
	 *
	 * @dataProvider data_remove_footnotes
	 *
	 * @param string $expected  Expected output.
	 * @param string $content   Content to run strip_shortcodes() on.
	 * @param string $message   The test message.
	 */
	public function test_remove_footnotes( $expected, $content, $message = '' ) {
		$this->assertSame( $expected, excerpt_remove_footnotes( $content ), $message );
	}

	public function data_remove_footnotes() {
		return array(
			array(
				'<p>This is a paragraph<sup class="fn" id="1"><a href="#1" id="1a">1</a></sup>.</p>',
				'<p>This is a paragraph<sup class="fn" id="1"><a href="#1" id="1a">1</a></sup>.</p>',
				'Should not replace if no footnote attribute is found in the content.',
			),
			array(
				'<p>This is a <a href="https://wordpress.org" data-type="URL" data-id="https://wordpress.org">paragraph</a>.</p>',
				'<p>This is a <a href="https://wordpress.org" data-type="URL" data-id="https://wordpress.org">paragraph</a><sup data-fn="d3b825b6-1890-4cb3-b276-002137515e99" class="fn"><a href="#d3b825b6-1890-4cb3-b276-002137515e99" id="d3b825b6-1890-4cb3-b276-002137515e99-link">1</a></sup>.</p>',
				'Footnote should be removed from HTML content.',
			),
			array(
				'<!-- wp:list --><ul><!-- wp:list-item --><li><strong>This</strong><em><strong><sup></sup></strong></em><strong> is a list</strong></li><!-- /wp:list-item --></ul><!-- /wp:list -->',
				'<!-- wp:list --><ul><!-- wp:list-item --><li><strong>This</strong><em><strong><sup><sup data-fn="e2fce624-74a5-4068-a20c-6ef793f1644c" class="fn"><a href="#e2fce624-74a5-4068-a20c-6ef793f1644c" id="e2fce624-74a5-4068-a20c-6ef793f1644c-link">2</a></sup></sup></strong></em><strong> is a list</strong><sup data-fn="ea7e892e-7bc2-424b-936b-36ec64f1c2fc" class="fn"><a href="#ea7e892e-7bc2-424b-936b-36ec64f1c2fc" id="ea7e892e-7bc2-424b-936b-36ec64f1c2fc-link">3</a></sup></li><!-- /wp:list-item --></ul><!-- /wp:list -->',
				'Multiple footnotes should be removed from block content.',
			),
			array(
				'<h2 class="wp-block-heading has-background" style="background-color:#f93b3b">これは見出しです</h2>',
				'<h2 class="wp-block-heading has-background" style="background-color:#f93b3b">これは<sup data-fn="382b3e39-4b0d-4b83-8461-c13f82fdbcfb" class="fn"><a href="#382b3e39-4b0d-4b83-8461-c13f82fdbcfb" id="382b3e39-4b0d-4b83-8461-c13f82fdbcfb-link">1</a></sup>見出しです<sup data-fn="addb0459-a048-453a-9101-dba64f63a630" class="fn"><a href="#addb0459-a048-453a-9101-dba64f63a630" id="addb0459-a048-453a-9101-dba64f63a630-link">2</a></sup></h2>',
				'Footnotes around non latin script should be removed from HTML content.',
			),
		);
	}
}
