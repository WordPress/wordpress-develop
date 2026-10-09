<?php

/**
 * Tests for WP_CSS_Token and WP_CSS_Token_Processor::get_token().
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token
 */
class Tests_CssApi_WpCssToken extends WP_UnitTestCase {
	/**
	 * Tests that tokens read with get_token() match the corpus.
	 *
	 * @ticket 65738
	 * @dataProvider data_corpus
	 *
	 * @covers WP_CSS_Token_Processor::get_token
	 */
	public function test_get_token_matches_spec( string $css, array $expected_tokens ): void {
		$processor = WP_CSS_Token_Processor::create( $css );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$tokens[] = $processor->get_token();
		}

		$actual_tokens = array();
		foreach ( $tokens as $token ) {
			$actual_token = array(
				'type'       => $token->get_type(),
				'startIndex' => $token->get_start(),
				'endIndex'   => $token->get_start() + $token->get_length(),
				'value'      => $token->get_value(),
			);
			if ( null !== $token->get_unit() ) {
				$actual_token['unit'] = $token->get_unit();
			}
			if (
				WP_CSS_Token_Processor::TOKEN_NUMBER === $token->get_type() ||
				WP_CSS_Token_Processor::TOKEN_DIMENSION === $token->get_type()
			) {
				$actual_token['numberType'] = $token->get_flag();
			}
			$actual_tokens[] = $actual_token;
		}

		$expected_tokens = array_map(
			static function ( array $token ): array {
				unset( $token['raw'], $token['normalized'] );
				return $token;
			},
			$expected_tokens
		);

		$this->assertSame( $expected_tokens, $actual_tokens );
	}

	/**
	 * Data provider for test_get_token_matches_spec().
	 *
	 * @return array[]
	 */
	public static function data_corpus(): array {
		return json_decode( file_get_contents( DIR_TESTDATA . '/css-api/css-test-cases.json' ), true );
	}

	/**
	 * Tests that get_token() returns null when the processor has no current token.
	 *
	 * @ticket 65738
	 *
	 * @covers WP_CSS_Token_Processor::get_token
	 */
	public function test_get_token_returns_null_without_a_current_token(): void {
		$processor = WP_CSS_Token_Processor::create( 'a' );
		$this->assertNull( $processor->get_token(), 'There is no token before next_token().' );

		$this->assertTrue( $processor->next_token() );
		$this->assertInstanceOf( WP_CSS_Token::class, $processor->get_token() );

		$this->assertFalse( $processor->next_token() );
		$this->assertNull( $processor->get_token(), 'There is no token after the last one.' );
	}

	/**
	 * Tests that a token decodes its value after the processor has moved on.
	 *
	 * @ticket 65738
	 *
	 * @covers ::get_value
	 */
	public function test_value_decodes_after_the_processor_moves_on(): void {
		$processor = WP_CSS_Token_Processor::create( '\\66 oo "b\\61 r" url(b\\61 z) #\\31 a 1\\70 x' );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$tokens[] = $processor->get_token();
		}

		$values = array();
		foreach ( $tokens as $token ) {
			$values[] = array( $token->get_type(), $token->get_value() );
		}

		$this->assertSame(
			array(
				array( WP_CSS_Token_Processor::TOKEN_IDENT, 'foo' ),
				array( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null ),
				array( WP_CSS_Token_Processor::TOKEN_STRING, 'bar' ),
				array( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null ),
				array( WP_CSS_Token_Processor::TOKEN_URL, 'baz' ),
				array( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null ),
				array( WP_CSS_Token_Processor::TOKEN_HASH, '1a' ),
				array( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null ),
				array( WP_CSS_Token_Processor::TOKEN_DIMENSION, '1' ),
			),
			$values
		);
		$this->assertSame( 'px', $tokens[8]->get_unit() );
	}

	/**
	 * Tests that a token reports the type flag of the token it was read from.
	 *
	 * @ticket 65738
	 * @dataProvider data_flag
	 *
	 * @covers ::get_flag
	 */
	public function test_flag( string $css, string $expected_type, ?string $expected_flag ): void {
		$processor = WP_CSS_Token_Processor::create( $css );
		$this->assertTrue( $processor->next_token() );

		$token = $processor->get_token();
		$this->assertSame( $expected_type, $token->get_type() );
		$this->assertSame( $expected_flag, $token->get_flag() );
	}

	/**
	 * Data provider for test_flag().
	 *
	 * @return array[]
	 */
	public static function data_flag(): array {
		return array(
			'hash id'            => array( '#a1', WP_CSS_Token_Processor::TOKEN_HASH, WP_CSS_Token_Processor::HASH_TOKEN_ID ),
			'hash escaped digit' => array( '#\\31 a', WP_CSS_Token_Processor::TOKEN_HASH, WP_CSS_Token_Processor::HASH_TOKEN_ID ),
			'hash unrestricted'  => array( '#1a', WP_CSS_Token_Processor::TOKEN_HASH, WP_CSS_Token_Processor::HASH_TOKEN_UNRESTRICTED ),
			'integer'            => array( '12', WP_CSS_Token_Processor::TOKEN_NUMBER, 'integer' ),
			'number'             => array( '1.5', WP_CSS_Token_Processor::TOKEN_NUMBER, 'number' ),
			'integer dimension'  => array( '1px', WP_CSS_Token_Processor::TOKEN_DIMENSION, 'integer' ),
			'number dimension'   => array( '1e2px', WP_CSS_Token_Processor::TOKEN_DIMENSION, 'number' ),
			'percentage'         => array( '50%', WP_CSS_Token_Processor::TOKEN_PERCENTAGE, null ),
			'ident'              => array( 'a', WP_CSS_Token_Processor::TOKEN_IDENT, null ),
		);
	}

	/**
	 * Tests that a token reports whether it ended at its closing delimiter.
	 *
	 * @ticket 65738
	 * @dataProvider data_is_terminated
	 *
	 * @covers ::is_terminated
	 */
	public function test_is_terminated( string $css, bool $expected ): void {
		$processor = WP_CSS_Token_Processor::create( $css );
		$this->assertTrue( $processor->next_token() );

		$token = $processor->get_token();
		$processor->next_token();
		$this->assertSame( $expected, $token->is_terminated(), 'The flag must not change when the processor moves on.' );
	}

	/**
	 * Data provider for test_is_terminated().
	 *
	 * @return array[]
	 */
	public static function data_is_terminated(): array {
		return array(
			'string terminated'    => array( '"a" b', true ),
			'string unterminated'  => array( '"a', false ),
			'url terminated'       => array( 'url(a) b', true ),
			'url unterminated'     => array( 'url(a', false ),
			'comment terminated'   => array( '/* a */ b', true ),
			'comment unterminated' => array( '/* a', false ),
			'ident'                => array( 'a b', true ),
		);
	}
}
