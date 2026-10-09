<?php

/**
 * Tests for WP_CSS_Token_Serializer.
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token_Serializer
 */
class Tests_CssApi_WpCssTokenSerializer extends WP_UnitTestCase {
	/**
	 * @dataProvider data_string_escaping
	 *
	 * @covers ::serialize_string
	 */
	public function test_string_escaping( string $input, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize_string( $input ) );

		/**
		 * Ensure that a single, equivalent CSS string is produced.
		 *
		 * CSS pre-processing normalization is applied to the input to ensure
		 * a match can be found.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#input-preprocessing
		 */
		$processor = WP_CSS_Token_Processor::create( WP_CSS_Token_Serializer::serialize_string( $input ) );
		$processor->next_token();
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $processor->get_token_type() );
		$expected_decoded_value = strtr(
			$input,
			array(
				"\r\n" => "\n",
				"\r"   => "\n",
				"\f"   => "\n",
				"\0"   => '�',
			)
		);
		$this->assertSame( $expected_decoded_value, $processor->get_token_value() );
		$this->assertFalse( $processor->next_token() );
	}

	/**
	 * Data provider for string escaping cases.
	 *
	 * @return array
	 */
	public static function data_string_escaping(): array {
		return array(
			// Passthrough — no escaping needed, only quoting.
			'empty string'            => array( '', '""' ),
			'simple ASCII'            => array( 'Arial', '"Arial"' ),
			'spaces preserved'        => array( 'Exo 2', '"Exo 2"' ),
			'leading/trailing spaces' => array( '  Arial  ', '"  Arial  "' ),
			'whitespace-only'         => array( '   ', '"   "' ),
			'numbers pass through'    => array( '12345', '"12345"' ),
			'non-ASCII passthrough'   => array( 'café', '"café"' ),

			// Backslash escaping — must happen first to prevent double-escaping.
			'backslash'               => array( 'Back\\Slash', '"Back\5C Slash"' ),
			'double backslash'        => array( '\\\\', '"\5C \5C "' ),
			'backslash before quote'  => array( "a\\'b", '"a\5C \27 b"' ),

			// NULL byte → U+FFFD replacement character.
			'null byte'               => array( "a\0b", '"a�b"' ),

			// Newline normalization — all variants become \A escape.
			'LF'                      => array( "a\nb", '"a\A b"' ),
			'CR'                      => array( "a\rb", '"a\A b"' ),
			'CRLF as single escape'   => array( "a\r\nb", '"a\A b"' ),
			'form feed'               => array( "a\fb", '"a\A b"' ),

			// HTML-problematic characters.
			'HTML characters < > &'   => array( 'a<b>c&d', '"a\3C b\3E c\26 d"' ),

			// CSS-problematic characters.
			'CSS syntax , ; { }'      => array( 'a,b;c{d}', '"a\2C b\3B c\7B d\7D "' ),
			'single quote'            => array( "CSS's strings", '"CSS\27 s strings"' ),
			'double quote'            => array( 'Say "Hi"', '"Say \22 Hi\22 "' ),
		);
	}

	/**
	 * Tests the example from the class docblock.
	 *
	 * @covers ::serialize_string
	 */
	public function test_docblock_example(): void {
		$value    = 'CSS & a "<style>" tag\'s strings';
		$expected = '"CSS \26  a \22 \3C style\3E \22  tag\27 s strings"';

		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize_string( $value ) );
	}

	/**
	 * Tests a string containing mixed newline types.
	 *
	 * @covers ::serialize_string
	 */
	public function test_mixed_newlines(): void {
		$input    = "line1\nline2\rline3\r\nline4\fline5";
		$expected = '"line1\A line2\A line3\A line4\A line5"';

		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize_string( $input ) );
	}

	/**
	 * Tests WP_CSS_Token_Serializer::serialize_ident() serializes CSS identifiers.
	 *
	 * @ticket 65738
	 *
	 * @dataProvider data_ident
	 *
	 * @covers ::serialize_ident
	 */
	public function test_ident( string $input, string $expected ): void {
		$serialized = WP_CSS_Token_Serializer::serialize_ident( $input );
		$this->assertSame( $expected, $serialized );

		$processor = WP_CSS_Token_Processor::create( $serialized );
		if ( '' === $input ) {
			$this->assertFalse( $processor->next_token() );
			return;
		}

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_IDENT, $processor->get_token_type() );

		$expected_decoded_value = strtr(
			$input,
			array(
				"\r\n" => "\n",
				"\r"   => "\n",
				"\f"   => "\n",
				"\0"   => '�',
			)
		);
		$this->assertSame( $expected_decoded_value, $processor->get_token_value() );
		$this->assertFalse( $processor->next_token() );
	}

	/**
	 * Data provider for serialize_ident() tests.
	 */
	public static function data_ident(): Generator {
		yield 'Empty identifier' => array( '', '' );

		// Simple idents — no escaping needed.
		yield 'Simple alpha ident' => array( 'serif', 'serif' );
		yield 'Hyphenated ident' => array( 'sans-serif', 'sans-serif' );
		yield 'Underscore prefix' => array( '_foo', '_foo' );
		yield 'Single char' => array( 'a', 'a' );
		yield 'Single hyphen' => array( '-', '\\2D ' );
		yield 'Custom property prefix' => array( '--custom', '--custom' );
		yield 'Non-ASCII' => array( 'café', 'café' );

		// Invalid ident starts — must be escaped.
		yield 'Leading digits' => array( '123', '\\31 23' );
		yield 'Leading digit with alpha' => array( '5foo', '\\35 foo' );
		yield 'Hyphen then digit' => array( '-5px', '-\\35 px' );
		yield 'Leading space' => array( ' leading-space', '\\20 leading-space' );
		yield 'Leading tab' => array( "\tleading-tab", '\\9 leading-tab' );
		yield 'Leading control character' => array( "\x01foo", '\\1 foo' );

		// Whitespace within ident.
		yield 'Space within' => array( 'My Font', 'My\\20 Font' );
		yield 'Multiple spaces within' => array( 'a b c', 'a\\20 b\\20 c' );
		yield 'Tab within' => array( "has\ttab", 'has\\9 tab' );
		yield 'Newline within' => array( "has\nnewline", 'has\\A newline' );
		yield 'NULL byte within' => array( "has\0null", 'has�null' );
		yield 'DEL within' => array( "has\x7Fdelete", 'has\\7F delete' );

		// Special characters.
		yield 'Apostrophe' => array( "Font's", 'Font\\27 s' );
		yield 'Angle brackets' => array( '<html>', '\\3C html\\3E ' );
		yield 'Comma' => array( 'a,b', 'a\\2C b' );
		yield 'Semicolon' => array( 'a;b', 'a\\3B b' );
		yield 'Backslash' => array( 'a\\b', 'a\\5C b' );
	}

	/**
	 * Tests that serialize() writes an empty CSS comment between the tokens of a marked pair.
	 *
	 * @ticket 65738
	 * @dataProvider data_marked_pairs
	 *
	 * @covers ::serialize
	 */
	public function test_marked_pair_is_separated_by_an_empty_comment( string $css, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) ) );
		$this->assertSame( self::significant_tokens( $css ), self::significant_tokens( $expected ), 'The output must read as the same tokens.' );
	}

	/**
	 * Data provider for test_marked_pair_is_separated_by_an_empty_comment().
	 *
	 * One case for each ✗ cell in the table in CSS Syntax Level 3 §9.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#serialization
	 *
	 * @return Generator
	 */
	public static function data_marked_pairs(): Generator {
		// Source text for the first token, keyed by its row heading.
		$rows = array(
			'ident'      => 'a',
			'at-keyword' => '@a',
			'hash'       => '#a',
			'dimension'  => '1px',
			'#'          => '#',
			'-'          => '-',
			'number'     => '1',
			'@'          => '@',
			'.'          => '.',
			'+'          => '+',
			'/'          => '/',
		);

		// Source text and its serialization for the second token, keyed by its column heading.
		$columns = array(
			'ident'      => array( 'b', 'b' ),
			'function'   => array( 'b()', 'b()' ),
			'url'        => array( 'url(b)', 'url(b)' ),
			'bad url'    => array( 'url(b c)', 'url(()' ),
			'-'          => array( '-', '-' ),
			'number'     => array( '2', '2' ),
			'percentage' => array( '2%', '2%' ),
			'dimension'  => array( '2px', '2px' ),
			'CDC'        => array( '-->', '-->' ),
			'('          => array( '()', '()' ),
			'*'          => array( '*', '*' ),
			'%'          => array( '%', '%' ),
		);

		$marked = array(
			'ident'      => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC', '(' ),
			'at-keyword' => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' ),
			'hash'       => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' ),
			'dimension'  => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' ),
			'#'          => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' ),
			'-'          => array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' ),
			'number'     => array( 'ident', 'function', 'url', 'bad url', 'number', 'percentage', 'dimension', 'CDC', '%' ),
			'@'          => array( 'ident', 'function', 'url', 'bad url', '-', 'CDC' ),
			'.'          => array( 'number', 'percentage', 'dimension' ),
			'+'          => array( 'number', 'percentage', 'dimension' ),
			'/'          => array( '*' ),
		);

		foreach ( $marked as $row => $row_columns ) {
			foreach ( $row_columns as $column ) {
				list( $column_css, $column_serialized ) = $columns[ $column ];
				yield "{$row} followed by {$column}" => array(
					"{$rows[ $row ]}/**/{$column_css}",
					"{$rows[ $row ]}/**/{$column_serialized}",
				);
			}
		}
	}

	/**
	 * Tests that serialize() writes no comment between the tokens of an unmarked pair.
	 *
	 * @ticket 65738
	 * @dataProvider data_unmarked_pairs
	 *
	 * @covers ::serialize
	 */
	public function test_unmarked_pair_is_not_separated( string $css, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) ) );
		$this->assertSame( self::significant_tokens( $css ), self::significant_tokens( $expected ), 'The output must read as the same tokens.' );
	}

	/**
	 * Data provider for test_unmarked_pair_is_not_separated().
	 *
	 * @return array[]
	 */
	public static function data_unmarked_pairs(): array {
		return array(
			'ident followed by *'          => array( 'a/**/*', 'a*' ),
			'ident followed by %'          => array( 'a/**/%', 'a%' ),
			'ident followed by colon'      => array( 'a/**/:', 'a:' ),
			'at-keyword followed by ('     => array( '@a/**/()', '@a()' ),
			'hash followed by *'           => array( '#a/**/*', '#a*' ),
			'dimension followed by %'      => array( '1px/**/%', '1px%' ),
			'# followed by %'              => array( '#/**/%', '#%' ),
			'- followed by *'              => array( '-/**/*', '-*' ),
			'number followed by -'         => array( '1/**/-', '1-' ),
			'number followed by ('         => array( '1/**/()', '1()' ),
			'@ followed by number'         => array( '@/**/1', '@1' ),
			'. followed by ident'          => array( './**/a', '.a' ),
			'+ followed by ident'          => array( '+/**/a', '+a' ),
			'/ followed by ident'          => array( '//**/a', '/a' ),
			'string followed by ident'     => array( '"a"/**/b', '"a"b' ),
			'url followed by ident'        => array( 'url(a)/**/b', 'url(a)b' ),
			'function followed by ident'   => array( 'a(/**/b)', 'a(b)' ),
			') followed by ident'          => array( '(a)/**/b', '(a)b' ),
			'CDC followed by ident'        => array( '-->/**/a', '-->a' ),
			'percentage followed by ident' => array( '1%/**/a', '1%a' ),
		);
	}

	/**
	 * Tests that serialize() drops comments and writes each whitespace run as one space.
	 *
	 * @ticket 65738
	 * @dataProvider data_whitespace_and_comments
	 *
	 * @covers ::serialize
	 */
	public function test_whitespace_and_comments( string $css, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) ) );
	}

	/**
	 * Data provider for test_whitespace_and_comments().
	 *
	 * @return array[]
	 */
	public static function data_whitespace_and_comments(): array {
		return array(
			'empty input'               => array( '', '' ),
			'one space'                 => array( 'a b', 'a b' ),
			'mixed whitespace run'      => array( "a \t\r\n\f b", 'a b' ),
			'run broken by a comment'   => array( 'a /* x */ b', 'a b' ),
			'whitespace then a comment' => array( 'a /**/b', 'a b' ),
			'a comment then whitespace' => array( 'a/**/ b', 'a b' ),
			'leading whitespace'        => array( "\n a", ' a' ),
			'trailing whitespace'       => array( "a \n", 'a ' ),
			'only whitespace'           => array( "\t\n", ' ' ),
			'only a comment'            => array( '/* x */', '' ),
			'comment in unmarked pair'  => array( 'a/* x */,b', 'a,b' ),
			'unterminated comment'      => array( 'a /* x', 'a ' ),
		);
	}

	/**
	 * Tests how serialize() writes each token type.
	 *
	 * @ticket 65738
	 * @dataProvider data_token_serialization
	 *
	 * @covers ::serialize
	 */
	public function test_token_serialization( string $css, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) ) );
		$this->assertSame( self::significant_tokens( $css ), self::significant_tokens( $expected ), 'The output must read as the same tokens.' );
	}

	/**
	 * Data provider for test_token_serialization().
	 *
	 * @return array[]
	 */
	public static function data_token_serialization(): array {
		return array(
			'ident escapes decoded'                  => array( '\\66 oo', 'foo' ),
			'ident escapes added'                    => array( 'a\\:b', 'a\\3A b' ),
			'function'                               => array( '\\66 oo(1)', 'foo(1)' ),
			'at-keyword'                             => array( '@\\66 oo', '@foo' ),
			'hash id'                                => array( '#a\\:', '#a\\3A ' ),
			'hash id escaped digit'                  => array( '#\\31 a', '#\\31 a' ),
			'hash unrestricted'                      => array( '#1a', '#1a' ),
			'hash unrestricted escape'               => array( '#1\\:', '#1\\3A ' ),
			'hash unrestricted hyphen'               => array( '#-', '#-' ),
			'string'                                 => array( "'a\"b'", '"a\\22 b"' ),
			'string ampersand'                       => array( '"a&b"', '"a\\26 b"' ),
			'string unterminated'                    => array( '"abc', '"abc"' ),
			'string escaped newline'                 => array( '"a\\A b"', '"a\\A b"' ),
			'string escaped carriage return'         => array( '"a\\D b"', '"a\\D b"' ),
			'string escaped form feed'               => array( '"a\\C b"', '"a\\C b"' ),
			'bad string'                             => array( "'a\nb", "\"\n b" ),
			'url'                                    => array( 'url( a )', 'url(a)' ),
			'url empty'                              => array( 'url()', 'url()' ),
			'url escaped parenthesis'                => array( 'url(a\\29 b)', 'url(a\\29 b)' ),
			'url escaped space'                      => array( 'url(a\\20 b)', 'url(a\\20 b)' ),
			'url escaped quote'                      => array( 'url(a\\"b)', 'url(a\\22 b)' ),
			'url escaped backslash'                  => array( 'url(a\\\\b)', 'url(a\\5C b)' ),
			'url escaped control'                    => array( 'url(a\\1 b)', 'url(a\\1 b)' ),
			'url escaped newline'                    => array( 'url(a\\A b)', 'url(a\\A b)' ),
			'url ampersand'                          => array( 'url(a&b)', 'url(a\\26 b)' ),
			'url NUL'                                => array( "url(a\x00b)", "url(a\u{FFFD}b)" ),
			'url unterminated'                       => array( 'url(a', 'url(a)' ),
			'url function with a string'             => array( "url( 'a')", 'url( "a")' ),
			'bad url'                                => array( 'url(a b)', 'url(()' ),
			'number'                                 => array( '+.5e-3', '+.5e-3' ),
			'percentage'                             => array( '-1.5%', '-1.5%' ),
			'dimension'                              => array( '1.5e3PX', '1.5e3PX' ),
			'dimension unit escape'                  => array( '1\\2D ', '1\\2D ' ),
			'dimension unit read as exponent'        => array( '1\\65 3', '1\\65 3' ),
			'dimension unit read as signed exponent' => array( '1\\45 -3', '1\\45 -3' ),
			'dimension unit e'                       => array( '1e', '1e' ),
			'backslash delim keeps newline'          => array( "a\\\nb", "a\\\n b" ),
			'delim'                                  => array( '!', '!' ),
			'CDO and CDC'                            => array( '<!-- -->', '<!-- -->' ),
			'punctuation'                            => array( 'a:b;c,d', 'a:b;c,d' ),
		);
	}

	/**
	 * Tests that serialize() closes blocks still open at the end, in reverse order.
	 *
	 * @ticket 65738
	 * @dataProvider data_open_blocks
	 *
	 * @covers ::serialize
	 */
	public function test_open_blocks_are_closed( string $css, string $expected ): void {
		$this->assertSame( $expected, WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) ) );
	}

	/**
	 * Data provider for test_open_blocks_are_closed().
	 *
	 * @return array[]
	 */
	public static function data_open_blocks(): array {
		return array(
			'function'               => array( 'calc(1px', 'calc(1px)' ),
			'nested blocks'          => array( 'a(b[c{d', 'a(b[c{d}])' ),
			'closed block'           => array( '(a)', '(a)' ),
			'mismatched closer'      => array( '(]', '(])' ),
			'closer without a block' => array( ')', ')' ),
			'brace'                  => array( '{', '{}' ),
			'function with a string' => array( 'url("a"', 'url("a")' ),
			'inner block closed'     => array( 'a((b)', 'a((b))' ),
		);
	}

	/**
	 * Tests that the hash type flag is preserved.
	 *
	 * @ticket 65738
	 *
	 * @covers ::serialize
	 */
	public function test_hash_flag_round_trips(): void {
		foreach ( array( '#\\31 a', '#1a', '#a1', '#-1', '#--' ) as $css ) {
			$tokens     = self::tokenize( $css );
			$serialized = WP_CSS_Token_Serializer::serialize( $tokens );
			$reparsed   = self::tokenize( $serialized );

			$this->assertCount( 1, $reparsed, "{$css} must serialize as one token." );
			$this->assertSame( $tokens[0]->get_value(), $reparsed[0]->get_value(), "The value of {$css} changed." );
			$this->assertSame( $tokens[0]->get_flag(), $reparsed[0]->get_flag(), "The type flag of {$css} changed." );
		}
	}

	/**
	 * Tests that serializing the corpus round-trips and is idempotent.
	 *
	 * The output must read as the input tokens, except that comments are dropped,
	 * whitespace runs are collapsed, and blocks still open are closed.
	 *
	 * @ticket 65738
	 * @dataProvider data_corpus
	 *
	 * @covers ::serialize
	 */
	public function test_corpus_round_trips( string $css ): void {
		$serialized = WP_CSS_Token_Serializer::serialize( self::tokenize( $css ) );

		$expected = self::significant_tokens( $css );
		$actual   = self::significant_tokens( $serialized );
		$closers  = array_splice( $actual, count( $expected ) );

		$this->assertSame( $expected, $actual, 'The output must read as the same tokens.' );
		foreach ( $closers as $closer ) {
			$this->assertContains(
				$closer[0],
				array( WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE ),
				'Only block closers may follow the input tokens.'
			);
		}
		$this->assertSame( $serialized, WP_CSS_Token_Serializer::serialize( self::tokenize( $serialized ) ), 'Serialization must be idempotent.' );
	}

	/**
	 * Data provider for test_corpus_round_trips().
	 *
	 * @return array[]
	 */
	public static function data_corpus(): array {
		$cases = json_decode( file_get_contents( DIR_TESTDATA . '/css-api/css-test-cases.json' ), true );
		return array_map(
			static function ( array $test_case ): array {
				return array( $test_case['css'] );
			},
			$cases
		);
	}

	/**
	 * Tokenizes CSS.
	 *
	 * @param string $css CSS text.
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

	/**
	 * Tokenizes CSS for comparison: comments are dropped, and each run of
	 * whitespace and comments that includes whitespace is one whitespace token.
	 *
	 * @param string $css CSS text.
	 * @return array[] Type, flag, value and unit of each token.
	 */
	private static function significant_tokens( string $css ): array {
		$tokens = array();
		foreach ( self::tokenize( $css ) as $token ) {
			$type = $token->get_type();
			if ( WP_CSS_Token_Processor::TOKEN_COMMENT === $type ) {
				continue;
			}
			if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE === $type ) {
				if ( array( $type ) !== end( $tokens ) ) {
					$tokens[] = array( $type );
				}
				continue;
			}
			$tokens[] = array( $type, $token->get_flag(), $token->get_value(), $token->get_unit() );
		}
		return $tokens;
	}
}
