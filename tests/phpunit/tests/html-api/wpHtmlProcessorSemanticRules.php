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
	 * Verifies that tags in the container group, including the ARTICLE element,
	 * close out an open P element if one exists.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 59914
	 *
	 * @dataProvider data_article_container_group
	 *
	 * @param string $tag_name Name of tag in group under test.
	 */
	public function test_in_body_article_group_closes_open_p_element( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<p><p><p><p><{$tag_name} target>" );

		while ( $processor->next_tag() && null === $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame(
			$tag_name,
			$processor->get_tag(),
			"Expected to find {$tag_name} but found {$processor->get_tag()} instead."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			"Expected to find {$tag_name} as direct child of BODY as a result of implicitly closing an open P element."
		);
	}

	/**
	 * Verifies that tags in the container group, including the ARTICLE element,
	 * nest inside each other despite being invalid in most cases.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 59914
	 *
	 * @dataProvider data_article_container_group
	 *
	 * @param string $tag_name Name of tag in group under test.
	 */
	public function test_in_body_article_group_can_nest_inside_itself( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<div><{$tag_name}><{$tag_name}></{$tag_name}><{$tag_name}><span><{$tag_name} target>" );

		while ( $processor->next_tag() && null === $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', $tag_name, $tag_name, 'SPAN', $tag_name ),
			$processor->get_breadcrumbs(),
			"Expected to find {$tag_name} deeply nested inside itself."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_article_container_group() {
		$group = array();

		foreach (
			array(
				'ADDRESS',
				'ARTICLE',
				'ASIDE',
				'BLOCKQUOTE',
				'CENTER',
				'DETAILS',
				'DIALOG',
				'DIR',
				'DL',
				'DIV',
				'FIELDSET',
				'FIGCAPTION',
				'FIGURE',
				'FOOTER',
				'HEADER',
				'HGROUP',
				'MAIN',
				'MENU',
				'NAV',
				'SEARCH',
				'SECTION',
				'SUMMARY',
			)
			as $tag_name
		) {
			$group[ $tag_name ] = array( $tag_name );
		}

		return $group;
	}

	/**
	 * Verifies that when encountering an end tag for which there is no corresponding
	 * element in scope, that it skips the tag entirely.
	 *
	 * @ticket 58961
	 */
	public function test_in_body_skips_unexpected_button_closer() {
		$processor = WP_HTML_Processor::create_fragment( '<div>Test</button></div>' );

		$processor->step();
		$this->assertSame( 'DIV', $processor->get_tag(), 'Did not stop at initial DIV tag.' );
		$this->assertFalse( $processor->is_tag_closer(), 'Did not find that initial DIV tag is an opener.' );

		$processor->step();
		$this->assertSame( '#text', $processor->get_token_type(), 'Should have found the text node.' );

		/*
		 * When encountering the BUTTON closing tag, there is no BUTTON in the stack of open elements.
		 * It should be ignored as there's no BUTTON to close.
		 */
		$this->assertTrue( $processor->step(), 'Found no further tags when it should have found the closing DIV' );
		$this->assertSame( 'DIV', $processor->get_tag(), "Did not skip unexpected BUTTON; stopped at {$processor->get_tag()}." );
		$this->assertTrue( $processor->is_tag_closer(), 'Did not find that the terminal DIV tag is a closer.' );
	}

	/**
	 * Verifies insertion of a BUTTON element when no existing BUTTON is already in scope.
	 *
	 * @ticket 58961
	 */
	public function test_in_body_button_with_no_button_in_scope() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p>Click the button <button one>here</button>!</p></div><button two>not here</button>' );

		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $processor->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		/*
		 * There's nothing special about this HTML construction, but it's important to verify that
		 * the HTML Processor can find a BUTTON under normal and normative scenarios, not just the
		 * malformed and unexpected ones.
		 */
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $processor->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );
	}

	/**
	 * Verifies that when inserting a BUTTON element, when a BUTTON is already in scope,
	 * that the open button is closed with all other elements inside of it.
	 *
	 * @ticket 58961
	 *
	 * @since 6.4.0
	 */
	public function test_in_body_button_with_button_in_scope_as_parent() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p>Click the button <button one>almost<button two>here</button>!</p></div><button three>not here</button>' );

		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $processor->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		/*
		 * A naive parser might skip the second BUTTON because it's looking for the close of the first one,
		 * or it may place it as a child of the first one, but it implicitly closes the open BUTTON.
		 */
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $processor->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'P', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );

		/*
		 * This is another form of the test for the second button, but from a different side. The test is
		 * looking for proper handling of the open and close sequence for the BUTTON tags.
		 */
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected third button.' );
		$this->assertTrue( $processor->get_attribute( 'three' ), 'Failed to match expected attribute on third button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for third button.' );
	}

	/**
	 * Verifies that when inserting a BUTTON element, when a BUTTON is already in scope,
	 * that the open button is closed with all other elements inside of it, even if the
	 * BUTTON in scope is not a direct parent of the new BUTTON element.
	 *
	 * @ticket 58961
	 *
	 * @since 6.4.0
	 */
	public function test_in_body_button_with_button_in_scope_as_ancestor() {
		$processor = WP_HTML_Processor::create_fragment( '<div><button one><p>Click the button <span><button two>here</button>!</span></p></div><button three>not here</button>' );

		// This button finds itself normally nesting inside the DIV.
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected first button.' );
		$this->assertTrue( $processor->get_attribute( 'one' ), 'Failed to match expected attribute on first button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for first button.' );

		/*
		 * Because the second button appears while a BUTTON is in scope, it generates implied end tags and closes
		 * the BUTTON, P, and SPAN elements. It looks like the BUTTON is inside the SPAN, but it's another case
		 * of an unexpected closing SPAN tag because the SPAN was closed by the second BUTTON. This element finds
		 * itself a child of the most-recent open element above the most-recent BUTTON, or the DIV.
		 */
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected second button.' );
		$this->assertTrue( $processor->get_attribute( 'two' ), 'Failed to match expected attribute on second button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for second button.' );

		// The third button is back to normal, because everything has been implicitly or explicitly closed by now.
		$this->assertTrue( $processor->next_tag( 'BUTTON' ), 'Could not find expected third button.' );
		$this->assertTrue( $processor->get_attribute( 'three' ), 'Failed to match expected attribute on third button.' );
		$this->assertSame( array( 'HTML', 'BODY', 'BUTTON' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting for third button.' );
	}

	/**
	 * Verifies that HR closes an open p tag
	 *
	 * @ticket 60283
	 */
	public function test_in_body_hr_element_closes_open_p_tag() {
		$processor = WP_HTML_Processor::create_fragment( '<p><hr>' );

		$processor->next_tag( 'HR' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'HR' ),
			$processor->get_breadcrumbs(),
			'Expected HR to be a direct child of the BODY, having closed the open P element.'
		);
	}

	/**
	 * Verifies that H1 through H6 elements close an open P element.
	 *
	 * @ticket 60215
	 *
	 * @dataProvider data_heading_elements
	 *
	 * @param string $tag_name Name of H1 - H6 element under test.
	 */
	public function test_in_body_heading_element_closes_open_p_tag( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment(
			"<p>Open<{$tag_name}>Closed P</{$tag_name}><img></p>"
		);

		$processor->next_tag( $tag_name );
		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			"Expected {$tag_name} to be a direct child of the BODY, having closed the open P element."
		);

		$processor->next_tag( 'IMG' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'IMG' ),
			$processor->get_breadcrumbs(),
			'Expected IMG to be a direct child of BODY, having closed the open P element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_heading_elements() {
		return array(
			'H1' => array( 'H1' ),
			'H2' => array( 'H2' ),
			'H3' => array( 'H3' ),
			'H4' => array( 'H4' ),
			'H5' => array( 'H5' ),
			'H6' => array( 'H5' ),
		);
	}

	/**
	 * Verifies that H1 through H6 elements close an open H1 through H6 element.
	 *
	 * @ticket 60215
	 *
	 * @dataProvider data_heading_combinations
	 *
	 * @param string $first_heading  H1 - H6 element appearing (unclosed) before the second.
	 * @param string $second_heading H1 - H6 element appearing after the first.
	 */
	public function test_in_body_heading_element_closes_other_heading_elements( $first_heading, $second_heading ) {
		$processor = WP_HTML_Processor::create_fragment(
			"<div><{$first_heading} first> then <{$second_heading} second> and end </{$second_heading}><img></{$first_heading}></div>"
		);

		while ( $processor->next_tag() && null === $processor->get_attribute( 'second' ) ) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'second' ),
			"Failed to find expected {$second_heading} tag."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', $second_heading ),
			$processor->get_breadcrumbs(),
			"Expected {$second_heading} to be a direct child of the DIV, having closed the open {$first_heading} element."
		);

		$processor->next_tag( 'IMG' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV', 'IMG' ),
			$processor->get_breadcrumbs(),
			"Expected IMG to be a direct child of DIV, having closed the open {$first_heading} element."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_heading_combinations() {
		$headings = array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' );

		$combinations = array();

		// Create all unique pairs of H1 - H6 elements.
		foreach ( $headings as $first_tag ) {
			foreach ( $headings as $second_tag ) {
				$combinations[ "{$first_tag} then {$second_tag}" ] = array( $first_tag, $second_tag );
			}
		}

		return $combinations;
	}

	/**
	 * Verifies that when "in body" and encountering "any other end tag"
	 * that the HTML processor ignores the end tag if there's a special
	 * element on the stack of open elements before the matching opening.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 58907
	 *
	 * @since 6.4.0
	 */
	public function test_in_body_any_other_end_tag_with_unclosed_special_element() {
		$processor = WP_HTML_Processor::create_fragment( '<div><span><p></span><div>' );

		$processor->next_tag( 'P' );
		$this->assertSame( 'P', $processor->get_tag(), "Expected to start test on P element but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'P' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting.' );

		$this->assertTrue( $processor->next_tag(), 'Failed to advance past P tag to expected DIV opener.' );
		$this->assertSame( 'DIV', $processor->get_tag(), "Expected to find DIV element, but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'DIV' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting: SPAN should still be open and DIV should be its child.' );
	}

	/**
	 * Verifies that when "in body" and encountering "any other end tag"
	 * that the HTML processor closes appropriate elements on the stack of
	 * open elements up to the matching opening.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 58907
	 *
	 * @since 6.4.0
	 */
	public function test_in_body_any_other_end_tag_with_unclosed_non_special_element() {
		$processor = WP_HTML_Processor::create_fragment( '<div><span><code></span><div>' );

		$processor->next_tag( 'CODE' );
		$this->assertSame( 'CODE', $processor->get_tag(), "Expected to start test on CODE element but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'CODE' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting.' );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'SPAN',
					'tag_closers' => 'visit',
				)
			),
			'Failed to advance past CODE tag to expected SPAN closer.'
		);
		$this->assertSame( 'SPAN', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer(), 'Expected to find closing SPAN, but found opener instead.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $processor->get_breadcrumbs(), 'Failed to advance past CODE tag to expected DIV opener.' );

		$this->assertTrue( $processor->next_tag(), 'Failed to advance past SPAN closer to expected DIV opener.' );
		$this->assertSame( 'DIV', $processor->get_tag(), "Expected to find DIV element, but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'DIV' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting: SPAN should be closed and DIV should be its sibling.' );
	}

	/**
	 * Verifies that when the adoption agency algorithm finds no matching
	 * active formatting element, it acts like "any other end tag".
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 65383
	 *
	 * @dataProvider data_in_body_adoption_agency_fallback_end_tags
	 *
	 * @param string $formatting_tag_name Formatting tag name with no active formatting element.
	 */
	public function test_in_body_adoption_agency_fallback_ignores_unexpected_formatting_end_tag( string $formatting_tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<div><span></{$formatting_tag_name}><code target></code></span></div>" );

		$this->assertTrue( $processor->next_tag( 'SPAN' ), 'Failed to find the SPAN opener before an unexpected formatting end tag.' );
		$this->assertSame( 'SPAN', $processor->get_tag(), "Expected to start test on SPAN element but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN' ), $processor->get_breadcrumbs(), 'Failed to produce expected DOM nesting before unexpected formatting closer.' );

		$this->assertTrue( $processor->next_tag( 'CODE' ), "Failed to ignore unexpected {$formatting_tag_name} closer and advance to CODE opener." );
		$this->assertSame( 'CODE', $processor->get_tag(), "Expected to find CODE element, but found {$processor->get_tag()} instead." );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV', 'SPAN', 'CODE' ), $processor->get_breadcrumbs(), 'Failed to keep SPAN open after unexpected formatting closer.' );
	}

	/**
	 * Verifies that the adoption agency fallback preserves the "any other end tag"
	 * step result when the ignored token is followed by EOF.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 65383
	 *
	 * @dataProvider data_in_body_adoption_agency_fallback_end_tags
	 *
	 * @param string $tag_name Formatting tag name with no active formatting element.
	 */
	public function test_in_body_adoption_agency_fallback_preserves_ignored_end_tag_step_result( string $tag_name ): void {
		$processor = WP_HTML_Processor::create_fragment( "</{$tag_name}>" );
		$this->assertFalse( $processor->step(), "Expected unexpected {$tag_name} end tag followed by EOF to return false." );
	}

	/**
	 * Verifies that when the adoption agency algorithm returns after removing
	 * a formatting element from the active formatting elements list, it does
	 * not report the current token as EOF.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 65383
	 *
	 * @dataProvider data_in_body_adoption_agency_fallback_end_tags
	 *
	 * @param string $tag_name Formatting tag name with no open element.
	 */
	public function test_in_body_adoption_agency_removes_inactive_formatting_element_and_continues( string $tag_name ): void {
		$processor = WP_HTML_Processor::create_fragment( "<p><{$tag_name}></p></{$tag_name}><span target></span>" );

		$this->assertTrue( $processor->next_tag( $tag_name ), "Failed to find the {$tag_name} opener before it is popped by the P closer." );
		$this->assertTrue( $processor->next_tag( 'SPAN' ), "Failed to advance past the inactive {$tag_name} closer to the following SPAN opener." );
		$this->assertSame( array( 'HTML', 'BODY', 'SPAN' ), $processor->get_breadcrumbs(), "Expected SPAN to be a BODY child after the inactive {$tag_name} closer." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_in_body_adoption_agency_fallback_end_tags(): array {
		return array(
			'A tag'      => array( 'a' ),
			'B tag'      => array( 'b' ),
			'BIG tag'    => array( 'big' ),
			'CODE tag'   => array( 'code' ),
			'EM tag'     => array( 'em' ),
			'FONT tag'   => array( 'font' ),
			'I tag'      => array( 'i' ),
			'NOBR tag'   => array( 'nobr' ),
			'S tag'      => array( 's' ),
			'SMALL tag'  => array( 'small' ),
			'STRIKE tag' => array( 'strike' ),
			'STRONG tag' => array( 'strong' ),
			'TT tag'     => array( 'tt' ),
			'U tag'      => array( 'u' ),
		);
	}

	/**
	 * Ensures that closing `</br>` tags are appropriately treated as opening tags with no attributes.
	 *
	 * > An end tag whose tag name is "br"
	 * >   Parse error. Drop the attributes from the token, and act as described in the next entry;
	 * >   i.e. act as if this was a "br" start tag token with no attributes, rather than the end
	 * >   tag token that it actually is.
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 *
	 * @ticket 60283
	 */
	public function test_br_end_tag_unsupported() {
		$processor = WP_HTML_Processor::create_fragment( '</br id="an-opener" html>' );

		$this->assertTrue( $processor->next_tag(), 'Failed to find the expected opening BR tag.' );
		$this->assertFalse( $processor->is_tag_closer(), 'Should have treated the tag as an opening tag.' );
		$this->assertNull( $processor->get_attribute_names_with_prefix( '' ), 'Should have ignored any attributes on the tag.' );
	}

	/*******************************************************************
	 * RULES FOR "IN TABLE" MODE
	 *******************************************************************/

	/**
	 * Ensure that form elements in tables (but not cells) are immediately popped off the stack.
	 *
	 * @ticket 61576
	 */
	public function test_table_form_element_immediately_popped() {
		$processor = WP_HTML_Processor::create_fragment( '<table><form><!--comment-->' );

		// There should be a FORM opener and a (virtual) FORM closer.
		$this->assertTrue( $processor->next_tag( 'FORM' ) );
		$this->assertTrue( $processor->next_token() );
		$this->assertSame( 'FORM', $processor->get_token_name() );
		$this->assertTrue( $processor->is_tag_closer() );

		// Followed by the comment token.
		$this->assertTrue( $processor->next_token() );
		$this->assertSame( '#comment', $processor->get_token_name() );
		$this->assertsame( array( 'HTML', 'BODY', 'TABLE', '#comment' ), $processor->get_breadcrumbs() );
	}
}
