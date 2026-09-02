<?php
/**
 * Unit tests covering WP_CSS_Selector_Parser_Matcher functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since {WP_VERSION}
 *
 * @group html-api
 */
class Tests_HtmlApi_WpCssSelectorParserMatcher extends WP_UnitTestCase {
	private $test_class;

	/**
	 * Preserves the `mb_substitute_character()` setting around each test.
	 *
	 * @var int|string
	 */
	private $original_substitute_character;

	public function set_up(): void {
		parent::set_up();

		/*
		 * Parse results must not depend on the process-global
		 * `mb_substitute_character()` setting. Pin it to a distinctive
		 * character — U+2603 SNOWMAN (☃) — for every test in this file: any
		 * dependence on the setting would surface as a ☃ in the results
		 * rather than the expected U+FFFD. This guards the invalid-byte
		 * decode, which once leaked the setting into parse results.
		 */
		$this->original_substitute_character = mb_substitute_character();
		mb_substitute_character( 0x2603 );
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

	public function tear_down(): void {
		mb_substitute_character( $this->original_substitute_character );
		parent::tear_down();
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_idents(): array {
		return array(
			'trailing #'                           => array( '_-foo123#xyz', '_-foo123', '#xyz' ),
			'trailing .'                           => array( '😍foo123.xyz', '😍foo123', '.xyz' ),
			'trailing " "'                         => array( '😍foo123 more', '😍foo123', ' more' ),
			'escaped ASCII character'              => array( '\\xyz', 'xyz', '' ),
			'escape after multibyte character'     => array( 'Ü\\sup', 'Üsup', '' ),
			'escape after multibyte characters'    => array( 'ÜÜ\\sup', 'ÜÜsup', '' ),
			'hex escape after multibyte character' => array( 'Ü\\31 23', 'Ü123', '' ),
			'escaped space'                        => array( '\\ x', ' x', '' ),
			'escaped emoji'                        => array( '\\😍', '😍', '' ),
			'hex unicode codepoint'                => array( '\\1f0a1', '🂡', '' ),
			'HEX UNICODE CODEPOINT'                => array( '\\1D4B2', '𝒲', '' ),

			'hex tab-suffixed 1'                   => array( "\\31\t23", '123', '' ),
			'hex newline-suffixed 1'               => array( "\\31\n23", '123', '' ),
			'hex space-suffixed 1'                 => array( "\\31 23", '123', '' ),
			'hex tab'                              => array( '\\9', "\t", '' ),
			'hex a'                                => array( '\\61 bc', 'abc', '' ),
			'hex a max escape length'              => array( '\\000061bc', 'abc', '' ),

			'out of range replacement min'         => array( '\\110000 ', "\u{fffd}", '' ),
			'out of range replacement max'         => array( '\\ffffff ', "\u{fffd}", '' ),
			'leading surrogate min replacement'    => array( '\\d800 ', "\u{fffd}", '' ),
			'leading surrogate max replacement'    => array( '\\dbff ', "\u{fffd}", '' ),
			'trailing surrogate min replacement'   => array( '\\dc00 ', "\u{fffd}", '' ),
			'trailing surrogate max replacement'   => array( '\\dfff ', "\u{fffd}", '' ),
			'can start with -ident'                => array( '-ident', '-ident', '' ),
			'can start with --anything'            => array( '--anything', '--anything', '' ),
			'can start with ---anything'           => array( '--_anything', '--_anything', '' ),
			'can start with --1anything'           => array( '--1anything', '--1anything', '' ),
			'can start with -\31 23'               => array( '-\31 23', '-123', '' ),
			'can start with --\31 23'              => array( '--\31 23', '--123', '' ),
			'ident ends before ]'                  => array( 'ident]', 'ident', ']' ),

			/*
			 * > EOF
			 * >   This is a parse error. Return U+FFFD REPLACEMENT CHARACTER (�).
			 *
			 * https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
			 */
			'escape at EOF'                        => array( 'foo\\', "foo\u{fffd}", '' ),
			'lone escape at EOF'                   => array( '\\', "\u{fffd}", '' ),
			'hyphen then escape at EOF'            => array( '-\\', "-\u{fffd}", '' ),

			// Identity escapes of multibyte characters, by UTF-8 sequence length.
			'escaped 2-byte character'             => array( "\\\u{FC}z", "\u{FC}z", '' ),
			'escaped 3-byte character'             => array( "\\\u{270F}z", "\u{270F}z", '' ),
			'escaped 4-byte character'             => array( "\\\u{1F0A1}z", "\u{1F0A1}z", '' ),
			'escaped 2-byte character at EOF'      => array( "a\\\u{FC}", "a\u{FC}", '' ),
			'escaped 3-byte character at EOF'      => array( "a\\\u{270F}", "a\u{270F}", '' ),
			'escaped 4-byte character at EOF'      => array( "a\\\u{1F0A1}", "a\u{1F0A1}", '' ),

			/*
			 * An escaped NUL byte passes through this low-level helper unchanged.
			 * This is unreachable through the public selector API, where
			 * normalize_selector_input() replaces NUL with U+FFFD before parsing.
			 */
			'escaped NUL byte'                     => array( "a\\\x00z", "a\x00z", '' ),

			/*
			 * Identity escapes of invalid UTF-8 byte sequences.
			 *
			 * These inputs are not valid UTF-8, which can only reach the parser
			 * through a direct `parse()` call: the public `from_selectors()` API
			 * replaces invalid byte sequences with U+FFFD before parsing. On
			 * this un-normalized path the escape decodes the maximal subpart of
			 * the invalid sequence (CSS Syntax §3.2 via the WHATWG Encoding
			 * Standard) to a single U+FFFD — independent of the
			 * `mb_substitute_character()` setting, which set_up() pins to ☃
			 * precisely to prove that independence. Invalid bytes *after* the
			 * escaped subpart are not escaped; they pass through this low-level
			 * helper raw, exactly as unescaped invalid bytes do (the 0xAF,
			 * 0xA0 0x80, and 0x90 0x80 0x80 tails below).
			 */
			'escaped lone continuation byte'       => array( "a\\\x80z", "a\u{FFFD}z", '' ),
			'escaped overlong lead 0xC0'           => array( "a\\\xC0\xAFz", "a\u{FFFD}\xAFz", '' ),
			'escaped invalid lead 0xF5'            => array( "a\\\xF5z", "a\u{FFFD}z", '' ),
			'escaped truncated 3-byte sequence'    => array( "a\\\xE2\x80z", "a\u{FFFD}z", '' ),
			'escaped truncated 4-byte at EOF'      => array( "a\\\xF0\x9F\x82", "a\u{FFFD}", '' ),
			'escaped UTF-8-encoded surrogate'      => array( "a\\\xED\xA0\x80z", "a\u{FFFD}\xA0\x80z", '' ),
			'escaped sequence above U+10FFFF'      => array( "a\\\xF4\x90\x80\x80z", "a\u{FFFD}\x90\x80\x80z", '' ),

			// Invalid
			'Invalid: (empty string)'              => array( '' ),
			'Invalid: bad start >'                 => array( '>ident' ),
			'Invalid: bad start ['                 => array( '[ident' ),
			'Invalid: bad start #'                 => array( '#ident' ),
			'Invalid: bad start " "'               => array( ' ident' ),
			'Invalid: bad start 1'                 => array( '1ident' ),
			'Invalid: bad start -1'                => array( '-1ident' ),
			'Invalid: bad start -'                 => array( '-' ),
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
	 * The rest-of-input assertion above cannot distinguish an offset at the end
	 * of the input from one past it (`substr()` returns '' for both), so the
	 * offset arithmetic of the invalid-byte decode is pinned explicitly here:
	 * the escape consumes exactly the 1-byte maximal subpart and the following
	 * `z`, leaving the offset at — never past — the end of the input. (The
	 * previous `mb_substr()`-based decode advanced by the byte length of the
	 * substitute character and overran the end by one byte under the ☃ canary.)
	 */
	public function test_parse_ident_escaped_invalid_byte_does_not_overrun_offset() {
		$input  = "a\\\x80z";
		$offset = 0;
		$result = $this->test_class::test_parse_ident( $input, $offset );

		$this->assertSame( "a\u{FFFD}z", $result, 'Ident did not match.' );
		$this->assertSame( strlen( $input ), $offset, 'Offset should stop exactly at the end of input.' );
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
			'"foo"'                            => array( '"foo"', 'foo', '' ),
			'"foo"after'                       => array( '"foo"after', 'foo', 'after' ),
			'"foo""two"'                       => array( '"foo""two"', 'foo', '"two"' ),
			'"foo"\'two\''                     => array( '"foo"\'two\'', 'foo', "'two'" ),

			"'foo'"                            => array( "'foo'", 'foo', '' ),
			"'foo'after"                       => array( "'foo'after", 'foo', 'after' ),
			"'foo'\"two\""                     => array( "'foo'\"two\"", 'foo', '"two"' ),
			"'foo''two'"                       => array( "'foo''two'", 'foo', "'two'" ),

			"'foo\\nbar'"                      => array( "'foo\\\nbar'", 'foobar', '' ),
			"'foo\\31 23'"                     => array( "'foo\\31 23'", 'foo123', '' ),
			"'Ü\\sup'"                         => array( "'Ü\\sup'", 'Üsup', '' ),
			"'foo\\31\\n23'"                   => array( "'foo\\31\n23'", 'foo123', '' ),
			"'foo\\31\\t23'"                   => array( "'foo\\31\t23'", 'foo123', '' ),
			"'foo\\00003123'"                  => array( "'foo\\00003123'", 'foo123', '' ),

			"'foo\\"                           => array( "'foo\\", 'foo', '' ),

			/*
			 * Invalid UTF-8 in string context, reachable only via a direct
			 * parse() call ( from_selectors() scrubs first ): an escaped
			 * invalid byte decodes its maximal subpart to U+FFFD, exactly as
			 * in ident context; raw invalid bytes pass through unexamined.
			 */
			'string with escaped invalid byte' => array( "'a\\\xC0z'", "a\u{FFFD}z", '' ),
			'string with raw invalid byte'     => array( "'a\xC0z'", "a\xC0z", '' ),

			'"'                                => array( '"', '', '' ),
			'"\\"'                             => array( '"\\"', '"', '' ),
			'"missing close'                   => array( '"missing close', 'missing close', '' ),

			// Invalid
			'Invalid: (empty string)'          => array( '' ),
			'Invalid: .foo'                    => array( '.foo' ),
			'Invalid: #foo'                    => array( '#foo' ),
			"Invalid: 'newline\\n'"            => array( "'newline\n'" ),
			'Invalid: foo'                     => array( 'foo' ),
		);
	}
}
