<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
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
class Tests_HtmlApi_wpHtmlProcessor extends WP_UnitTestCase {
	/**
	 * Ensure that the HTML Processor's public constructor function warns a developer to call
	 * the static creator methods instead of directly instantiating a new class.
	 *
	 * The Tag Processor's constructor method is public and PHP doesn't allow changing the
	 * visibility for a method on a subclass, which means that the HTML Processor must
	 * maintain the public interface. However, constructors cannot fail to construct, so
	 * if there are pre-conditions (such as the context node, the encoding form, and the
	 * parsing mode with the HTML Processor) these must be handled through static factory
	 * methods on the class.
	 *
	 * The HTML Processor requires a sentinel string as an optional parameter that hints
	 * at using the static methods. In the absence of the optional parameter it instructs
	 * the callee that it should be using those static methods instead.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::__construct
	 */
	public function test_warns_that_the_static_creator_methods_should_be_called_instead_of_the_public_constructor() {
		$this->setExpectedIncorrectUsage( 'WP_HTML_Processor::__construct' );

		new WP_HTML_Processor( '<p>Light roast.</p>' );

		$this->assertNotNull(
			$this->caught_doing_it_wrong['WP_HTML_Processor::__construct'],
			"Calling the public constructor should warn to call the static creator methods instead, but didn't."
		);
	}

	/**
	 * Ensures that the HTML Processor maintains its internal state through seek calls.
	 *
	 * Because the HTML Processor must track a stack of open elements and active formatting
	 * elements, when it seeks to another location within its document it must adjust those
	 * stacks, its internal state, in such a way that they remain valid after the seek.
	 *
	 * For instance, if currently matched inside an LI element and the Processor seeks to
	 * an earlier location before the parent UL, then it should not report that it's still
	 * inside an open LI element.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::next_tag
	 * @covers WP_HTML_Processor::seek
	 *
	 * @throws WP_HTML_Unsupported_Exception
	 */
	public function test_clear_to_navigate_after_seeking() {
		$p = WP_HTML_Processor::createFragment( '<div one><strong></strong></div><p><strong two></strong></p>' );

		while ( $p->next_tag() ) {
			// Create a bookmark before entering a stack of elements and formatting elements.
			if ( null !== $p->get_attribute( 'one' ) ) {
				$this->assertTrue( $p->set_bookmark( 'one' ) );
				continue;
			}

			// Create a bookmark inside of that stack.
			if ( null !== $p->get_attribute( 'two' ) ) {
				$p->set_bookmark( 'two' );
				break;
			}
		}

		// Ensure that it's possible to seek back to the outside location.
		$this->assertTrue( $p->seek( 'one' ), 'Could not seek to earlier-seen location.' );
		$this->assertSame( 'DIV', $p->get_tag(), "Should have jumped back to DIV but found {$p->get_tag()} instead." );

		/*
		 * Ensure that the P element from the inner location isn't still on the stack of open elements.
		 * If it were, then the first STRONG element, inside the outer DIV would match the next call.
		 */
		$this->assertTrue( $p->next_tag( array( 'breadcrumbs' => array( 'P', 'STRONG' ) ) ), 'Failed to find given location after seeking.' );

		// Only if the stack is properly managed will the processor advance to the inner STRONG element.
		$this->assertTrue( $p->get_attribute( 'two' ), "Found the wrong location given the breadcrumbs, at {$p->get_tag()}." );

		// Ensure that in seeking backwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $p->seek( 'one' ), 'Failed to jump back to first bookmark.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $p->get_breadcrumbs(), 'Found wrong set of breadcrumbs navigating to node "one".' );

		// Ensure that in seeking forwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $p->seek( 'two' ), 'Failed to jump forward to second bookmark.' );
		$this->assertTrue( $p->get_attribute( 'two' ), "Found the wrong location given the bookmark, at {$p->get_tag()}." );

		$this->assertSame( array( 'HTML', 'BODY', 'P', 'STRONG' ), $p->get_breadcrumbs(), 'Found wrong set of bookmarks navigating to node "two".' );
	}

	/**
	 * Ensures that support is added for reconstructing active formatting elements
	 * before the HTML Processor handles situations with unclosed formats requiring it.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::reconstruct_active_formatting_elements
	 */
	public function test_fails_to_reconstruct_formatting_elements() {
		$p = WP_HTML_Processor::createFragment( '<p><em>One<p><em>Two<p><em>Three<p><em>Four' );

		$this->assertTrue( $p->next_tag( 'EM' ), 'Could not find first EM.' );
		$this->assertFalse( $p->next_tag( 'EM' ), 'Should have aborted before finding second EM as it required reconstructing the first EM.' );
	}
}
