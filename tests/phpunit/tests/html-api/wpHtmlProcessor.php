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
	 * @expectedIncorrectUsage WP_HTML_Processor::__construct
	 */
	public function test_warns_that_the_static_creator_methods_should_be_called_instead_of_the_public_constructor() {
		new WP_HTML_Processor( '<p>Light roast.</p>' );
	}

	/**
	 * @ticket 63854
	 *
	 * @covers ::create_fragment
	 * @expectedIncorrectUsage WP_HTML_Processor::create_fragment
	 */
	public function test_create_fragment_validates_html_parameter() {
		$processor = WP_HTML_Processor::create_fragment( null );
		$this->assertNull( $processor );
	}

	/**
	 * @ticket 63854
	 *
	 * @covers ::create_full_parser
	 * @expectedIncorrectUsage WP_HTML_Processor::create_full_parser
	 */
	public function test_create_full_parser_validates_html_parameter() {
		$processor = WP_HTML_Processor::create_full_parser( null );
		$this->assertNull( $processor );
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
	 * Ensures that the proper tag-name remapping happens for the `IMAGE` tag.
	 *
	 * An HTML parser should treat an IMAGE tag as if it were an IMG tag, but
	 * only when found in the HTML namespace. As part of this rule, IMAGE tags
	 * in the HTML namespace are also void elements, while those in foreign
	 * content are not, making the self-closing flag significant.
	 *
	 * Example:
	 *
	 *     // This input...
	 *     <image/><svg><image/></svg>
	 *
	 *     // ...is equivalent to this normative HTML.
	 *     <img><svg><image/></svg>
	 *
	 * @ticket 61576
	 *
	 * @covers WP_HTML_Processor::get_tag
	 */
	public function test_get_tag_replaces_image_with_namespace_awareness() {
		$processor = WP_HTML_Processor::create_fragment( '<image/><svg><image/></svg>' );

		$this->assertTrue(
			$processor->next_tag(),
			'Could not find initial "<image/>" tag: check test setup.'
		);

		$this->assertSame(
			'IMG',
			$processor->get_tag(),
			'HTML tags with the name "IMAGE" should be remapped to "IMG"'
		);

		$this->assertTrue(
			$processor->next_tag(),
			'Could not find "<svg>" tag: check test setup.'
		);

		$this->assertTrue(
			$processor->next_tag(),
			'Could not find SVG "<image/>" tag: check test setup.'
		);

		$this->assertSame(
			'IMAGE',
			$processor->get_tag(),
			'Should not remap "IMAGE" to "IMG" for foreign elements.'
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
				$this->assertTrue( $processor->set_bookmark( 'two' ) );
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
	 * @dataProvider data_void_tags_not_ignored_in_body
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
	 * Ensure reporting that normal non-void HTML elements expect a closer.
	 *
	 * @ticket 61257
	 */
	public function test_expects_closer_regular_tags() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p><b><em>' );

		$tags = 0;
		while ( $processor->next_tag() ) {
			$this->assertTrue(
				$processor->expects_closer(),
				"Should have expected a closer for '{$processor->get_tag()}', but didn't."
			);
			++$tags;
		}

		$this->assertSame(
			4,
			$tags,
			'Did not find all the expected tags.'
		);
	}

	/**
	 * Ensure reporting that non-tag HTML nodes expect a closer.
	 *
	 * @ticket 61257
	 *
	 * @dataProvider data_self_contained_node_tokens
	 *
	 * @param string $self_contained_token String starting with HTML token that doesn't expect a closer,
	 *                                     e.g. an HTML comment, text node, void tag, or special element.
	 */
	public function test_expects_closer_expects_no_closer_for_self_contained_tokens( $self_contained_token ) {
		$processor   = WP_HTML_Processor::create_fragment( $self_contained_token );
		$found_token = $processor->next_token();

		$this->assertTrue(
			$found_token,
			"Failed to find any tokens in '{$self_contained_token}': check test data provider."
		);

		$this->assertFalse(
			$processor->expects_closer(),
			"Incorrectly expected a closer for node of type '{$processor->get_token_type()}'."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_self_contained_node_tokens(): array {
		$self_contained_nodes = array(
			'Normative comment'              => array( '<!-- comment -->' ),
			'Comment with invalid closing'   => array( '<!-- comment --!>' ),
			'CDATA Section lookalike'        => array( '<![CDATA[ comment ]]>' ),
			'Processing Instruction'         => array( '<?ok pi ?>' ),
			'Bogus PI-lookalike xml comment' => array( '<?xml version="1.0"?>' ),
			'Bogus comment'                  => array( '<?🔥?>' ),
			'Funky comment'                  => array( '<//wp:post-meta key=isbn>' ),
			'Text node'                      => array( 'Trombone' ),
		);

		foreach ( self::data_void_tags_not_ignored_in_body() as $tag_name => $_name ) {
			$self_contained_nodes[ "Void elements ({$tag_name})" ] = array( "<{$tag_name}>" );
		}

		foreach ( self::data_special_tags() as $tag_name => $_name ) {
			$self_contained_nodes[ "Special atomic elements ({$tag_name})" ] = array( "<{$tag_name}>content</{$tag_name}>" );
		}

		return $self_contained_nodes;
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_special_tags() {
		return array(
			'IFRAME'   => array( 'IFRAME' ),
			'NOEMBED'  => array( 'NOEMBED' ),
			'NOFRAMES' => array( 'NOFRAMES' ),
			'SCRIPT'   => array( 'SCRIPT' ),
			'STYLE'    => array( 'STYLE' ),
			'TEXTAREA' => array( 'TEXTAREA' ),
			'TITLE'    => array( 'TITLE' ),
			'XMP'      => array( 'XMP' ),
		);
	}

	/**
	 * Ensure non-nesting tags do not nest when processing tokens.
	 *
	 * @ticket 60382
	 *
	 * @dataProvider data_void_tags_not_ignored_in_body
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

		$this->assertTrue(
			$found_tag,
			"Could not find first {$tag_name}."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			'Found incorrect nesting of first element.'
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
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_void_tags_not_ignored_in_body() {
		$all_void_tags = self::data_void_tags();
		unset( $all_void_tags['COL'] );

		return $all_void_tags;
	}

	/**
	 * Ensures that the HTML Processor properly reports the depth of a given element.
	 *
	 * @ticket 61255
	 *
	 * @dataProvider data_html_with_target_element_and_depth_in_body
	 *
	 * @param string $html_with_target_element HTML containing element with `target` class.
	 * @param int    $depth_at_element         Depth into document at target node.
	 */
	public function test_reports_proper_element_depth_in_body( $html_with_target_element, $depth_at_element ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_element );

		$this->assertTrue(
			$processor->next_tag( array( 'class_name' => 'target' ) ),
			'Failed to find target element: check test data provider.'
		);

		$this->assertSame(
			$depth_at_element,
			$processor->get_current_depth(),
			'HTML Processor reported the wrong depth at the matched element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_with_target_element_and_depth_in_body() {
		return array(
			'Single element'                    => array( '<div class="target">', 3 ),
			'Basic layout and formatting stack' => array( '<div><span><p><b><em class="target">', 7 ),
			'Adjacent elements'                 => array( '<div><span></span><span class="target"></div>', 4 ),
		);
	}

	/**
	 * Ensures that the HTML Processor properly reports the depth of a given non-element.
	 *
	 * @ticket 61255
	 *
	 * @dataProvider data_html_with_target_element_and_depth_of_next_node_in_body
	 *
	 * @param string $html_with_target_element HTML containing element with `target` class.
	 * @param int    $depth_after_element      Depth into document immediately after target node.
	 */
	public function test_reports_proper_non_element_depth_in_body( $html_with_target_element, $depth_after_element ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_element );

		$this->assertTrue(
			$processor->next_tag( array( 'class_name' => 'target' ) ),
			'Failed to find target element: check test data provider.'
		);

		$this->assertTrue(
			$processor->next_token(),
			'Failed to find next node after target element: check tests data provider.'
		);

		$this->assertSame(
			$depth_after_element,
			$processor->get_current_depth(),
			'HTML Processor reported the wrong depth after the matched element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_with_target_element_and_depth_of_next_node_in_body() {
		return array(
			'Element then text'                 => array( '<div class="target">One Deeper', 4 ),
			'Basic layout and formatting stack' => array( '<div><span><p><b><em class="target">Formatted', 8 ),
			'Basic layout with text'            => array( '<div>a<span>b<p>c<b>e<em class="target">e', 8 ),
			'Adjacent elements'                 => array( '<div><span></span><span class="target">Here</div>', 5 ),
			'Adjacent text'                     => array( '<p>Before<img class="target">After</p>', 4 ),
			'HTML comment'                      => array( '<img class="target"><!-- this is inside the BODY -->', 3 ),
			'HTML comment in DIV'               => array( '<div class="target"><!-- this is inside the BODY -->', 4 ),
			'Funky comment'                     => array( '<div><p>What <br class="target"><//wp:post-author></p></div>', 5 ),
		);
	}

	/**
	 * Ensures that elements which are unopened at the end of a document are implicitly closed.
	 *
	 * @ticket 61576
	 */
	public function test_closes_unclosed_elements() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p><span>' );

		$this->assertTrue(
			$processor->next_tag( 'SPAN' ),
			'Could not find SPAN element: check test setup.'
		);

		// This is the end of the document, but there should be three closing events.
		$processor->next_token();
		$this->assertSame(
			'SPAN',
			$processor->get_tag(),
			'Should have found implicit SPAN closing tag.'
		);

		$processor->next_token();
		$this->assertSame(
			'P',
			$processor->get_tag(),
			'Should have found implicit P closing tag.'
		);

		$processor->next_token();
		$this->assertSame(
			'DIV',
			$processor->get_tag(),
			'Should have found implicit DIV closing tag.'
		);

		$this->assertFalse(
			$processor->next_token(),
			"Should have failed to find any more tokens but found a '{$processor->get_token_name()}'"
		);
	}

	/**
	 * Ensures that subclasses can be created from ::create_fragment method.
	 *
	 * @ticket 61374
	 */
	public function test_subclass_create_fragment_creates_subclass() {
		$processor = WP_HTML_Processor::create_fragment( '' );
		$this->assertInstanceOf( WP_HTML_Processor::class, $processor, '::create_fragment did not return class instance.' );

		$subclass_instance = new class('') extends WP_HTML_Processor {
			public function __construct( $html ) {
				parent::__construct( $html, parent::CONSTRUCTOR_UNLOCK_CODE );
			}
		};

		$subclass_processor = call_user_func( array( get_class( $subclass_instance ), 'create_fragment' ), '' );
		$this->assertInstanceOf( get_class( $subclass_instance ), $subclass_processor, '::create_fragment did not return subclass instance.' );
	}

	/**
	 * Ensures that self-closing elements in foreign content properly report
	 * that they expect no closer.
	 *
	 * @ticket 61576
	 */
	public function test_expects_closer_foreign_content_self_closing() {
		$processor = WP_HTML_Processor::create_fragment( '<svg /><math>' );

		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SVG', $processor->get_tag() );
		$this->assertFalse( $processor->expects_closer() );

		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'MATH', $processor->get_tag() );
		$this->assertTrue( $processor->expects_closer() );
	}

	/**
	 * Ensures a slash-only unquoted attribute value does not close foreign content.
	 *
	 * @ticket 65372
	 */
	public function test_unquoted_slash_attribute_does_not_self_close_foreign_content(): void {
		$processor = WP_HTML_Processor::create_fragment( '<math><mi a=/>math:mi is not self-closing, it has [a="/"] attribute.' );

		$this->assertTrue( $processor->next_tag( 'MI' ), 'Failed to find the MI tag: check test setup.' );
		$this->assertSame( '/', $processor->get_attribute( 'a' ), 'Failed to treat the slash as the unquoted attribute value.' );
		$this->assertFalse(
			$processor->has_self_closing_flag(),
			'Failed to avoid interpreting the slash-only unquoted attribute value as a self-closing flag.'
		);

		$this->assertTrue( $processor->next_token(), 'Failed to find text following the MI tag: check test setup.' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'MATH', 'MI', '#text' ),
			$processor->get_breadcrumbs(),
			'Failed to keep text following the MI tag inside the MI element.'
		);
	}

	/**
	 * Ensures that expects_closer works for void-like elements in foreign content.
	 *
	 * For example, `<svg><input>text` creates an `svg:input` that contains a text node.
	 * This input should not be treated as a void tag and _should_ expect a close tag.
	 *
	 * @dataProvider data_void_tags
	 *
	 * @ticket 62363
	 */
	public function test_expects_closer_foreign_content_not_void( string $void_tag ) {
		$processor = WP_HTML_Processor::create_fragment( "<svg><{$void_tag}>" );

		$this->assertTrue( $processor->next_tag( $void_tag ) );

		// Some void-like tags will close the SVG element and be HTML tags.
		if ( $processor->get_namespace() === 'svg' ) {
			$this->assertSame( array( 'HTML', 'BODY', 'SVG', $void_tag ), $processor->get_breadcrumbs() );
			$this->assertTrue( $processor->expects_closer() );
		} else {
			$this->assertSame( array( 'HTML', 'BODY', $void_tag ), $processor->get_breadcrumbs() );
			$this->assertFalse( $processor->expects_closer() );
		}
	}

	/**
	 * Ensures that self-closing foreign SCRIPT elements are properly found.
	 *
	 * @ticket 61576
	 */
	public function test_foreign_content_script_self_closing() {
		$processor = WP_HTML_Processor::create_fragment( '<svg><script />' );
		$this->assertTrue( $processor->next_tag( 'script' ) );
	}

	/**
	 * Ensures that the HTML Processor correctly handles TEMPLATE tag closing and namespaces.
	 *
	 * This is a tricky test case that corresponds to the Web Platform Tests fixture "template/line1466".
	 *
	 * When the `</template>` token is reached it is in the HTML namespace (thanks to the
	 * SVG `foreignObject` element). It is not handled as foreign content; therefore, it
	 * closes the open HTML `TEMPLATE` element (the first `<template>` token) - _not_ the
	 * SVG `TEMPLATE` element (the second `<template>` token).
	 *
	 * The test is included here because it may show up as unsupported markup and be skipped by
	 * the Web Platform Tests suite.
	 *
	 * @ticket 61576
	 */
	public function test_template_tag_closes_html_template_element() {
		$processor = WP_HTML_Processor::create_fragment( '<template><svg><template><foreignObject><div></template><div>' );

		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertSame( array( 'HTML', 'BODY', 'TEMPLATE', 'SVG', 'TEMPLATE', 'FOREIGNOBJECT', 'DIV' ), $processor->get_breadcrumbs() );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $processor->get_breadcrumbs() );
	}

	/**
	 * Ensures foreign TEMPLATE elements do not satisfy HTML template handling.
	 *
	 * @ticket 65372
	 */
	public function test_unmatched_template_closer_after_mathml_template_is_ignored() {
		$processor = WP_HTML_Processor::create_fragment( '<math><template><mi><c></template>here' );

		$this->assertTrue( $processor->next_tag( 'C' ), 'Failed to find C tag.' );
		$this->assertTrue( $processor->next_token(), 'Failed to advance past the C tag.' );

		// Closing HTML </template> tag should be ignored, advancing to "here" text without modifying breadcrumbs.
		$this->assertSame( '#text', $processor->get_token_type(), 'Failed to reach text node.' );
		$this->assertSame( 'here', $processor->get_modifiable_text() );
		$this->assertSame(
			array( 'HTML', 'BODY', 'MATH', 'TEMPLATE', 'MI', 'C', '#text' ),
			$processor->get_breadcrumbs(),
		);
	}

	/**
	 * Ensures that the tag processor is case sensitive when removing CSS classes in no-quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::remove_class
	 */
	public function test_remove_class_no_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<!DOCTYPE html><span class="UPPER">' );
		$processor->next_tag( 'SPAN' );
		$processor->remove_class( 'upper' );
		$this->assertSame( '<!DOCTYPE html><span class="UPPER">', $processor->get_updated_html() );

		$processor->remove_class( 'UPPER' );
		$this->assertSame( '<!DOCTYPE html><span >', $processor->get_updated_html() );
	}

	/**
	 * Ensures that the tag processor is case sensitive when adding CSS classes in no-quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::add_class
	 */
	public function test_add_class_no_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<!DOCTYPE html><span class="UPPER">' );
		$processor->next_tag( 'SPAN' );
		$processor->add_class( 'UPPER' );
		$this->assertSame( '<!DOCTYPE html><span class="UPPER">', $processor->get_updated_html() );

		$processor->add_class( 'upper' );
		$this->assertSame( '<!DOCTYPE html><span class="UPPER upper">', $processor->get_updated_html() );
	}

	/**
	 * Ensures that the tag processor is case sensitive when checking has CSS classes in no-quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::has_class
	 */
	public function test_has_class_no_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<!DOCTYPE html><span class="UPPER">' );
		$processor->next_tag( 'SPAN' );
		$this->assertFalse( $processor->has_class( 'upper' ) );
		$this->assertTrue( $processor->has_class( 'UPPER' ) );
	}

	/**
	 * Ensures that the tag processor lists unique CSS class names in no-quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::class_list
	 */
	public function test_class_list_no_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser(
			/*
			 * U+00C9 is LATIN CAPITAL LETTER E WITH ACUTE
			 * U+0045 is LATIN CAPITAL LETTER E
			 * U+0301 is COMBINING ACUTE ACCENT
			 *
			 * This tests not only that the class matching deduplicates the É, but also
			 * that it treats the same character in different normalization forms as
			 * distinct, since matching occurs on a byte-for-byte basis.
			 */
			"<!DOCTYPE html><span class='A A a B b \u{C9} \u{45}\u{0301} \u{C9} é'>"
		);
		$processor->next_tag( 'SPAN' );
		$class_list = iterator_to_array( $processor->class_list() );
		$this->assertSame(
			array( 'A', 'a', 'B', 'b', 'É', "E\u{0301}", 'é' ),
			$class_list
		);
	}

	/**
	 * Ensures that the tag processor is case insensitive when removing CSS classes in quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::remove_class
	 */
	public function test_remove_class_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<span class="uPPER">' );
		$processor->next_tag( 'SPAN' );
		$processor->remove_class( 'upPer' );
		$this->assertSame( '<span >', $processor->get_updated_html() );
	}

	/**
	 * Ensures that the tag processor is case insensitive when adding CSS classes in quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::add_class
	 */
	public function test_add_class_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<span class="UPPER">' );
		$processor->next_tag( 'SPAN' );
		$processor->add_class( 'upper' );

		$this->assertSame( '<span class="UPPER">', $processor->get_updated_html() );

		$processor->add_class( 'ANOTHER-UPPER' );
		$this->assertSame( '<span class="UPPER ANOTHER-UPPER">', $processor->get_updated_html() );
	}

	/**
	 * Ensures that the tag processor is case sensitive when checking has CSS classes in quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::has_class
	 */
	public function test_has_class_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser( '<span class="UPPER">' );
		$processor->next_tag( 'SPAN' );
		$this->assertTrue( $processor->has_class( 'upper' ) );
		$this->assertTrue( $processor->has_class( 'UPPER' ) );
	}

	/**
	 * Ensures that the tag processor lists unique CSS class names in quirks mode.
	 *
	 * @ticket 61531
	 *
	 * @covers ::class_list
	 */
	public function test_class_list_quirks_mode() {
		$processor = WP_HTML_Processor::create_full_parser(
			/*
			 * U+00C9 is LATIN CAPITAL LETTER E WITH ACUTE
			 * U+0045 is LATIN CAPITAL LETTER E
			 * U+0065 is LATIN SMALL LETTER E
			 * U+0301 is COMBINING ACUTE ACCENT
			 *
			 * This tests not only that the class matching deduplicates the É, but also
			 * that it treats the same character in different normalization forms as
			 * distinct, since matching occurs on a byte-for-byte basis.
			 */
			"<span class='A A a B b \u{C9} \u{45}\u{301} \u{C9} é \u{65}\u{301}'>"
		);
		$processor->next_tag( 'SPAN' );
		$class_list = iterator_to_array( $processor->class_list() );
		$this->assertSame(
			array( 'a', 'b', 'É', "e\u{301}", 'é' ),
			$class_list
		);
	}

	/**
	 * Ensures that the processor correctly adjusts the namespace
	 * for elements inside HTML integration points.
	 *
	 * @ticket 61576
	 */
	public function test_adjusts_for_html_integration_points_in_svg() {
		$processor = WP_HTML_Processor::create_full_parser(
			'<svg><foreignobject><image /><svg /><image />'
		);

		// At the foreignObject, the processor is in the SVG namespace.
		$this->assertTrue(
			$processor->next_tag( 'foreignObject' ),
			'Failed to find "foreignObject" under test: check test setup.'
		);

		$this->assertSame(
			'svg',
			$processor->get_namespace(),
			'Found the wrong namespace for the "foreignObject" element.'
		);

		/*
		 * The IMAGE tag should be handled according to HTML processing rules
		 * and transformted to an IMG tag because `foreignObject` is an HTML
		 * integration point. At this point, the processor is entering the HTML
		 * integration point.
		 */
		$this->assertTrue(
			$processor->next_tag( 'IMG' ),
			'Failed to find expected "IMG" tag from "<IMAGE>" source tag.'
		);

		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'Found the wrong namespace for the transformed "IMAGE"/"IMG" element.'
		);

		/*
		 * Again, the IMAGE tag should be handled according to HTML processing
		 * rules and transformted to an IMG tag because `foreignObject` is an
		 * HTML integration point. At this point, the processor is has entered
		 * SVG and is returning to an HTML integration point.
		 */
		$this->assertTrue(
			$processor->next_tag( 'IMG' ),
			'Failed to find expected "IMG" tag from "<IMAGE>" source tag.'
		);

		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'Found the wrong namespace for the transformed "IMAGE"/"IMG" element.'
		);
	}

	/**
	 * Ensures that CDATA sections remain available inside SVG HTML integration points.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_sections_in_svg_html_integration_points() {
		$processor = WP_HTML_Processor::create_fragment(
			'<svg><foreignObject><![CDATA[foo]]></foreignObject></svg>'
		);

		$this->assertTrue(
			$processor->next_tag( 'foreignObject' ),
			'Failed to find the foreignObject element under test.'
		);
		$this->assertSame( 'svg', $processor->get_namespace(), 'Found the wrong namespace for the foreignObject element.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			'CDATA should remain available at an SVG HTML integration point.'
		);
		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'A CDATA section at an integration point is processed in the current insertion mode and reports the same namespace as a text node there.'
		);
		$this->assertSame( 'foo', $processor->get_modifiable_text(), 'Found incorrect CDATA content.' );
	}

	/**
	 * Ensures that the processor correctly adjusts the namespace
	 * for elements inside MathML integration points.
	 *
	 * @ticket 61576
	 */
	public function test_adjusts_for_mathml_integration_points() {
		$processor = WP_HTML_Processor::create_fragment(
			'<mo><image /></mo><math><image /><mo><image /></mo></math>'
		);

		// Advance token-by-token to ensure matching the right raw "<image />" token.
		$processor->next_token(); // Advance past the +MO.
		$processor->next_token(); // Advance into the +IMG.

		$this->assertSame(
			'IMG',
			$processor->get_tag(),
			'Failed to find expected "IMG" tag from "<IMAGE>" source tag.'
		);

		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'Found the wrong namespace for the transformed "IMAGE"/"IMG" element.'
		);

		// Advance token-by-token to ensure matching the right raw "<image />" token.
		$processor->next_token(); // Advance past the -MO.
		$processor->next_token(); // Advance past the +MATH.
		$processor->next_token(); // Advance into the +IMAGE.

		$this->assertSame(
			'IMAGE',
			$processor->get_tag(),
			'Failed to find the un-transformed "<image />" tag.'
		);

		$this->assertSame(
			'math',
			$processor->get_namespace(),
			'Found the wrong namespace for the transformed "IMAGE"/"IMG" element.'
		);

		$processor->next_token(); // Advance past the +MO.
		$processor->next_token(); // Advance into the +IMG.

		$this->assertSame(
			'IMG',
			$processor->get_tag(),
			'Failed to find expected "IMG" tag from "<IMAGE>" source tag.'
		);

		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'Found the wrong namespace for the transformed "IMAGE"/"IMG" element.'
		);
	}

	/**
	 * Ensures that CDATA sections remain available inside MathML HTML integration points.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_sections_in_mathml_html_integration_points() {
		$processor = WP_HTML_Processor::create_fragment(
			'<math><annotation-xml encoding="text/html"><![CDATA[x]]></annotation-xml></math>'
		);

		$this->assertTrue(
			$processor->next_tag( 'ANNOTATION-XML' ),
			'Failed to find the ANNOTATION-XML element under test.'
		);
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			'CDATA should remain available at a MathML HTML integration point.'
		);
		$this->assertSame(
			'html',
			$processor->get_namespace(),
			'A CDATA section at an integration point is processed in the current insertion mode and reports the same namespace as a text node there.'
		);
		$this->assertSame( 'x', $processor->get_modifiable_text(), 'Found incorrect CDATA content.' );
	}

	/**
	 * Ensures that CDATA parsing context is restored after leaving an HTML child.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_context_restored_after_html_child_of_integration_point() {
		$processor = WP_HTML_Processor::create_fragment(
			'<svg><foreignObject><p>HTML</p><![CDATA[SVG]]></foreignObject></svg>'
		);

		$this->assertTrue( $processor->next_tag( 'P' ), 'Failed to find the P element under test.' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'P',
					'tag_closers' => 'visit',
				)
			),
			'Failed to find the P element closer under test.'
		);
		$this->assertTrue( $processor->is_tag_closer(), 'Expected to stop on the P element closer.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			'CDATA should be available again after leaving an HTML child of an integration point.'
		);
		$this->assertSame( 'SVG', $processor->get_modifiable_text(), 'Found incorrect CDATA content.' );
	}

	/**
	 * Ensures that seeking restores the CDATA parsing context.
	 *
	 * @ticket 65967
	 */
	public function test_seek_restores_cdata_context() {
		$processor = WP_HTML_Processor::create_fragment(
			'<svg><title><![CDATA[title]]></title><path></path></svg>'
		);

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		$this->assertTrue( $processor->set_bookmark( 'title' ), 'Failed to bookmark the TITLE element.' );
		$this->assertTrue( $processor->next_tag( 'PATH' ), 'Failed to advance beyond the bookmarked TITLE element.' );
		$this->assertTrue( $processor->seek( 'title' ), 'Failed to seek back to the bookmarked TITLE element.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section after seeking.' );
		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			'Seeking should restore the CDATA parsing context at an integration point.'
		);
		$this->assertSame( 'title', $processor->get_modifiable_text(), 'Found incorrect CDATA content after seeking.' );
	}

	/**
	 * Ensures that NULL bytes in a CDATA section at an HTML integration point are removed.
	 *
	 * Character tokens at an integration point are processed in the current
	 * insertion mode, where a NULL character token is ignored.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_section_at_integration_point_removes_null_bytes() {
		$processor = WP_HTML_Processor::create_fragment( "<svg><title><![CDATA[a\0b]]></title></svg>" );

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame( '#cdata-section', $processor->get_token_name(), 'Failed to find the expected CDATA section.' );
		$this->assertSame( 'html', $processor->get_namespace(), 'A character token at an integration point should report the HTML namespace.' );
		$this->assertSame( 'ab', $processor->get_modifiable_text(), 'NULL bytes should be removed from a CDATA section at an integration point.' );
	}

	/**
	 * Ensures that NULL bytes in a CDATA section in foreign content are replaced.
	 *
	 * Character tokens in foreign content are processed by the rules for
	 * parsing tokens in foreign content, where a NULL character token is
	 * replaced by U+FFFD REPLACEMENT CHARACTER.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_section_in_foreign_content_replaces_null_bytes() {
		$processor = WP_HTML_Processor::create_fragment( "<svg><![CDATA[a\0b]]></svg>" );

		$this->assertTrue( $processor->next_tag( 'SVG' ), 'Failed to find the SVG element under test.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame( '#cdata-section', $processor->get_token_name(), 'Failed to find the expected CDATA section.' );
		$this->assertSame( 'svg', $processor->get_namespace(), 'A character token in foreign content should report the foreign namespace.' );
		$this->assertSame( "a\u{FFFD}b", $processor->get_modifiable_text(), 'NULL bytes should be replaced in a CDATA section in foreign content.' );
	}

	/**
	 * Ensures that a CDATA section holding only NULL bytes is ignored at an integration point.
	 *
	 * @ticket 65967
	 */
	public function test_cdata_section_of_only_null_bytes_is_ignored_at_integration_point() {
		$processor = WP_HTML_Processor::create_fragment( "<svg><title><![CDATA[\0]]><b></b></title></svg>" );

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find the token after the ignored CDATA section.' );
		$this->assertSame( 'B', $processor->get_tag(), 'A CDATA section of only NULL bytes should be ignored at an integration point.' );
	}

	/**
	 * Ensures that text and CDATA sections at an integration point report the same namespace.
	 *
	 * @ticket 65967
	 */
	public function test_character_tokens_at_integration_point_share_namespace() {
		$processor = WP_HTML_Processor::create_fragment( '<svg><title>a<![CDATA[b]]>c</title></svg>' );

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );

		$expected = array(
			array( '#text', 'a' ),
			array( '#cdata-section', 'b' ),
			array( '#text', 'c' ),
		);
		foreach ( $expected as list( $token_name, $text ) ) {
			$this->assertTrue( $processor->next_token(), "Failed to find the expected {$token_name} token." );
			$this->assertSame( $token_name, $processor->get_token_name(), 'Found the wrong token.' );
			$this->assertSame( $text, $processor->get_modifiable_text(), "Found incorrect {$token_name} content." );
			$this->assertSame( 'html', $processor->get_namespace(), "A {$token_name} token at an integration point should report the HTML namespace." );
		}
	}

	/**
	 * Ensures that consecutive CDATA sections at an integration point are each recognized.
	 *
	 * @ticket 65967
	 */
	public function test_consecutive_cdata_sections_at_integration_point() {
		$processor = WP_HTML_Processor::create_fragment( '<svg><title><![CDATA[a]]><![CDATA[b]]></title></svg>' );

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		foreach ( array( 'a', 'b' ) as $text ) {
			$this->assertTrue( $processor->next_token(), 'Failed to find the expected CDATA section.' );
			$this->assertSame( '#cdata-section', $processor->get_token_name(), 'Failed to find the expected CDATA section.' );
			$this->assertSame( $text, $processor->get_modifiable_text(), 'Found incorrect CDATA content.' );
		}
	}

	/**
	 * Ensures that a CDATA section at an integration point follows the text node rules for active formatting elements.
	 *
	 * The B element is closed by the P closer but remains in the list of active
	 * formatting elements, so the character tokens that follow must reconstruct
	 * it: the tree is `<p><b>x</b></p><b>y</b>`. Reconstruction is not yet
	 * supported by the HTML Processor (see #61576), so parsing must stop at the
	 * CDATA section exactly as it stops at the same content in a text node,
	 * instead of inserting the section outside the B element.
	 *
	 * @ticket 65967
	 *
	 * @dataProvider data_character_tokens_requiring_reconstruction_at_integration_point
	 *
	 * @param string $html Fragment whose final character tokens require reconstruction.
	 */
	public function test_cdata_section_at_integration_point_stops_when_reconstruction_is_required( string $html ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'P',
					'tag_closers' => 'visit',
				)
			),
			'Failed to find the P element under test.'
		);
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'P',
					'tag_closers' => 'visit',
				)
			),
			'Failed to find the P element closer under test.'
		);
		$this->assertTrue( $processor->is_tag_closer(), 'Expected to stop on the P element closer.' );

		$this->assertFalse( $processor->next_token(), 'Should have stopped at the character tokens requiring reconstruction.' );
		$this->assertSame(
			WP_HTML_Processor::ERROR_UNSUPPORTED,
			$processor->get_last_error(),
			'Should have reported unsupported markup instead of inserting the character tokens outside the formatting element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_character_tokens_requiring_reconstruction_at_integration_point() {
		return array(
			'Text node'     => array( '<svg><foreignObject><p><b>x</p>y</foreignObject></svg>' ),
			'CDATA section' => array( '<svg><foreignObject><p><b>x</p><![CDATA[y]]></foreignObject></svg>' ),
			'MathML text'   => array( '<math><mtext><p><b>x</p>y</mtext></math>' ),
			'MathML CDATA'  => array( '<math><mtext><p><b>x</p><![CDATA[y]]></mtext></math>' ),
		);
	}

	/**
	 * Ensures that a whitespace-only CDATA section at an integration point keeps the insertion mode after the body.
	 *
	 * In the "after body" and "after after body" insertion modes, whitespace-only
	 * character tokens are processed using the rules for "in body" and the
	 * insertion mode is kept, while any other token switches the insertion mode
	 * back to "in body". Foreign content stays open after the BODY closer, so
	 * character tokens at an integration point reach these modes.
	 *
	 * The comment after the foreign content is processed in the kept mode, where
	 * comments are not supported. If a whitespace-only CDATA section switched the
	 * mode, the comment would be inserted into the BODY element instead.
	 *
	 * @ticket 65967
	 *
	 * @dataProvider data_whitespace_character_tokens_at_integration_point_after_body
	 *
	 * @param string $html       Document whose foreign content is still open when the body is closed.
	 * @param string $token_name Name of the whitespace-only character token in the document.
	 */
	public function test_whitespace_character_token_at_integration_point_after_body_keeps_insertion_mode( string $html, string $token_name ) {
		$processor = WP_HTML_Processor::create_full_parser( $html );

		$this->assertTrue( $processor->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		$this->assertTrue( $processor->next_token(), "Failed to find the expected {$token_name} token." );
		$this->assertSame( $token_name, $processor->get_token_name(), 'Found the wrong token after the TITLE element.' );
		$this->assertSame( ' ', $processor->get_modifiable_text(), 'Found the wrong whitespace content.' );

		$visited = array();
		while ( $processor->next_token() ) {
			$visited[] = $processor->get_token_name();
		}

		$this->assertSame(
			WP_HTML_Processor::ERROR_UNSUPPORTED,
			$processor->get_last_error(),
			'Should have stopped at the comment after the body instead of inserting it into the BODY element.'
		);
		$this->assertNotContains( '#comment', $visited, 'Should not have inserted the comment after the body into the BODY element.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_whitespace_character_tokens_at_integration_point_after_body() {
		return array(
			'After body, text'        => array( '<svg><g></body><title> </title></g></svg><!--c-->', '#text' ),
			'After body, CDATA'       => array( '<svg><g></body><title><![CDATA[ ]]></title></g></svg><!--c-->', '#cdata-section' ),
			'After after body, text'  => array( '<svg><g></body></html><title> </title></g></svg><!--c-->', '#text' ),
			'After after body, CDATA' => array( '<svg><g></body></html><title><![CDATA[ ]]></title></g></svg><!--c-->', '#cdata-section' ),
		);
	}

	/**
	 * Ensures that a CDATA section of whitespace and NULL bytes at an integration point switches the insertion mode after the body.
	 *
	 * In the "after body" and "after after body" insertion modes only
	 * whitespace character tokens keep the insertion mode; a NULL character
	 * token is "anything else" and switches the insertion mode back to
	 * "in body", where it is ignored. The equivalent text is subdivided into
	 * whitespace and NULL tokens, so its NULL token switches the mode; a CDATA
	 * section is one token and must switch the mode the same way.
	 *
	 * The comment after the foreign content shows which mode was in effect:
	 * "in body" inserts it into the BODY element, while the after-body modes
	 * stop because comments are not supported there.
	 *
	 * @ticket 65967
	 *
	 * @dataProvider data_cdata_sections_of_whitespace_and_null_bytes_after_body
	 *
	 * @param string $closers Tag closers that select the insertion mode before the foreign content ends.
	 * @param string $content Whitespace and NULL bytes to place inside the CDATA section and the text.
	 */
	public function test_cdata_section_of_whitespace_and_null_bytes_at_integration_point_after_body_switches_insertion_mode( string $closers, string $content ) {
		$cdata = WP_HTML_Processor::create_full_parser( "<svg><g>{$closers}<title><![CDATA[{$content}]]></title></g></svg><!--c-->" );
		$text  = WP_HTML_Processor::create_full_parser( "<svg><g>{$closers}<title>{$content}</title></g></svg><!--c-->" );

		$this->assertTrue( $cdata->next_tag( 'TITLE' ), 'Failed to find the TITLE element under test.' );
		$this->assertTrue( $cdata->next_token(), 'Failed to find the expected CDATA section.' );
		$this->assertSame( '#cdata-section', $cdata->get_token_name(), 'Found the wrong token after the TITLE element.' );
		$this->assertSame(
			str_replace( array( "\0", "\r" ), array( '', "\n" ), $content ),
			$cdata->get_modifiable_text(),
			'Should have removed the NULL bytes from the CDATA section and normalized its newlines.'
		);

		$comment_breadcrumbs = null;
		while ( $cdata->next_token() ) {
			if ( '#comment' === $cdata->get_token_name() ) {
				$comment_breadcrumbs = $cdata->get_breadcrumbs();
			}
		}

		$text_comment_breadcrumbs = null;
		while ( $text->next_token() ) {
			if ( '#comment' === $text->get_token_name() ) {
				$text_comment_breadcrumbs = $text->get_breadcrumbs();
			}
		}

		$this->assertNull( $text->get_last_error(), 'The equivalent text should have parsed without error.' );
		$this->assertNull( $cdata->get_last_error(), 'Should have switched to the "in body" insertion mode and inserted the comment.' );
		$this->assertSame( array( 'HTML', 'BODY', '#comment' ), $text_comment_breadcrumbs, 'The equivalent text should have inserted the comment into the BODY element.' );
		$this->assertSame( $text_comment_breadcrumbs, $comment_breadcrumbs, 'Should have inserted the comment where the equivalent text inserts it.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cdata_sections_of_whitespace_and_null_bytes_after_body() {
		$cases = array();
		foreach ( array(
			'After body'       => '</body>',
			'After after body' => '</body></html>',
		) as $mode => $closers ) {
			foreach ( array(
				'leading NULL'    => "\0 ",
				'trailing NULL'   => " \0",
				'surrounded NULL' => " \0 ",
				'every kind'      => " \t\n\f\r\0",
			) as $name => $content ) {
				$cases[ "{$mode}, {$name}" ] = array( $closers, $content );
			}
		}
		return $cases;
	}

	/**
	 * Ensures that a CDATA section at an integration point changes the frameset-ok flag the way its text would.
	 *
	 * In the "in body" insertion mode whitespace and NULL character tokens
	 * leave the frameset-ok flag alone, while any other character token sets
	 * it to "not ok". A CDATA section of whitespace and NULL bytes is one
	 * token, and must not be treated as generic text.
	 *
	 * The processor stops at a FRAMESET start tag in the "in body" insertion
	 * mode unless the frameset-ok flag is "not ok", in which case the tag is
	 * ignored. The equivalent text is parsed alongside to show the expected
	 * outcome.
	 *
	 * @ticket 65967
	 *
	 * @dataProvider data_cdata_sections_and_frameset_ok
	 *
	 * @param string $content         Content of the CDATA section and of the equivalent text.
	 * @param bool   $is_frameset_ok  Whether the FRAMESET start tag after the content should stop the processor.
	 */
	public function test_cdata_section_at_integration_point_changes_frameset_ok_like_text( string $content, bool $is_frameset_ok ) {
		$cdata = WP_HTML_Processor::create_full_parser( "<svg><title><![CDATA[{$content}]]></title></svg><frameset>" );
		$text  = WP_HTML_Processor::create_full_parser( "<svg><title>{$content}</title></svg><frameset>" );

		while ( $cdata->next_token() ) {
			continue;
		}
		while ( $text->next_token() ) {
			continue;
		}

		if ( $is_frameset_ok ) {
			$this->assertSame( WP_HTML_Processor::ERROR_UNSUPPORTED, $text->get_last_error(), 'The equivalent text should have left frameset-ok set and stopped at the FRAMESET tag.' );
			$this->assertSame( WP_HTML_Processor::ERROR_UNSUPPORTED, $cdata->get_last_error(), 'Should have left frameset-ok set and stopped at the FRAMESET tag.' );
			$this->assertSame( 'Cannot process non-ignored FRAMESET tags.', $cdata->get_unsupported_exception()->getMessage(), 'Should have stopped at the FRAMESET tag.' );
		} else {
			$this->assertNull( $text->get_last_error(), 'The equivalent text should have cleared frameset-ok so that the FRAMESET tag is ignored.' );
			$this->assertNull( $cdata->get_last_error(), 'Should have cleared frameset-ok so that the FRAMESET tag is ignored.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cdata_sections_and_frameset_ok() {
		return array(
			'Whitespace'               => array( ' ', true ),
			'NULL bytes'               => array( "\0\0", true ),
			'Whitespace and NULL byte' => array( " \0 ", true ),
			'Text'                     => array( 'x', false ),
			'Text with NULL byte'      => array( " \0x ", false ),
		);
	}

	/**
	 * Ensures that the processor stops correctly on a FORM tag closer token.
	 *
	 * Form tag closers have complicated conditions. There was a bug where the processor
	 * would not stop correctly on a FORM tag closer token. Ensure this token is reachable.
	 *
	 * @ticket 61576
	 */
	public function test_ensure_form_tag_closer_token_is_reachable() {
		$processor = WP_HTML_Processor::create_fragment( '<form></form>' );

		// Advance to </form>.
		$processor->next_token();
		$processor->next_token();

		$this->assertSame( 'FORM', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_html_processor_with_extended_next_token() {
		return array(
			'single_instance_per_tag'   => array(
				'html'                  => '
					<html>
						<head>
							<meta charset="utf-8">
							<title>Hello World</title>
						</head>
						<body>
							<h1>Hello World!</h1>
							<img src="example.png">
							<p>Each tag should occur only once in this document.<!--Closing P tag omitted intentionally.-->
							<footer>The end.</footer>
						</body>
					</html>
				',
				'expected_token_counts' => array(
					'+HTML'    => 1,
					'+HEAD'    => 1,
					'#text'    => 14,
					'+META'    => 1,
					'+TITLE'   => 1,
					'-HEAD'    => 1,
					'+BODY'    => 1,
					'+H1'      => 1,
					'-H1'      => 1,
					'+IMG'     => 1,
					'+P'       => 1,
					'#comment' => 1,
					'-P'       => 1,
					'+FOOTER'  => 1,
					'-FOOTER'  => 1,
					'-BODY'    => 1,
					'-HTML'    => 1,
					''         => 1,
				),
			),

			'multiple_tag_instances'    => array(
				'html'                  => '
					<html>
						<body>
							<h1>Hello World!</h1>
							<p>First
							<p>Second
							<p>Third
							<ul>
								<li>1
								<li>2
								<li>3
							</ul>
						</body>
					</html>
				',
				'expected_token_counts' => array(
					'+HTML' => 1,
					'+HEAD' => 1,
					'-HEAD' => 1,
					'+BODY' => 1,
					'#text' => 13,
					'+H1'   => 1,
					'-H1'   => 1,
					'+P'    => 3,
					'-P'    => 3,
					'+UL'   => 1,
					'+LI'   => 3,
					'-LI'   => 3,
					'-UL'   => 1,
					'-BODY' => 1,
					'-HTML' => 1,
					''      => 1,
				),
			),

			'extreme_nested_formatting' => array(
				'html'                  => '
					<html>
						<body>
							<p>
								<strong><em><strike><i><b><u>FORMAT</u></b></i></strike></em></strong>
							</p>
						</body>
					</html>
				',
				'expected_token_counts' => array(
					'+HTML'   => 1,
					'+HEAD'   => 1,
					'-HEAD'   => 1,
					'+BODY'   => 1,
					'#text'   => 7,
					'+P'      => 1,
					'+STRONG' => 1,
					'+EM'     => 1,
					'+STRIKE' => 1,
					'+I'      => 1,
					'+B'      => 1,
					'+U'      => 1,
					'-U'      => 1,
					'-B'      => 1,
					'-I'      => 1,
					'-STRIKE' => 1,
					'-EM'     => 1,
					'-STRONG' => 1,
					'-P'      => 1,
					'-BODY'   => 1,
					'-HTML'   => 1,
					''        => 1,
				),
			),
		);
	}

	/**
	 * Ensures that subclasses to WP_HTML_Processor can do bookkeeping by extending the next_token() method.
	 *
	 * @ticket 62269
	 * @dataProvider data_html_processor_with_extended_next_token
	 */
	public function test_ensure_next_token_method_extensibility( $html, $expected_token_counts ) {
		require_once DIR_TESTDATA . '/html-api/token-counting-html-processor.php';

		$processor = Token_Counting_HTML_Processor::create_full_parser( $html );
		while ( $processor->next_tag() ) {
			continue;
		}

		$this->assertEquals( $expected_token_counts, $processor->token_seen_count, 'Snapshot: ' . var_export( $processor->token_seen_count, true ) );
	}

	/**
	 * Ensure that lowercased tag_name query matches tags case-insensitively.
	 *
	 * @ticket 62427
	 */
	public function test_next_tag_lowercase_tag_name() {
		// The upper case <DIV> is irrelevant but illustrates the case-insensitivity.
		$processor = WP_HTML_Processor::create_fragment( '<section><DIV>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'div' ) ) );

		// The upper case <RECT> is irrelevant but illustrates the case-insensitivity.
		$processor = WP_HTML_Processor::create_fragment( '<svg><RECT>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'rect' ) ) );
	}

	/**
	 * Ensure that the processor does not throw errors in cases of extreme HTML nesting.
	 *
	 * @ticket 64394
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_deep_nesting_fails_process_without_error() {
		$html      = str_repeat( '<i>', WP_HTML_Processor::MAX_BOOKMARKS * 2 );
		$processor = WP_HTML_Processor::create_fragment( $html );

		while ( $processor->next_token() ) {
			// Process tokens.
		}

		$this->assertSame(
			WP_HTML_Processor::ERROR_EXCEEDED_MAX_BOOKMARKS,
			$processor->get_last_error(),
			'Failed to report exceeded-max-bookmarks error.'
		);
	}

	/**
	 * @ticket 64394
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_deep_nesting_fails_processing_virtual_tokens_without_error() {
		/*
		 * This test has some variability depending on how the virtual tokens align.
		 * In order to ensure that bookmarks are exhausted on a virtual token
		 * without throwing an error, 3 documents are parsed with different "offsets"
		 * to ensure that the bookmarks are exhaused on a virtual token in at least one of the runs.
		 *
		 * "<table><td><table><td>…" produces:
		 * └─TABLE (real)
		 *   └─TBODY (virtual)
		 *     └─TR (virtual)
		 *       └─TD (real)
		 *         └─TABLE (real)
		 *           └─TBODY (virtual)
		 *             └─TR (virtual)
		 *               └─TD (real)
		 *                 └─…
		 */
		$html_table_td = str_repeat( '<table><td>', WP_HTML_Processor::MAX_BOOKMARKS * 2 );

		// Offset 0
		$processor = WP_HTML_Processor::create_fragment( $html_table_td );
		while ( $processor->next_token() ) {
			// Process tokens.
		}
		$this->assertSame(
			WP_HTML_Processor::ERROR_EXCEEDED_MAX_BOOKMARKS,
			$processor->get_last_error(),
			'Failed to report exceeded-max-bookmarks error.'
		);

		// Offset 1
		$processor = WP_HTML_Processor::create_fragment( "<div>{$html_table_td}" );
		while ( $processor->next_token() ) {
			// Process tokens.
		}
		$this->assertSame(
			WP_HTML_Processor::ERROR_EXCEEDED_MAX_BOOKMARKS,
			$processor->get_last_error(),
			'Failed to report exceeded-max-bookmarks error.'
		);

		// Offset 2
		$processor = WP_HTML_Processor::create_fragment( "<div><div>{$html_table_td}" );
		while ( $processor->next_token() ) {
			// Process tokens.
		}
		$this->assertSame(
			WP_HTML_Processor::ERROR_EXCEEDED_MAX_BOOKMARKS,
			$processor->get_last_error(),
			'Failed to report exceeded-max-bookmarks error.'
		);
	}

	/**
	 * @ticket 64394
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_prevents_unbounded_bookmarking() {
		$processor = WP_HTML_Processor::create_full_parser( '<!DOCTYPE html><html>' );
		$processor->next_tag();

		// This might fail before the MAX_BOOKMARK limit, which is okay.
		foreach ( range( 0, WP_HTML_Processor::MAX_BOOKMARKS ) as $n ) {
			if ( ! $processor->set_bookmark( "{$n}" ) ) {
				break;
			}
		}

		$this->assertFalse(
			$processor->set_bookmark( 'beyond the limit' )
		);
	}
}
