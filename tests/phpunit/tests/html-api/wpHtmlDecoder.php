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
	 * Ensures semicolonless legacy references decode before non-ASCII UTF-8 bytes in attributes.
	 *
	 * @ticket 65372
	 */
	public function test_semicolonless_legacy_reference_before_multibyte_attribute_follower() {
		$previous_locale = setlocale( LC_CTYPE, 0 );

		$locale_candidates = array(
			'C.UTF-8',
			'C.utf8',
			'en_US.UTF-8',
			'en_US.utf8',
			'en_GB.UTF-8',
			'en_GB.utf8',
			'en_AU.UTF-8',
			'en_AU.utf8',
			'en_CA.UTF-8',
			'en_CA.utf8',
			'en_NZ.UTF-8',
			'en_NZ.utf8',
			'en_IE.UTF-8',
			'en_IE.utf8',
		);

		$affected_locale = false;

		foreach ( $locale_candidates as $locale ) {
			$candidate_locale = setlocale( LC_CTYPE, $locale );

			if ( false !== $candidate_locale && ctype_alnum( "\xC2" ) ) {
				$affected_locale = $candidate_locale;
				break;
			}
		}

		if ( false === $affected_locale ) {
			if ( false !== $previous_locale ) {
				setlocale( LC_CTYPE, $previous_locale );
			}

			$this->markTestSkipped( 'Requires an LC_CTYPE locale where ctype_alnum() classifies high-bit bytes as alphanumeric.' );
		}

		$raw_attribute = "&Aacute\xC2\x80";

		try {
			$this->assertSame(
				"\xC3\x81\xC2\x80",
				WP_HTML_Decoder::decode_attribute( $raw_attribute ),
				'Should have decoded the semicolonless legacy reference before a multibyte follower.'
			);

			$match_byte_length = null;
			$this->assertSame(
				"\xC3\x81",
				WP_HTML_Decoder::read_character_reference( 'attribute', $raw_attribute, 0, $match_byte_length ),
				'Should have matched the semicolonless legacy reference before a multibyte follower.'
			);
			$this->assertSame( strlen( '&Aacute' ), $match_byte_length );
		} finally {
			if ( false !== $previous_locale ) {
				setlocale( LC_CTYPE, $previous_locale );
			}
		}
	}

	/**
	 * Ensures semicolonless legacy references remain ambiguous before ASCII alnum or equals.
	 *
	 * @dataProvider data_ambiguous_ascii_attribute_followers
	 *
	 * @ticket 65372
	 *
	 * @param string $raw_attribute Raw attribute value with an ambiguous legacy reference follower.
	 */
	public function test_semicolonless_legacy_reference_before_ascii_attribute_follower_is_ambiguous( $raw_attribute ) {
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
	 * @return array[].
	 */
	public static function data_ambiguous_ascii_attribute_followers() {
		return array(
			'ASCII digit'           => array( '&Aacute0' ),
			'ASCII uppercase alpha' => array( '&AacuteA' ),
			'ASCII lowercase alpha' => array( '&Aacutea' ),
			'equals'                => array( '&Aacute=' ),
		);
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
}
