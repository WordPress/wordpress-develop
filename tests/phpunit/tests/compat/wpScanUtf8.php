<?php
/**
 * Unit tests covering _wp_scan_utf8().
 *
 * @package    WordPress
 * @subpackage Charset
 *
 * @group      compat
 *
 * @covers ::_wp_scan_utf8
 */
class Tests_Compat_wpScanUtf8 extends WP_UnitTestCase {

	/**
	 * Ensures that valid UTF-8 is scanned to the end, counting every code point.
	 *
	 * @ticket 65817
	 *
	 * @dataProvider data_valid_utf8
	 *
	 * @param string $text Valid UTF-8 text without noncharacters.
	 */
	public function test_counts_code_points_in_valid_utf8( string $text ) {
		$at                = 0;
		$invalid_length    = 0;
		$has_noncharacters = null;

		$count = _wp_scan_utf8( $text, $at, $invalid_length, null, null, $has_noncharacters );

		$this->assertSame( mb_strlen( $text, 'UTF-8' ), $count, 'Should have counted every code point.' );
		$this->assertSame( strlen( $text ), $at, 'Should have scanned to the end of the text.' );
		$this->assertSame( 0, $invalid_length, 'Should not have found any invalid bytes.' );
		$this->assertFalse( $has_noncharacters, 'Should not have found any noncharacters.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_valid_utf8() {
		return array(
			'Empty string'                       => array( '' ),
			'US-ASCII'                           => array( 'Hello, World!' ),
			'Two-byte sequences'                 => array( "Pi\u{00F1}a \u{00E9}t\u{00E9}" ),
			'Three-byte sequences'               => array( "\u{65E5}\u{672C}\u{8A9E}" ),
			'Four-byte sequences'                => array( "\u{1F600}\u{1F170}\u{10348}" ),
			'Mixed sequence lengths'             => array( "a\u{00F1}\u{65E5}\u{1F600}z" ),
			'Boundaries between lengths'         => array( "\u{007F}\u{0080}\u{07FF}\u{0800}\u{FFFD}\u{10000}\u{10FFFD}" ),
			'Combining character'                => array( "e\u{0301}" ),
			'Hangul syllable as conjoining jamo' => array( "\u{1112}\u{1161}\u{11AB}" ),
			'Devanagari grapheme clusters'       => array( "\u{0928}\u{092E}\u{0938}\u{094D}\u{0924}\u{0947}" ),
			'Regional indicator pair (flag)'     => array( "\u{1F1E7}\u{1F1E9}" ),
			'Emoji with skin tone modifier'      => array( "\u{1F44D}\u{1F3FD}" ),
			'Keycap sequence'                    => array( "1\u{FE0F}\u{20E3}" ),
			'ZWJ sequence'                       => array( "\u{1F469}\u{200D}\u{1F4BB}" ),
			'ZWJ family sequence'                => array( "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}" ),
			'ZWJ sequence with VS-16'            => array( "\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}" ),
		);
	}

	/**
	 * Ensures that each maximal subpart of an invalid byte sequence is reported
	 * as its own invalid span, so that it can be replaced by a single U+FFFD.
	 *
	 * @ticket 65817
	 *
	 * @dataProvider data_invalid_utf8
	 *
	 * @param string $text                 Text containing invalid UTF-8 bytes.
	 * @param int[]  $expected_spans       Byte length of each invalid span, in order.
	 * @param int    $expected_code_points Code points when each invalid span counts as one.
	 */
	public function test_finds_maximal_subparts_of_invalid_bytes( string $text, array $expected_spans, int $expected_code_points ) {
		$end            = strlen( $text );
		$at             = 0;
		$invalid_length = 0;
		$invalid_spans  = array();
		$code_points    = 0;

		while ( $at < $end ) {
			$code_points += _wp_scan_utf8( $text, $at, $invalid_length );

			if ( 0 === $invalid_length ) {
				break;
			}

			$invalid_spans[] = $invalid_length;
			++$code_points;
			$at += $invalid_length;
		}

		$this->assertSame( $expected_spans, $invalid_spans, 'Should have found the maximal subpart of each invalid span.' );
		$this->assertSame( $expected_code_points, $code_points, 'Should have counted one code point for each invalid span.' );
		$this->assertSame( $end, $at, 'Should have scanned to the end of the text.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: int[], 2: int}>
	 */
	public static function data_invalid_utf8() {
		return array(
			'Lone continuation byte'               => array( "a\x80b", array( 1 ), 3 ),
			'Run of continuation bytes'            => array( "a\x80\xBF\x80b", array( 1, 1, 1 ), 5 ),
			'Truncated two-byte sequence'          => array( "a\xC3b", array( 1 ), 3 ),
			'Truncated three-byte sequence'        => array( "a\xE2\x82b", array( 2 ), 3 ),
			'Truncated four-byte sequence'         => array( "a\xF0\x9F\x98b", array( 3 ), 3 ),
			'Truncated sequence at the end'        => array( "abc\xF0\x9F\x98", array( 3 ), 4 ),
			'Overlong two-byte sequence'           => array( "a\xC0\xAFb", array( 1, 1 ), 4 ),
			'Overlong three-byte sequence'         => array( "a\xE0\x80\xAFb", array( 1, 1, 1 ), 5 ),
			'UTF-16 surrogate half'                => array( "a\xED\xA0\x80b", array( 1, 1, 1 ), 5 ),
			'Code point above U+10FFFF'            => array( "a\xF4\x90\x80\x80b", array( 1, 1, 1, 1 ), 6 ),
			'Byte that never appears in UTF-8'     => array( "a\xFFb", array( 1 ), 3 ),
			'Windows-1252 encoded text'            => array( "Pi\xF1a", array( 1 ), 4 ),
			'Truncated sequences back to back'     => array( "\xE2\x82\xF0\x9F\x98", array( 2, 3 ), 2 ),
			'Invalid span between valid multibyte' => array( "\u{1F600}\xE2\x82\u{65E5}", array( 2 ), 3 ),
		);
	}

	/**
	 * Tests scanning UTF-8 string containing noncharacters.
	 *
	 * @ticket 65817
	 *
	 * @dataProvider data_strings_with_noncharacters
	 *
	 * @param string $text UTF-8 string containing a noncharacter.
	 */
	public function test_scan_string_with_noncharacters( string $text ) {
		$at                = 0;
		$invalid_length    = 0;
		$has_noncharacters = null;

		_wp_scan_utf8( $text, $at, $invalid_length, null, null, $has_noncharacters );

		$this->assertTrue( $has_noncharacters );
	}

	/**
	 * Data provider for noncharacters.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_strings_with_noncharacters() {
		return array(
			'U+FFFF (3-byte noncharacter)'  => array( "test \xEF\xBF\xBF end" ),
			'U+FFFE (3-byte noncharacter)'  => array( "test \xEF\xBF\xBE end" ),
			'U+FDD0 (3-byte noncharacter)'  => array( "test \xEF\xB7\x90 end" ),
			'U+1FFFF (4-byte noncharacter)' => array( "test \xF0\x9F\xBF\xBF end" ),
		);
	}
}
