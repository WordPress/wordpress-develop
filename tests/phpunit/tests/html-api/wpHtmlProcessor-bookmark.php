<?php
/**
 * Unit tests covering WP_HTML_Processor bookmark functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor_Bookmark extends WP_UnitTestCase {
	/**
	 * @dataProvider data_processor_constructors
	 *
	 * @ticket 62290
	 */
	public function test_processor_seek_same_location( callable $factory ) {
		$processor = $factory( '<div><span>' );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertTrue( $processor->set_bookmark( 'mark' ), 'Failed to set bookmark.' );
		$this->assertTrue( $processor->has_bookmark( 'mark' ), 'Failed has_bookmark check.' );

		// Confirm the bookmark works and processing continues normally.
		$this->assertTrue( $processor->seek( 'mark' ), 'Failed to seek to bookmark.' );
		$this->assertSame( 'DIV', $processor->get_tag() );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $processor->get_breadcrumbs() );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SPAN', $processor->get_tag() );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN' ), $processor->get_breadcrumbs() );
	}

	/**
	 * @dataProvider data_processor_constructors
	 *
	 * @ticket 62290
	 */
	public function test_processor_seek_backward( callable $factory ) {
		$processor = $factory( '<div><span>' );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertTrue( $processor->set_bookmark( 'mark' ), 'Failed to set bookmark.' );
		$this->assertTrue( $processor->has_bookmark( 'mark' ), 'Failed has_bookmark check.' );

		// Move past the bookmark so it must scan backwards.
		$this->assertTrue( $processor->next_tag( 'SPAN' ) );

		// Confirm the bookmark works.
		$this->assertTrue( $processor->seek( 'mark' ), 'Failed to seek to bookmark.' );
		$this->assertSame( 'DIV', $processor->get_tag() );
	}

	/**
	 * @dataProvider data_processor_constructors
	 *
	 * @ticket 62290
	 */
	public function test_processor_seek_forward( callable $factory ) {
		$processor = $factory( '<div one></div><span two></span><a three>' );
		$this->assertTrue( $processor->next_tag( 'DIV' ) );
		$this->assertTrue( $processor->set_bookmark( 'one' ), 'Failed to set bookmark "one".' );
		$this->assertTrue( $processor->has_bookmark( 'one' ), 'Failed "one" has_bookmark check.' );

		// Move past the bookmark so it must scan backwards.
		$this->assertTrue( $processor->next_tag( 'SPAN' ) );
		$this->assertTrue( $processor->get_attribute( 'two' ) );
		$this->assertTrue( $processor->set_bookmark( 'two' ), 'Failed to set bookmark "two".' );
		$this->assertTrue( $processor->has_bookmark( 'two' ), 'Failed "two" has_bookmark check.' );

		// Seek back.
		$this->assertTrue( $processor->seek( 'one' ), 'Failed to seek to bookmark "one".' );
		$this->assertSame( 'DIV', $processor->get_tag() );

		// Seek forward and continue processing.
		$this->assertTrue( $processor->seek( 'two' ), 'Failed to seek to bookmark "two".' );
		$this->assertSame( 'SPAN', $processor->get_tag() );
		$this->assertTrue( $processor->get_attribute( 'two' ) );

		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'A', $processor->get_tag() );
		$this->assertTrue( $processor->get_attribute( 'three' ) );
	}

	/**
	 * Ensure the parsing namespace is handled when seeking from foreign content.
	 *
	 * @dataProvider data_processor_constructors
	 *
	 * @ticket 62290
	 */
	public function test_seek_back_from_foreign_content( callable $factory ) {
		$processor = $factory( '<custom-element /><svg><rect />' );
		$this->assertTrue( $processor->next_tag( 'CUSTOM-ELEMENT' ) );
		$this->assertTrue( $processor->set_bookmark( 'mark' ), 'Failed to set bookmark "mark".' );
		$this->assertTrue( $processor->has_bookmark( 'mark' ), 'Failed "mark" has_bookmark check.' );

		/*
		 * <custom-element /> has self-closing flag, but HTML elements (that are not void elements) cannot self-close,
		 * they must be closed by some means, usually a closing tag.
		 *
		 * If the div were interpreted as foreign content, it would self-close.
		 */
		$this->assertTrue( $processor->has_self_closing_flag() );
		$this->assertTrue( $processor->expects_closer(), 'Incorrectly interpreted HTML custom-element with self-closing flag as self-closing element.' );

		// Proceed into foreign content.
		$this->assertTrue( $processor->next_tag( 'RECT' ) );
		$this->assertSame( 'svg', $processor->get_namespace() );
		$this->assertTrue( $processor->has_self_closing_flag() );
		$this->assertFalse( $processor->expects_closer() );
		$this->assertSame( array( 'HTML', 'BODY', 'CUSTOM-ELEMENT', 'SVG', 'RECT' ), $processor->get_breadcrumbs() );

		// Seek back.
		$this->assertTrue( $processor->seek( 'mark' ), 'Failed to seek to bookmark "mark".' );
		$this->assertSame( 'CUSTOM-ELEMENT', $processor->get_tag() );
		// If the parsing namespace were not correct here (html),
		// then the self-closing flag would be misinterpreted.
		$this->assertTrue( $processor->has_self_closing_flag() );
		$this->assertTrue( $processor->expects_closer(), 'Incorrectly interpreted HTML custom-element with self-closing flag as self-closing element.' );

		// Proceed into foreign content again.
		$this->assertTrue( $processor->next_tag( 'RECT' ) );
		$this->assertSame( 'svg', $processor->get_namespace() );
		$this->assertTrue( $processor->has_self_closing_flag() );
		$this->assertFalse( $processor->expects_closer() );

		// The RECT should still descend from the CUSTOM-ELEMENT despite its self-closing flag.
		$this->assertSame( array( 'HTML', 'BODY', 'CUSTOM-ELEMENT', 'SVG', 'RECT' ), $processor->get_breadcrumbs() );
	}

	/**
	 * Covers a regression where the root node may not be present on the stack of open elements.
	 *
	 * Heading elements (h1, h2, etc.) check the current node on the stack of open elements
	 * and expect it to be defined. If the root-node has been popped, pushing a new heading
	 * onto the stack will create a warning and fail the test.
	 *
	 * @ticket 62290
	 */
	public function test_fragment_starts_with_h1() {
		$processor = WP_HTML_Processor::create_fragment( '<h1>' );
		$this->assertTrue( $processor->next_tag( 'H1' ) );
		$this->assertTrue( $processor->set_bookmark( 'mark' ) );
		$this->assertTrue( $processor->next_token() );
		$this->assertTrue( $processor->seek( 'mark' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_processor_constructors(): array {
		return array(
			'Full parser'     => array( array( WP_HTML_Processor::class, 'create_full_parser' ) ),
			'Fragment parser' => array( array( WP_HTML_Processor::class, 'create_fragment' ) ),
		);
	}
}
