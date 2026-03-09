<?php
/**
 * Unit tests covering WP_CSS_Token_Processor tokenization.
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
class Tests_CssApi_WpCssTokenProcessor extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// Group A: EOF and whitespace
	// -------------------------------------------------------------------------

	/**
	 * Tests that next_token() returns false immediately on an empty input string.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 */
	public function test_eof_on_empty_input() {
		$p = new WP_CSS_Token_Processor( '' );
		$this->assertFalse( $p->next_token() );
	}

	/**
	 * Tests that a whitespace-only input produces a single WHITESPACE_TOKEN
	 * followed by end-of-input.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_whitespace_token() {
		$p = new WP_CSS_Token_Processor( '   ' );
		$this->assertTrue( $p->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::WHITESPACE_TOKEN, $p->get_token_type() );
		$this->assertSame( '   ', $p->get_token_value() );
		$this->assertFalse( $p->next_token() );
	}

	/**
	 * Tests that mixed whitespace characters (space, tab, newline) are collapsed
	 * into a single WHITESPACE_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_whitespace_token_mixed_chars() {
		$p = new WP_CSS_Token_Processor( " \t\n\r\f" );
		$this->assertTrue( $p->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::WHITESPACE_TOKEN, $p->get_token_type() );
		$this->assertSame( " \t\n\r\f", $p->get_token_value() );
		$this->assertFalse( $p->next_token() );
	}

	// -------------------------------------------------------------------------
	// Group B: Single-character punctuation
	// -------------------------------------------------------------------------

	/**
	 * Tests that `:` produces a COLON_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_colon_token() {
		$p = new WP_CSS_Token_Processor( ':' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::COLON_TOKEN, $p->get_token_type() );
		$this->assertSame( ':', $p->get_token_value() );
	}

	/**
	 * Tests that `;` produces a SEMICOLON_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_semicolon_token() {
		$p = new WP_CSS_Token_Processor( ';' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::SEMICOLON_TOKEN, $p->get_token_type() );
		$this->assertSame( ';', $p->get_token_value() );
	}

	/**
	 * Tests that `,` produces a COMMA_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_comma_token() {
		$p = new WP_CSS_Token_Processor( ',' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::COMMA_TOKEN, $p->get_token_type() );
		$this->assertSame( ',', $p->get_token_value() );
	}

	/**
	 * Tests that `{` produces an OPEN_CURLY_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_open_curly_token() {
		$p = new WP_CSS_Token_Processor( '{' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::OPEN_CURLY_TOKEN, $p->get_token_type() );
		$this->assertSame( '{', $p->get_token_value() );
	}

	/**
	 * Tests that `}` produces a CLOSE_CURLY_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_close_curly_token() {
		$p = new WP_CSS_Token_Processor( '}' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::CLOSE_CURLY_TOKEN, $p->get_token_type() );
		$this->assertSame( '}', $p->get_token_value() );
	}

	/**
	 * Tests that `(` produces an OPEN_PAREN_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_open_paren_token() {
		$p = new WP_CSS_Token_Processor( '(' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::OPEN_PAREN_TOKEN, $p->get_token_type() );
		$this->assertSame( '(', $p->get_token_value() );
	}

	/**
	 * Tests that `)` produces a CLOSE_PAREN_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_close_paren_token() {
		$p = new WP_CSS_Token_Processor( ')' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::CLOSE_PAREN_TOKEN, $p->get_token_type() );
		$this->assertSame( ')', $p->get_token_value() );
	}

	/**
	 * Tests that `[` produces an OPEN_SQUARE_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_open_square_token() {
		$p = new WP_CSS_Token_Processor( '[' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::OPEN_SQUARE_TOKEN, $p->get_token_type() );
		$this->assertSame( '[', $p->get_token_value() );
	}

	/**
	 * Tests that `]` produces a CLOSE_SQUARE_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_close_square_token() {
		$p = new WP_CSS_Token_Processor( ']' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::CLOSE_SQUARE_TOKEN, $p->get_token_type() );
		$this->assertSame( ']', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group C: ident-token and function-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that a simple property name produces an IDENT_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_ident_token_simple() {
		$p = new WP_CSS_Token_Processor( 'color' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
		$this->assertSame( 'color', $p->get_token_value() );
	}

	/**
	 * Tests that an ident with an internal hyphen is tokenized as a single IDENT_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_ident_token_with_hyphen() {
		$p = new WP_CSS_Token_Processor( 'background-color' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
		$this->assertSame( 'background-color', $p->get_token_value() );
	}

	/**
	 * Tests that a CSS custom property name starting with `--` produces an IDENT_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_ident_token_custom_property() {
		$p = new WP_CSS_Token_Processor( '--my-var' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
		$this->assertSame( '--my-var', $p->get_token_value() );
	}

	/**
	 * Tests that an ident followed immediately by `(` produces a FUNCTION_TOKEN
	 * whose value includes the opening parenthesis.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_function_token() {
		$p = new WP_CSS_Token_Processor( 'calc(' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::FUNCTION_TOKEN, $p->get_token_type() );
		$this->assertSame( 'calc(', $p->get_token_value() );
	}

	/**
	 * Tests that an upper-case ident is tokenized correctly.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_ident_token_uppercase() {
		$p = new WP_CSS_Token_Processor( 'COLOR' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
		$this->assertSame( 'COLOR', $p->get_token_value() );
	}

	/**
	 * Tests that an ident starting with an underscore is tokenized correctly.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_ident_token_underscore_start() {
		$p = new WP_CSS_Token_Processor( '_private' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
		$this->assertSame( '_private', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group D: at-keyword-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that `@media` produces an AT_KEYWORD_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_at_keyword_token_media() {
		$p = new WP_CSS_Token_Processor( '@media' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
		$this->assertSame( '@media', $p->get_token_value() );
	}

	/**
	 * Tests that `@import` produces an AT_KEYWORD_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_at_keyword_token_import() {
		$p = new WP_CSS_Token_Processor( '@import' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
		$this->assertSame( '@import', $p->get_token_value() );
	}

	/**
	 * Tests that `@keyframes` produces an AT_KEYWORD_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_at_keyword_token_keyframes() {
		$p = new WP_CSS_Token_Processor( '@keyframes' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
		$this->assertSame( '@keyframes', $p->get_token_value() );
	}

	/**
	 * Tests that a lone `@` with no following ident produces a DELIM_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_at_sign_alone_is_delim() {
		$p = new WP_CSS_Token_Processor( '@' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
	}

	// -------------------------------------------------------------------------
	// Group E: hash-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that a hex color value produces a HASH_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_hash_token_color() {
		$p = new WP_CSS_Token_Processor( '#ff0000' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::HASH_TOKEN, $p->get_token_type() );
		$this->assertSame( '#ff0000', $p->get_token_value() );
	}

	/**
	 * Tests that a short hex color produces a HASH_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_hash_token_short_color() {
		$p = new WP_CSS_Token_Processor( '#abc' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::HASH_TOKEN, $p->get_token_type() );
		$this->assertSame( '#abc', $p->get_token_value() );
	}

	/**
	 * Tests that a lone `#` with no following ident character produces a DELIM_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_hash_alone_is_delim() {
		$p = new WP_CSS_Token_Processor( '# ' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
	}

	// -------------------------------------------------------------------------
	// Group F: numeric tokens
	// -------------------------------------------------------------------------

	/**
	 * Tests that a plain integer produces a NUMBER_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_number_token_integer() {
		$p = new WP_CSS_Token_Processor( '42' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
		$this->assertSame( '42', $p->get_token_value() );
	}

	/**
	 * Tests that a number followed by a unit produces a DIMENSION_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_dimension_token() {
		$p = new WP_CSS_Token_Processor( '16px' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DIMENSION_TOKEN, $p->get_token_type() );
		$this->assertSame( '16px', $p->get_token_value() );
	}

	/**
	 * Tests that a number followed by `%` produces a PERCENTAGE_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_percentage_token() {
		$p = new WP_CSS_Token_Processor( '50%' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::PERCENTAGE_TOKEN, $p->get_token_type() );
		$this->assertSame( '50%', $p->get_token_value() );
	}

	/**
	 * Tests that a decimal number followed by a unit produces a DIMENSION_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_dimension_token_rem() {
		$p = new WP_CSS_Token_Processor( '1.5rem' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DIMENSION_TOKEN, $p->get_token_type() );
		$this->assertSame( '1.5rem', $p->get_token_value() );
	}

	/**
	 * Tests that a decimal number with no unit produces a NUMBER_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_number_token_decimal() {
		$p = new WP_CSS_Token_Processor( '3.14' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
		$this->assertSame( '3.14', $p->get_token_value() );
	}

	/**
	 * Tests that a number starting with `.` (no leading digit) produces a NUMBER_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_number_token_leading_dot() {
		$p = new WP_CSS_Token_Processor( '.5' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
		$this->assertSame( '.5', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group G: string-token and bad-string-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that a double-quoted string produces a STRING_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_string_token_double_quoted() {
		$p = new WP_CSS_Token_Processor( '"hello world"' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::STRING_TOKEN, $p->get_token_type() );
		$this->assertSame( '"hello world"', $p->get_token_value() );
	}

	/**
	 * Tests that a single-quoted string produces a STRING_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_string_token_single_quoted() {
		$p = new WP_CSS_Token_Processor( "'hello'" );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::STRING_TOKEN, $p->get_token_type() );
	}

	/**
	 * Tests that a string containing an unescaped newline produces a BAD_STRING_TOKEN.
	 *
	 * A newline inside a string without a backslash escape is a bad-string-token
	 * per the CSS Syntax Level 3 specification.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_bad_string_token_unterminated() {
		$p = new WP_CSS_Token_Processor( "\"hello\nworld\"" );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::BAD_STRING_TOKEN, $p->get_token_type() );
	}

	/**
	 * Tests that a string with an escaped newline (`\<newline>`) is valid (STRING_TOKEN).
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_string_token_escaped_newline_is_valid() {
		$p = new WP_CSS_Token_Processor( "\"hello\\\nworld\"" );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::STRING_TOKEN, $p->get_token_type() );
	}

	// -------------------------------------------------------------------------
	// Group H: url-token and bad-url-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that an unquoted url() produces a URL_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_url_token_unquoted() {
		$p = new WP_CSS_Token_Processor( 'url(foo.png)' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::URL_TOKEN, $p->get_token_type() );
		$this->assertSame( 'url(foo.png)', $p->get_token_value() );
	}

	/**
	 * Tests that a quoted url() produces a FUNCTION_TOKEN (the string token is
	 * consumed separately by the caller).
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_url_token_with_quotes_is_function() {
		$p = new WP_CSS_Token_Processor( 'url("foo.png")' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::FUNCTION_TOKEN, $p->get_token_type() );
	}

	/**
	 * Tests that an unquoted url() containing a space in the URL body produces
	 * a BAD_URL_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_bad_url_token() {
		$p = new WP_CSS_Token_Processor( 'url(bad url)' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::BAD_URL_TOKEN, $p->get_token_type() );
	}

	/**
	 * Tests that `URL(` (uppercase) with an unquoted URL produces a URL_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_url_token_uppercase() {
		$p = new WP_CSS_Token_Processor( 'URL(foo.png)' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::URL_TOKEN, $p->get_token_type() );
	}

	// -------------------------------------------------------------------------
	// Group I: CDO-token and CDC-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that `<!--` produces a CDO_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_cdo_token() {
		$p = new WP_CSS_Token_Processor( '<!--' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::CDO_TOKEN, $p->get_token_type() );
		$this->assertSame( '<!--', $p->get_token_value() );
	}

	/**
	 * Tests that `-->` produces a CDC_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_cdc_token() {
		$p = new WP_CSS_Token_Processor( '-->' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::CDC_TOKEN, $p->get_token_type() );
		$this->assertSame( '-->', $p->get_token_value() );
	}

	/**
	 * Tests that `<!--` at the start of longer input is correctly distinguished
	 * from a `<` DELIM_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_cdo_token_not_confused_with_lt_delim() {
		$p = new WP_CSS_Token_Processor( '<!' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
		$this->assertSame( '<', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group J: delim-token
	// -------------------------------------------------------------------------

	/**
	 * Tests that `&` produces a DELIM_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_delim_token_ampersand() {
		$p = new WP_CSS_Token_Processor( '&' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
		$this->assertSame( '&', $p->get_token_value() );
	}

	/**
	 * Tests that `>` produces a DELIM_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_delim_token_child_combinator() {
		$p = new WP_CSS_Token_Processor( '>' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
		$this->assertSame( '>', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group K: get_block_depth() and sequence tests
	// -------------------------------------------------------------------------

	/**
	 * Tests that get_block_depth() correctly tracks `{ }` nesting.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_block_depth
	 * @covers ::next_token
	 */
	public function test_block_depth_tracking() {
		$p = new WP_CSS_Token_Processor( '.a { .b { color: red; } }' );
		$this->assertSame( 0, $p->get_block_depth() );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::OPEN_CURLY_TOKEN === $p->get_token_type() ) {
				break;
			}
		}
		$this->assertSame( 1, $p->get_block_depth() );
	}

	/**
	 * Tests that block depth decrements on `}` and never goes below 0.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_block_depth
	 * @covers ::next_token
	 */
	public function test_block_depth_never_below_zero() {
		$p = new WP_CSS_Token_Processor( '}}' );
		$p->next_token();
		$this->assertSame( 0, $p->get_block_depth() );
		$p->next_token();
		$this->assertSame( 0, $p->get_block_depth() );
	}

	/**
	 * Tests that block depth is correctly decremented on CLOSE_CURLY_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_block_depth
	 * @covers ::next_token
	 */
	public function test_block_depth_increments_and_decrements() {
		$p = new WP_CSS_Token_Processor( '{ { } }' );
		$p->next_token(); // `{` depth=1
		$this->assertSame( 1, $p->get_block_depth() );
		$p->next_token(); // whitespace
		$p->next_token(); // `{` depth=2
		$this->assertSame( 2, $p->get_block_depth() );
		$p->next_token(); // whitespace
		$p->next_token(); // `}` depth=1
		$this->assertSame( 1, $p->get_block_depth() );
		$p->next_token(); // whitespace
		$p->next_token(); // `}` depth=0
		$this->assertSame( 0, $p->get_block_depth() );
	}

	/**
	 * Tests that a full CSS declaration tokenizes to the expected token types.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_sequence_of_tokens_in_declaration() {
		$p      = new WP_CSS_Token_Processor( 'color: red;' );
		$tokens = array();
		while ( $p->next_token() ) {
			$tokens[] = $p->get_token_type();
		}
		$this->assertSame(
			array(
				WP_CSS_Token_Processor::IDENT_TOKEN,
				WP_CSS_Token_Processor::COLON_TOKEN,
				WP_CSS_Token_Processor::WHITESPACE_TOKEN,
				WP_CSS_Token_Processor::IDENT_TOKEN,
				WP_CSS_Token_Processor::SEMICOLON_TOKEN,
			),
			$tokens,
			'Token sequence for "color: red;" must be: ident, colon, whitespace, ident, semicolon.'
		);
	}

	/**
	 * Tests that a null byte in the input is silently stripped by the constructor.
	 *
	 * @since X.X.0
	 *
	 * @covers ::__construct
	 * @covers ::next_token
	 * @covers ::get_token_value
	 */
	public function test_null_bytes_are_stripped() {
		$p = new WP_CSS_Token_Processor( "co\0lor" );
		$p->next_token();
		$this->assertSame( 'color', $p->get_token_value() );
	}

	/**
	 * Tests that get_token_value() returns null before the first next_token() call.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_token_value
	 */
	public function test_get_token_value_returns_null_before_first_token() {
		$p = new WP_CSS_Token_Processor( 'color' );
		$this->assertNull( $p->get_token_value() );
	}

	/**
	 * Tests that get_token_type() returns null before the first next_token() call.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_token_type
	 */
	public function test_get_token_type_returns_null_before_first_call() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		$this->assertNull( $p->get_token_type() );
	}

	/**
	 * Tests that a negative integer produces a NUMBER_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_negative_integer_is_number_token() {
		$p = new WP_CSS_Token_Processor( '-5' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
		$this->assertSame( '-5', $p->get_token_value() );
	}

	/**
	 * Tests that a negative decimal number produces a NUMBER_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_negative_decimal_is_number_token() {
		$p = new WP_CSS_Token_Processor( '-.5' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
		$this->assertSame( '-.5', $p->get_token_value() );
	}

	/**
	 * Tests that a negative dimension value produces a DIMENSION_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_negative_dimension_token() {
		$p = new WP_CSS_Token_Processor( '-0.5em' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::DIMENSION_TOKEN, $p->get_token_type() );
		$this->assertSame( '-0.5em', $p->get_token_value() );
	}

	/**
	 * Tests that url() with leading whitespace before an unquoted URL produces a URL_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 */
	public function test_url_token_with_leading_whitespace() {
		$p = new WP_CSS_Token_Processor( 'url(  foo.png)' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::URL_TOKEN, $p->get_token_type() );
	}

	/**
	 * Tests that a vendor-prefixed at-keyword produces an AT_KEYWORD_TOKEN.
	 *
	 * @since X.X.0
	 *
	 * @covers ::next_token
	 * @covers ::get_token_type
	 * @covers ::get_token_value
	 */
	public function test_at_keyword_vendor_prefixed() {
		$p = new WP_CSS_Token_Processor( '@-webkit-keyframes' );
		$p->next_token();
		$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
		$this->assertSame( '@-webkit-keyframes', $p->get_token_value() );
	}

	// -------------------------------------------------------------------------
	// Group L: get_updated_css(), remove_token(), set_token_value()
	// -------------------------------------------------------------------------

	/**
	 * Tests that get_updated_css() returns the original CSS when no modifications are made.
	 *
	 * @since X.X.0
	 *
	 * @covers ::get_updated_css
	 */
	public function test_get_updated_css_unchanged_when_no_modifications() {
		$css = 'color: red;';
		$p   = new WP_CSS_Token_Processor( $css );
		while ( $p->next_token() ) {}
		$this->assertSame( $css, $p->get_updated_css() );
	}

	/**
	 * Tests that remove_token() removes the current token from the output.
	 *
	 * @since X.X.0
	 *
	 * @covers ::remove_token
	 * @covers ::get_updated_css
	 */
	public function test_remove_token_removes_it_from_output() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
				$p->remove_token();
			}
		}
		$this->assertSame( 'color: ;', $p->get_updated_css() );
	}

	/**
	 * Tests that set_token_value() replaces the current token's text in the output.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 * @covers ::get_updated_css
	 */
	public function test_set_token_value_replaces_value_in_output() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
				$p->set_token_value( 'blue' );
			}
		}
		$this->assertSame( 'color: blue;', $p->get_updated_css() );
	}

	/**
	 * Tests that remove_token() returns false before the first next_token() call.
	 *
	 * @since X.X.0
	 *
	 * @covers ::remove_token
	 */
	public function test_remove_token_returns_false_before_first_next_token() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		$this->assertFalse( $p->remove_token() );
	}

	/**
	 * Tests that set_token_value() returns false before the first next_token() call.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 */
	public function test_set_token_value_returns_false_before_first_next_token() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		$this->assertFalse( $p->set_token_value( 'blue' ) );
	}

	/**
	 * Tests that multiple remove_token() calls produce correct output.
	 *
	 * @since X.X.0
	 *
	 * @covers ::remove_token
	 * @covers ::get_updated_css
	 */
	public function test_multiple_removals_produce_correct_output() {
		// Remove both 'color' and 'red' — the property name and value.
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() ) {
				$p->remove_token();
			}
		}
		$this->assertSame( ': ;', $p->get_updated_css() );
	}

	/**
	 * Tests that set_token_value() can replace multiple tokens in a single pass.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 * @covers ::get_updated_css
	 */
	public function test_set_token_value_on_multiple_tokens() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'color' === $p->get_token_value() ) {
				$p->set_token_value( 'background' );
			}
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
				$p->set_token_value( 'blue' );
			}
		}
		$this->assertSame( 'background: blue;', $p->get_updated_css() );
	}

	/**
	 * Tests that remove_token() returns false after next_token() has exhausted the input.
	 *
	 * @since X.X.0
	 *
	 * @covers ::remove_token
	 */
	public function test_remove_token_returns_false_after_input_exhausted() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {}
		$this->assertFalse( $p->remove_token() );
	}

	/**
	 * Tests that set_token_value() returns false after next_token() has exhausted the input.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 */
	public function test_set_token_value_returns_false_after_input_exhausted() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {}
		$this->assertFalse( $p->set_token_value( 'blue' ) );
	}

	/**
	 * Tests that calling set_token_value() after input is exhausted does not modify the output.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 * @covers ::get_updated_css
	 */
	public function test_set_token_value_after_exhaustion_does_not_modify_output() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {}
		$p->set_token_value( 'INJECTED' );
		$this->assertSame( 'color: red;', $p->get_updated_css() );
	}

	/**
	 * Tests that when the same token position is modified twice, the last call wins.
	 *
	 * @since X.X.0
	 *
	 * @covers ::set_token_value
	 * @covers ::get_updated_css
	 */
	public function test_last_modification_wins_when_same_token_modified_twice() {
		$p = new WP_CSS_Token_Processor( 'color: red;' );
		while ( $p->next_token() ) {
			if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
				$p->set_token_value( 'green' ); // first call
				$p->set_token_value( 'blue' );  // second call — should win
			}
		}
		$this->assertSame( 'color: blue;', $p->get_updated_css() );
	}
}
