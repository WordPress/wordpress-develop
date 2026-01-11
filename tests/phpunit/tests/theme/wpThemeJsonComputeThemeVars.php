<?php
/**
 * Tests for WP_Theme_JSON::compute_theme_vars().
 *
 * @group themes
 * @group theme-json
 *
 * @covers WP_Theme_JSON::compute_theme_vars
 */
class Tests_Theme_WpThemeJsonComputeThemeVars extends WP_UnitTestCase {
	private function compute_theme_vars( $settings ) {
		$method = new ReflectionMethod( WP_Theme_JSON::class, 'compute_theme_vars' );
		$method->setAccessible( true );
		return $method->invoke( null, $settings );
	}

	/**
	 * Test that CSS variable names are properly sanitized.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_sanitizes_variable_names() {
		$settings = array(
			'custom' => array(
				'color}body{background' => 'red',
				'valid-name'            => 'blue',
				'UPPERCASE'             => 'green',
				'with_underscores'      => 'yellow',
				'special!@#chars'       => 'purple',
			),
		);

		$result = $this->compute_theme_vars( $settings );
		$vars   = array();
		foreach ( $result as $declaration ) {
			$vars[ $declaration['name'] ] = $declaration['value'];
		}

		$this->assertCount( 5, $result );
		$this->assertArrayHasKey( '--wp--custom--color-body-background', $vars );
		$this->assertSame( 'red', $vars['--wp--custom--color-body-background'] );
		$this->assertSame( 'blue', $vars['--wp--custom--valid-name'] );
		$this->assertSame( 'green', $vars['--wp--custom--uppercase'] );
		$this->assertSame( 'yellow', $vars['--wp--custom--with-underscores'] );
		$this->assertSame( 'purple', $vars['--wp--custom--special-chars'] );
	}

	/**
	 * Test that CSS injection via semicolons is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_semicolon_injection() {
		$settings = array(
			'custom' => array(
				'color' => 'red; } body { display: none; } /*',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( '--wp--custom--color', $result[0]['name'] );
		// Semicolons and braces outside quotes should be stripped.
		$this->assertStringNotContainsString( ';', $result[0]['value'] );
		$this->assertStringNotContainsString( '{', $result[0]['value'] );
		$this->assertStringNotContainsString( '}', $result[0]['value'] );
	}

	/**
	 * Test that CSS injection via braces is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_brace_injection() {
		$settings = array(
			'custom' => array(
				'evil' => 'x; } * { background: red; } /*',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		// Braces should be removed.
		$this->assertStringNotContainsString( '{', $result[0]['value'] );
		$this->assertStringNotContainsString( '}', $result[0]['value'] );
	}

	/**
	 * Test that javascript: URLs are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_javascript_urls() {
		$settings = array(
			'custom' => array(
				'bg1' => 'url(javascript:alert(1))',
				'bg2' => 'url("javascript:alert(2)")',
				'bg3' => "url('javascript:alert(3)')",
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// All javascript: URLs should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that data: URLs are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_data_urls() {
		$settings = array(
			'custom' => array(
				'bg' => 'url(data:text/html,<script>alert(1)</script>)',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// data: URLs should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that vbscript: URLs are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_vbscript_urls() {
		$settings = array(
			'custom' => array(
				'bg' => 'url(vbscript:msgbox(1))',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// vbscript: URLs should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that @import rules are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_import_rules() {
		$settings = array(
			'custom' => array(
				'evil' => 'x; } @import url(evil.com/malicious.css); /*',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// @import should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that @charset rules are blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_charset_rules() {
		$settings = array(
			'custom' => array(
				'evil' => '@charset "UTF-8";',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// @charset should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that IE expression() is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_ie_expressions() {
		$settings = array(
			'custom' => array(
				'width' => 'expression(alert(1))',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// IE expressions should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that behavior is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_behavior() {
		$settings = array(
			'custom' => array(
				'evil' => 'behavior(url(evil.htc))',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// behavior should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that -moz-binding is blocked.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_moz_binding() {
		$settings = array(
			'custom' => array(
				'evil' => '-moz-binding(url(evil.xml))',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// -moz-binding should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that excessively long values are blocked (DoS prevention).
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_blocks_long_values() {
		$settings = array(
			'custom' => array(
				'long' => str_repeat( 'a', 3000 ),
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// Values over 2000 characters should be blocked.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that legitimate calc() values are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_calc() {
		$settings = array(
			'custom' => array(
				'spacing' => 'calc(100% - 20px)',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( 'calc(100% - 20px)', $result[0]['value'] );
	}

	/**
	 * Test that legitimate var() values are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_var() {
		$settings = array(
			'custom' => array(
				'color' => 'var(--wp--preset--color--primary)',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( 'var(--wp--preset--color--primary)', $result[0]['value'] );
	}

	/**
	 * Test that legitimate gradient values are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_gradients() {
		$settings = array(
			'custom' => array(
				'gradient' => 'linear-gradient(90deg, #ff0000, #0000ff)',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( 'linear-gradient(90deg, #ff0000, #0000ff)', $result[0]['value'] );
	}

	/**
	 * Test that legitimate quoted font names are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_quoted_values() {
		$settings = array(
			'custom' => array(
				'font' => '"Times New Roman", serif',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( '"Times New Roman", serif', $result[0]['value'] );
	}

	/**
	 * Test that semicolons inside quoted strings are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_semicolons_in_quotes() {
		$settings = array(
			'custom' => array(
				'content' => '"Hello; World"',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( '"Hello; World"', $result[0]['value'] );
	}

	/**
	 * Test that braces inside quoted strings are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_braces_in_quotes() {
		$settings = array(
			'custom' => array(
				'content' => '"{not a CSS rule}"',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( '"{not a CSS rule}"', $result[0]['value'] );
	}

	/**
	 * Test that legitimate HTTPS URLs are preserved.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_preserves_https_urls() {
		$settings = array(
			'custom' => array(
				'bg' => 'url(https://example.com/image.jpg)',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( 'url(https://example.com/image.jpg)', $result[0]['value'] );
	}

	/**
	 * Test that non-scalar values are ignored.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_ignores_non_scalar_values() {
		$settings = array(
			'custom' => array(
				'array'  => array( 'nested' => 'value' ),
				'object' => new stdClass(),
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// Nested arrays should be flattened and processed (1 result).
		// Objects should be ignored (non-scalar after flattening).
		$this->assertCount( 1, $result );
		$this->assertSame( '--wp--custom--array--nested', $result[0]['name'] );
		$this->assertSame( 'value', $result[0]['value'] );
	}

	/**
	 * Test that empty values are ignored.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_ignores_empty_values() {
		$settings = array(
			'custom' => array(
				'empty1' => '',
				'empty2' => '   ',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		// Empty values should be ignored.
		$this->assertCount( 0, $result );
	}

	/**
	 * Test that HTML tags are stripped from values.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_strips_html_tags() {
		$settings = array(
			'custom' => array(
				'color' => '<script>alert(1)</script>red',
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertStringNotContainsString( '<script>', $result[0]['value'] );
		$this->assertStringNotContainsString( '</script>', $result[0]['value'] );
	}

	/**
	 * Test that control characters are removed.
	 *
	 * @ticket 62224
	 */
	public function test_compute_theme_vars_removes_control_characters() {
		$settings = array(
			'custom' => array(
				'color' => "red\x00\x1F",
			),
		);

		$result = $this->compute_theme_vars( $settings );

		$this->assertCount( 1, $result );
		$this->assertSame( 'red', $result[0]['value'] );
	}
}
