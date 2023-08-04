<?php
/**
 * Unit tests covering WP_HTML_Processor::set_raw_outer_markup()
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
class Tests_HtmlApi_WpHtmlProcessorSetOuterMarkup extends WP_UnitTestCase {
	/**
	 * @ticket {TICKET_NUMBER}
	 *
	 * @covers WP_HTML_Processor::set_raw_outer_markup
	 *
	 * @dataProvider data_html_with_outer_markup_changes
	 *
	 * @since 6.4.0
	 *
	 * @param string $html_with_target_node HTML containing a node with the `target` attribute set.
	 * @param string $new_markup            HTML for replacing the outer markup of the target node.
	 * @param string $expected_output       New HTMl after replacing outer markup.
	 */
	public function test_replaces_outer_html_appropriately( $html_with_target_node, $new_markup, $expected_output ) {
		$p = WP_HTML_Processor::createFragment( $html_with_target_node );

		while ( $p->next_tag() && null === $p->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertTrue( $p->set_raw_outer_markup( $new_markup ), 'Failed to set outer markup.' );
		$this->assertSame( $expected_output, $p->get_updated_html(), 'Failed to appropriately set outer markup.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_html_with_outer_markup_changes() {
		$data = array(
			'Void element'                        => array( '<img target>', '', '' ),
			'Void element inside text'            => array( 'before<img src="atat.png" loading=lazy target>after', '', 'beforeafter' ),
			'Void element inside another element' => array( '<p>Look at this <img target> graph.</p>', '', '<p>Look at this  graph.</p>' ),
			'Empty elements'                      => array( '<div target></div>', '', '' ),
			'Element with nested tags'            => array( '<div target>inside <span>the</span> div</div>', '', '' ),
			'Element inside another element'      => array( '<div>inside <span target>the</span> div</div>', '', '<div>inside  div</div>' ),
			'Unclosed element'                    => array( '<div target>This is <em>all</em> inside the DIV', '', '' ),
			'Unclosed nested element'             => array( '<div><p target>One thought<p>And another', '', '<div><p>And another' ),
			'Partially-closed element'            => array( '<div target>This is <em>all</em> inside the DIV</div', '', '' ),
			'Implicitly-closed element'           => array( '<div><p target>Inside the P</div>Outside the P</p>', '', '<div></div>Outside the P</p>' ),
		);

		/*
		 * Removing the indent on this variable keeps the test easy to reason about,
		 * otherwise extra indents appear after removing the inner content, because
		 * that indentation before and after is whitespace and not part of the tag.
		 */
		$inner_html = <<<HTML
<div target>
	<p>This is inside the <strong>Match</strong></p>
	<p><img></p>
	<div>
		  <figure>
			   <img>
			   <figcaption>Look at the <strike>picture</strike> photograph.</figcaption>
		  </figure>
	</div>
</div>
HTML;

		$prefix = <<<HTML
			<div>
				<p>This is not in the match.
				<p>This is another paragraph not <a href="#">in</a> the match.
			</div>
HTML;

		$suffix = <<<HTML
			<div>
				<p>This is also note in the match.</p>
			</div>
HTML;

		$data['Complicated inner nesting'] = array( $prefix . $inner_html . $suffix, '', $prefix . $suffix );

		return $data;
	}

	/**
	 * Ensures that the cursor isn't moved when setting the outer markup. It should
	 * remain at the same location as the tag opener where it was called.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @covers WP_HTML_Processor::set_raw_outer_markup
	 *
	 * @since 6.4.0
	 */
	public function test_preserves_cursor() {
		$p = WP_HTML_Processor::createFragment( '<div><p><span>The <code target>cursor</code> should not move <em next-target>unexpectedly</em>.</span></p></div>' );

		while ( $p->next_tag() && null === $p->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertTrue( $p->set_raw_outer_markup( '<img>' ) );
		$this->assertSame(
			'<div><p><span>The <img> should not move <em next-target>unexpectedly</em>.</span></p></div>',
			$p->get_updated_html(),
			'Failed to replace appropriate outer markup.'
		);

		$this->assertSame( 'IMG', $p->get_tag(), "Should have remained on IMG, but found {$p->get_tag()} instead." );

		$p->next_tag();
		$this->assertNotNull( $p->get_attribute( 'next-target' ), "Expected to move to following EM element, but found {$p->get_tag()} instead." );
	}
}
