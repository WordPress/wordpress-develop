<?php
/**
 * Unit tests covering WP_HTML_Processor select functionality.
 *
 * Covers functionality related to CSS selectors and the {@see WP_HTML_Processor::select()}
 * and {@see WP_HTML_Processor::select_all()} methods.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 */
class Tests_HtmlApi_WpHtmlProcessor_Select extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 */
	public function test_select_miss() {
		$processor = WP_HTML_Processor::create_full_parser( '<span>' );
		$this->assertFalse( $processor->select( 'div' ) );
	}

	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_selectors
	 */
	public function test_select_all( string $html, string $selector, int $match_count ) {
		$processor = WP_HTML_Processor::create_full_parser( $html );
		$count     = 0;
		foreach ( $processor->select_all( $selector ) as $_ ) {
			$breadcrumb_string = implode( ', ', $processor->get_breadcrumbs() );
			$this->assertTrue(
				$processor->get_attribute( 'match' ),
				"Matched unexpected tag {$processor->get_tag()} @ {$breadcrumb_string}"
			);
			++$count;
		}
		$this->assertSame( $match_count, $count, 'Did not match expected number of tags.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_selectors(): array {
		return array(
			'any'                            => array( '<html match><head match><meta match><body match><p match>', '*', 5 ),
			'quirks mode ID'                 => array( '<p id="id" match><p id="ID" match>In quirks mode, ID matching is case-insensitive.', '#id', 2 ),
			'quirks mode class'              => array( '<p class="c" match><p class="C" match>In quirks mode, class matching is case-insensitive.', '.c', 2 ),
			'no-quirks mode ID'              => array( '<!DOCTYPE html><p id="id" match><p id="ID" match>In no-quirks mode, ID matching is case-sensitive.', '#id', 1 ),
			'no-quirks mode class'           => array( '<!DOCTYPE html><p class="c" match><p class="C">In no-quirks mode, class matching is case-sensitive.', '.c', 1 ),
			'any descendant'                 => array( '<section><p match><i match><em match><p match>', 'section *', 4 ),
			'any child matches all children' => array( '<section><p match><i><em><p match>', 'section > *', 2 ),

			'multiple complex selectors'     => array( '<section><div><p><span><i></i><p><i match>', 'section > div p > i', 1 ),
		);
	}

	/**
	 * @ticket 62653
	 *
	 * @expectedIncorrectUsage WP_HTML_Processor::select_all
	 *
	 * @dataProvider data_invalid_selectors
	 */
	public function test_invalid_selector( string $selector ) {
		$processor = WP_HTML_Processor::create_fragment( 'irrelevant' );
		$this->assertFalse( $processor->select( $selector ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_invalid_selectors(): array {
		return array(
			'invalid selector'                => array( '[invalid!selector]' ),

			// The class selectors below are not allowed in non-final position.
			'unsupported child selector'      => array( '.parent > .child' ),
			'unsupported descendant selector' => array( '.ancestor .descendant' ),
		);
	}
}
