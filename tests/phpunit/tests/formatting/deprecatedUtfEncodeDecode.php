<?php

/**
 * @group formatting
 */
class Tests_DeprecatedUtf8EncodeDecodeTest extends WP_UnitTestCase {
	/**
	 * Ensures that the fallback for {@see \utf8_encode()} maps the ISO-8859-1 characters properly.
	 *
	 * @ticket 63863.
	 */
	public function test_utf8_encode_characters() {
		for ( $i = 0; $i <= 0xFF; $i++ ) {
			$c     = chr( $i );
			$hex_i = strtoupper( str_pad( dechex( $i ), 2, '0', STR_PAD_LEFT ) );

			$this->assertSame(
				bin2hex( mb_convert_encoding( $c, 'UTF-8', 'ISO-8859-1' ) ),
				bin2hex( _wp_utf8_encode_fallback( $c ) ),
				"Failed to convert U+{$hex_i} properly."
			);
		}
	}

	/**
	 * Ensures that the fallback for {@see \utf8_encode()} properly
	 * matches the legacy behavior for a given set of test cases.
	 *
	 * @ticket 63863.
	 *
	 * @dataProvider data_utf8_strings
	 */
	public function test_utf8_encode_cases( $input ) {
		$this->assertSame(
			mb_convert_encoding( $input, 'UTF-8', 'ISO-8859-1' ),
			_wp_utf8_encode_fallback( $input ),
			'Failed to properly convert.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_utf8_strings() {
		return array(
			'Basic valid string' => array( 'Dan eats cinnamon toast.' ),
			'Valid with Emoji'   => array( 'The best Emoji is 🅰.' ),
			'Truncated bytes'    => array( substr( 'England has 🏴󠁧󠁢󠁥󠁮󠁧󠁿', 0, -1 ) ),
			'Minimal subpart'    => array( "One \xC0, two \xE2\x80, three \xF0\x95\x85." ),
		);
	}

	/**
	 * Ensures that the fallback for {@see \utf8_decode()} maps the UTF-8 characters properly.
	 *
	 * @ticket 63863.
	 */
	public function test_utf8_decode_characters() {
		for ( $i = 0; $i <= 0x10FFFF; $i++ ) {
			$hex_i = strtoupper( str_pad( dechex( $i ), 2, '0', STR_PAD_LEFT ) );

			if ( $i < 0xD800 || $i > 0xE000 ) {
				$c = mb_chr( $i );
			} else {
				/*
				 * Since the UTF-16 surrogate halves are not valid Unicode characters,
				 * these have to be manually constructed as invalid UTF-8.
				 */
				$byte1 = 0xE0 | ( $i >> 12 );
				$byte2 = 0x80 | ( ( $i >> 6 ) & 0x3F );
				$byte3 = 0x80 | ( $i & 0x3F );

				$c = "{$byte1}{$byte2}{$byte3}";
			}

			$this->assertSame(
				bin2hex( mb_convert_encoding( $c, 'ISO-8859-1', 'UTF-8' ) ),
				bin2hex( _wp_utf8_decode_fallback( $c ) ),
				"Failed to convert U+{$hex_i} properly."
			);
		}
	}

	/**
	 * Ensures that the fallback for {@see \utf8_encode()} properly
	 * matches the legacy behavior for a given set of test cases.
	 *
	 * @ticket 63863.
	 *
	 * @dataProvider data_iso_8859_1_strings
	 */
	public function test_utf8_decode_cases( $input ) {
		$this->assertSame(
			mb_convert_encoding( $input, 'ISO-8859-1', 'UTF-8' ),
			_wp_utf8_decode_fallback( $input ),
			'Failed to properly convert.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_iso_8859_1_strings() {
		return array(
			'Basic valid string'     => array( 'Dan eats cinnamon toast' ),
			'Latin1 supplement'      => array( 'Pi\xF1a is another name for Pineapple.' ),
			'Bytes as invalid UTF-8' => array( 'The \x95 is invalid UTF-8.' ),
		);
	}
}
