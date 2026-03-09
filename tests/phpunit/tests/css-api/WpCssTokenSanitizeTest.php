<?php
/**
 * Unit tests covering WP_CSS_Token_Processor::sanitize().
 *
 * @package WordPress
 * @subpackage CSS-API
 *
 * @since X.X.0
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token_Processor
 */
class Tests_CssApi_WpCssTokenSanitize extends WP_UnitTestCase {

	/**
	 * Helper: run sanitize() on a CSS string and return the result.
	 *
	 * @param string $css
	 * @return string
	 */
	private function sanitize( string $css ): string {
		return ( new WP_CSS_Token_Processor( $css ) )->sanitize();
	}

	// --- Injection guard ---

	/**
	 * @covers ::sanitize
	 */
	public function test_style_close_tag_returns_empty_string() {
		$this->assertSame( '', $this->sanitize( 'color: red; </style> .evil {}' ) );
	}

	public function test_partial_style_close_tag_returns_empty_string() {
		$this->assertSame( '', $this->sanitize( 'color: red; </style' ) );
	}

	public function test_style_close_tag_case_insensitive() {
		$this->assertSame( '', $this->sanitize( 'color: red; </STYLE>' ) );
	}

	// --- Null bytes ---

	public function test_null_bytes_are_stripped() {
		$this->assertSame( 'color: red;', $this->sanitize( "color\0: red;" ) );
	}

	// --- PR #11104 regression cases: CSS nesting must survive ---

