<?php
/**
 * Unit tests covering WP_HTML_Processor compliance with HTML5 semantic parsing rules
 * for the H1 - H6 heading elements.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.5.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorSemanticRulesHeadingElements extends WP_UnitTestCase {
	/*******************************************************************
	 * RULES FOR "IN BODY" MODE
	 *******************************************************************/

	/**
	 * Verifies that H1 through H6 elements generate implied end tags.
	 *
	 * @ticket 60060
	 *
	 * @covers WP_HTML_Processor::step
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
	 * @ticket 60060
	 *
	 * @covers WP_HTML_Processor::step
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
}
