<?php
/**
 * Unit tests covering WP_HTML_Processor compliance with HTML5 semantic parsing rules.
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
class Tests_HtmlApi_WpHtmlProcessorSemanticRules extends WP_UnitTestCase {
	/*******************************************************************
	 * RULES FOR "IN BODY" MODE
	 *******************************************************************/

	/*
	 * Verifies that when "in body" and encountering "any other end tag"
	 * that the HTML processor ignores the end tag if there's a special
	 * element on the stack of open elements before the matching opening.
	 *
	 * @ticket 58907
	 *
	 * @since 6.4.0
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 */
	public function test_in_body_any_other_end_tag_with_unclosed_special_element() {
		$p = WP_HTML_Processor::createFragment( '<div><span><p></span><div>' );

		$p->next_tag( 'P' );
		$this->assertSame( 'P', $p->get_tag(), "Expected to start test on P element but found {$p->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'P' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting.' );

		$this->assertTrue( $p->next_tag(), 'Failed to advance past P tag to expected DIV opener.' );
		$this->assertSame( 'DIV', $p->get_tag(), "Expected to find DIV element, but found {$p->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'DIV' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting: SPAN should still be open and DIV should be its child.' );
	}

	/*
	 * Verifies that when "in body" and encountering "any other end tag"
	 * that the HTML processor closes appropriate elements on the stack of
	 * open elements up to the matching opening.
	 *
	 * @ticket 58907
	 *
	 * @since 6.4.0
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 */
	public function test_in_body_any_other_end_tag_with_unclosed_non_special_element() {
		$p = WP_HTML_Processor::createFragment( '<div><span><code></span><div>' );

		$p->next_tag( 'CODE' );
		$this->assertSame( 'CODE', $p->get_tag(), "Expected to start test on CODE element but found {$p->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'CODE' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting.' );

		$this->assertTrue( $p->next_tag(), 'Failed to advance past CODE tag to expected SPAN closer.' );
		$this->assertTrue( $p->is_tag_closer(), 'Expected to find closing SPAN, but found opener instead.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $p->get_breadcrumbs(), 'Failed to advance past CODE tag to expected DIV opener.' );

		$this->assertTrue( $p->next_tag(), 'Failed to advance past SPAN closer to expected DIV opener.' );
		$this->assertSame( 'DIV', $p->get_tag(), "Expected to find DIV element, but found {$p->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'DIV' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting: SPAN should be closed and DIV should be its sibling.' );
	}
}
