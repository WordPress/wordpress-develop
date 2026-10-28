<?php

/**
 * @group formatting
 *
 * @covers ::balanceTags
 */
class Tests_Formatting_BalanceTags extends WP_UnitTestCase {

	/**
	 * @ticket 47014
	 * @dataProvider data_supported_traditional_tag_names
	 */
	public function test_detects_traditional_tag_names( $tag ) {
		$normalized = strtolower( $tag );

		$this->assertSame( "<$normalized>inside</$normalized>", balanceTags( "<$tag>inside", true ) );
	}

	public static function data_supported_traditional_tag_names() {
		return array(
			array( 'a' ),
			array( 'div' ),
			array( 'blockquote' ),
			// HTML tag names can be CAPITALIZED and are case-insensitive.
			array( 'A' ),
			array( 'dIv' ),
			array( 'BLOCKQUOTE' ),
		);
	}

	/**
	 * @ticket 47014
	 * @dataProvider data_supported_custom_element_tag_names
	 */
	public function test_detects_supported_custom_element_tag_names( $tag ) {
		$this->assertSame( "<$tag>inside</$tag>", balanceTags( "<$tag>inside", true ) );
	}

	public static function data_supported_custom_element_tag_names() {
		return array(
			array( 'custom-element' ),
			array( 'my-custom-element' ),
			array( 'weekday-5-item' ),
			array( 'a-big-old-tag-name' ),
			array( 'with_underscores-and_the_dash' ),
			array( 'a-.' ),
			array( 'a._-.-_' ),
		);
	}

	/**
	 * @ticket 47014
	 * @dataProvider data_invalid_tag_names
	 */
	public function test_ignores_invalid_tag_names( $input, $output ) {
		$this->assertSame( $output, balanceTags( $input, true ) );
	}

	public static function data_invalid_tag_names() {
		return array(
			array( '<0-day>inside', '&lt;0-day>inside' ), // Can't start with a number - handled by the "<3" fix.
			array( '<UPPERCASE-TAG>inside', '<UPPERCASE-TAG>inside' ), // Custom elements cannot be uppercase.
		);
	}

	/**
	 * @ticket 47014
	 * @dataProvider data_unsupported_valid_tag_names
	 */
	public function test_ignores_unsupported_custom_tag_names( $tag ) {
		$this->assertSame( "<$tag>inside", balanceTags( "<$tag>inside", true ) );
	}

	/**
	 * These are valid custom elements but we don't support them yet.
	 *
	 * @link https://html.spec.whatwg.org/multipage/custom-elements.html#valid-custom-element-name
	 */
	public static function data_unsupported_valid_tag_names() {
		return array(
			// We don't allow ending in a dash.
			array( '<what->inside' ),
			// Examples from the spec working document.
			array( 'math-α' ),
			array( 'emotion-😍' ),
			// Unicode ranges.
			// 0x00b7
			array( 'b-·' ),
			// Latin characters with accents/modifiers.
			// 0x00c0-0x00d6
			// 0x00d8-0x00f6
			array( 'a-À-Ó-Ý' ),
			// 0x00f8-0x037d
			array( 'a-ͳ' ),
			// No 0x037e, which is a Greek semicolon.
			// 0x037f-0x1fff
			array( 'a-Ფ' ),
			// Zero-width characters, probably never supported.
			// 0x200c-0x200d
			array( 'a-‌to-my-left-is-a-zero-width-non-joiner-do-not-delete-it' ),
			array( 'a-‍to-my-left-is-a-zero-width-joiner-do-not-delete-it' ),
			// Ties.
			// 0x203f-0x2040
			array( 'under-‿-tie' ),
			array( 'over-⁀-tie' ),
			// 0x2170-0x218f
			array( 'a-⁰' ),
			array( 'a-⅀' ),
			array( 'tag-ↀ-it' ),
			// 0x2c00-0x2fef
			array( 'a-Ⰰ' ),
			array( 'b-ⴓ-c' ),
			array( 'd-⽗' ),
			// 0x3001-0xd7ff
			array( 'a-、' ),
			array( 'z-态' ),
			array( 'a-送-䠺-ퟱ-퟿' ),
			// 0xf900-0xfdcf
			array( 'a-豈' ),
			array( 'my-切' ),
			array( 'aﴀ-tag' ),
			array( 'my-﷌' ),
			// 0xfdf0-0xfffd
			array( 'a-ﷰ' ),
			array( 'a-￰-￸-�' ), // Warning; blank characters are in there.
			// Extended ranges.
			// 0x10000-0xeffff
			array( 'a-𐀀' ),
			array( 'my-𝀀' ),
			array( 'a𞀀-𜿐' ),
		);
	}

