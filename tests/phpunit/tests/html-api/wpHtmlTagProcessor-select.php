<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor CSS selection functionality.
 *
 * Covers functionality related to CSS selectors and the {@see WP_HTML_Tag_Processor::select()} method.
 *
 * @since {WP_VERSION}
 *
 * @group html-api
 */
class Tests_HtmlApi_WpHtmlTagProcessor_Select extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 */
	public function test_select_miss() {
		$processor = new WP_HTML_Tag_Processor( '<span>' );
		$this->assertFalse( $processor->select( 'div' ) );
	}

	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_selectors
	 */
	public function test_select( string $html, string $selector, int $match_count ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$count     = 0;
		while ( $processor->select( $selector ) ) {
			$this->assertTrue(
				$processor->get_attribute( 'match' ),
				"Matched unexpected tag {$processor->get_tag()}"
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
			'simple type'                         => array( '<div match><div match><span>', 'div', 2 ),
			'any type'                            => array( '<div match><span match>', '*', 2 ),
			'simple class'                        => array( '<div><div class="x" match><span><span class="x" match>', '.x', 2 ),
			'simple id'                           => array( '<div><div id="x" match><span id="x" match>', '#x', 2 ),

			'attribute presence'                  => array( '<div tta><div att match><span att="foo" match>', '[att]', 2 ),
			'attribute empty string match'        => array( '<div tta><div att match><span match att=>', '[att=""]', 2 ),
			'attribute value'                     => array( '<div att="v"><div att="val" match><p match att=val>', '[att=val]', 2 ),
			'attribute quoted value'              => array( '<div att><div att="::" match><p att=\'::\' match>', '[att="::"]', 2 ),
			'attribute case insensitive'          => array( '<div att="value"><div att="val" match><p match att=VaL>', '[att="VAL"i]', 2 ),
			'attribute case sensitive mod'        => array( '<div att="VAL"><div att="val" match><p att=val match>', '[att="val"s]', 2 ),

			'attribute one of'                    => array( '<div att="a B c"><div att="a b c" match><p att="b" match><p att="abc"><p att="abcdef b   " match>', '[att~="b"]', 3 ),
			'attribute one of insensitive'        => array( '<div att="a c"><div att="a B c" match>', '[att~="b"i]', 1 ),
			'attribute one of mod sensitive'      => array( '<div att="a B c"><div att="a b c" match>', '[att~="b"s]', 1 ),
			'attribute one of whitespace cases'   => array( "<div att='     a'><div att='\na\ta   b   ' match>", '[att~="b"]', 1 ),

			'attribute with-hyphen'               => array( '<p att="special_not"><p att="special" match><p att="special-great" match>', '[att|="special"]', 2 ),
			'attribute with-hyphen insensitive'   => array( '<p att="special_not"><p att="SPECIAL" match><p att="SPECIAL-great" match>', '[att|="special" i]', 2 ),
			'attribute with-hyphen sensitive mod' => array( '<p att="SPECIAL"><p att="special" match>', '[att|="special"s]', 1 ),

			'attribute prefixed'                  => array( '<p att="notprefix"><p att="prefix" match><p att="perfect" match>', '[att^="p"]', 2 ),
			'attribute prefixed insensitive'      => array( '<p att="notprefix"><p att="Prefix" match>', '[att^="p"i]', 1 ),
			'attribute prefixed sensitive mod'    => array( '<p att="Prefix"><p att="prefix" match>', '[att^="p"s]', 1 ),

			'attribute suffixed'                  => array( '<p att="suffix_"><p att="suffix" match><p att="brilliant…x" match>', '[att$="x"]', 2 ),
			'attribute suffixed insensitive'      => array( '<p att="suffix_"><p att="suffiX" match>', '[att$="x"i]', 1 ),
			'attribute suffixed sensitive mod'    => array( '<p att="suffiX"><p att="suffix" match>', '[att$="x"s]', 1 ),

			'attribute contains'                  => array( '<p att="abcyz"><p att="abcxyz" match><p att="x" match>', '[att*="x"]', 2 ),
			'attribute contains insensitive'      => array( '<p att="abcyz"><p att="abcXyz" match>', '[att*="x"i]', 1 ),
			'attribute contains sensitive mod'    => array( '<p att="abcXyz"><p att="abcxyz" match>', '[att*="x"s]', 1 ),

			/*
			 * An escaped trailing whitespace code point is part of the ident,
			 * not trailing whitespace: `.foo\ ` is the class `foo ` (with a
			 * space). Class attribute values are whitespace-separated token
			 * lists, so such a class can never match. It must NOT be confused
			 * with a backslash at the end of input, which decodes to U+FFFD.
			 */
			'escaped space at end'                => array( "<div class=\"foo\u{FFFD}\"><div class=\"foo\"><div class=\"foo \">", '.foo\\ ', 0 ),
			'escaped tab at end'                  => array( "<div class=\"foo\u{FFFD}\"><div class=\"foo\"><div class=\"foo\t\">", ".foo\\\t", 0 ),

			/*
			 * The end of input closes an open attribute selector ( and an
			 * unterminated string ): tokenization auto-closes simple blocks
			 * at EOF.
			 */
			'EOF-truncated attribute presence'    => array( '<div att match><span>', '[att', 1 ),
			'EOF-truncated attribute value'       => array( '<div att=val match><div att=other>', '[att=val', 1 ),
			'EOF-truncated quoted value'          => array( '<div att="a b" match><div att="a">', '[att="a b', 1 ),
			'EOF-truncated with modifier'         => array( '<div att="VAL" match><div att=x>', '[att=val i', 1 ),

			/*
			 * HTML defines a set of attributes whose values must match ASCII
			 * case-insensitively in selectors when no modifier is present.
			 * An explicit `s` modifier still forces case-sensitive matching.
			 * Attributes outside the list stay case-sensitive by default.
			 *
			 * https://html.spec.whatwg.org/multipage/semantics-other.html#case-sensitivity-of-selectors
			 */
			'HTML insensitive attribute ='        => array( '<input type="text" match><input type="TEXT" match><input type="other">', '[type=TEXT]', 2 ),
			'HTML insensitive attribute ~='       => array( '<a rel="NoFollow external" match><a rel="me">', '[rel~=nofollow]', 1 ),
			'HTML insensitive attribute ^='       => array( '<b media="SCREEN and x" match><b media="print">', '[media^=screen]', 1 ),
			'HTML insensitive attribute |='       => array( '<b hreflang="EN-US" match><b hreflang="deu">', '[hreflang|=en]', 1 ),
			'HTML insensitive attribute s mod'    => array( '<input type="text" match><input type="TEXT">', '[type=text s]', 1 ),
			'HTML insensitive attribute i mod'    => array( '<input type="text" match><input type="TEXT" match>', '[type=text i]', 2 ),
			'unlisted attribute stays sensitive'  => array( '<i data-type="text"><i data-type="TEXT" match>', '[data-type=TEXT]', 1 ),
			'listed attribute name is matched case-insensitively in the list' => array( '<input type="text" match>', '[TYPE=TEXT]', 1 ),

			'list'                                => array( '<div><p match><a match><span>', 'a, p, .class, #id, [att]', 2 ),
			'compound'                            => array( '<div att><custom-el att="bar" fruit="APPLE BANANA" match>', 'custom-el[att="bar"][    fruit ~= "banana" i]', 1 ),
		);
	}

	/**
	 * @ticket 62653
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::select
	 *
	 * @dataProvider data_invalid_selectors
	 */
	public function test_invalid_selector( string $selector ) {
		$processor = new WP_HTML_Tag_Processor( 'irrelevant' );
		$this->assertFalse( $processor->select( $selector ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_invalid_selectors(): array {
		return array(
			'complex descendant' => array( 'div *' ),
			'complex child'      => array( 'div > *' ),
			'invalid selector'   => array( '[invalid!selector]' ),

			/*
			 * A backslash before a newline at the end of input is not a valid
			 * escape and is not trailing whitespace: the selector is invalid.
			 * The CR and FF variants are normalized to a newline before
			 * tokenizing.
			 */
			'escape before newline at end' => array( ".foo\\\n" ),
			'escape before CR at end'      => array( ".foo\\\r" ),
			'escape before FF at end'      => array( ".foo\\\f" ),

			/*
			 * EOF auto-closes an open attribute selector block, but
			 * grammar-level truncation is still invalid.
			 */
			'truncated matcher without value' => array( '[a=' ),
			'truncated half matcher'          => array( '[a~' ),
			'lone open bracket'               => array( '[' ),
		);
	}
}
