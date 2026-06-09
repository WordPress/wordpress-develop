<?php
/**
 * Unit tests covering WordPress’ UTF-8 handling: noncharacter detection.
 *
 * @package WordPress
 * @group unicode
 */

class Tests_WpHasNoncharacters extends WP_UnitTestCase {
	/**
	 * Ensures that a noncharacter inside a string will be properly detected.
	 *
	 * @ticket 63863
	 *
	 * @dataProvider data_noncharacters
	 *
	 * @param string $noncharacter Noncharacter as a UTF-8 string.
	 */
	public function test_detects_non_characters( string $noncharacter ) {
		$this->assertTrue(
			wp_has_noncharacters( $noncharacter ),
			'Failed to detect entire string as noncharacter.'
		);

		$this->assertTrue(
			wp_has_noncharacters( "{$noncharacter} and more." ),
			'Failed to detect noncharacter prefix.'
		);

		$this->assertTrue(
			wp_has_noncharacters( "Some text and then a {$noncharacter} and more." ),
			'Failed to detect medial noncharacter.'
		);

		$this->assertTrue(
			wp_has_noncharacters( "Some text and a {$noncharacter}." ),
			'Failed to detect noncharacter suffix.'
		);
	}

	/**
	 * Ensures that a noncharacter inside a string will be properly detected
	 * using the fallback function when Unicode PCRE support is missing.
	 *
	 * @ticket 63863
	 *
	 * @dataProvider data_noncharacters
	 *
	 * @param string $noncharacter Noncharacter as a UTF-8 string.
	 */
	public function test_fallback_detects_non_characters( string $noncharacter ) {
		$this->assertTrue(
			_wp_has_noncharacters_fallback( $noncharacter ),
			'Failed to detect entire string as noncharacter.'
		);

		$this->assertTrue(
			_wp_has_noncharacters_fallback( "{$noncharacter} and more." ),
			'Failed to detect noncharacter prefix.'
		);

		$this->assertTrue(
			_wp_has_noncharacters_fallback( "Some text and then a {$noncharacter} and more." ),
			'Failed to detect medial noncharacter.'
		);

		$this->assertTrue(
			_wp_has_noncharacters_fallback( "Some text and a {$noncharacter}." ),
			'Failed to detect noncharacter suffix.'
		);
	}

	/**
	 * Ensures that Unicode characters are not falsely detect as noncharacters.
	 *
	 * @ticket 63863
	 */
	public function test_avoids_false_positives() {
		// Get all the noncharacters in one long string, each surrounded on both sides by null bytes.
		$noncharacters = implode(
			"\x00",
			array_map(
				static function ( $c ) {
					return "\x00{$c}";
				},
				array_column( array_values( iterator_to_array( self::data_noncharacters() ) ), 0 )
			)
		) . "\x00";

		$this->assertFalse(
			wp_has_noncharacters( "\x00" ),
			'Falsely detected noncharacter in U+0000'
		);

		for ( $code_point = 1; $code_point <= 0x10FFFF; $code_point++ ) {
			// Surrogate halves are invalid UTF-8.
			if ( $code_point >= 0xD800 && $code_point <= 0xDFFF ) {
				continue;
			}

			$char     = mb_chr( $code_point );
			$hex_char = strtoupper( str_pad( dechex( $code_point ), 4, '0', STR_PAD_LEFT ) );

			if ( str_contains( $noncharacters, $char ) ) {
				$this->assertTrue(
					wp_has_noncharacters( $char ),
					"Failed to detect noncharacter as test verification for U+{$hex_char}"
				);
			} else {
				$this->assertFalse(
					wp_has_noncharacters( $char ),
					"Falsely detected noncharacter in U+{$hex_char}."
				);
			}
		}
	}

	/**
	 * Ensures that Unicode characters are not falsely detect as noncharacters
	 * using the fallback function when Unicode PCRE support is missing.
	 *
	 * @ticket 63863
	 */
	public function test_fallback_avoids_false_positives() {
		// Get all the noncharacters in one long string, each surrounded on both sides by null bytes.
		$noncharacters = implode(
			"\x00",
			array_map(
				static function ( $c ) {
					return "\x00{$c}";
				},
				array_column( array_values( iterator_to_array( self::data_noncharacters() ) ), 0 )
			)
		) . "\x00";

		$this->assertFalse(
			_wp_has_noncharacters_fallback( "\x00" ),
			'Falsely detected noncharacter in U+0000'
		);

		for ( $code_point = 1; $code_point <= 0x10FFFF; $code_point++ ) {
			// Surrogate halves are invalid UTF-8.
			if ( $code_point >= 0xD800 && $code_point <= 0xDFFF ) {
				continue;
			}

			$char     = mb_chr( $code_point );
			$hex_char = strtoupper( str_pad( dechex( $code_point ), 4, '0', STR_PAD_LEFT ) );

			if ( str_contains( $noncharacters, $char ) ) {
				$this->assertTrue(
					_wp_has_noncharacters_fallback( $char ),
					"Failed to detect noncharacter as test verification for U+{$hex_char}"
				);
			} else {
				$this->assertFalse(
					_wp_has_noncharacters_fallback( $char ),
					"Falsely detected noncharacter in U+{$hex_char}."
				);
			}
		}
	}

	/**
	 * Data provider
	 *
	 * @return array[]
	 */
	public static function data_noncharacters() {
		for ( $code_point = 0xFDD0; $code_point <= 0xFDEF; $code_point++ ) {
			$hex_char = strtoupper( str_pad( dechex( $code_point ), 4, '0', STR_PAD_LEFT ) );
			yield "U+{$hex_char}" => array( mb_chr( $code_point ) );
		}

		yield 'U+FFFE' => array( "\u{FFFE}" );
		yield 'U+FFFF' => array( "\u{FFFF}" );

		for ( $plane = 0x10000; $plane <= 0x10FFFF; $plane += 0x10000 ) {
			$code_point = $plane + 0xFFFE;
			$hex_char   = strtoupper( str_pad( dechex( $code_point ), 4, '0', STR_PAD_LEFT ) );
			yield "U+{$hex_char}" => array( mb_chr( $code_point ) );

			$code_point = $plane + 0xFFFF;
			$hex_char   = strtoupper( str_pad( dechex( $code_point ), 4, '0', STR_PAD_LEFT ) );
			yield "U+{$hex_char}" => array( mb_chr( $code_point ) );
		}
	}
}
