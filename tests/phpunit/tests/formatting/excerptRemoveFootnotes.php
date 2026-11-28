<?php

/**
 * Tests for excerpt_remove_footnotes().
 *
 * @group formatting
 * @group blocks
 *
 * @covers ::excerpt_remove_footnotes
 */
class Tests_Formatting_ExcerptRemoveFootnotes extends WP_UnitTestCase {

	/**
	 * Footnote markup should be removed regardless of tag and attribute name casing.
	 *
	 * @ticket 66839
	 *
	 * @dataProvider data_footnotes_with_any_casing
	 *
	 * @param string $content  Content containing footnote markup.
	 * @param string $expected Content with the footnotes removed.
	 */
	public function test_removes_footnotes_regardless_of_casing( $content, $expected ) {
		$this->assertSame( $expected, excerpt_remove_footnotes( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_footnotes_with_any_casing() {
		return array(
			'lowercase (baseline)'                 => array(
				'content'  => '<p>Never say never<sup data-fn="abc-123" class="fn"><a href="#abc-123" id="abc-123-link">1</a></sup>.</p>',
				'expected' => '<p>Never say never.</p>',
			),
			'uppercase tags and attributes'        => array(
				'content'  => '<p>Never say never<SUP DATA-FN="abc-123" CLASS="fn"><A HREF="#abc-123" ID="abc-123-link">1</A></SUP>.</p>',
				'expected' => '<p>Never say never.</p>',
			),
			'mixed-case attribute names'           => array(
				'content'  => '<p>Never say never<sup Data-Fn="abc-123" Class="fn"><a href="#abc-123" id="abc-123-link">1</a></sup>.</p>',
				'expected' => '<p>Never say never.</p>',
			),
			'uppercase attribute on lowercase tag' => array(
				'content'  => '<p>Never say never<sup DATA-FN="abc-123" class="fn"><a href="#abc-123" id="abc-123-link">1</a></sup>.</p>',
				'expected' => '<p>Never say never.</p>',
			),
			'multiple footnotes, none lowercase'   => array(
				'content'  => '<p>One<SUP DATA-FN="a-1" CLASS="fn"><A HREF="#a-1" ID="a-1-link">1</A></SUP> and two<sup Data-Fn="b-2" class="fn"><a href="#b-2" id="b-2-link">2</a></sup>.</p>',
				'expected' => '<p>One and two.</p>',
			),
		);
	}

	/**
	 * Content that mentions "data-fn" without containing a footnote should be left unchanged.
	 *
	 * @ticket 66839
	 *
	 * @dataProvider data_content_without_footnotes
	 *
	 * @param string $content Content without footnote markup.
	 */
	public function test_leaves_non_footnote_content_unchanged( $content ) {
		$this->assertSame( $content, excerpt_remove_footnotes( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_content_without_footnotes() {
		return array(
			'lowercase mention in prose'     => array( '<p>The data-fn attribute marks a footnote.</p>' ),
			'uppercase mention in prose'     => array( '<p>The DATA-FN attribute marks a footnote.</p>' ),
			'escaped markup in a code block' => array( '<pre><code>&lt;sup DATA-FN="x"&gt;1&lt;/sup&gt;</code></pre>' ),
			'data-fn on a non-sup element'   => array( '<p>Text<span DATA-FN="x">1</span>.</p>' ),
		);
	}
}
