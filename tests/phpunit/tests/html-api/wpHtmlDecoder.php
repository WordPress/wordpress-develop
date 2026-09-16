<?php
/**
 * Unit tests covering WP_HTML_Decoder functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Decoder
 */
class Tests_HtmlApi_WpHtmlDecoder extends WP_UnitTestCase {
	/**
	 * Original LC_CTYPE locale.
	 *
	 * @var string|bool
	 */
	private static $original_lc_ctype = false;

	/**
	 * Locale where ctype_alnum() classifies high-bit bytes as alphanumeric.
	 *
	 * @var string|null
	 */
	private static ?string $problematic_lc_ctype = null;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$original_lc_ctype = setlocale( LC_CTYPE, 0 );

		// Find a locale where ctype_alnum() classifies high-bit bytes as alphanumeric.
		$locale_candidates = array(
			'C.UTF-8',
			'C.utf8',
			'en_US.UTF-8',
			'en_US.utf8',
			'en_GB.UTF-8',
			'en_GB.utf8',
		);
		foreach ( $locale_candidates as $locale ) {
			$candidate_locale = setlocale( LC_CTYPE, $locale );

			if ( false !== $candidate_locale && ctype_alnum( "\xC2" ) ) {
				self::$problematic_lc_ctype = $candidate_locale;
				break;
			}
		}

