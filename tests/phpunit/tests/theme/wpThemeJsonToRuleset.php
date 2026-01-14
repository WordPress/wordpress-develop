<?php
/**
 * Tests for WP_Theme_JSON::to_ruleset() and related CSS sanitization methods.
 *
 * @group themes
 * @group theme-json
 *
 * @covers WP_Theme_JSON::to_ruleset
 * @covers WP_Theme_JSON::sanitize_css_selector
 * @covers WP_Theme_JSON::sanitize_css_property_name
 * @covers WP_Theme_JSON::sanitize_css_property_value
 */
class Tests_Theme_WpThemeJsonToRuleset extends WP_UnitTestCase {
	private function to_ruleset( $selector, $declarations ) {
		$method = new ReflectionMethod( WP_Theme_JSON::class, 'to_ruleset' );
		$method->setAccessible( true );
		return $method->invoke( null, $selector, $declarations );
	}

	/**
	 * Test that to_ruleset generates valid CSS.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_generates_valid_css() {
		$selector     = '.wp-block-test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'red',
			),
			array(
				'name'  => 'font-size',
				'value' => '16px',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '.wp-block-test{', $result );
		$this->assertStringContainsString( 'color: red;', $result );
		$this->assertStringContainsString( 'font-size: 16px;', $result );
	}

	/**
	 * Test that selector injection via braces is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_selector_injection_braces() {
		$selector     = '.test } body { background: red; } .fake {';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Malicious selector should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that selector injection via semicolons is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_selector_injection_semicolons() {
		$selector     = '.test; } body { display: none; } .fake';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Malicious selector should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that CSS comments in selectors are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_selector_comments() {
		$selector     = '.test /* comment */ .nested';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Selectors with comments should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that @-rules in selectors are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_selector_at_rules() {
		$selector     = '@media screen { .test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Selectors with @-rules should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that excessively long selectors are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_long_selectors() {
		$selector     = str_repeat( '.test-class-name-', 100 );
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Excessively long selectors should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that property name injection is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_sanitizes_property_names() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color; } body { background',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Dangerous characters in property name should be removed.
		// The sanitized name becomes 'colorbodybackground' (no spaces or special chars).
		$this->assertStringContainsString( 'colorbodybackground: blue;', $result );
		$this->assertStringNotContainsString( 'color; } body { background', $result );
	}

	/**
	 * Test that property value injection via braces is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_property_value_injection_braces() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'red; } body { background: url(evil.com); } .fake { color',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// The property should still be present but sanitized.
		$this->assertStringContainsString( 'color:', $result );
	}

	/**
	 * Test that javascript: URLs are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_javascript_urls() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'background',
				'value' => 'url(javascript:alert(1))',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Declaration with javascript: URL should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that data: URLs are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_data_urls() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'background',
				'value' => 'url(data:text/html,<script>alert(1)</script>)',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Declaration with data: URL should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that @import is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_import() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'font',
				'value' => 'Arial; @import url(evil.com);',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Declaration with @import should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that IE expression() is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_ie_expressions() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'width',
				'value' => 'expression(alert(1))',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Declaration with expression() should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that excessively long values are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_blocks_long_values() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => str_repeat( 'a', 3000 ),
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Excessively long value should be rejected.
		$this->assertSame( '', $result );
	}

	/**
	 * Test that calc() is preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_calc() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'width',
				'value' => 'calc(100% - 20px)',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'calc(100% - 20px)', $result );
	}

	/**
	 * Test that var() is preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_var() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'var(--wp--preset--color--primary)',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'var(--wp--preset--color--primary)', $result );
	}

	/**
	 * Test that gradients are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_gradients() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'background',
				'value' => 'linear-gradient(90deg, #ff0000, #0000ff)',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'linear-gradient(90deg, #ff0000, #0000ff)', $result );
	}

	/**
	 * Test that quoted strings are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_quoted_strings() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'font-family',
				'value' => '"Times New Roman", serif',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '"Times New Roman", serif', $result );
	}

	/**
	 * Test that braces inside quotes are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_braces_in_quotes() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'content',
				'value' => '"{not a rule}"',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '"{not a rule}"', $result );
	}

	/**
	 * Test that HTTPS URLs are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_https_urls() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'background-image',
				'value' => 'url(https://example.com/image.jpg)',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'url(https://example.com/image.jpg)', $result );
	}

	/**
	 * Test that pseudo-selectors are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_pseudo_selectors() {
		$selector     = '.test:hover';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'blue',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '.test:hover{', $result );
	}

	/**
	 * Test that attribute selectors are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_attribute_selectors() {
		$selector     = 'input[type="text"]';
		$declarations = array(
			array(
				'name'  => 'border',
				'value' => '1px solid black',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'input[type="text"]{', $result );
	}

	/**
	 * Test that combinators are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_combinators() {
		$selector     = '.parent > .child';
		$declarations = array(
			array(
				'name'  => 'margin',
				'value' => '10px',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '.parent > .child{', $result );
	}

	/**
	 * Test that empty declarations return empty string.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_returns_empty_for_empty_declarations() {
		$selector     = '.test';
		$declarations = array();

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that invalid declarations are skipped.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_skips_invalid_declarations() {
		$selector     = '.test';
		$declarations = array(
			array( 'invalid' => 'format' ),
			array(
				'name'  => 'color',
				'value' => 'red',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( 'color: red', $result );
	}

	/**
	 * Test that custom properties are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_preserves_custom_properties() {
		$selector     = ':root';
		$declarations = array(
			array(
				'name'  => '--wp--custom--spacing',
				'value' => '20px',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		$this->assertStringContainsString( '--wp--custom--spacing: 20px', $result );
	}

	/**
	 * Test that all invalid declarations result in empty output.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_returns_empty_for_all_invalid_declarations() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'url(javascript:alert(1))',
			),
			array(
				'name'  => 'background',
				'value' => '@import url(evil.com);',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// All declarations invalid = empty output.
		$this->assertSame( '', $result );
	}

	/**
	 * Test mixed valid and invalid declarations.
	 *
	 * @ticket 62224
	 */
	public function test_to_ruleset_handles_mixed_declarations() {
		$selector     = '.test';
		$declarations = array(
			array(
				'name'  => 'color',
				'value' => 'red',
			),
			array(
				'name'  => 'background',
				'value' => 'url(javascript:alert(1))',
			),
			array(
				'name'  => 'font-size',
				'value' => '16px',
			),
		);

		$result = $this->to_ruleset( $selector, $declarations );

		// Valid declarations should be included.
		$this->assertStringContainsString( 'color: red', $result );
		$this->assertStringContainsString( 'font-size: 16px', $result );
		// Invalid declaration should be excluded.
		$this->assertStringNotContainsString( 'javascript', $result );
	}
}
