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
			'NULL byte'        => array( "\0", "\0" ),
			'Unknown entity'   => array( '&unknown;', '&unknown;' ),
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
