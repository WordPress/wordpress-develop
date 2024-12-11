<?php
/**
 * Unit tests covering WP_CSS_Complex_Selector_List functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_Complex_Selector_List
 */
class Tests_HtmlApi_WpCssComplexSelectorList extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 */
	public function test_parse_complex_selector_list() {
		$input  = 'el1 el2 el.foo#bar[baz=quux], second > selector';
		$result = WP_CSS_Complex_Selector_List::from_selectors( $input );
		$this->assertNotNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_invalid_selector_list() {
		$input  = 'el,,';
		$result = WP_CSS_Complex_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_invalid_selector_list2() {
		$input  = 'el!';
		$result = WP_CSS_Complex_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_empty_selector_list() {
		$input  = " \t   \t\n\r\f";
		$result = WP_CSS_Complex_Selector_List::from_selectors( $input );
		$this->assertNull( $result );
	}
}
