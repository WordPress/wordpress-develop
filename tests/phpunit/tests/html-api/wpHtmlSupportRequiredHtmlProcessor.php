<?php
/**
 * Unit tests for the HTML API indicating that changes are needed to the
 * WP_HTML_Processor class before specific features are added to the API.
 *
 * Note! Duplication of test cases and the helper function in this file are intentional.
 * This test file exists to warn developers of related areas of code that need to update
 * together when adding support for new elements to the HTML Processor. For example,
 * when adding support for the LI element it's necessary to update the function which
 * generates implied end tags. This is because each element might bring with it semantic
 * rules that impact the way the document should be parsed.
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
class Tests_HtmlApi_WpHtmlSupportRequiredHtmlProcessor extends WP_UnitTestCase {
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
	 * Generating implied end tags walks up the stack of open elements
	 * as long as any of the following missing elements is the current node.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58907
	 *
	 * @covers WP_HTML_Processor::generate_implied_end_tags
	 */
	public function test_generate_implied_end_tags_needs_support() {
		$this->ensure_support_is_added_everywhere( 'OPTGROUP' );
		$this->ensure_support_is_added_everywhere( 'OPTION' );
		$this->ensure_support_is_added_everywhere( 'RB' );
		$this->ensure_support_is_added_everywhere( 'RP' );
		$this->ensure_support_is_added_everywhere( 'RT' );
		$this->ensure_support_is_added_everywhere( 'RTC' );
	}

	/**
	 * Generating implied end tags thoroughly walks up the stack of open elements
	 * as long as any of the following missing elements is the current node.
	 *
	 * @since 6.4.0
	 *
	 * @ticket 58907
	 *
	 * @covers WP_HTML_Processor::generate_implied_end_tags_thoroughly
	 */
	public function test_generate_implied_end_tags_thoroughly_needs_support() {
		$this->ensure_support_is_added_everywhere( 'CAPTION' );
		$this->ensure_support_is_added_everywhere( 'COLGROUP' );
		$this->ensure_support_is_added_everywhere( 'OPTGROUP' );
		$this->ensure_support_is_added_everywhere( 'OPTION' );
		$this->ensure_support_is_added_everywhere( 'RB' );
		$this->ensure_support_is_added_everywhere( 'RP' );
		$this->ensure_support_is_added_everywhere( 'RT' );
		$this->ensure_support_is_added_everywhere( 'RTC' );
		$this->ensure_support_is_added_everywhere( 'TBODY' );
		$this->ensure_support_is_added_everywhere( 'TD' );
		$this->ensure_support_is_added_everywhere( 'TFOOT' );
		$this->ensure_support_is_added_everywhere( 'TH' );
		$this->ensure_support_is_added_everywhere( 'HEAD' );
		$this->ensure_support_is_added_everywhere( 'TR' );
	}
}
