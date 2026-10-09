<?php

/**
 * Comprehensive CSS processor tests based on the CSS Syntax Level 3 specification.
 *
 * @group css-api
 */
class Tests_CssApi_WpCssTokenProcessor extends WP_UnitTestCase {
	/**
	 * Tests that the processor produces the expected tokens for all test cases.
	 *
	 * @dataProvider corpus_provider
	 */
	public function test_processor_matches_spec( string $css, array $expected_tokens ): void {
		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor );
		$this->assertSame( $expected_tokens, $actual_tokens );
	}

	/**
	 * Provides the test cases derived from the @rmenke/css-tokenizer-tests corpus (MIT).
	 *
	 * @see https://github.com/romainmenke/css-tokenizer-tests/
	 * @return array
	 */
	public static function corpus_provider(): array {
		return json_decode( file_get_contents( DIR_TESTDATA . '/css-api/css-test-cases.json' ), true );
	}

	/**
	 * Collects all tokens from a CSS processor into an array.
	 *
	 * @param WP_CSS_Token_Processor $processor The CSS processor.
	 * @return array Array of tokens with type, raw, startIndex, endIndex, structured.
	 */
	public static function collect_tokens( WP_CSS_Token_Processor $processor, $keys = null ): array {
		$tokens = array();

		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();

			$byte_start = $processor->get_token_start();
			$byte_end   = $byte_start + $processor->get_token_length();

			$token = array(
				'type'       => $type,
				'raw'        => $processor->get_unnormalized_token(),
				'startIndex' => $byte_start,
				'endIndex'   => $byte_end,
				'normalized' => $processor->get_normalized_token(),
				'value'      => $processor->get_token_value(),
			);
			if ( null !== $processor->get_token_unit() ) {
				$token['unit'] = $processor->get_token_unit();
			}
			if (
				WP_CSS_Token_Processor::TOKEN_NUMBER === $type ||
				WP_CSS_Token_Processor::TOKEN_DIMENSION === $type
			) {
				$token['numberType'] = $processor->get_token_type_flag();
			}

			if ( null !== $keys ) {
				$token = array_intersect_key( $token, array_flip( $keys ) );
			}

			$tokens[] = $token;
		}

		return $tokens;
	}

	/**
	 * Tests that backslash-newline in a string token contributes nothing to the value.
	 *
	 * @ticket 65738
	 * @dataProvider data_string_backslash_newline
	 */
	public function test_string_backslash_newline( string $css, string $expected_value ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $processor->get_token_type() );
		$this->assertSame( $expected_value, $processor->get_token_value() );
	}

	/**
	 * Data provider for test_string_backslash_newline().
	 *
	 * @return array[]
	 */
	public static function data_string_backslash_newline(): array {
		return array(
			'backslash-LF'   => array( "'str\\\ning'", 'string' ),
			'backslash-FF'   => array( "'str\\\fing'", 'string' ),
			'backslash-CR'   => array( "'str\\\ring'", 'string' ),
			'backslash-CRLF' => array( "'str\\\r\ning'", 'string' ),
		);
	}

	/**
	 * Tests that backslash-EOF in a string token contributes nothing to the value.
	 *
	 * @ticket 65738
	 */
	public function test_string_backslash_eof(): void {
		$processor = WP_CSS_Token_Processor::create( "'string\\" );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $processor->get_token_type() );
		$this->assertSame( 'string', $processor->get_token_value() );
	}

	/**
	 * Tests that backslash-newline in an unquoted URL produces a bad-url token.
	 *
	 * @ticket 65738
	 * @dataProvider data_url_backslash_newline
	 */
	public function test_url_backslash_newline( string $css ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_BAD_URL, $processor->get_token_type() );
	}

	/**
	 * Data provider for test_url_backslash_newline().
	 *
	 * @return array[]
	 */
	public static function data_url_backslash_newline(): array {
		return array(
			'backslash-LF'   => array( "url(ab\\\ncd)" ),
			'backslash-FF'   => array( "url(ab\\\fcd)" ),
			'backslash-CR'   => array( "url(ab\\\rcd)" ),
			'backslash-CRLF' => array( "url(ab\\\r\ncd)" ),
		);
	}

	/**
	 * Tests that backslash-EOF in an unquoted URL produces U+FFFD in the value.
	 *
	 * @ticket 65738
	 */
	public function test_url_backslash_eof(): void {
		$processor = WP_CSS_Token_Processor::create( 'url(string\\' );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_URL, $processor->get_token_type() );
		$this->assertSame( "string\u{FFFD}", $processor->get_token_value() );
	}

	/**
	 * Tests that preprocessing replaces NUL in the token value and keeps the source bytes.
	 *
	 * NUL becomes U+FFFD before tokenization, so it is valid inside an unquoted
	 * URL. Other control bytes still produce a bad-url token.
	 *
	 * @ticket 65738
	 * @dataProvider data_preprocessed_token_keeps_source_bytes
	 */
	public function test_preprocessed_token_keeps_source_bytes( string $css, string $expected_type, ?string $expected_value ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( $expected_type, $processor->get_token_type() );
		$this->assertSame( $expected_value, $processor->get_token_value() );
		$this->assertSame( $css, $processor->get_unnormalized_token() );
		$this->assertSame( 0, $processor->get_token_start() );
		$this->assertSame( strlen( $css ), $processor->get_token_length() );
		$this->assertFalse( $processor->next_token() );
	}

	/**
	 * Data provider for test_preprocessed_token_keeps_source_bytes().
	 *
	 * @return Generator
	 */
	public static function data_preprocessed_token_keeps_source_bytes(): Generator {
		yield 'NUL as the URL' => array( "url(\x00)", WP_CSS_Token_Processor::TOKEN_URL, "\u{FFFD}" );
		yield 'NUL before text' => array( "url(\x00a)", WP_CSS_Token_Processor::TOKEN_URL, "\u{FFFD}a" );
		yield 'NUL inside text' => array( "url(a\x00b)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}b" );
		yield 'NUL before closing parenthesis' => array( "url(a\x00)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}" );
		yield 'NUL before EOF' => array( "url(a\x00", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}" );
		yield 'consecutive NUL bytes' => array( "url(a\x00\x00b)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}\u{FFFD}b" );
		yield 'escaped literal NUL' => array( "url(a\\\x00b)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}b" );
		yield 'zero escape' => array( 'url(a\0 b)', WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}b" );
		yield 'zero escape followed by CRLF' => array( "url(a\\0\r\nb)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}b" );
		yield 'NUL followed by trailing CRLF' => array( "url(a\x00\r\n)", WP_CSS_Token_Processor::TOKEN_URL, "a\u{FFFD}" );
		yield 'NUL in a string' => array( "\"a\x00b\"", WP_CSS_Token_Processor::TOKEN_STRING, "a\u{FFFD}b" );
		yield 'NUL in a name' => array( "a\x00b", WP_CSS_Token_Processor::TOKEN_IDENT, "a\u{FFFD}b" );
		yield 'NUL after trailing whitespace' => array( "url(a \x00)", WP_CSS_Token_Processor::TOKEN_BAD_URL, null );
		yield 'NUL after a forbidden control' => array( "url(a\x01\x00b)", WP_CSS_Token_Processor::TOKEN_BAD_URL, null );

		foreach ( array( "\t", "\n", "\r", "\f", "\r\n" ) as $whitespace ) {
			yield 'internal whitespace ' . bin2hex( $whitespace ) => array( "url(a{$whitespace}b)", WP_CSS_Token_Processor::TOKEN_BAD_URL, null );
		}

		foreach ( array_merge( range( 0x01, 0x08 ), array( 0x0B ), range( 0x0E, 0x1F ), array( 0x7F ) ) as $byte ) {
			yield 'forbidden control ' . dechex( $byte ) => array( 'url(a' . chr( $byte ) . 'b)', WP_CSS_Token_Processor::TOKEN_BAD_URL, null );
		}
	}

	/**
	 * Tests that backslash-newline stops an ident sequence.
	 *
	 * @ticket 65738
	 * @dataProvider data_ident_backslash_newline
	 */
	public function test_ident_backslash_newline( string $css ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_IDENT, $processor->get_token_type() );
		$this->assertSame( 'abc', $processor->get_token_value() );
	}

	/**
	 * Data provider for test_ident_backslash_newline().
	 *
	 * @return array[]
	 */
	public static function data_ident_backslash_newline(): array {
		return array(
			'backslash-LF'   => array( "abc\\\n" ),
			'backslash-FF'   => array( "abc\\\f" ),
			'backslash-CR'   => array( "abc\\\r" ),
			'backslash-CRLF' => array( "abc\\\r\n" ),
		);
	}

	/**
	 * Tests that backslash-EOF in an ident produces U+FFFD in the value.
	 *
	 * @ticket 65738
	 */
	public function test_ident_backslash_eof(): void {
		$processor = WP_CSS_Token_Processor::create( 'abc\\' );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_IDENT, $processor->get_token_type() );
		$this->assertSame( "abc\u{FFFD}", $processor->get_token_value() );
	}

	/**
	 * Bad string tokens have no associated value.
	 *
	 * @ticket 65738
	 */
	public function test_bad_string_token_value_is_null(): void {
		$processor = WP_CSS_Token_Processor::create( "'str\ning'" );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_BAD_STRING, $processor->get_token_type() );
		$this->assertNull( $processor->get_token_value() );
	}

	/**
	 * Tests that is_token_terminated() reports whether the last token ended at its closing delimiter.
	 *
	 * @ticket 65738
	 * @dataProvider data_is_token_terminated
	 */
	public function test_is_token_terminated( string $css, string $expected_type, bool $expected ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertFalse( $processor->is_token_terminated(), 'Before the first token there is nothing to report.' );

		$type = null;
		while ( $processor->next_token() ) {
			$type       = $processor->get_token_type();
			$terminated = $processor->is_token_terminated();
		}

		$this->assertSame( $expected_type, $type, 'The last token type did not match.' );
		$this->assertSame( $expected, $terminated, 'The last token termination flag did not match.' );
	}

	/**
	 * Data provider for test_is_token_terminated().
	 *
	 * @return array[]
	 */
	public static function data_is_token_terminated(): array {
		return array(
			'comment terminated'                 => array( '/* a */', WP_CSS_Token_Processor::TOKEN_COMMENT, true ),
			'comment empty terminated'           => array( '/**/', WP_CSS_Token_Processor::TOKEN_COMMENT, true ),
			'comment unterminated'               => array( '/* a', WP_CSS_Token_Processor::TOKEN_COMMENT, false ),
			'comment "/*/" unterminated'         => array( '/*/', WP_CSS_Token_Processor::TOKEN_COMMENT, false ),
			'comment terminated at end of input' => array( 'a /**/', WP_CSS_Token_Processor::TOKEN_COMMENT, true ),
			'string terminated'                  => array( '"a"', WP_CSS_Token_Processor::TOKEN_STRING, true ),
			'string escaped backslash'           => array( '"a\\\\"', WP_CSS_Token_Processor::TOKEN_STRING, true ),
			'string unterminated'                => array( '"a', WP_CSS_Token_Processor::TOKEN_STRING, false ),
			'string escape consumed the quote'   => array( '"a\\"', WP_CSS_Token_Processor::TOKEN_STRING, false ),
			'string lone quote'                  => array( '"', WP_CSS_Token_Processor::TOKEN_STRING, false ),
			'string single quoted terminated'    => array( "a 'b'", WP_CSS_Token_Processor::TOKEN_STRING, true ),
			'string single quoted unterminated'  => array( "a 'b", WP_CSS_Token_Processor::TOKEN_STRING, false ),
			'url terminated'                     => array( 'url(a)', WP_CSS_Token_Processor::TOKEN_URL, true ),
			'url escaped backslash'              => array( 'url(a\\\\)', WP_CSS_Token_Processor::TOKEN_URL, true ),
			'url unterminated'                   => array( 'url(a', WP_CSS_Token_Processor::TOKEN_URL, false ),
			'url escape consumed the paren'      => array( 'url(a\\)', WP_CSS_Token_Processor::TOKEN_URL, false ),
			'url trailing whitespace'            => array( 'url(a ', WP_CSS_Token_Processor::TOKEN_URL, false ),
			'url whitespace then paren'          => array( 'url(a )', WP_CSS_Token_Processor::TOKEN_URL, true ),
			'url empty unterminated'             => array( 'url(', WP_CSS_Token_Processor::TOKEN_URL, false ),
			'bad url terminated'                 => array( 'url(a b)', WP_CSS_Token_Processor::TOKEN_BAD_URL, true ),
			'bad url unterminated'               => array( 'url(a b', WP_CSS_Token_Processor::TOKEN_BAD_URL, false ),
			'ident at end of input'              => array( 'color: red', WP_CSS_Token_Processor::TOKEN_IDENT, true ),
			'ident backslash at end of input'    => array( 'abc\\', WP_CSS_Token_Processor::TOKEN_IDENT, true ),
			'function at end of input'           => array( 'calc(', WP_CSS_Token_Processor::TOKEN_FUNCTION, true ),
		);
	}

	/**
	 * Tests that is_token_terminated() is reset for the token after an unterminated one.
	 *
	 * @ticket 65738
	 */
	public function test_is_token_terminated_resets_between_tokens(): void {
		$processor = WP_CSS_Token_Processor::create( "'a\nb" );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_BAD_STRING, $processor->get_token_type() );
		$this->assertTrue( $processor->is_token_terminated() );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_WHITESPACE, $processor->get_token_type() );
		$this->assertTrue( $processor->is_token_terminated() );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_IDENT, $processor->get_token_type() );
		$this->assertTrue( $processor->is_token_terminated() );
	}

	/**
	 * Tests that hash tokens expose the proper type flag.
	 *
	 * @ticket 65738
	 * @dataProvider data_hash_tokens_expose_type_flags
	 */
	public function test_hash_tokens_expose_type_flags( string $css, array $expected_tokens ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		foreach ( $expected_tokens as $expected_token ) {
			$this->assertTrue( $processor->next_token() );
			$this->assertSame( $expected_token['type'], $processor->get_token_type() );
			$this->assertSame( $expected_token['raw'], $processor->get_unnormalized_token() );
			$this->assertSame( $expected_token['type_flag'], $processor->get_token_type_flag() );
		}

		$this->assertFalse( $processor->next_token() );
		$this->assertNull( $processor->get_token_type_flag() );
	}

	/**
	 * Data provider for test_hash_tokens_expose_type_flags().
	 *
	 * @return array[]
	 */
	public static function data_hash_tokens_expose_type_flags(): array {
		return array(
			'id hash'                           => array(
				'#id',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#id',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_ID,
					),
				),
			),
			'unrestricted hash starting digit'  => array(
				'#1id',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#1id',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_UNRESTRICTED,
					),
				),
			),
			'id hash starting hyphen ident'     => array(
				'#-id',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#-id',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_ID,
					),
				),
			),
			'unrestricted hash starting hyphen' => array(
				'#-1id',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#-1id',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_UNRESTRICTED,
					),
				),
			),
			'id hash starting escape'           => array(
				'#\\@special',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#\\@special',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_ID,
					),
				),
			),
			'hash delimiter has no type flag'   => array(
				'#',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_DELIM,
						'raw'       => '#',
						'type_flag' => null,
					),
				),
			),
			'following token clears type flag'  => array(
				'#id .',
				array(
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_HASH,
						'raw'       => '#id',
						'type_flag' => WP_CSS_Token_Processor::HASH_TOKEN_ID,
					),
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
						'raw'       => ' ',
						'type_flag' => null,
					),
					array(
						'type'      => WP_CSS_Token_Processor::TOKEN_DELIM,
						'raw'       => '.',
						'type_flag' => null,
					),
				),
			),
		);
	}

	/**
	 * Tests token type flags for numeric and non-numeric tokens.
	 *
	 * @dataProvider data_token_type_flag
	 */
	public function test_token_type_flag( string $css, ?string $expected_type ): void {
		$processor = WP_CSS_Token_Processor::create( $css );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( $expected_type, $processor->get_token_type_flag() );
	}

	/**
	 * Data provider for test_token_type_flag().
	 *
	 * @return array[]
	 */
	public static function data_token_type_flag(): array {
		return array(
			'integer'               => array( '42', 'integer' ),
			'positive integer'      => array( '+42', 'integer' ),
			'negative integer'      => array( '-42', 'integer' ),
			'zero'                  => array( '0', 'integer' ),
			'decimal'               => array( '42.0', 'number' ),
			'decimal with fraction' => array( '42.5', 'number' ),
			'leading decimal point' => array( '.5', 'number' ),
			'exponent lowercase'    => array( '1e2', 'number' ),
			'exponent uppercase'    => array( '1E2', 'number' ),
			'exponent with plus'    => array( '1E+2', 'number' ),
			'exponent with minus'   => array( '1e-2', 'number' ),
			'dimension integer'     => array( '10px', 'integer' ),
			'dimension decimal'     => array( '10.5px', 'number' ),
			'dimension exponent'    => array( '1e2px', 'number' ),
			'percentage integer'    => array( '20%', null ),
			'percentage decimal'    => array( '20.0%', null ),
			'ident token'           => array( 'red', null ),
			'string token'          => array( '"hello"', null ),
		);
	}

	/**
	 * Tests handling of non-UTF-8 byte sequences in identifiers.
	 *
	 * Invalid UTF-8 sequences should be replaced with U+FFFD replacement characters
	 * during tokenization, allowing the CSS to continue processing.
	 */
	public function test_non_utf8_sequences_in_identifiers(): void {
		// Invalid UTF-8 sequence 0xC0 0x80 (overlong encoding).
		$css = ".class\xF1name";

		$expected = array(
			// .class�name (0xF1 replaced with U+FFFD).
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => "class\xF1name",
				'normalized' => 'class�name',
				'value'      => 'class�name',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value', 'unit' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	public function test_invalid_utf8_in_normal_segment_combined_with_escape(): void {
		$css = ".test\xF1\\41name";

		$expected = array(
			array(
				'type'  => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'   => '.',
				'value' => '.',
			),
			array(
				'type'  => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'   => "test\xF1\\41name",
				'value' => "test\u{FFFD}Aname",
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	public function test_invalid_utf8_as_escaped_character(): void {
		$css = ".a\\\xF1b";

		$expected = array(
			array(
				'type'  => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'   => '.',
				'value' => '.',
			),
			array(
				'type'  => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'   => "a\\\xF1b",
				'value' => "a\u{FFFD}b",
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	public function test_invalid_utf8_with_valid_prefix_in_identifiers(): void {
		// Invalid 2-byte prefix is replaced with a single U+FFFD.
		$css = ".test\xE2\x80name";

		$expected = array(
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => "test\xE2\x80name",
				'normalized' => 'test�name',
				'value'      => 'test�name',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	public function test_invalid_utf8_with_two_single_byte_invalid_sequences(): void {
		// Two distinct single byte invalid sequences are replaced with
		// two separate U+FFFD replacement characters.
		$css = ".test\xE2\xE2name";

		$expected = array(
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => "test\xE2\xE2name",
				'normalized' => 'test��name',
				'value'      => 'test��name',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	/**
	 * Legacy test to ensure basic tokenization still works.
	 */
	public function test_tokenize_labels_core_tokens(): void {
		$css = <<<CSS
@media screen and (min-width: 10px) {
	background: url("/images/a.png");
}
CSS;

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_AT_KEYWORD,
				'raw'  => '@media',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'screen',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'and',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_PAREN,
				'raw'  => '(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'min-width',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '10px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => "\n\t",
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'background',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'url(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'  => '"/images/a.png"',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => "\n",
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of complex selectors with pseudo-classes.
	 */
	public function test_complex_selector_with_pseudo_classes(): void {
		$css = 'a:hover::before, div.class#id:not(.disabled)';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'a',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'hover',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'before',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'div',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '.',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'class',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_HASH,
				'raw'  => '#id',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'not(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '.',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'disabled',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of CSS comments.
	 */
	public function test_css_comments(): void {
		$css = '/* This is a comment */ .class { color: red; /* Another comment */ }';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMENT,
				'raw'  => '/* This is a comment */',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '.',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'class',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'red',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMENT,
				'raw'  => '/* Another comment */',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of media queries.
	 */
	public function test_media_query(): void {
		$css = '@media screen and (min-width: 768px) and (max-width: 1024px)';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_AT_KEYWORD,
				'raw'  => '@media',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'screen',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'and',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_PAREN,
				'raw'  => '(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'min-width',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '768px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'and',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_PAREN,
				'raw'  => '(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'max-width',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '1024px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of keyframes animation.
	 */
	public function test_keyframes_animation(): void {
		$css = '@keyframes slide-in { 0% { opacity: 0; } 100% { opacity: 1; } }';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_AT_KEYWORD,
				'raw'  => '@keyframes',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'slide-in',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'  => '0%',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'opacity',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'  => '100%',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'opacity',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '1',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of vendor-prefixed properties.
	 */
	public function test_vendor_prefixed_properties(): void {
		$css = '-webkit-transform: rotate(45deg); -moz-border-radius: 5px;';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => '-webkit-transform',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'rotate(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '45deg',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => '-moz-border-radius',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '5px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of attribute selectors.
	 */
	public function test_attribute_selectors(): void {
		$css = 'input[type="text"][required], a[href^="https://"]';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'input',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET,
				'raw'  => '[',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'type',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '=',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'  => '"text"',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET,
				'raw'  => ']',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET,
				'raw'  => '[',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'required',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET,
				'raw'  => ']',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'a',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET,
				'raw'  => '[',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'href',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '^',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '=',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'  => '"https://"',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET,
				'raw'  => ']',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of calc() function with complex expressions.
	 */
	public function test_calc_function(): void {
		$css = 'width: calc(100% - 20px * 2 + 5em);';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'width',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'calc(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'  => '100%',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '-',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '20px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '*',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '2',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '+',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '5em',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of RGB/RGBA color functions.
	 */
	public function test_color_functions(): void {
		$css = 'color: rgb(255, 128, 0); background: rgba(0, 0, 0, 0.5);';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'rgb(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '255',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '128',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'background',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'rgba(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '0.5',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of CSS custom properties (variables).
	 */
	public function test_css_variables(): void {
		$css = '--main-color: #ff0000; color: var(--main-color);';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => '--main-color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_HASH,
				'raw'  => '#ff0000',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'var(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => '--main-color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of gradient functions.
	 */
	public function test_gradient_functions(): void {
		$css = 'background: linear-gradient(to right, red 0%, blue 100%);';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'background',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'linear-gradient(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'to',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'right',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'red',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'  => '0%',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'blue',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'  => '100%',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of grid layout properties.
	 */
	public function test_grid_layout(): void {
		$css = 'grid-template-columns: repeat(3, 1fr); gap: 10px 20px;';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'grid-template-columns',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'repeat(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_NUMBER,
				'raw'  => '3',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '1fr',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'gap',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '10px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '20px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of URL functions with various formats.
	 */
	public function test_url_formats(): void {
		$css = 'background: url("image.png"), url(\'font.woff\'), url(https://example.com/bg.jpg);';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'background',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'url(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'  => '"image.png"',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'  => 'url(',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'  => "'font.woff'",
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'  => ')',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_URL,
				'raw'  => 'url(https://example.com/bg.jpg)',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of !important declarations.
	 */
	public function test_important_declarations(): void {
		$css = 'color: red !important; margin: 0px !important;';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'red',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '!',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'important',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'margin',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'  => '0px',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '!',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'important',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of multiple selectors with combinators.
	 */
	public function test_complex_combinators(): void {
		$css = 'div > p + span ~ a.link';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'div',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '>',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'p',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '+',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'span',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '~',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'a',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '.',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'link',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests tokenization of escaped characters in identifiers.
	 */
	public function test_escaped_identifiers(): void {
		$css = '.class\\:name, #id\\@special { color: blue; }';

		$expected = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '.',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'class\\:name',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COMMA,
				'raw'  => ',',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_HASH,
				'raw'  => '#id\\@special',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'  => '{',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'color',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'  => ':',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'  => 'blue',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'  => ';',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'  => ' ',
			),
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'  => '}',
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$this->assertSame( $actual_tokens, $expected );
	}

	/**
	 * Tests that get_normalized_token() applies CSS normalization.
	 *
	 * Uses a comprehensive CSS selector with rules that includes:
	 * - CSS escapes in class names and IDs
	 * - URLs with escape sequences
	 * - String values with escapes and line endings
	 * - Comments with various line ending characters
	 * - Null bytes in identifiers
	 * - Mixed line endings (\r\n, \r, \f) that need normalization
	 */
	public function test_get_normalized_token_applies_normalization(): void {
		// Comprehensive CSS with normalization requirements.
		$css = "/* Comment\r\nwith\flines */\r\n" .
				".c\\6c ass.n\\61 me\r#id\\@value\r\n{\r\n" .
				"\tbackground:\furl(path\\2f to\\2f image.png);\r\n" .
				"\tcontent:\r\"text\\A string\";\r\n" .
				'}';

		$expected = array(
			// Comment with \r\n and \f.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COMMENT,
				'raw'        => "/* Comment\r\nwith\flines */",
				'normalized' => "/* Comment\nwith\nlines */",
				'value'      => null,
			),
			// Whitespace with \r\n.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r\n",
				'normalized' => "\n",
				'value'      => null,
			),
			// Class selector delimiter.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			// Class name with escape (\6c = 'l'), space gets consumed by escape.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'c\\6c ass',
				'normalized' => 'class', // Escapes decoded.
				'value'      => 'class', // Decoded: \6c → l, space consumed.
			),
			// Delimiter.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			// Identifier with escape (\61 = 'a'), space gets consumed.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'n\\61 me',
				'normalized' => 'name', // Escapes decoded.
				'value'      => 'name', // Decoded: \61 → a, space consumed.
			),
			// Whitespace with \r.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r",
				'normalized' => "\n",
				'value'      => null,
			),
			// ID selector with escape.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_HASH,
				'raw'        => '#id\\@value',
				'normalized' => '#id@value', // Escapes decoded.
				'value'      => 'id@value', // Decoded value.
			),
			// Whitespace with \r\n.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r\n",
				'normalized' => "\n",
				'value'      => null,
			),
			// Opening brace.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'        => '{',
				'normalized' => '{',
				'value'      => null,
			),
			// Whitespace with \r\n and tab (consumed together).
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r\n\t",
				'normalized' => "\n\t",
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'background',
				'normalized' => 'background',
				'value'      => 'background',
			),
			// Colon.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			// Whitespace with \f.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\f",
				'normalized' => "\n",
				'value'      => null,
			),
			// URL token with escapes (entire url(...) is one token).
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_URL,
				'raw'        => 'url(path\\2f to\\2f image.png)',
				'normalized' => 'url(path/to/image.png)', // Escapes decoded.
				'value'      => 'path/to/image.png', // Decoded: \2f → /, spaces consumed.
			),
			// Semicolon.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			// Whitespace with \r\n and tab (consumed together).
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r\n\t",
				'normalized' => "\n\t",
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'content',
				'normalized' => 'content',
				'value'      => 'content',
			),
			// Colon.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			// Whitespace with \r.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r",
				'normalized' => "\n",
				'value'      => null,
			),
			// String with escape (\A = newline, space consumed).
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'        => '"text\\A string"',
				'normalized' => "\"text\nstring\"", // Escapes decoded, quotes preserved.
				'value'      => "text\nstring", // \A → \n, space consumed.
			),
			// Semicolon.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			// Whitespace with \r\n.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => "\r\n",
				'normalized' => "\n",
				'value'      => null,
			),
			// Closing brace.
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'        => '}',
				'normalized' => '}',
				'value'      => null,
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	public function test_dimension_token_value(): void {
		$css           = '10px;15em;20%;30pt;40pc;50vw;';
		$expected      = array(
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '10px',
				'normalized' => '10px',
				'value'      => '10',
				'unit'       => 'px',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '15em',
				'normalized' => '15em',
				'value'      => '15',
				'unit'       => 'em',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
				'raw'        => '20%',
				'normalized' => '20%',
				'value'      => '20',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '30pt',
				'normalized' => '30pt',
				'value'      => '30',
				'unit'       => 'pt',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '40pc',
				'normalized' => '40pc',
				'value'      => '40',
				'unit'       => 'pc',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '50vw',
				'normalized' => '50vw',
				'value'      => '50',
				'unit'       => 'vw',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
		);
		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value', 'unit' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	/**
	 * Tests that create() validates encoding and only accepts UTF-8.
	 */
	public function test_create_validates_encoding(): void {
		// UTF-8 encoding should work (default).
		$processor = WP_CSS_Token_Processor::create( '.class { color: red; }' );
		$this->assertInstanceOf( WP_CSS_Token_Processor::class, $processor );

		// UTF-8 encoding should work (explicit).
		$processor = WP_CSS_Token_Processor::create( '.class { color: red; }', 'UTF-8' );
		$this->assertInstanceOf( WP_CSS_Token_Processor::class, $processor );

		// Other encodings should return null.
		$processor = WP_CSS_Token_Processor::create( '.class { color: red; }', 'ISO-8859-1' );
		$this->assertNull( $processor );

		$processor = WP_CSS_Token_Processor::create( '.class { color: red; }', 'Windows-1252' );
		$this->assertNull( $processor );
	}

	/**
	 * Tests escape sequences in unusual and edge-case positions.
	 *
	 * Covers:
	 * - Multiple consecutive escapes
	 * - Escapes in function names
	 * - Escapes in at-keywords
	 * - Escapes in dimension units
	 * - Null byte escapes (\0)
	 * - Escaped special characters (@, #, !, etc.)
	 * - Escaped whitespace that gets consumed by the escape
	 * - Unicode escapes for various characters
	 */
	public function test_escape_sequences_in_unusual_places() {
		// Complex CSS with escapes in many unusual but valid positions
		$css = '@\\6D edia ' . // @media with \6D (m) and space consumed
				'\\73 creen ' . // screen with \73 (s) and space consumed
				'{' .
				' .\\63 l\\61 ss\\5F name ' . // .class_name with escapes and spaces consumed
				"#\\69 d\\5C 0test\x00 " . // #id\0test with null byte escape (should be preserved)
														// AND an actual null byte (should be replaced with a U+FFFD REPLACEMENT CHARACTER)
				'{' .
				' c\\6F lor: ' . // color: with \6F (o) and space consumed
				'r\\65 d ' . // red with escape
				'\\21 important;' . // !important with escaped !
				' w\\69 dth: ' . // width:
				'10\\70 x;' . // 10px (dimension with escaped unit)
				' background: ' .
				'\\75 rl(' . // url( with escaped u
				'"p\\61 th\\2F img\\2E png"' . // "path/img.png" with escapes
				');' .
				' content: "\\5C \\5C ";' . // "\\ \\" - escaped backslashes
				' font-family: \\22 Arial\\22 ;' . // "Arial" with escaped quotes
				' }' .
				'}';

		$expected = array(
			// @\6D edia -> @media
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_AT_KEYWORD,
				'raw'        => '@\\6D edia',
				'normalized' => '@media',
				'value'      => 'media',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// \73 creen -> screen
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => '\\73 creen',
				'normalized' => 'screen',
				'value'      => 'screen',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'        => '{',
				'normalized' => '{',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// Delimiter .
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'        => '.',
				'normalized' => '.',
				'value'      => '.',
			),
			// \63 l\61 ss\5F name -> class_name
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => '\\63 l\\61 ss\\5F name',
				'normalized' => 'class_name',
				'value'      => 'class_name',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// #\69 d\5C 0test -> #id\0test (with encoded null byte)
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_HASH,
				'raw'        => "#\\69 d\\5C 0test\x00",
				'normalized' => "#id\\0test�",
				// Ensure the value is normalized.
				'value'      => "id\\0test�",
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_LEFT_BRACE,
				'raw'        => '{',
				'normalized' => '{',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// c\6F lor -> color
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'c\\6F lor',
				'normalized' => 'color',
				'value'      => 'color',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// r\65 d -> red
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'r\\65 d',
				'normalized' => 'red',
				'value'      => 'red',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// \21 important -> !important (single identifier with escaped !)
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => '\\21 important',
				'normalized' => '!important',
				'value'      => '!important',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// w\69 dth -> width
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'w\\69 dth',
				'normalized' => 'width',
				'value'      => 'width',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// 10\70 x -> 10px (dimension with escaped unit)
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_DIMENSION,
				'raw'        => '10\\70 x',
				'normalized' => '10px',
				'value'      => '10',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'background',
				'normalized' => 'background',
				'value'      => 'background',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// \75 rl( -> url( (escaped function name)
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_FUNCTION,
				'raw'        => '\\75 rl(',
				'normalized' => 'url(',
				'value'      => 'url',
			),
			// String with escapes: "p\61 th\2F img\2E png"
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'        => '"p\\61 th\\2F img\\2E png"',
				'normalized' => '"path/img.png"',
				'value'      => 'path/img.png',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
				'raw'        => ')',
				'normalized' => ')',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'content',
				'normalized' => 'content',
				'value'      => 'content',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// String with escaped backslashes: "\5C \5C " -> "\\"
			// Each \5C sequence (with trailing space consumed) becomes one backslash
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_STRING,
				'raw'        => '"\\5C \\5C "',
				'normalized' => '"\\\\"',
				'value'      => '\\\\',  // Two backslashes total
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => 'font-family',
				'normalized' => 'font-family',
				'value'      => 'font-family',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_COLON,
				'raw'        => ':',
				'normalized' => ':',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			// \22 Arial\22 -> "Arial" (escaped quotes make it an ident)
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_IDENT,
				'raw'        => '\\22 Arial\\22 ',
				'normalized' => '"Arial"',
				'value'      => '"Arial"',
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_SEMICOLON,
				'raw'        => ';',
				'normalized' => ';',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_WHITESPACE,
				'raw'        => ' ',
				'normalized' => ' ',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'        => '}',
				'normalized' => '}',
				'value'      => null,
			),
			array(
				'type'       => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
				'raw'        => '}',
				'normalized' => '}',
				'value'      => null,
			),
		);

		$processor     = WP_CSS_Token_Processor::create( $css );
		$actual_tokens = $this->collect_tokens( $processor, array( 'type', 'raw', 'normalized', 'value' ) );
		$this->assertSame( $expected, $actual_tokens );
	}

	/**
	 * Tests that decode_range() respects the token's length boundary.
	 *
	 * The escape sequence \41 (= "A") triggers the slow path. The CSS after
	 * the closing quote must not appear in the token value.
	 */
	public function test_decode_range_respects_length_boundary(): void {
		$processor = WP_CSS_Token_Processor::create( '"hello\\41 world"; color: red;' );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $processor->get_token_type() );
		$this->assertSame( 'helloAworld', $processor->get_token_value() );
		$this->assertSame( '"helloAworld"', $processor->get_normalized_token() );
	}

	/**
	 * Tests that decode_escape_at() consumes at most six hex digits.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
	 */
	public function test_decode_escape_at_hex_limit_is_six_digits(): void {
		$processor = WP_CSS_Token_Processor::create( '"\\0000411rest"' );

		$this->assertTrue( $processor->next_token() );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $processor->get_token_type() );
		$this->assertSame( 'A1rest', $processor->get_token_value() );
	}

	/**
	 * Test bounds check when consuming and ident start token.
	 */
	public function test_ident_start_codepoint_bounds_check(): void {
		$processor       = WP_CSS_Token_Processor::create( '-' );
		$actual_tokens   = $this->collect_tokens( $processor, array( 'type', 'raw' ) );
		$expected_tokens = array(
			array(
				'type' => WP_CSS_Token_Processor::TOKEN_DELIM,
				'raw'  => '-',
			),
		);
		$this->assertSame( $expected_tokens, $actual_tokens );
	}
}
