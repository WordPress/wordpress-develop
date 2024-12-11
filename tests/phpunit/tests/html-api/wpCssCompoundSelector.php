<?php
/**
 * Unit tests covering WP_CSS_Compound_Selector functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_Compound_Selector
 */
class Tests_HtmlApi_WpCssCompoundSelector extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 */
	public function test_parse_selector() {
		$input  = 'el.foo#bar[baz=quux] > .child';
		$offset = 0;
		$sel    = WP_CSS_Compound_Selector::parse( $input, $offset );

		$this->assertSame( 'el', $sel->type_selector->type );
		$this->assertSame( 3, count( $sel->subclass_selectors ) );
		$this->assertSame( 'foo', $sel->subclass_selectors[0]->class_name, 'foo' );
		$this->assertSame( 'bar', $sel->subclass_selectors[1]->id, 'bar' );
		$this->assertSame( 'baz', $sel->subclass_selectors[2]->name, 'baz' );
		$this->assertSame( WP_CSS_Attribute_Selector::MATCH_EXACT, $sel->subclass_selectors[2]->matcher );
		$this->assertSame( 'quux', $sel->subclass_selectors[2]->value );
		$this->assertSame( ' > .child', substr( $input, $offset ) );
	}

	/**
	 * @ticket 62653
	 */
	public function test_parse_empty_selector() {
		$input  = '';
		$offset = 0;
		$result = WP_CSS_Compound_Selector::parse( $input, $offset );
		$this->assertNull( $result );
		$this->assertSame( 0, $offset );
	}
}