	public function test_css_nesting_ampersand_survives() {
		$css = 'color: blue; & p { color: red; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_child_combinator_survives() {
		$css = '& > p { margin: 0; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_adjacent_sibling_combinator_survives() {
		$css = '& + span { color: green; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	// --- CDO/CDC stripped ---

	public function test_cdo_token_stripped() {
		$this->assertSame( 'color: red;', $this->sanitize( '<!--color: red;' ) );
	}

	public function test_cdc_token_stripped() {
		$this->assertSame( 'color: red;', $this->sanitize( '-->color: red;' ) );
	}

	// --- Bad tokens stripped ---

	public function test_bad_string_token_stripped() {
		// An unescaped newline produces a bad-string-token for the partial string up to the newline.
		// The remainder of the line (after the newline) is preserved as separate tokens,
		// so we can only assert that the bad content is not present rather than the exact output.
		$result = $this->sanitize( "content: \"bad\nstring\";" );
		$this->assertStringNotContainsString( 'bad', $result );
	}

	public function test_bad_url_token_stripped() {
		// Whitespace inside url() produces a bad-url-token; the whole url(…) is stripped.
		$this->assertSame( 'background-image: ;', $this->sanitize( 'background-image: url(bad url);' ) );
	}

	public function test_get_removed_tokens_reason_bad_string() {
		$p = new WP_CSS_Token_Processor( "color: \"bad\nstring\";" );
		$p->sanitize();
		$removed = $p->get_removed_tokens();
		$this->assertNotEmpty( $removed, 'Expected bad_string token to be recorded' );
		$this->assertSame( 'bad_string', $removed[0]['reason'] );
	}

	public function test_get_removed_tokens_reason_bad_url() {
		$p = new WP_CSS_Token_Processor( 'background: url(bad url);' );
		$p->sanitize();
		$removed = $p->get_removed_tokens();
		$this->assertNotEmpty( $removed, 'Expected bad_url token to be recorded' );
		$this->assertSame( 'bad_url', $removed[0]['reason'] );
	}

	// --- URL protocol filtering ---

	public function test_url_with_javascript_protocol_stripped() {
		// url(javascript:evil) is a URL_TOKEN; javascript: scheme is always stripped entirely.
		$this->assertSame( 'background: ;', $this->sanitize( 'background: url(javascript:evil);' ) );
	}

	public function test_url_with_data_protocol_stripped() {
		$this->assertSame( 'background: ;', $this->sanitize( 'background: url(data:image/png;base64,abc);' ) );
	}

	public function test_url_with_https_survives() {
		$css = 'background: url(https://example.com/image.png);';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_url_with_relative_path_survives() {
		$css = 'background: url(image.png);';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	// --- At-rule allowlist ---

	public function test_allowed_at_rule_media_survives() {
		$css = '@media (max-width: 768px) { color: red; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_allowed_at_rule_supports_survives() {
		$css = '@supports (display: grid) { color: red; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_allowed_at_rule_keyframes_survives() {
		$css = '@keyframes slide { from { opacity: 0; } to { opacity: 1; } }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_blocked_at_rule_import_stripped() {
		$result = $this->sanitize( "@import url('https://evil.com/style.css'); color: red;" );
		$this->assertStringNotContainsString( '@import', $result );
		$this->assertStringContainsString( 'color: red;', $result );
	}

	public function test_blocked_at_rule_charset_stripped() {
		$result = $this->sanitize( '@charset "UTF-8"; color: red;' );
		$this->assertStringNotContainsString( '@charset', $result );
		$this->assertStringContainsString( 'color: red;', $result );
	}

	public function test_unknown_at_rule_stripped() {
		$result = $this->sanitize( '@unknown-future-rule { color: red; } .a { color: blue; }' );
		$this->assertStringNotContainsString( '@unknown-future-rule', $result );
		$this->assertStringContainsString( 'color: blue;', $result );
	}

	public function test_allowed_at_rule_layer_survives() {
		$css = '@layer utilities { color: red; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_allowed_at_rule_container_survives() {
		$css = '@container (width > 400px) { color: red; }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_allowed_at_rule_font_face_survives() {
		$css = '@font-face { font-family: "My Font"; src: url(my-font.woff2); }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_blocked_at_rule_namespace_stripped() {
		$result = $this->sanitize( '@namespace url(http://www.w3.org/1999/xhtml); color: red;' );
		$this->assertStringNotContainsString( '@namespace', $result );
		$this->assertStringContainsString( 'color: red;', $result );
	}

	public function test_allowed_at_rule_webkit_keyframes_survives() {
		$css = '@-webkit-keyframes slide { from { opacity: 0; } to { opacity: 1; } }';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_multiple_blocked_at_rules_all_stripped() {
		$result = $this->sanitize( "@import 'evil.css'; @charset 'UTF-8'; color: red;" );
		$this->assertStringNotContainsString( '@import', $result );
		$this->assertStringNotContainsString( '@charset', $result );
		$this->assertStringContainsString( 'color: red;', $result );
	}

	// --- get_removed_tokens() ---

	public function test_get_removed_tokens_empty_when_nothing_stripped() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		$p->sanitize();
		$this->assertEmpty( $p->get_removed_tokens() );
	}

	public function test_get_removed_tokens_populated_after_strip() {
		$p = new WP_CSS_Token_Processor( 'background: url(javascript:alert(1));' );
		$p->sanitize();
		$removed = $p->get_removed_tokens();
		$this->assertNotEmpty( $removed );
		$this->assertArrayHasKey( 'token', $removed[0] );
		$this->assertArrayHasKey( 'reason', $removed[0] );
	}

	public function test_get_removed_tokens_contains_correct_reason_for_html_comment() {
		$p = new WP_CSS_Token_Processor( '<!--color: red;' );
		$p->sanitize();
		$removed = $p->get_removed_tokens();
		$this->assertSame( 'html_comment', $removed[0]['reason'] );
	}

	public function test_get_removed_tokens_empty_on_fresh_instance_with_clean_css() {
		// First call removes something.
		$p = new WP_CSS_Token_Processor( '<!--color: red;' );
		$p->sanitize();
		$this->assertNotEmpty( $p->get_removed_tokens() );

		// A fresh instance with clean CSS should have an empty removed-tokens log.
		$p2 = new WP_CSS_Token_Processor( 'color: red;' );
		$p2->sanitize();
		$this->assertEmpty( $p2->get_removed_tokens() );
	}

	// --- Idempotency ---

	/**
	 * sanitize( sanitize( $css ) ) must equal sanitize( $css ).
	 *
	 * This is the core guarantee that fixes the compounding corruption
	 * bug described in https://github.com/WordPress/wordpress-develop/pull/11104.
	 *
	 * @dataProvider data_idempotency_fixtures
	 */
	public function test_sanitize_is_idempotent( string $css ) {
		$once  = $this->sanitize( $css );
		$twice = $this->sanitize( $once );
		$this->assertSame( $once, $twice, 'sanitize() must be idempotent' );
	}

	/**
	 * Data provider for idempotency test.
	 *
	 * @return array
	 */
	public function data_idempotency_fixtures(): array {
		return array(
			'simple declaration'       => array( 'color: red;' ),
			'nesting ampersand'        => array( 'color: blue; & p { color: red; }' ),
			'child combinator'         => array( '& > p { margin: 0; }' ),
			'media query'              => array( '@media (max-width: 768px) { color: red; }' ),
			'custom property'          => array( '--my-color: #ff0000;' ),
			'multiple declarations'    => array( 'color: red; font-size: 16px; margin: 0;' ),
			'var() usage'              => array( 'color: var(--my-color);' ),
			'empty string'             => array( '' ),
			'whitespace only'          => array( '   ' ),
		);
	}

	// --- PR #11104 specific regression tests ---

	public function test_pr_11104_nesting_survives_repeated_saves() {
		$original    = 'color: blue; & p { color: red; }';
		$after_save1 = $this->sanitize( $original );
		$after_save2 = $this->sanitize( $after_save1 );
		$after_save3 = $this->sanitize( $after_save2 );
		$this->assertSame( $original, $after_save1 );
		$this->assertSame( $original, $after_save2 );
		$this->assertSame( $original, $after_save3 );
	}

	public function test_pr_11104_child_combinator_survives_repeated_saves() {
		$original    = '& > p { margin: 0; }';
		$after_save1 = $this->sanitize( $original );
		$after_save2 = $this->sanitize( $after_save1 );
		$this->assertSame( $original, $after_save1 );
		$this->assertSame( $original, $after_save2 );
	}

	// --- Additional ---

	public function test_sanitize_called_twice_on_same_instance() {
		$p    = new WP_CSS_Token_Processor( 'color: blue; & p { color: red; }' );
		$once = $p->sanitize();
		// Calling sanitize() again on the same instance must return the same result.
		$twice = $p->sanitize();
		$this->assertSame( $once, $twice );
	}

	public function test_custom_properties_survive() {
		$css = '--my-color: #ff0000;';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_var_function_survives() {
		$css = 'color: var(--my-color);';
		$this->assertSame( $css, $this->sanitize( $css ) );
	}

	public function test_empty_input_returns_empty_string() {
		$this->assertSame( '', $this->sanitize( '' ) );
	}
}
