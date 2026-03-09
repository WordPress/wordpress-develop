<?php
/**
 * Unit tests covering WP_CSS_Token_Processor::validate().
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
class Tests_CssApi_WpCssTokenValidate extends WP_UnitTestCase {

	/**
	 * Helper: run validate() on a CSS string and return the result.
	 *
	 * @param string $css
	 * @return true|WP_Error
	 */
	private function validate( string $css ) {
		return ( new WP_CSS_Token_Processor( $css ) )->validate();
	}

	// --- Valid CSS ---

	/**
	 * @covers ::validate
	 */
	public function test_valid_css_returns_true() {
		$result = $this->validate( 'color: red;' );
		$this->assertTrue( $result );
	}

	/**
	 * @covers ::validate
	 */
	public function test_valid_css_with_nesting_returns_true() {
		$result = $this->validate( '& > p { color: red; }' );
		$this->assertTrue( $result );
	}

	// --- Injection guard ---

	/**
	 * @covers ::validate
	 */
	public function test_style_close_tag_returns_wp_error() {
		$result = $this->validate( 'color: red; </style>' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_injection', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_style_close_tag_case_insensitive() {
		$result = $this->validate( '</STYLE>' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_injection', $result->get_error_code() );
	}

	// --- HTML comment tokens ---

	/**
	 * @covers ::validate
	 */
	public function test_cdo_token_returns_wp_error_with_css_html_comment() {
		$result = $this->validate( '<!--color: red;' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_html_comment', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_cdc_token_returns_wp_error_with_css_html_comment() {
		$result = $this->validate( '-->color: red;' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_html_comment', $result->get_error_code() );
	}

	// --- Malformed tokens ---

	/**
	 * @covers ::validate
	 */
	public function test_bad_string_token_returns_wp_error_with_css_malformed_token() {
		$result = $this->validate( "\"bad\nstring\"" );
		$this->assertWPError( $result );
		$this->assertSame( 'css_malformed_token', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_bad_url_token_returns_wp_error_with_css_malformed_token() {
		$result = $this->validate( 'url(bad url)' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_malformed_token', $result->get_error_code() );
	}

	// --- Unsafe URLs ---

	/**
	 * @covers ::validate
	 */
	public function test_javascript_url_returns_wp_error_with_css_unsafe_url() {
		$result = $this->validate( 'url(javascript:evil)' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_unsafe_url', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_data_url_returns_wp_error_with_css_unsafe_url() {
		$result = $this->validate( 'url(data:image/png;base64,abc)' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_unsafe_url', $result->get_error_code() );
	}

	// --- Disallowed at-rules ---

	/**
	 * @covers ::validate
	 */
	public function test_import_at_rule_returns_wp_error_with_css_disallowed_at_rule() {
		$result = $this->validate( "@import url('evil.css');" );
		$this->assertWPError( $result );
		$this->assertSame( 'css_disallowed_at_rule', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_unknown_at_rule_returns_wp_error_with_css_disallowed_at_rule() {
		$result = $this->validate( '@unknown { }' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_disallowed_at_rule', $result->get_error_code() );
	}

	/**
	 * @covers ::validate
	 */
	public function test_media_at_rule_returns_true() {
		$result = $this->validate( '@media (max-width: 768px) { color: red; }' );
		$this->assertTrue( $result );
	}

	// --- Guarantee: validate() === true implies sanitize() is a no-op ---

	/**
	 * validate() === true guarantees sanitize() is a no-op on the same input.
	 *
	 * @dataProvider data_validate_true_implies_sanitize_noop
	 *
	 * @covers ::validate
	 * @covers ::sanitize
	 */
	public function test_validate_true_implies_sanitize_noop( string $css ) {
		$this->assertTrue( $this->validate( $css ), 'Expected validate() to return true for this fixture' );
		$sanitized = ( new WP_CSS_Token_Processor( $css ) )->sanitize();
		$this->assertSame( $css, $sanitized, 'validate() returning true must mean sanitize() is a no-op' );
	}

	/**
	 * Data provider for the validate() === true implies sanitize() is a no-op guarantee.
	 *
	 * @return array
	 */
	public function data_validate_true_implies_sanitize_noop(): array {
		return array(
			'simple declaration' => array( 'color: red;' ),
			'nesting ampersand'  => array( 'color: blue; & p { color: red; }' ),
			'child combinator'   => array( '& > p { margin: 0; }' ),
			'media query'        => array( '@media (max-width: 768px) { color: red; }' ),
			'custom property'    => array( '--my-color: #ff0000;' ),
			'var() usage'        => array( 'color: var(--my-color);' ),
			'empty string'       => array( '' ),
			'https url'          => array( 'background: url(https://example.com/image.png);' ),
			'relative url'       => array( 'background: url(image.png);' ),
		);
	}
}
