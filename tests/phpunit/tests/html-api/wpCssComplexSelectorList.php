<?php
/**
 * Unit tests covering WP_CSS_Complex_Selector_List functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since {WP_VERSION}
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

	/**
	 * The invalid-UTF-8 scrub notice reports the called class: through this
	 * class it must be named WP_CSS_Complex_Selector_List::from_selectors,
	 * not the WP_CSS_Compound_Selector_List parent where from_selectors()
	 * and the scrub are implemented. The fuzzer's notice model depends on
	 * the per-class name.
	 *
	 * @expectedIncorrectUsage WP_CSS_Complex_Selector_List::from_selectors
	 */
	public function test_invalid_utf8_scrub_notice_reports_the_called_class() {
		$result = WP_CSS_Complex_Selector_List::from_selectors( "el \xC2.child" );
		$this->assertNotNull( $result, 'Selector with invalid UTF-8 should parse after scrubbing.' );
	}
}
