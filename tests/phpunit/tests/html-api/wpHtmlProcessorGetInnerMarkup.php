<?php
/**
 * Unit tests covering WP_HTML_Processor::get_raw_inner_markup()
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.4.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorGetInnerMarkup extends WP_UnitTestCase {
	/**
	 * @ticket {TICKET_NUMBER}
	 *
	 * @covers WP_HTML_Processor::get_raw_inner_markup
	 *
	 * @since 6.4.0
	 */
	public function test_returns_null_when_not_on_a_matching_tag() {
		$p = WP_HTML_Processor::createFragment( '<p><div><span></span></div>' );

		$this->assertNull( $p->get_raw_inner_markup() );

		$this->assertFalse( $p->next_tag( 'BUTTON' ), "Should not have found a BUTTON tag but stopped at {$p->get_tag()}." );
		$this->assertNull( $p->get_raw_inner_markup() );
	}

	/**
	 * @ticket {TICKET_NUMBER}
	 *
	 * @covers WP_HTML_Processor::get_raw_inner_markup
	 *
	 * @dataProvider data_html_with_inner_markup
	 *
	 * @since 6.4.0
	 *
	 * @param string $html_with_target_node HTML containing a node with the `target` attribute set.
	 * @param string $expected_inner_markup Inner markup of target node.
	 */
	public function test_returns_appropriate_inner_markup( $html_with_target_node, $expected_inner_markup ) {
		$p = WP_HTML_Processor::createFragment( $html_with_target_node );

		while ( $p->next_tag() && null === $p->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame( $expected_inner_markup, $p->get_raw_inner_markup(), 'Failed to return appropriate inner markup.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_html_with_inner_markup() {
		$data = array(
			'Void elements'                => array( '<img target>', '' ),
			'Empty elements'               => array( '<div target></div>', '' ),
			'Element containing only text' => array( '<div target>inside</div>', 'inside' ),
			'Element with nested tags'     => array( '<div target>inside <span>the</span> div</div>', 'inside <span>the</span> div' ),
			'Unclosed element'             => array( '<div target>This is <em>all</em> inside the DIV', 'This is <em>all</em> inside the DIV' ),
			'Unclosed elements'            => array( '<div><div target>Inside <em>P</em> <i>tags</div>', 'Inside <em>P</em> <i>tags' ),
			'Partially-closed element'     => array( '<div target>This is <em>all</em> inside the DIV</div', 'This is <em>all</em> inside the DIV</div' ),
			'Implicitly-closed element'    => array( '<div><p target>Inside the P</div>Outside the P</p>', 'Inside the P' ),
		);

		$inner_html = <<<HTML
			<p>This is inside the <strong>Match</strong></p>
			<p><img></p>
			<div>
				<figure>
					<img>
					<figcaption>Look at the <strike>picture</strike> photograph.</figcaption>
				</figure>
			</div>
HTML;

		$html = <<<HTML
			<div>
				 <p>This is not in the match.
				 <p>This is another paragraph not <a href="#">in</a> the match.
			</div>
			<div target>{$inner_html}</div>
			<div>
				 <p>This is also note in the match.</p>
			</div>
HTML;

		$data['Complicated inner nesting'] = array( $html, $inner_html );

		return $data;
	}

	/**
	 * Ensures that the cursor isn't moved when getting the inner markup.
	 * It should remain at the tag opener from where it was called.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @covers WP_HTML_Processor::get_raw_inner_markup
	 *
	 * @since 6.4.0
	 */
	public function test_preserves_cursor() {
		$p = WP_HTML_Processor::createFragment( '<div><p><span target>The <code inner-target>cursor</code> should not move <em>unexpectedly</em>.</span></p></div>' );

		while ( $p->next_tag() && null === $p->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame(
			'The <code inner-target>cursor</code> should not move <em>unexpectedly</em>.',
			$p->get_raw_inner_markup(),
			'Failed to return appropriate inner markup.'
		);

		$this->assertSame( 'SPAN', $p->get_tag(), "Should have remained on SPAN, but found {$p->get_tag()} instead." );
		$this->assertFalse( $p->is_tag_closer(), 'Should have remained on SPAN opening tag, but stopped at closing tag instead.' );

		$p->next_tag();
		$this->assertNotNull( $p->get_attribute( 'inner-target' ), "Expected to move to inner CODE element, but found {$p->get_tag()} instead." );
	}
}
