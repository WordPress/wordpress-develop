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
		 * Decoding invalid UTF-8 in identity escapes leaks the process-global
		 * `mb_substitute_character()` setting into parse results. Pin it to a
		 * distinctive character — U+2603 SNOWMAN (☃) — so that any dependence
		 * on the setting is unmistakable in test expectations, rather than a
		 * `?` that looks like an intentional placeholder. The escape-decode
		 * cases below document the leak; a parser that decodes invalid bytes
		 * to U+FFFD per CSS Syntax §3.2 would be unaffected by this setting.
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
			 * These inputs are not valid UTF-8. The escaped invalid byte decodes to
			 * the process-global `mb_substitute_character()` — pinned to U+2603 (☃)
			 * in set_up() to make the dependence visible — and the offset then
			 * advances by the byte length of *the substitute character* (3 bytes
			 * for ☃, 1 byte for the default `?`), not of the invalid sequence.
			 * The expectations below show the damage: following characters are
			 * swallowed (the `z` in most cases) and the offset can even overrun
			 * the end of the input (the lone-continuation and 0xF5 cases end with
			 * the offset one byte past the end; see the dedicated offset test).
			 *
			 * These cases pin the current behavior to document the leak, not to
			 * endorse it. CSS Syntax §3.2 decodes the input byte stream per the
			 * WHATWG Encoding Standard, which replaces each maximal subpart of an
			 * invalid sequence with U+FFFD; when the parser does that, these
			 * expectations flip to U+FFFD outputs that are independent of
			 * `mb_substitute_character()`.
			 */
			'escaped lone continuation byte'       => array( "a\\\x80z", "a\u{2603}", '' ),
			'escaped overlong lead 0xC0'           => array( "a\\\xC0\xAFz", "a\u{2603}", '' ),
			'escaped invalid lead 0xF5'            => array( "a\\\xF5z", "a\u{2603}", '' ),
			'escaped truncated 3-byte sequence'    => array( "a\\\xE2\x80z", "a\u{2603}", '' ),
			'escaped truncated 4-byte at EOF'      => array( "a\\\xF0\x9F\x82", "a\u{2603}", '' ),
			'escaped UTF-8-encoded surrogate'      => array( "a\\\xED\xA0\x80z", "a\u{2603}z", '' ),
			'escaped sequence above U+10FFFF'      => array( "a\\\xF4\x90\x80\x80z", "a\u{2603}\x80z", '' ),

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
	 * offset overrun caused by decoding an escaped invalid byte to a multibyte
	 * substitute character is pinned explicitly here: the 3-byte ☃ advance over
	 * the 1-byte invalid sequence leaves the offset one byte past the end.
	 * Decoding invalid bytes to U+FFFD with maximal-subpart consumption would
	 * turn this case into `"a\u{FFFD}z"` with the offset at the end of input.
	 */
	public function test_parse_ident_escaped_invalid_byte_overruns_offset() {
		$input  = "a\\\x80z";
		$offset = 0;
		$result = $this->test_class::test_parse_ident( $input, $offset );

		$this->assertSame( "a\u{2603}", $result, 'Ident did not match.' );
		$this->assertSame( strlen( $input ) + 1, $offset, 'Offset did not overrun the end of input.' );
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
			"'Ü\\sup'"                => array( "'Ü\\sup'", 'Üsup', '' ),
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
