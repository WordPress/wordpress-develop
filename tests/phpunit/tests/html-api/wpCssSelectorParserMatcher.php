<?php
/**
 * Unit tests covering WP_CSS_Selector_Parser_Matcher functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 */
class Tests_HtmlApi_WpCssSelectorParserMatcher extends WP_UnitTestCase {
	private $test_class;

	public function set_up(): void {
		parent::set_up();
		$this->test_class = new class() extends WP_CSS_Selector_Parser_Matcher {
			public function matches( $processor ): bool {
				throw new Error( 'Matches called on test class.' );
			}
			public static function parse( string $input, int &$offset ) {
				throw new Error( 'Parse called on test class.' );
			}

			/*
			 * Parsing
			 */
			public static function test_parse_ident( string $input, int &$offset ) {
				return self::parse_ident( $input, $offset );
			}

			public static function test_parse_string( string $input, int &$offset ) {
				return self::parse_string( $input, $offset );
			}

			/*
			 * Utilities
			 */
			public static function test_is_ident_codepoint( string $input, int $offset ) {
				return self::is_ident_codepoint( $input, $offset );
			}

			public static function test_is_ident_start_codepoint( string $input, int $offset ) {
				return self::is_ident_start_codepoint( $input, $offset );
			}
		};
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_idents(): array {
		return array(
			'trailing #'                         => array( '_-foo123#xyz', '_-foo123', '#xyz' ),
			'trailing .'                         => array( '😍foo123.xyz', '😍foo123', '.xyz' ),
			'trailing " "'                       => array( '😍foo123 more', '😍foo123', ' more' ),
			'escaped ASCII character'            => array( '\\xyz', 'xyz', '' ),
			'escaped space'                      => array( '\\ x', ' x', '' ),
			'escaped emoji'                      => array( '\\😍', '😍', '' ),
			'hex unicode codepoint'              => array( '\\1f0a1', '🂡', '' ),
			'HEX UNICODE CODEPOINT'              => array( '\\1D4B2', '𝒲', '' ),

			'hex tab-suffixed 1'                 => array( "\\31\t23", '123', '' ),
			'hex newline-suffixed 1'             => array( "\\31\n23", '123', '' ),
			'hex space-suffixed 1'               => array( "\\31 23", '123', '' ),
			'hex tab'                            => array( '\\9', "\t", '' ),
			'hex a'                              => array( '\\61 bc', 'abc', '' ),
			'hex a max escape length'            => array( '\\000061bc', 'abc', '' ),

			'out of range replacement min'       => array( '\\110000 ', "\u{fffd}", '' ),
			'out of range replacement max'       => array( '\\ffffff ', "\u{fffd}", '' ),
			'leading surrogate min replacement'  => array( '\\d800 ', "\u{fffd}", '' ),
			'leading surrogate max replacement'  => array( '\\dbff ', "\u{fffd}", '' ),
			'trailing surrogate min replacement' => array( '\\dc00 ', "\u{fffd}", '' ),
			'trailing surrogate max replacement' => array( '\\dfff ', "\u{fffd}", '' ),
			'can start with -ident'              => array( '-ident', '-ident', '' ),
			'can start with --anything'          => array( '--anything', '--anything', '' ),
			'can start with ---anything'         => array( '--_anything', '--_anything', '' ),
			'can start with --1anything'         => array( '--1anything', '--1anything', '' ),
			'can start with -\31 23'             => array( '-\31 23', '-123', '' ),
			'can start with --\31 23'            => array( '--\31 23', '--123', '' ),
			'ident ends before ]'                => array( 'ident]', 'ident', ']' ),

			// Invalid
			'Invalid: (empty string)'            => array( '' ),
			'Invalid: bad start >'               => array( '>ident' ),
			'Invalid: bad start ['               => array( '[ident' ),
			'Invalid: bad start #'               => array( '#ident' ),
			'Invalid: bad start " "'             => array( ' ident' ),
			'Invalid: bad start 1'               => array( '1ident' ),
			'Invalid: bad start -1'              => array( '-1ident' ),
			'Invalid: bad start -'               => array( '-' ),
		);
	}

	/**
	 * @ticket 62653
	 */
	public function test_is_ident_and_is_ident_start() {
		$this->assertFalse( $this->test_class::test_is_ident_codepoint( '[', 0 ) );
		$this->assertFalse( $this->test_class::test_is_ident_codepoint( ']', 0 ) );
		$this->assertFalse( $this->test_class::test_is_ident_start_codepoint( '[', 0 ) );
		$this->assertFalse( $this->test_class::test_is_ident_start_codepoint( ']', 0 ) );
	}

	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_idents
	 */
	public function test_parse_ident( string $input, ?string $expected = null, ?string $rest = null ) {

		$offset = 0;
		$result = $this->test_class::test_parse_ident( $input, $offset );
		if ( null === $expected ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $expected, $result, 'Ident did not match.' );
			$this->assertSame( $rest, substr( $input, $offset ), 'Offset was not updated correctly.' );
		}
	}

	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_strings
	 */
	public function test_parse_string( string $input, ?string $expected = null, ?string $rest = null ) {
		$offset = 0;
		$result = $this->test_class::test_parse_string( $input, $offset );
		if ( null === $expected ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $expected, $result, 'String did not match.' );
			$this->assertSame( $rest, substr( $input, $offset ), 'Offset was not updated correctly.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_strings(): array {
		return array(
			'"foo"'                   => array( '"foo"', 'foo', '' ),
			'"foo"after'              => array( '"foo"after', 'foo', 'after' ),
			'"foo""two"'              => array( '"foo""two"', 'foo', '"two"' ),
			'"foo"\'two\''            => array( '"foo"\'two\'', 'foo', "'two'" ),

			"'foo'"                   => array( "'foo'", 'foo', '' ),
			"'foo'after"              => array( "'foo'after", 'foo', 'after' ),
			"'foo'\"two\""            => array( "'foo'\"two\"", 'foo', '"two"' ),
			"'foo''two'"              => array( "'foo''two'", 'foo', "'two'" ),

			"'foo\\nbar'"             => array( "'foo\\\nbar'", 'foobar', '' ),
			"'foo\\31 23'"            => array( "'foo\\31 23'", 'foo123', '' ),
			"'foo\\31\\n23'"          => array( "'foo\\31\n23'", 'foo123', '' ),
			"'foo\\31\\t23'"          => array( "'foo\\31\t23'", 'foo123', '' ),
			"'foo\\00003123'"         => array( "'foo\\00003123'", 'foo123', '' ),

			"'foo\\"                  => array( "'foo\\", 'foo', '' ),

			'"'                       => array( '"', '', '' ),
			'"\\"'                    => array( '"\\"', '"', '' ),
			'"missing close'          => array( '"missing close', 'missing close', '' ),

			// Invalid
			'Invalid: (empty string)' => array( '' ),
			'Invalid: .foo'           => array( '.foo' ),
			'Invalid: #foo'           => array( '#foo' ),
			"Invalid: 'newline\\n'"   => array( "'newline\n'" ),
			'Invalid: foo'            => array( 'foo' ),
		);
	}
}