	/**
	 * @ticket 47014
	 * @dataProvider data_supported_invalid_tag_names
	 */
	public function test_detects_supported_invalid_tag_names( $tag ) {
		$this->assertSame( "<$tag>inside</$tag>", balanceTags( "<$tag>inside", true ) );
	}

	/**
	 * These are invalid custom elements but we support them right now in order to keep the parser simpler.
	 *
	 * @link https://w3c.github.io/webcomponents/spec/custom/#valid-custom-element-name
	 */
	public static function data_supported_invalid_tag_names() {
		return array(
			// Reserved names for custom elements.
			array( 'annotation-xml' ),
			array( 'color-profile' ),
			array( 'font-face' ),
			array( 'font-face-src' ),
			array( 'font-face-uri' ),
			array( 'font-face-format' ),
			array( 'font-face-name' ),
			array( 'missing-glyph' ),
		);
	}

	/**
	 * If a recognized valid single tag appears unclosed, it should get self-closed
	 *
	 * @ticket 1597
	 * @dataProvider data_single_tags
	 */
	public function test_selfcloses_unclosed_known_single_tags( $tag ) {
		$this->assertSame( "<$tag />", balanceTags( "<$tag>", true ) );
	}

	/**
	 * If a recognized valid single tag is given a closing tag, the closing tag
	 *   should get removed and tag should be self-closed.
	 *
	 * @ticket 1597
	 * @dataProvider data_single_tags
	 */
	public function test_selfcloses_known_single_tags_having_closing_tag( $tag ) {
		$this->assertSame( "<$tag />", balanceTags( "<$tag></$tag>", true ) );
	}

	// This is a complete(?) listing of valid single/self-closing tags.
	public static function data_single_tags() {
		return array(
			array( 'area' ),
			array( 'base' ),
			array( 'basefont' ),
			array( 'br' ),
			array( 'col' ),
			array( 'command' ),
			array( 'embed' ),
			array( 'frame' ),
			array( 'hr' ),
			array( 'img' ),
			array( 'input' ),
			array( 'isindex' ),
			array( 'link' ),
			array( 'meta' ),
			array( 'param' ),
			array( 'source' ),
			array( 'track' ),
			array( 'wbr' ),
		);
	}

	/**
	 * Tests closing unknown single tags.
	 *
	 * @ticket 1597
	 * @dataProvider data_unknown_single_tags_with_closing_tag
	 */
	public function test_closes_unknown_single_tags_with_closing_tag( $input, $expected ) {
		$this->assertSame( $expected, balanceTags( $input, true ) );
	}

