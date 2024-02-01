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
class Tests_HtmlApi_WpHtmlProcessor extends WP_UnitTestCase {
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
	 * Once stepping to the end of the document, WP_HTML_Processor::get_tag
	 * should no longer report a tag. It should report `null` because there
	 * is no tag matched or open.
	 *
	 * @ticket 59167
	 *
	 * @covers WP_HTML_Processor::get_tag
	 */
	public function test_get_tag_is_null_once_document_is_finished() {
		$processor = WP_HTML_Processor::create_fragment( '<div class="test">Test</div>' );
		$processor->next_tag();
		$this->assertSame( 'DIV', $processor->get_tag() );

		$this->assertFalse( $processor->next_tag() );
		$this->assertNull( $processor->get_tag() );
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
	 */
	public function test_clear_to_navigate_after_seeking() {
		$processor = WP_HTML_Processor::create_fragment( '<div one><strong></strong></div><p><strong two></strong></p>' );

		while ( $processor->next_tag() ) {
			// Create a bookmark before entering a stack of elements and formatting elements.
			if ( null !== $processor->get_attribute( 'one' ) ) {
				$this->assertTrue( $processor->set_bookmark( 'one' ) );
				continue;
			}

			// Create a bookmark inside of that stack.
			if ( null !== $processor->get_attribute( 'two' ) ) {
				$processor->set_bookmark( 'two' );
				break;
			}
		}

		// Ensure that it's possible to seek back to the outside location.
		$this->assertTrue( $processor->seek( 'one' ), 'Could not seek to earlier-seen location.' );
		$this->assertSame( 'DIV', $processor->get_tag(), "Should have jumped back to DIV but found {$processor->get_tag()} instead." );

		/*
		 * Ensure that the P element from the inner location isn't still on the stack of open elements.
		 * If it were, then the first STRONG element, inside the outer DIV would match the next call.
		 */
		$this->assertTrue( $processor->next_tag( array( 'breadcrumbs' => array( 'P', 'STRONG' ) ) ), 'Failed to find given location after seeking.' );

		// Only if the stack is properly managed will the processor advance to the inner STRONG element.
		$this->assertTrue( $processor->get_attribute( 'two' ), "Found the wrong location given the breadcrumbs, at {$processor->get_tag()}." );

		// Ensure that in seeking backwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $processor->seek( 'one' ), 'Failed to jump back to first bookmark.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $processor->get_breadcrumbs(), 'Found wrong set of breadcrumbs navigating to node "one".' );

		// Ensure that in seeking forwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $processor->seek( 'two' ), 'Failed to jump forward to second bookmark.' );
		$this->assertTrue( $processor->get_attribute( 'two' ), "Found the wrong location given the bookmark, at {$processor->get_tag()}." );

		$this->assertSame( array( 'HTML', 'BODY', 'P', 'STRONG' ), $processor->get_breadcrumbs(), 'Found wrong set of bookmarks navigating to node "two".' );
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
		$processor = WP_HTML_Processor::create_fragment( '<p><em>One<p><em>Two<p><em>Three<p><em>Four' );

		$this->assertTrue( $processor->next_tag( 'EM' ), 'Could not find first EM.' );
		$this->assertFalse( $processor->next_tag( 'EM' ), 'Should have aborted before finding second EM as it required reconstructing the first EM.' );
	}

	/**
	 * Ensure non-nesting tags do not nest.
	 *
	 * @ticket 60283
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 * @covers WP_HTML_Processor::is_void
	 *
	 * @dataProvider data_void_tags
	 *
	 * @param string $tag_name Name of void tag under test.
	 */
	public function test_cannot_nest_void_tags( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<{$tag_name}><div>" );

		/*
		 * This HTML represents the same as the following HTML,
		 * assuming that it were provided `<img>` as the tag:
		 *
		 *     <html>
		 *         <body>
		 *             <img>
		 *             <div></div>
		 *         </body>
		 *     </html>
		 */

		$found_tag = $processor->next_tag();

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $processor->get_last_error() ) {
			$this->markTestSkipped( "Tag {$tag_name} is not supported." );
		}

		$this->assertTrue(
			$found_tag,
			"Could not find first {$tag_name}."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			'Found incorrect nesting of first element.'
		);

		$this->assertTrue(
			$processor->next_tag(),
			'Should have found the DIV as the second tag.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV' ),
			$processor->get_breadcrumbs(),
			"DIV should have been a sibling of the {$tag_name}."
		);
	}

	/**
	 * Ensure non-nesting tags do not nest when processing tokens.
	 *
	 * @ticket 60382
	 *
	 * @dataProvider data_void_tags
	 *
	 * @param string $tag_name Name of void tag under test.
	 */
	public function test_cannot_nest_void_tags_next_token( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<{$tag_name}><div>" );

		/*
		 * This HTML represents the same as the following HTML,
		 * assuming that it were provided `<img>` as the tag:
		 *
		 *     <html>
		 *         <body>
		 *             <img>
		 *             <div></div>
		 *         </body>
		 *     </html>
		 */

		$found_tag = $processor->next_token();

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $processor->get_last_error() ) {
			$this->markTestSkipped( "Tag {$tag_name} is not supported." );
		}

		$this->assertTrue(
			$found_tag,
			"Could not find first {$tag_name}."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			'Found incorrect nesting of first element.'
		);

		$this->assertTrue(
			$processor->next_token(),
			'Should have found the DIV as the second tag.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV' ),
			$processor->get_breadcrumbs(),
			"DIV should have been a sibling of the {$tag_name}."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_void_tags() {
		return array(
			'AREA'   => array( 'AREA' ),
			'BASE'   => array( 'BASE' ),
			'BR'     => array( 'BR' ),
			'COL'    => array( 'COL' ),
			'EMBED'  => array( 'EMBED' ),
			'HR'     => array( 'HR' ),
			'IMG'    => array( 'IMG' ),
			'INPUT'  => array( 'INPUT' ),
			'KEYGEN' => array( 'KEYGEN' ),
			'LINK'   => array( 'LINK' ),
			'META'   => array( 'META' ),
			'PARAM'  => array( 'PARAM' ),
			'SOURCE' => array( 'SOURCE' ),
			'TRACK'  => array( 'TRACK' ),
			'WBR'    => array( 'WBR' ),
		);
	}

	/**
	 * Ensures that special handling of unsupported tags is cleaned up
	 * as handling is implemented. Otherwise there's risk of leaving special
	 * handling (that is never reached) when tag handling is implemented.
	 *
	 * @ticket 60092
	 *
	 * @dataProvider data_unsupported_special_in_body_tags
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @param string $tag_name Name of the tag to test.
	 */
	public function test_step_in_body_fails_on_unsupported_tags( $tag_name ) {
		$fragment = WP_HTML_Processor::create_fragment( '<' . $tag_name . '></' . $tag_name . '>' );
		$this->assertFalse( $fragment->next_tag(), 'Should fail to find tag: ' . $tag_name . '.' );
		$this->assertEquals( $fragment->get_last_error(), WP_HTML_Processor::ERROR_UNSUPPORTED, 'Should have unsupported last error.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unsupported_special_in_body_tags() {
		return array(
			'APPLET'    => array( 'APPLET' ),
			'BASE'      => array( 'BASE' ),
			'BASEFONT'  => array( 'BASEFONT' ),
			'BGSOUND'   => array( 'BGSOUND' ),
			'BODY'      => array( 'BODY' ),
			'CAPTION'   => array( 'CAPTION' ),
			'COL'       => array( 'COL' ),
			'COLGROUP'  => array( 'COLGROUP' ),
			'FORM'      => array( 'FORM' ),
			'FRAME'     => array( 'FRAME' ),
			'FRAMESET'  => array( 'FRAMESET' ),
			'HEAD'      => array( 'HEAD' ),
			'HTML'      => array( 'HTML' ),
			'IFRAME'    => array( 'IFRAME' ),
			'LINK'      => array( 'LINK' ),
			'MARQUEE'   => array( 'MARQUEE' ),
			'MATH'      => array( 'MATH' ),
			'META'      => array( 'META' ),
			'NOBR'      => array( 'NOBR' ),
			'NOEMBED'   => array( 'NOEMBED' ),
			'NOFRAMES'  => array( 'NOFRAMES' ),
			'NOSCRIPT'  => array( 'NOSCRIPT' ),
			'OBJECT'    => array( 'OBJECT' ),
			'OPTGROUP'  => array( 'OPTGROUP' ),
			'OPTION'    => array( 'OPTION' ),
			'PLAINTEXT' => array( 'PLAINTEXT' ),
			'RB'        => array( 'RB' ),
			'RP'        => array( 'RP' ),
			'RT'        => array( 'RT' ),
			'RTC'       => array( 'RTC' ),
			'SARCASM'   => array( 'SARCASM' ),
			'SCRIPT'    => array( 'SCRIPT' ),
			'SELECT'    => array( 'SELECT' ),
			'STYLE'     => array( 'STYLE' ),
			'SVG'       => array( 'SVG' ),
			'TABLE'     => array( 'TABLE' ),
			'TBODY'     => array( 'TBODY' ),
			'TD'        => array( 'TD' ),
			'TEMPLATE'  => array( 'TEMPLATE' ),
			'TEXTAREA'  => array( 'TEXTAREA' ),
			'TFOOT'     => array( 'TFOOT' ),
			'TH'        => array( 'TH' ),
			'THEAD'     => array( 'THEAD' ),
			'TITLE'     => array( 'TITLE' ),
			'TR'        => array( 'TR' ),
			'XMP'       => array( 'XMP' ),
		);
	}
}
