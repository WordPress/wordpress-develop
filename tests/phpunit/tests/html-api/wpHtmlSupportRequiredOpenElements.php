<?php
/**
 * Unit tests for the HTML API indicating that changes are needed to the
 * WP_HTML_Open_Elements class before specific features are added to the API.
 *
 * Note! Duplication of test cases and the helper function in this file are intentional.
 * This test file exists to warn developers of related areas of code that need to update
 * together when adding support for new elements to the HTML Processor. For example,
 * when adding support for the BUTTON element it's necessary to update multiple methods
 * in the class governing the stack of open elements as well as the HTML Processor class
 * itself. This is because each element might bring with it semantic rules that impact
 * the way the document should be parsed. BUTTON creates a kind of boundary in the
 * DOM tree and implicitly closes existing open BUTTON elements.
 *
 * Without these tests a developer needs to investigate all possible places they
 * might need to update when adding support for more elements and risks overlooking
 * important parts that, in the absence of the related support, will lead to errors.
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
class Tests_HtmlApi_WpHtmlSupportRequiredOpenElements extends WP_UnitTestCase {
	/**
	 * Fails to assert if the HTML Processor handles the given tag.
	 *
	 * This test helper is used throughout this test file for one purpose only: to
	 * fail a test if the HTML Processor handles the given tag. In other words, it
	 * ensures that the HTML Processor aborts when encountering the given tag.
	 *
	 * This is used to ensure that when support for a new tag is added to the
	 * HTML Processor it receives full support and not partial support, which
	 * could lead to a variety of issues.
	 *
	 * Do not remove this helper function as it provides semantic meaning to the
	 * assertions in the tests in this file and its behavior is incredibly specific
	 * and limited and doesn't warrant adding a new abstraction into WP_UnitTestCase.
	 *
	 * @param string $tag_name the HTML Processor should abort when encountering this tag, e.g. "BUTTON".
	 */
	private function ensure_support_is_added_everywhere( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<$tag_name>" );

		$this->assertFalse( $processor->step(), "Must support terminating elements in specific scope check before adding support for the {$tag_name} element." );
	}

	/**
	 * The check for whether an element is in a scope depends on
	 * looking for a number of terminating elements in the stack of open
	 * elements. Until the listed elements are supported in the HTML
	 * processor, there are no terminating elements and there's no
	 * point in taking the time to look for them.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 */
	public function test_has_element_in_scope_needs_support() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );
	}

	/**
	 * The check for whether an element is in list item scope depends on
	 * the elements for any scope, plus UL and OL.
	 *
	 * The method for asserting list item scope doesn't currently exist
	 * because the LI element isn't yet supported and the LI element is
	 * the only element that needs to know about list item scope.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::has_element_in_list_item_scope
	 */
	public function test_has_element_in_list_item_scope_needs_support() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );
	}

	/**
	 * The check for whether an element is in BUTTON scope depends on
	 * the elements for any scope, plus BUTTON.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::has_element_in_button_scope
	 */
	public function test_has_element_in_button_scope_needs_support() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );
	}

	/**
	 * The optimization maintaining a flag for "P is in BUTTON scope" requires
	 * updating that flag every time an element is popped from the stack of
	 * open elements.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::after_element_pop
	 */
	public function test_after_element_pop_must_maintain_p_in_button_scope_flag() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );
	}

	/**
	 * The optimization maintaining a flag for "P is in BUTTON scope" requires
	 * updating that flag every time an element is pushed onto the stack of
	 * open elements.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::after_element_push
	 */
	public function test_after_element_push_must_maintain_p_in_button_scope_flag() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );
	}

	/**
	 * The check for whether an element is in TABLE scope depends on
	 * the HTML, TABLE, and TEMPLATE elements.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::has_element_in_table_scope
	 */
	public function test_has_element_in_table_scope_needs_support() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );

		// These elements are specific to TABLE scope.
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// These elements depend on table scope.
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'COL' );
		$this->ensure_support_is_added_everywhere( 'COLGROUP' );
		$this->ensure_support_is_added_everywhere( 'TBODY' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TFOOT' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'THEAD' );
		$this->ensure_support_is_added_everywhere( 'TR' );
	}

	/**
	 * The check for whether an element is in SELECT scope depends on
	 * the OPTGROUP and OPTION elements.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Open_Elements::has_element_in_select_scope
	 */
	public function test_has_element_in_select_scope_needs_support() {
		// These elements impact all scopes.
		$this->ensure_support_is_added_everywhere( 'APPLET' );
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'HTML' );
		$this->ensure_support_is_added_everywhere( 'TABLE' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'MARQUEE' );
		$this->ensure_support_is_added_everywhere( 'OBJECT' );
		$this->ensure_support_is_added_everywhere( 'TEMPLATE' );

		// MathML Elements: MI, MO, MN, MS, MTEXT, ANNOTATION-XML.
		$this->ensure_support_is_added_everywhere( 'MATH' );

		/*
		 * SVG elements: note that TITLE is both an HTML element and an SVG element
		 * so care must be taken when adding support for either one.
		 *
		 * FOREIGNOBJECT, DESC, TITLE.
		 */
		$this->ensure_support_is_added_everywhere( 'SVG' );

		// These elements are specific to SELECT scope.
		$this->ensure_support_is_added_everywhere( 'OPTGROUP' );
		$this->ensure_support_is_added_everywhere( 'OPTION' );
	}
}
