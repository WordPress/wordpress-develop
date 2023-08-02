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

	/**
	 * Verifies that when encountering an end tag for which there is no corresponding
	 * element in scope, that it skips the tag entirely.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @since 6.4.0
	 *
	 * @throws Exception
	 */
	public function test_in_body_skips_unexpected_button_closer() {
		$p = WP_HTML_Processor::createFragment( '<div>Test</button></div>' );

		$p->step();
		$this->assertEquals( 'DIV', $p->get_tag(), 'Did not stop at initial DIV tag.' );
		$this->assertFalse( $p->is_tag_closer(), 'Did not find that initial DIV tag is an opener.' );

		$this->assertTrue( $p->step(), 'Found no further tags when it should have found the closing DIV' );
		$this->assertEquals( 'DIV', $p->get_tag(), "Did not skip unexpected BUTTON; stopped at {$p->get_tag()}." );
		$this->assertTrue( $p->is_tag_closer(), 'Did not find that the terminal DIV tag is a closer.' );
	}

	/**
	 * Verifies insertion of a BUTTON element when no existing BUTTON is already in scope.
	 *
	 * @ticket 58961
	 *
	 * @since 6.4.0
	 *
	 * @throws WP_HTML_Unsupported_Exception
	 */
	public function test_in_body_button_with_no_button_in_scope() {
		$p = WP_HTML_Processor::createFragment( '<div><p>Click the button <button one>here</button>!</p></div><button two>not here</button>' );

		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $p->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $p->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );
	}

	/**
	 * Verifies what when inserting a BUTTON element, when a BUTTON is already in scope,
	 * that the open button is closed with all other elements inside of it.
	 *
	 * @ticket 58961
	 *
	 * @since 6.4.0
	 *
	 * @throws WP_HTML_Unsupported_Exception
	 */
	public function test_in_body_button_with_button_in_scope_as_parent() {
		$p = WP_HTML_Processor::createFragment( '<div><p>Click the button <button one>almost<button two>here</button>!</p></div><button three>not here</button>' );

		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $p->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $p->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );

		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected third button.' );
		$this->assertTrue( $p->get_attribute( 'three' ), 'Failed to match expected attribute on third button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for third button.' );
	}

	/**
	 * Verifies what when inserting a BUTTON element, when a BUTTON is already in scope,
	 * that the open button is closed with all other elements inside of it, even if the
	 * BUTTON in scope is not a direct parent of the new BUTTON element.
	 *
	 * @ticket 58961
	 *
	 * @since 6.4.0
	 *
	 * @throws WP_HTML_Unsupported_Exception
	 */
	public function test_in_body_button_with_button_in_scope_as_ancestor() {
		$p = WP_HTML_Processor::createFragment( '<div><button one><p>Click the button <span><button two>here</button>!</span></p></div><button three>not here</button>' );

		// This button finds itself normally nesting inside the DIV.
		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $p->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		/*
		 * Because the second button appears while a BUTTON is in scope, it generates implied end tags and closes
		 * the BUTTON, P, and SPAN elements. It looks like the BUTTON is inside the SPAN, but we have another case
		 * of an unexpected closing SPAN tag because the SPAN was closed by the second BUTTON. This element finds
		 * itself a child of the most-recent open element above the most-recent BUTTON, or the DIV.
		 */
		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $p->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );

		// The third button is back to normal, because everything has been implicitly or explicitly closed by now.
		$this->assertTrue( $p->next_tag( 'BUTTON' ), 'Could not find expected third button.' );
		$this->assertTrue( $p->get_attribute( 'three' ), 'Failed to match expected attribute on third button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting for third button.' );
	}

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

		$this->assertTrue( $p->step(), 'Failed to advance past CODE tag to expected SPAN closer.' );
		$this->assertTrue( $p->is_tag_closer(), 'Expected to find closing SPAN, but found opener instead.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $p->get_breadcrumbs(), 'Failed to advance past CODE tag to expected DIV opener.' );

		$this->assertTrue( $p->next_tag(), 'Failed to advance past SPAN closer to expected DIV opener.' );
		$this->assertSame( 'DIV', $p->get_tag(), "Expected to find DIV element, but found {$p->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'DIV' ), $p->get_breadcrumbs(), 'Failed to produce expected DOM nesting: SPAN should be closed and DIV should be its sibling.' );
	}
}
