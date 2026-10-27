<?php

/**
 * Tests for WP_CSS_Builder.
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Builder
 */
class Tests_CssApi_WpCssBuilder extends WP_UnitTestCase {
	/**
	 * Tests that string() writes one string token that reads back as the value.
	 *
	 * @ticket 65738
	 * @dataProvider data_values
	 *
	 * @covers ::string
	 */
	public function test_string_reads_back_as_the_value( string $value, string $expected_value ): void {
		$tokens = self::tokenize( WP_CSS_Builder::string( $value ) );

		$this->assertCount( 1, $tokens );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $tokens[0]->get_type() );
		$this->assertTrue( $tokens[0]->is_terminated() );
		$this->assertSame( $expected_value, $tokens[0]->get_value() );
	}

	/**
	 * Tests that url() writes a url function holding one string token that reads back as the value.
	 *
	 * @ticket 65738
	 * @dataProvider data_values
	 *
	 * @covers ::url
	 */
	public function test_url_reads_back_as_the_value( string $value, string $expected_value ): void {
		$tokens = self::tokenize( WP_CSS_Builder::url( $value ) );

		$this->assertCount( 3, $tokens );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_FUNCTION, $tokens[0]->get_type() );
		$this->assertSame( 'url', $tokens[0]->get_value() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $tokens[1]->get_type() );
		$this->assertSame( $expected_value, $tokens[1]->get_value() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN, $tokens[2]->get_type() );
	}

	/**
	 * Tests that ident() writes one ident token that reads back as the value.
	 *
	 * @ticket 65738
	 * @dataProvider data_values
	 *
	 * @covers ::ident
	 */
	public function test_ident_reads_back_as_the_value( string $value, string $expected_value ): void {
		$tokens = self::tokenize( WP_CSS_Builder::ident( $value ) );

		$this->assertCount( 1, $tokens );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_IDENT, $tokens[0]->get_type() );
		$this->assertSame( $expected_value, $tokens[0]->get_value() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values(): array {
		return array(
			'plain'                  => array( 'abc', 'abc' ),
			'url with a query'       => array( 'https://example.com/a?b=1&c=2', 'https://example.com/a?b=1&c=2' ),
			'parentheses and spaces' => array( 'https://example.com/a (1).jpg', 'https://example.com/a (1).jpg' ),
			'double quote'           => array( 'a"b', 'a"b' ),
			'single quote'           => array( "a'b", "a'b" ),
			'backslash'              => array( 'a\\b', 'a\\b' ),
			'declaration delimiters' => array( 'a;b:c}d{e', 'a;b:c}d{e' ),
			'html delimiters'        => array( '</style><a>&amp;', '</style><a>&amp;' ),
			'newline and tab'        => array( "a\nb\tc\r\fd", "a\nb\tc\r\fd" ),
			'leading digit'          => array( '1a', '1a' ),
			'leading hyphen digit'   => array( '-1a', '-1a' ),
			'lone hyphen'            => array( '-', '-' ),
			'custom property name'   => array( '--a', '--a' ),
			'non-ascii'              => array( 'café 😀', 'café 😀' ),
			'null byte'              => array( "a\0b", "a\u{FFFD}b" ),
			'invalid utf-8'          => array( "a\xFFb", "a\u{FFFD}b" ),
		);
	}

	/**
	 * Tests that string() and url() write an empty value as an empty string.
	 *
	 * @ticket 65738
	 *
	 * @covers ::string
	 * @covers ::url
	 */
	public function test_empty_string_and_url(): void {
		$this->assertSame( '""', WP_CSS_Builder::string( '' ) );
		$this->assertSame( 'url("")', WP_CSS_Builder::url( '' ) );
	}

	/**
	 * Tests that ident() returns an empty string for an empty value.
	 *
	 * @ticket 65738
	 *
	 * @covers ::ident
	 */
	public function test_empty_ident(): void {
		$this->assertSame( '', WP_CSS_Builder::ident( '' ) );
	}

	/**
	 * Tests the examples in the docblocks.
	 *
	 * @ticket 65738
	 *
	 * @covers ::string
	 * @covers ::url
	 * @covers ::ident
	 */
	public function test_docblock_examples(): void {
		$this->assertSame(
			'url("https://example.com/a (1) \\22 b\\22 .jpg")',
			WP_CSS_Builder::url( 'https://example.com/a (1) "b".jpg' )
		);
		$this->assertSame( '"Say \\22 hi\\22 "', WP_CSS_Builder::string( 'Say "hi"' ) );
		$this->assertSame( 'url("https://example.com/a(1).png")', WP_CSS_Builder::url( 'https://example.com/a(1).png' ) );
		$this->assertSame( '\\31 st\\20 Font', WP_CSS_Builder::ident( '1st Font' ) );
	}

	/**
	 * Tokenizes CSS.
	 *
	 * @param string $css CSS to tokenize.
	 * @return WP_CSS_Token[] Tokens.
	 */
	private static function tokenize( string $css ): array {
		$processor = WP_CSS_Token_Processor::create( $css );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$tokens[] = $processor->get_token();
		}
		return $tokens;
	}
}
