<?php
/**
 * Unit tests covering WP_HTML_Processor select functionality.
 *
 * Covers functionality related to CSS selectors and the {@see WP_HTML_Processor::select()}
 * and {@see WP_HTML_Processor::select()} methods.
 *
 * @since {WP_VERSION}
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
	public function test_selects_all_matches( string $html, string $selector, int $match_count ) {
		$processor = WP_HTML_Processor::create_full_parser( $html );
		$count     = 0;
		while ( $processor->select( $selector ) ) {
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
			'escaped * type selector'        => array( '<!DOCTYPE html><p><div>', '\\*', 0 ),
			'escaped lowercase hex * type'   => array( '<!DOCTYPE html><p><div>', '\\2a', 0 ),
			'escaped uppercase hex * type'   => array( '<!DOCTYPE html><p><div>', '\\2A', 0 ),
			'escaped padded hex * type'      => array( '<!DOCTYPE html><p><div>', '\\00002A', 0 ),
			'escaped p type selector'        => array( '<!DOCTYPE html><p match><div>', '\\p', 1 ),
			'escaped hex p type selector'    => array( '<!DOCTYPE html><p match><div>', '\\70', 1 ),
			'escaped padded hex p type'      => array( '<!DOCTYPE html><p match><div>', '\\000070', 1 ),
			'quirks mode ID'                 => array( '<p id="id" match><p id="ID" match>In quirks mode, ID matching is case-insensitive.', '#id', 2 ),
			'quirks mode class'              => array( '<p class="c" match><p class="C" match>In quirks mode, class matching is case-insensitive.', '.c', 2 ),
			'no-quirks mode ID'              => array( '<!DOCTYPE html><p id="id" match><p id="ID" match>In no-quirks mode, ID matching is case-sensitive.', '#id', 1 ),
			'no-quirks mode class'           => array( '<!DOCTYPE html><p class="c" match><p class="C">In no-quirks mode, class matching is case-sensitive.', '.c', 1 ),
			'any descendant'                 => array( '<section><p match><i match><em match><p match>', 'section *', 4 ),
			'any child matches all children' => array( '<section><p match><i><em><p match>', 'section > *', 2 ),

			'multiple complex selectors'     => array( '<section><div><p><span><i></i><p><i match>', 'section > div p > i', 1 ),

			// Per Selectors-4, the substring matchers ^= $= *= match nothing when the value
			// is empty. ~= also matches nothing: an empty string is never a list item.
			'empty value ^= matches nothing' => array( '<i x=""><b x="abc">', '[x^=""]', 0 ),
			'empty value $= matches nothing' => array( '<i x=""><b x="abc">', '[x$=""]', 0 ),
			'empty value *= matches nothing' => array( '<i x=""><b x="abc">', '[x*=""]', 0 ),
			'empty value ~= matches nothing' => array( '<i x=""><b x="abc">', '[x~=""]', 0 ),
			'empty value ^= i matches nothing' => array( '<i x=""><b x="abc">', '[x^="" i]', 0 ),
			'empty value = matches empty'    => array( '<i x="" match><b x="abc">', '[x=""]', 1 ),
			'empty value |= matches empty or hyphen-prefixed' => array( '<i x="" match><s x="-foo" match><b x="abc">', '[x|=""]', 2 ),

			/*
			 * HTML's case-insensitive attribute value list applies to
			 * "an HTML element in an HTML document": a foreign element with
			 * the same attribute name keeps case-sensitive matching.
			 * ( Chromium applies the list to foreign elements as well,
			 * diverging from the HTML specification here. )
			 *
			 * https://html.spec.whatwg.org/multipage/semantics-other.html#case-sensitivity-of-selectors
			 */
			'HTML-namespace-only attribute case-insensitivity' => array( '<!DOCTYPE html><a type="text" match></a><svg><a type="text"></a></svg>', '[type=TEXT]', 1 ),
		);
	}

	/**
	 * @ticket 62653
	 *
	 * @expectedIncorrectUsage WP_HTML_Processor::select
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
			'invalid selector'                        => array( '[invalid!selector]' ),

			// The class selectors below are not allowed in non-final position.
			'unsupported child selector'              => array( '.parent > .child' ),
			'unsupported descendant selector'         => array( '.ancestor .descendant' ),

			// Unsupported combinators
			'unsupported next sibling selector'       => array( 'p + p' ),
			'unsupported subsequent sibling selector' => array( 'p ~ p' ),
		);
	}
}