	/**
	 * Data provider for test_closes_unknown_single_tags_with_closing_tag.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function data_unknown_single_tags_with_closing_tag() {
		return array(
			'default'              => array( '<strong/>', '<strong></strong>' ),
			'with-space'           => array( '<em />', '<em></em>' ),
			'with-class'           => array( '<p class="main1"/>', '<p class="main1"></p>' ),
			'with-class-and-space' => array( '<p class="main2" />', '<p class="main2"></p>' ),
			// Valid tags are transformed to lowercase.
			'uppercase'            => array( '<STRONG/>', '<strong></strong>' ),
		);
	}

	public function test_closes_unclosed_single_tags_having_attributes() {
		$inputs   = array(
			'<img src="/images/example.png">',
			'<input type="text" name="example">',
		);
		$expected = array(
			'<img src="/images/example.png"/>',
			'<input type="text" name="example"/>',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	public function test_allows_validly_closed_single_tags() {
		$inputs = array(
			'<br />',
			'<hr />',
			'<img src="/images/example.png" />',
			'<input type="text" name="example" />',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $inputs[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	/**
	 * @dataProvider data_nestable_tags
	 */
	public function test_balances_nestable_tags( $tag ) {
		$inputs   = array(
			"<$tag>Test<$tag>Test</$tag>",
			"<$tag><$tag>Test",
			"<$tag>Test</$tag></$tag>",
		);
		$expected = array(
			"<$tag>Test<$tag>Test</$tag></$tag>",
			"<$tag><$tag>Test</$tag></$tag>",
			"<$tag>Test</$tag>",
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	public static function data_nestable_tags() {
		return array(
			array( 'article' ),
			array( 'aside' ),
			array( 'blockquote' ),
			array( 'details' ),
			array( 'div' ),
			array( 'figure' ),
			array( 'maction' ),
			array( 'menclose' ),
			array( 'merror' ),
			array( 'mfrac' ),
			array( 'mmultiscripts' ),
			array( 'mover' ),
			array( 'mpadded' ),
			array( 'mphantom' ),
			array( 'mroot' ),
			array( 'mrow' ),
			array( 'msqrt' ),
			array( 'mstyle' ),
			array( 'msub' ),
			array( 'msubsup' ),
			array( 'msup' ),
			array( 'munder' ),
			array( 'munderover' ),
			array( 'object' ),
			array( 'q' ),
			array( 'section' ),
			array( 'span' ),
		);
	}

	public function test_allows_adjacent_nestable_tags() {
		$inputs = array(
			'<blockquote><blockquote>Example quote</blockquote></blockquote>',
			'<div class="container"><div>This is allowed></div></div>',
			'<span><span><span>Example in spans</span></span></span>',
			'<blockquote>Main quote<blockquote>Example quote</blockquote> more text</blockquote>',
			'<q><q class="inner-q">Inline quote</q></q>',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $inputs[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	/**
	 * @ticket 20401
	 */
	public function test_allows_immediately_nested_object_tags() {
		$object = '<object id="obj1"><param name="param1"/><object id="obj2"><param name="param2"/></object></object>';
		$this->assertSame( $object, balanceTags( $object, true ) );
	}

	/**
	 * MathML elements nest within themselves: an `mrow` inside an `mrow`, a fraction inside
	 * a fraction. The rule that closes a non-nestable tag when the same tag is opened again
	 * must not apply to them, or the markup the Math block saves is corrupted.
	 *
	 * @ticket 66398
	 * @dataProvider data_nested_mathml_elements
	 */
	public function test_allows_nested_mathml_elements( $input ) {
		$this->assertSame( $input, balanceTags( $input, true ) );
	}

	public static function data_nested_mathml_elements() {
		return array(
			'mrow in mrow'         => array( '<math display="block"><semantics><mrow><mi>y</mi><mo>=</mo><mfrac><mrow><mrow><mi mathvariant="normal">Δ</mi></mrow><mi>x</mi></mrow><mi>t</mi></mfrac><mo>+</mo><mn>1</mn></mrow><annotation encoding="application/x-tex">y = \frac{\Delta x}{t} + 1</annotation></semantics></math>' ),
			'mfrac in mfrac'       => array( '<math><mfrac><mfrac><mi>a</mi><mi>b</mi></mfrac><mi>c</mi></mfrac></math>' ),
			'msub in msub'         => array( '<math><msub><msub><mi>x</mi><mi>i</mi></msub><mi>j</mi></msub></math>' ),
			'mstyle in mstyle'     => array( '<math><mstyle displaystyle="true"><mstyle mathsize="0.9em"><mi>x</mi></mstyle></mstyle></math>' ),
			'within other content' => array( '<p>Power is</p><div class="wp-block-math"><math><mrow><mrow><mi>P</mi></mrow></mrow></math></div><p>as shown.</p>' ),
		);
	}

	/**
	 * The balancer still closes MathML elements that were left open.
	 *
	 * @ticket 66398
	 */
	public function test_balances_unclosed_mathml_elements() {
		$this->assertSame(
			'<math><mrow><mrow><mi>x</mi></mrow></mrow></math>',
			balanceTags( '<math><mrow><mrow><mi>x</mi>', true )
		);
	}

	/**
	 * A `math` element cannot contain another, and the token elements such as `mi` hold
	 * text rather than elements, so opening one of them again still closes the previous one.
	 *
	 * @ticket 66398
	 * @dataProvider data_mathml_elements_that_cannot_contain_themselves
	 */
	public function test_closes_mathml_elements_that_cannot_contain_themselves( $input, $expected ) {
		$this->assertSame( $expected, balanceTags( $input, true ) );
	}

	public static function data_mathml_elements_that_cannot_contain_themselves() {
		return array(
			'math in math' => array( '<math><mi>a</mi><math><mi>b</mi></math>', '<math><mi>a</mi></math><math><mi>b</mi></math>' ),
			'mi in mi'     => array( '<math><mi>x<mi>y</math>', '<math><mi>x</mi><mi>y</mi></math>' ),
			'mo in mo'     => array( '<math><mrow><mo>+<mo>-</mrow></math>', '<math><mrow><mo>+</mo><mo>-</mo></mrow></math>' ),
		);
	}

	public function test_balances_nested_non_nestable_tags() {
		$inputs   = array(
			'<b><b>This is bold</b></b>',
			'<b>Some text here <b>This is bold</b></b>',
		);
		$expected = array(
			'<b></b><b>This is bold</b>',
			'<b>Some text here </b><b>This is bold</b>',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	public function test_fixes_improper_closing_tag_sequence() {
		$inputs   = array(
			'<p>Here is a <strong class="part">bold <em>and emphasis</p></em></strong>',
			'<ul><li>Aaa</li><li>Bbb</ul></li>',
		);
		$expected = array(
			'<p>Here is a <strong class="part">bold <em>and emphasis</em></strong></p>',
			'<ul><li>Aaa</li><li>Bbb</li></ul>',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	public function test_adds_missing_closing_tags() {
		$inputs   = array(
			'<b><i>Test</b>',
			'<p>Test',
			'<p>Test test</em> test</p>',
			'</p>Test',
			'<p>We are <strong class="wp">#WordPressStrong</p>',
		);
		$expected = array(
			'<b><i>Test</i></b>',
			'<p>Test</p>',
			'<p>Test test test</p>',
			'Test',
			'<p>We are <strong class="wp">#WordPressStrong</strong></p>',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	public function test_removes_extraneous_closing_tags() {
		$inputs   = array(
			'<b>Test</b></b>',
			'<div>Test</div></div><div>Test',
			'<p>Test test</em> test</p>',
			'</p>Test',
		);
		$expected = array(
			'<b>Test</b>',
			'<div>Test</div><div>Test</div>',
			'<p>Test test test</p>',
			'Test',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], balanceTags( $inputs[ $key ], true ) );
		}
	}

	/**
	 * Get custom element data.
	 *
	 * @return array Data.
	 */
	public static function data_custom_elements() {
		return array(
			// Valid custom element tags.
			array(
				'<my-custom-element data-attribute="value"/>',
				'<my-custom-element data-attribute="value"></my-custom-element>',
			),
			array(
				'<my-custom-element>Test</my-custom-element>',
				'<my-custom-element>Test</my-custom-element>',
			),
			array(
				'<my-custom-element>Test',
				'<my-custom-element>Test</my-custom-element>',
			),
			array(
				'Test</my-custom-element>',
				'Test',
			),
			array(
				'</my-custom-element>Test',
				'Test',
			),
			array(
				'<my-custom-element/>',
				'<my-custom-element></my-custom-element>',
			),
			array(
				'<my-custom-element />',
				'<my-custom-element></my-custom-element>',
			),
			// Invalid (or at least temporarily unsupported) custom element tags.
			array(
				'<MY-CUSTOM-ELEMENT>Test',
				'<MY-CUSTOM-ELEMENT>Test',
			),
			array(
				'<my->Test',
				'<my->Test',
			),
			array(
				'<--->Test',
				'<--->Test',
			),
		);
	}

	/**
	 * Test custom elements.
	 *
	 * @ticket 47014
	 * @dataProvider data_custom_elements
	 *
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test_custom_elements( $source, $expected ) {
		$this->assertSame( $expected, balanceTags( $source, true ) );
	}
}
