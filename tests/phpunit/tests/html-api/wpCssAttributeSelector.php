<?php
/**
 * Unit tests covering WP_CSS_Attribute_Selector functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_Attribute_Selector
 */
class Tests_HtmlApi_WpCssAttributeSelector extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_attribute_selectors
	 */
	public function test_parse_attribute(
		string $input,
		?string $expected_name = null,
		?string $expected_matcher = null,
		?string $expected_value = null,
		?string $expected_modifier = null,
		?string $rest = null
	) {
		$offset = 0;
		$result = WP_CSS_Attribute_Selector::parse( $input, $offset );
		if ( null === $expected_name ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $expected_name, $result->name );
			$this->assertSame( $expected_matcher, $result->matcher );
			$this->assertSame( $expected_value, $result->value );
			$this->assertSame( $expected_modifier, $result->modifier );
			$this->assertSame( $rest, substr( $input, $offset ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_attribute_selectors(): array {
		return array(
			'[href]'                   => array( '[href]', 'href', null, null, null, '' ),
			'[href] type'              => array( '[href] type', 'href', null, null, null, ' type' ),
			'[href]#id'                => array( '[href]#id', 'href', null, null, null, '#id' ),
			'[href].class'             => array( '[href].class', 'href', null, null, null, '.class' ),
			'[href][href2]'            => array( '[href][href2]', 'href', null, null, null, '[href2]' ),
			'[\n href\t\r]'            => array( "[\n href\t\r]", 'href', null, null, null, '' ),
			'[href=foo]'               => array( '[href=foo]', 'href', WP_CSS_Attribute_Selector::MATCH_EXACT, 'foo', null, '' ),
			'[href \n =   bar   ]'     => array( "[href \n =   bar   ]", 'href', WP_CSS_Attribute_Selector::MATCH_EXACT, 'bar', null, '' ),
			'[href \n ^=   baz   ]'    => array( "[href \n ^=   baz   ]", 'href', WP_CSS_Attribute_Selector::MATCH_PREFIXED_BY, 'baz', null, '' ),

			'[match $= insensitive i]' => array( '[match $= insensitive i]', 'match', WP_CSS_Attribute_Selector::MATCH_SUFFIXED_BY, 'insensitive', WP_CSS_Attribute_Selector::MODIFIER_CASE_INSENSITIVE, '' ),
			'[match|=sensitive s]'     => array( '[match|=sensitive s]', 'match', WP_CSS_Attribute_Selector::MATCH_EXACT_OR_HYPHEN_PREFIXED, 'sensitive', WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE, '' ),
			'[att=val I]'              => array( '[att=val I]', 'att', WP_CSS_Attribute_Selector::MATCH_EXACT, 'val', WP_CSS_Attribute_Selector::MODIFIER_CASE_INSENSITIVE, '' ),
			'[att=val S]'              => array( '[att=val S]', 'att', WP_CSS_Attribute_Selector::MATCH_EXACT, 'val', WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE, '' ),

			'[match~="quoted[][]"]'    => array( '[match~="quoted[][]"]', 'match', WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT, 'quoted[][]', null, '' ),
			"[match$='quoted!{}']"     => array( "[match$='quoted!{}']", 'match', WP_CSS_Attribute_Selector::MATCH_SUFFIXED_BY, 'quoted!{}', null, '' ),
			"[match*='quoted's]"       => array( "[match*='quoted's]", 'match', WP_CSS_Attribute_Selector::MATCH_CONTAINS, 'quoted', WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE, '' ),

			'[escape-nl="foo\\nbar"]'  => array( "[escape-nl='foo\\\nbar']", 'escape-nl', WP_CSS_Attribute_Selector::MATCH_EXACT, 'foobar', null, '' ),
			'[escape-seq="\\31 23"]'   => array( "[escape-seq='\\31 23']", 'escape-seq', WP_CSS_Attribute_Selector::MATCH_EXACT, '123', null, '' ),

			// Invalid
			'Invalid: (empty string)'  => array( '' ),
			'Invalid: foo'             => array( 'foo' ),
			'Invalid: [foo'            => array( '[foo' ),
			'Invalid: [#foo]'          => array( '[#foo]' ),
			'Invalid: [*|*]'           => array( '[*|*]' ),
			'Invalid: [ns|*]'          => array( '[ns|*]' ),
			'Invalid: [* |att]'        => array( '[* |att]' ),
			'Invalid: [*| att]'        => array( '[*| att]' ),
			'Invalid: [att * =]'       => array( '[att * =]' ),
			'Invalid: [att+=val]'      => array( '[att+=val]' ),
			'Invalid: [att=val '       => array( '[att=val ' ),
			'Invalid: [att i]'         => array( '[att i]' ),
			'Invalid: [att s]'         => array( '[att s]' ),
			"Invalid: [att='val\\n']"  => array( "[att='val\n']" ),
			'Invalid: [att=val i '     => array( '[att=val i ' ),
			'Invalid: [att="val"ix'    => array( '[att="val"ix' ),
		);
	}
}
