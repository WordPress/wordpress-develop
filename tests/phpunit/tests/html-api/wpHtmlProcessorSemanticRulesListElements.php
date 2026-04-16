<?php
/**
 * Unit tests covering WP_HTML_Processor compliance with HTML5 semantic parsing rules
 * for the list elements, including DD, DL, DT, LI, OL, and UL.
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
class Tests_HtmlApi_WpHtmlProcessorSemanticRulesListElements extends WP_UnitTestCase {
	/*******************************************************************
	 * RULES FOR "IN BODY" MODE
	 *******************************************************************/

	/**
	 * Ensures that an opening LI element implicitly closes an open LI element.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_li_closes_open_li() {
		$processor = WP_HTML_Processor::create_fragment( '<li><li><li target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'LI' ),
			$processor->get_breadcrumbs(),
			"LI should have closed open LI, but didn't."
		);
	}

	/**
	 * Ensures that an opening LI element implicitly closes other open elements with optional closing tags.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_li_generates_implied_end_tags_inside_open_li() {
		$processor = WP_HTML_Processor::create_fragment( '<li><li><div><li target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'LI' ),
			$processor->get_breadcrumbs(),
			"LI should have closed open LI, but didn't."
		);
	}

	/**
	 * Ensures that when closing tags with optional tag closers, an opening LI tag doesn't close beyond a special boundary.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_li_generates_implied_end_tags_inside_open_li_but_stopping_at_special_tags() {
		$processor = WP_HTML_Processor::create_fragment( '<li><li><blockquote><li target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'LI', 'BLOCKQUOTE', 'LI' ),
			$processor->get_breadcrumbs(),
			'LI should have left the BLOCKQOUTE open, but closed it.'
		);
	}

	/**
	 * Ensures that an opening LI closes an open P in button scope.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_li_in_li_closes_p_in_button_scope() {
		$processor = WP_HTML_Processor::create_fragment( '<li><li><p><button><p><li target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'LI', 'P', 'BUTTON', 'LI' ),
			$processor->get_breadcrumbs(),
			'LI should have left the outer P open, but closed it.'
		);
	}

	/**
	 * Ensures that an opening DD closes an open DD element.
	 *
	 * Note that a DD closes an open DD and also an open DT.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dd_closes_open_dd() {
		$processor = WP_HTML_Processor::create_fragment( '<dd><dd><dd target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DD' ),
			$processor->get_breadcrumbs(),
			"DD should have closed open DD, but didn't."
		);
	}

	/**
	 * Ensures that an opening DD closes an open DT element.
	 *
	 * Note that a DD closes an open DD and also an open DT.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dd_closes_open_dt() {
		$processor = WP_HTML_Processor::create_fragment( '<dt><dt><dd target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DD' ),
			$processor->get_breadcrumbs(),
			"DD should have closed open DD, but didn't."
		);
	}

	/**
	 * Ensures that an opening DD implicitly closes open elements with optional closing tags.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dd_generates_implied_end_tags_inside_open_dd() {
		$processor = WP_HTML_Processor::create_fragment( '<dd><dd><div><dd target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DD' ),
			$processor->get_breadcrumbs(),
			"DD should have closed open DD, but didn't."
		);
	}

	/**
	 * Ensures that an opening DD implicitly closes open elements with optional closing tags,
	 * but doesn't close beyond a special boundary.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dd_generates_implied_end_tags_inside_open_dd_but_stopping_at_special_tags() {
		$processor = WP_HTML_Processor::create_fragment( '<dd><dd><blockquote><dd target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DD', 'BLOCKQUOTE', 'DD' ),
			$processor->get_breadcrumbs(),
			'DD should have left the BLOCKQOUTE open, but closed it.'
		);
	}

	/**
	 * Ensures that an opening DD inside a DD closes a P in button scope.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dd_in_dd_closes_p_in_button_scope() {
		$processor = WP_HTML_Processor::create_fragment( '<dd><dd><p><button><p><dd target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DD', 'P', 'BUTTON', 'DD' ),
			$processor->get_breadcrumbs(),
			'DD should have left the outer P open, but closed it.'
		);
	}

	/**
	 * Ensures that an opening DT closes an open DT element.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dt_closes_open_dt() {
		$processor = WP_HTML_Processor::create_fragment( '<dt><dt><dt target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DT' ),
			$processor->get_breadcrumbs(),
			"DT should have closed open DT, but didn't."
		);
	}

	/**
	 * Ensures that an opening DT closes an open DD.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dt_closes_open_dd() {
		$processor = WP_HTML_Processor::create_fragment( '<dd><dd><dt target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DT' ),
			$processor->get_breadcrumbs(),
			"DT should have closed open DT, but didn't."
		);
	}

	/**
	 * Ensures that an opening DT implicitly closes open elements with optional closing tags.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dt_generates_implied_end_tags_inside_open_dt() {
		$processor = WP_HTML_Processor::create_fragment( '<dt><dt><div><dt target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DT' ),
			$processor->get_breadcrumbs(),
			"DT should have closed open DT, but didn't."
		);
	}

	/**
	 * Ensures that an opening DT implicitly closes open elements with optional closing tags,
	 * but doesn't close beyond a special boundary.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dt_generates_implied_end_tags_inside_open_dt_but_stopping_at_special_tags() {
		$processor = WP_HTML_Processor::create_fragment( '<dt><dt><blockquote><dt target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DT', 'BLOCKQUOTE', 'DT' ),
			$processor->get_breadcrumbs(),
			'DT should have left the BLOCKQOUTE open, but closed it.'
		);
	}

	/**
	 * Ensures that an opening DT inside a DT closes a P in button scope.
	 *
	 * @ticket 60215
	 */
	public function test_in_body_dt_in_dt_closes_p_in_button_scope() {
		$processor = WP_HTML_Processor::create_fragment( '<dt><dt><p><button><p><dt target>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DT', 'P', 'BUTTON', 'DT' ),
			$processor->get_breadcrumbs(),
			'DT should have left the outer P open, but closed it.'
		);
	}

	/**
	 * Ensures that an unexpected LI doesn't close more elements than it should, that it doesn't
	 * close open LI elements that are beyond a special element (in this case, the UL).
	 *
	 * @ticket 60215
	 */
	public function test_unexpected_li_close_tag_is_properly_contained() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li><ul></li><li target>a</li></ul></li></ul>' );

		while (
			null === $processor->get_attribute( 'target' ) &&
			$processor->next_tag()
		) {
			continue;
		}

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Failed to find target node.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'UL', 'LI', 'UL', 'LI' ),
			$processor->get_breadcrumbs(),
			'Unexpected LI close tag should have left its containing UL open, but closed it.'
		);
	}
}