		if ( self::$original_lc_ctype ) {
			setlocale( LC_CTYPE, self::$original_lc_ctype );
		}
	}

	public function tear_down() {
		if ( self::$original_lc_ctype ) {
			setlocale( LC_CTYPE, self::$original_lc_ctype );
		}
		parent::tear_down();
	}

	/**
	 * Ensures proper decoding of edge cases.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_edge_cases
	 *
	 * @param $raw_text_node Raw input text.
	 * @param $decoded_value The expected decoded text result.
	 */
	public function test_edge_cases( $raw_text_node, $decoded_value ) {
		$this->assertSame(
			$decoded_value,
			WP_HTML_Decoder::decode_text_node( $raw_text_node ),
			'Improperly decoded raw text node.'
		);
	}

	public static function data_edge_cases() {
		return array(
			'Single ampersand' => array( '&', '&' ),
			'NULL byte'        => array( "\0", "\0" ),
			'Unknown entity'   => array( '&unknown;', '&unknown;' ),
		);
	}

	/**
	 * Ensures that character references followed by NULL bytes do not emit native PHP errors.
	 *
	 * @ticket 65372
	 */
	public function test_character_reference_with_null_byte_does_not_emit_native_errors() {
		$errors = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ) {
				$errors[] = "{$errno}: {$errstr}";
				return true;
			}
		);

		try {
			$decoded = WP_HTML_Decoder::decode_text_node( "&\x00b" );
		} finally {
			restore_error_handler();
		}

		// Use assertSame() instead of assertEmpty() so PHPUnit shows captured error messages on failure.
		$this->assertSame( array(), $errors );
		$this->assertSame( "&\x00b", $decoded, 'Should have decoded the text without changing it.' );
	}

	/**
	 * Ensures that numeric character references for U+0000 decode to U+FFFD
	 * while raw NULL bytes pass through the decoder untransformed.
	 *
	 * The tokenizer, not the decoder, is responsible for replacing raw NULL
	 * bytes; in the Tag Processor that responsibility falls on the methods
	 * which read values out of the input document.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_null_code_points
	 *
	 * @param string $raw_value     Raw attribute value.
	 * @param string $decoded_value The expected decoded attribute value.
	 */
	public function test_null_code_points_in_attribute_values( string $raw_value, string $decoded_value ): void {
		$this->assertSame(
			$decoded_value,
			WP_HTML_Decoder::decode_attribute( $raw_value ),
			'Improperly decoded raw attribute value.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function data_null_code_points(): array {
		return array(
			'Decimal zero'                 => array( 'a&#0;b', "a\u{FFFD}b" ),
			'Hexadecimal zero'             => array( 'a&#x0;b', "a\u{FFFD}b" ),
			'Multiple zeros'               => array( 'a&#0000;b', "a\u{FFFD}b" ),
			'Raw NULL byte passes through' => array( "a\x00b", "a\x00b" ),
		);
	}

	/**
	 * Ensures unmatched named character references leave the by-ref match length unchanged.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_unmatched_named_character_references
	 *
	 * @param string $context       Decoder context.
	 * @param string $raw_text_node Raw text containing an unmatched named character reference.
	 */
	public function test_unmatched_named_character_reference_does_not_set_match_byte_length( $context, $raw_text_node ): void {
		$match_byte_length = 'sentinel';
		$this->assertNull(
			WP_HTML_Decoder::read_character_reference( $context, $raw_text_node, 0, $match_byte_length ),
			'Should not have matched an unmatched named character reference.'
		);
		$this->assertSame( 'sentinel', $match_byte_length );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string, string}>.
	 */
	public static function data_unmatched_named_character_references(): array {
		return array(
			'text invalid name'                      => array( 'data', '&bogus;' ),
			'text invalid short-name candidate'      => array( 'data', '&Fv=q' ),
			'attribute invalid name'                 => array( 'attribute', '&bogus;' ),
			'attribute invalid short-name candidate' => array( 'attribute', '&Fv=q' ),
		);
	}

	/**
	 * Ensures semicolonless legacy references decode before non-ASCII UTF-8 bytes in attributes.
	 *
	 * @dataProvider data_semicolonless_attribute_behaviors
	 *
	 * @ticket 65372
	 */
	public function test_semicolonless_legacy_reference_before_multibyte_attribute_follower( string $encoded_attribute_value, string $expected, string $expected_decode, int $expected_byte_length ): void {
		if ( null !== self::$problematic_lc_ctype ) {
			setlocale( LC_CTYPE, self::$problematic_lc_ctype );
		}

		$this->assertSame(
			$expected,
			WP_HTML_Decoder::decode_attribute( $encoded_attribute_value ),
			'Failed to decode the full attribute value as expected.'
		);

		$match_byte_length = null;
		$this->assertSame(
			$expected_decode,
			WP_HTML_Decoder::read_character_reference( 'attribute', $encoded_attribute_value, 0, $match_byte_length ),
			'Failed to decode the character reference as expected.'
		);
		$this->assertSame( $expected_byte_length, $match_byte_length, 'Failed to produce expected byte length.' );
	}

	/**
	 * Data provider.
	 *
	 * Attribute values encoded with character references including followers that are
	 * treated as alphanumerics by `ctype_alnum()` on some systems, but should never
	 * be recognized as ASCII Alphanumerics according to the HTML standards.
	 *
	 * @see https://html.spec.whatwg.org/#named-character-reference-state
	 *
	 * @return array<array{
	 *   string, // Encoded attribute value.
	 *   string, // Expected full decode.
	 *   string, // Expected character decode.
	 *   int,    // Replaced character reference byte length.
	 * }> Test cases.
	 */
	public static function data_semicolonless_attribute_behaviors(): array {
		return array(
			array( '&copy¯\_(ツ)_/¯', '©¯\_(ツ)_/¯', '©', 5 ),
			array( '&notಠ_ಠ', '¬ಠ_ಠ', '¬', 4 ),
			array( '&nbsp£20', "\u{00A0}£20", "\u{00A0}", 5 ),
			array( '&nbsp🎉', "\u{00A0}🎉", "\u{00A0}", 5 ),
			array( '&reg™', '®™', '®', 4 ),
		);
	}

	/**
	 * Ensures ambiguous ampersand is recognized with trailing ASCII alphanumerics.
	 *
	 * @dataProvider data_semicolonless_attribute_character_reference_no_decode_followers
	 *
	 * @ticket 65372
	 *
	 * @param string $raw_attribute Raw attribute value with an ambiguous legacy reference follower.
	 */
	public function test_ascii_alphanumeric_attribute_follower_is_ambiguous( string $raw_attribute ): void {
		$this->assertSame(
			$raw_attribute,
			WP_HTML_Decoder::decode_attribute( $raw_attribute ),
			'Should not have decoded an ambiguous semicolonless legacy reference.'
		);

		$match_byte_length = 'sentinel';
		$this->assertNull(
			WP_HTML_Decoder::read_character_reference( 'attribute', $raw_attribute, 0, $match_byte_length ),
			'Should not have matched an ambiguous semicolonless legacy reference.'
		);
		$this->assertSame( 'sentinel', $match_byte_length );
	}

	/**
	 * Data provider.
	 *
	 * HTML character references with followers that trigger the literal flush behavior
	 * when parsing attribute values. HTML defines this as `=` or an ASCII alphanumeric character.
	 *
	 * > An ASCII alphanumeric is an ASCII digit or ASCII alpha.
	 * > An ASCII alpha is an ASCII upper alpha or ASCII lower alpha.
	 *
	 * @see https://html.spec.whatwg.org/#named-character-reference-state
	 *
	 * @return Generator<string, array{ string }> Test cases.
	 */
	public static function data_semicolonless_attribute_character_reference_no_decode_followers(): Generator {
		yield "Equals sign follower '='" => array( '&Aacute=' );
		// > An ASCII digit is a code point in the range U+0030 (0) to U+0039 (9), inclusive.
		for ( $i = 0x30; $i <= 0x39; $i++ ) {
			$char = chr( $i );
			yield "ASCII digit follower '{$char}'" => array( "&Aacute{$char}" );
		}
		// > An ASCII upper alpha is a code point in the range U+0041 (A) to U+005A (Z), inclusive.
		for ( $i = 0x41; $i <= 0x5A; $i++ ) {
			$char = chr( $i );
			yield "ASCII upper alpha follower '{$char}'" => array( "&Aacute{$char}" );
		}
		// > An ASCII lower alpha is a code point in the range U+0061 (a) to U+007A (z), inclusive.
		for ( $i = 0x61; $i <= 0x7A; $i++ ) {
			$char = chr( $i );
			yield "ASCII lower alpha follower '{$char}'" => array( "&Aacute{$char}" );
		}
	}

	/**
	 * Ensures proper detection of attribute prefixes ignoring ASCII case.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_case_variants_of_attribute_prefixes
	 *
	 * @param string $attribute_value Raw attribute value from HTML string.
	 * @param string $search_string   Prefix contained in encoded attribute value.
	 */
	public function test_detects_ascii_case_insensitive_attribute_prefixes( $attribute_value, $search_string ) {
		$this->assertTrue(
			WP_HTML_Decoder::attribute_starts_with( $attribute_value, $search_string, 'ascii-case-insensitive' ),
			"Should have found that '{$attribute_value}' starts with '{$search_string}'"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return Generator.
	 */
	public static function data_case_variants_of_attribute_prefixes() {
		$with_javascript_prefix = array(
			'javascript:',
			'JAVASCRIPT:',
			'&#106;avascript:',
			'&#x6A;avascript:',
			'&#X6A;avascript:',
			'&#X6A;avascript&colon;',
			'javascript:alert(1)',
			'JaVaScRiPt:alert(1)',
			'javascript:alert(1);',
			'javascript&#58;alert(1);',
			'javascript&#0058;alert(1);',
			'javascript&#0000058alert(1);',
			'javascript&#x3A;alert(1);',
			'javascript&#X3A;alert(1);',
			'javascript&#X3a;alert(1);',
			'javascript&#x3a;alert(1);',
			'javascript&#x003a;alert(1);',
			'&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29',
			'javascript:javascript:alert(1);',
			'javascript&#58;javascript:alert(1);',
			'javascript&#0000058javascript:alert(1);',
			'javascript:javascript&#58;alert(1);',
			'javascript:javascript&#0000058alert(1);',
			'javascript&#0000058alert(1)//?:',
			'javascript&#58alert(1)',
			'javascript&#x3ax=1;alert(1)',
		);

		foreach ( $with_javascript_prefix as $attribute_value ) {
			yield $attribute_value => array( $attribute_value, 'javascript:' );
		}
	}

	/**
	 * Ensures that `attribute_starts_with` checks the full search string.
	 *
	 * @ticket 65372
	 *
	 * @dataProvider data_attribute_starts_with_search_string_boundaries
	 *
	 * @param string $attribute_value  Raw attribute value from HTML string.
	 * @param string $search_string    Prefix contained or not contained in encoded attribute value.
	 * @param string $case_sensitivity Whether to search with ASCII case sensitivity;
	 *                                 'ascii-case-insensitive' or 'case-sensitive'.
	 * @param bool   $is_match         Whether the search string is a prefix for the attribute value.
	 */
	public function test_attribute_starts_with_checks_search_string_boundaries(
		string $attribute_value,
		string $search_string,
		string $case_sensitivity,
		bool $is_match
	): void {
		if ( $is_match ) {
			$this->assertTrue(
				WP_HTML_Decoder::attribute_starts_with( $attribute_value, $search_string, $case_sensitivity ),
				'Should have matched attribute prefix.'
			);
		} else {
			$this->assertFalse(
				WP_HTML_Decoder::attribute_starts_with( $attribute_value, $search_string, $case_sensitivity ),
				'Should not have matched attribute with prefix.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return Generator<string, array{string, string, string, bool}> Test cases.
	 */
	public static function data_attribute_starts_with_search_string_boundaries(): Generator {
		yield 'Empty attribute does not match non-empty prefix' => array( '', 'http', 'case-sensitive', false );
		yield 'Short attribute does not match longer prefix' => array(
			'java',
			'javascript',
			'case-sensitive',
			false,
		);
		yield 'Attribute ending in a character reference does not match a longer prefix' => array(
			'&amp;',
			'&&',
			'case-sensitive',
			false,
		);
		yield 'Longer attribute matches shorter prefix' => array(
			'javascript',
			'java',
			'case-sensitive',
			true,
		);
		yield "&fjlig; (decodes to 2-codepoint 'fj') starts with f" => array(
			'&fjlig; is literally "f" followed by "j"',
			'f',
			'case-sensitive',
			true,
		);
		yield "&nvlt; (decodes to 2-codepoint '<⃒') starts with '<'" => array(
			'&nvlt;script>',
			'<',
			'case-sensitive',
			true,
		);
		yield "Combining character references (¬̸) full match on '¬̸' prefix" => array(
			'&not;&#x338; A negated not?',
			'¬̸',
			'case-sensitive',
			true,
		);
		yield "Combining character references (¬̸) partial match on '¬' prefix" => array(
			'&not;&#x338; A negated not?',
			'¬',
			'case-sensitive',
			true,
		);
		yield 'Search A: prefix continues past a decoded character reference' => array(
			'start&fjlig;ord',
			'startfjord',
			'case-sensitive',
			true,
		);
		yield 'Search B: prefix ends part-way through a decoded character reference' => array(
			'start&fjlig;ord',
			'startf',
			'case-sensitive',
			true,
		);
		yield 'Search C: prefix mismatches within a decoded character reference' => array(
			'start&fjlig;ord',
			'startfr',
			'case-sensitive',
			false,
		);
		yield 'ASCII-case-insensitive prefix ends part-way through a decoded character reference' => array(
			'start&fjlig;ord',
			'STARTF',
			'ascii-case-insensitive',
			true,
		);
		yield 'ASCII-case-insensitive prefix mismatches within a decoded character reference' => array(
			'start&fjlig;ord',
			'STARTFR',
			'ascii-case-insensitive',
			false,
		);
	}

	/**
	 * Ensures that `attribute_starts_with` respects the case sensitivity argument.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_attributes_with_prefix_and_case_sensitive_match
	 *
	 * @param string $attribute_value  Raw attribute value from HTML string.
	 * @param string $search_string    Prefix contained or not contained in encoded attribute value.
	 * @param string $case_sensitivity Whether to search with ASCII case sensitivity;
	 *                                 'ascii-case-insensitive' or 'case-sensitive'.
	 * @param bool   $is_match         Whether the search string is a prefix for the attribute value,
	 *                                 given the case sensitivity setting.
	 */
	public function test_attribute_starts_with_heeds_case_sensitivity( $attribute_value, $search_string, $case_sensitivity, $is_match ) {
		if ( $is_match ) {
			$this->assertTrue(
				WP_HTML_Decoder::attribute_starts_with( $attribute_value, $search_string, $case_sensitivity ),
				'Should have found attribute prefix with case-sensitive search.'
			);
		} else {
			$this->assertFalse(
				WP_HTML_Decoder::attribute_starts_with( $attribute_value, $search_string, $case_sensitivity ),
				'Should not have matched attribute with prefix with ASCII-case-insensitive search.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_attributes_with_prefix_and_case_sensitive_match() {
		return array(
			array( 'http://wordpress.org', 'http', 'case-sensitive', true ),
			array( 'http://wordpress.org', 'http', 'ascii-case-insensitive', true ),
			array( 'http://wordpress.org', 'HTTP', 'case-sensitive', false ),
			array( 'http://wordpress.org', 'HTTP', 'ascii-case-insensitive', true ),
			array( 'http://wordpress.org', 'Http', 'case-sensitive', false ),
			array( 'http://wordpress.org', 'Http', 'ascii-case-insensitive', true ),
			array( 'http://wordpress.org', 'https', 'case-sensitive', false ),
			array( 'http://wordpress.org', 'https', 'ascii-case-insensitive', false ),
		);
	}

	/**
	 * Ensures strict decoding of named entities in attributes.
	 *
	 * @ticket 61072
	 */
	public function test_decode_attribute_decodes_named_entities() {
		$this->assertSame( '&', WP_HTML_Decoder::decode_attribute( '&amp;' ) );
		$this->assertSame( '&', WP_HTML_Decoder::decode_attribute( '&amp' ) );
		$this->assertSame( '<', WP_HTML_Decoder::decode_attribute( '&lt;' ) );
		$this->assertSame( '<', WP_HTML_Decoder::decode_attribute( '&lt' ) );
		$this->assertSame( '>', WP_HTML_Decoder::decode_attribute( '&gt;' ) );
		$this->assertSame( '>', WP_HTML_Decoder::decode_attribute( '&gt' ) );
		$this->assertSame( '"', WP_HTML_Decoder::decode_attribute( '&quot;' ) );
		$this->assertSame( '"', WP_HTML_Decoder::decode_attribute( '&quot' ) );
		$this->assertSame( '©', WP_HTML_Decoder::decode_attribute( '&copy;' ) );
		$this->assertSame( '©', WP_HTML_Decoder::decode_attribute( '&copy' ) );
	}

	/**
	 * Ensures strict decoding of decimal numeric entities.
	 *
	 * @ticket 61072
	 */
	public function test_decode_attribute_decodes_decimal_numeric_entities() {
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#65;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#065;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#000065;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#65' ) );
	}

	/**
	 * Ensures strict decoding of hex numeric entities.
	 *
	 * @ticket 61072
	 */
	public function test_decode_attribute_decodes_hex_numeric_entities() {
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#x41;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#x041;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#x000041;' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#x41' ) );
		$this->assertSame( 'A', WP_HTML_Decoder::decode_attribute( '&#X41;' ) );
		$this->assertSame( '😀', WP_HTML_Decoder::decode_attribute( '&#x1F600;' ) );
	}

	/**
	 * Ensures that Windows-1252 mapped characters are properly decoded.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_windows_1252_mapped_characters
	 *
	 * @param string $raw_text Raw numeric character reference.
	 * @param string $expected Expected decoded character.
	 */
	public function test_decodes_windows_1252_mapped_characters( $raw_text, $expected ) {
		$this->assertSame( $expected, WP_HTML_Decoder::decode_text_node( $raw_text ) );
		$this->assertSame( $expected, WP_HTML_Decoder::decode_attribute( $raw_text ) );
	}

	/**
	 * Data provider for Windows-1252 mapped characters.
	 *
	 * @return array[]
	 */
	public static function data_windows_1252_mapped_characters() {
		return array(
			'Euro sign'        => array( '&#x80;', '€' ),
			'Single low-9'     => array( '&#x82;', '‚' ),
			'F with hook'      => array( '&#x83;', 'ƒ' ),
			'Double low-9'     => array( '&#x84;', '„' ),
			'Ellipsis'         => array( '&#x85;', '…' ),
			'Dagger'           => array( '&#x86;', '†' ),
			'Double dagger'    => array( '&#x87;', '‡' ),
			'Circumflex'       => array( '&#x88;', 'ˆ' ),
			'Per mille'        => array( '&#x89;', '‰' ),
			'S with caron'     => array( '&#x8A;', 'Š' ),
			'Less single guil' => array( '&#x8B;', '‹' ),
			'OE ligature'      => array( '&#x8C;', 'Œ' ),
			'Z with caron'     => array( '&#x8E;', 'Ž' ),
			'Left single quot' => array( '&#x91;', '‘' ),
			'Right single quo' => array( '&#x92;', '’' ),
			'Left double quot' => array( '&#x93;', '“' ),
			'Right double quo' => array( '&#x94;', '”' ),
			'Bullet'           => array( '&#x95;', '•' ),
			'En dash'          => array( '&#x96;', '–' ),
			'Em dash'          => array( '&#x97;', '—' ),
			'Small tilde'      => array( '&#x98;', '˜' ),
			'Trade mark'       => array( '&#x99;', '™' ),
			's with caron'     => array( '&#x9A;', 'š' ),
			'Right single gui' => array( '&#x9B;', '›' ),
			'oe ligature'      => array( '&#x9C;', 'œ' ),
			'z with caron'     => array( '&#x9E;', 'ž' ),
			'Y with diaeresis' => array( '&#x9F;', 'Ÿ' ),
		);
	}

	/**
	 * Ensures decoding of invalid and special numeric character references.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_invalid_numeric_references
	 *
	 * @param string $raw_text Raw numeric character reference.
	 * @param string $expected Expected decoded string.
	 */
	public function test_decodes_invalid_numeric_references( $raw_text, $expected ) {
		$this->assertSame( $expected, WP_HTML_Decoder::decode_text_node( $raw_text ) );
	}

	/**
	 * Data provider for invalid numeric references.
	 *
	 * @return array[]
	 */
	public static function data_invalid_numeric_references() {
		$replacement = "\xEF\xBF\xBD";
		return array(
			'Null byte'             => array( '&#0;', $replacement ),
			'Null byte (hex)'       => array( '&#x00;', $replacement ),
			'Surrogate low'         => array( '&#xD800;', $replacement ),
			'Surrogate mid'         => array( '&#xDABC;', $replacement ),
			'Surrogate high'        => array( '&#xDFFF;', $replacement ),
			'Out of range'          => array( '&#x110000;', $replacement ),
			'No digits'             => array( '&#;', '&#;' ),
			'No digits (hex)'       => array( '&#x;', '&#x;' ),
			'Too many digits'       => array( '&#12345678;', $replacement ), // Limit is 7.
			'Too many digits (hex)' => array( '&#x10FFFFF;', $replacement ), // Limit is 6.
			'Only zeros'            => array( '&#0000;', $replacement ),
		);
	}

	/**
	 * Ensures proper decoding of ambiguous ampersands.
	 *
	 * @ticket 61072
	 *
	 * @dataProvider data_ambiguous_ampersands
	 *
	 * @param string $context  'attribute' or 'data'.
	 * @param string $raw_text Raw text.
	 * @param string $expected Expected decoded string.
	 */
	public function test_decodes_ambiguous_ampersands( $context, $raw_text, $expected ) {
		$this->assertSame( $expected, WP_HTML_Decoder::decode( $context, $raw_text ) );
	}

	/**
	 * Data provider for ambiguous ampersands.
	 *
	 * @return array[]
	 */
	public static function data_ambiguous_ampersands() {
		return array(
			'Starting with logical AND'           => array( 'data', '&amp', '&' ),
			'Starting with logical AND (attr)'    => array( 'attribute', '&amp', '&' ),
			'Ambiguous with equals'               => array( 'data', '&not=', '¬=' ),
			'Ambiguous with equals (attr)'        => array( 'attribute', '&not=', '&not=' ),
			'Ambiguous with alphanumeric'         => array( 'data', '&notit', '¬it' ),
			'Ambiguous with alphanumeric (attr)'  => array( 'attribute', '&notit', '&notit' ),
			'Not ambiguous (semicolon)'           => array( 'data', '&not;', '¬' ),
			'Not ambiguous (semicolon) (attr)'    => array( 'attribute', '&not;', '¬' ),
			'Not ambiguous (non-alphanum)'        => array( 'data', '&not ', '¬ ' ),
			'Not ambiguous (non-alphanum) (attr)' => array( 'attribute', '&not ', '¬ ' ),
		);
	}
}
