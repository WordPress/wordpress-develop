<?php
/**
 * Unit tests covering fallback UTF-8 code-point counting.
 *
 * @package    WordPress
 * @subpackage Charset
 *
 * @since      6.9.0
 *
 * @group      compat
 *
 * @covers ::_wp_utf8_codepoint_count()
 */
class Tests_Compat_wpUtf8CodePointCount extends WP_UnitTestCase {
	/**
	 * Ensures that there are zero code points reported when starting before the text.
	 *
	 * @ticket 63863
	 */
	public function test_rejects_negative_byte_offsets() {
		$this->assertSame(
			0,
			_wp_utf8_codepoint_count( 'any old text', -5, 3 ),
			'Should have indicated that there are zero code points before the start of the text.'
		);

		$this->assertSame(
			0,
			_wp_utf8_codepoint_count( 'any old text', -5, 5 + 12 ),
			'Should have found no code points before the start of the text, even if the length overlaps the text.'
		);
	}

	/**
	 * Ensures that there are zero code points reported when scanning a negative length.
	 *
	 * @ticket 63863
	 */
	public function test_rejects_negative_byte_lengths() {
		$this->assertSame(
			0,
			_wp_utf8_codepoint_count( 'any old text', 2, -5 ),
			'Should have indicated that there are zero code points in a span of negative length.'
		);
	}

	/**
	 * Ensures that code points are counted properly across different byte offsets
	 * and lengths, equivalent to counting code points for an equivalent substring.
	 *
	 * @ticket 63863
	 *
	 * @dataProvider data_strings_and_substring_offsets
	 *
	 * @param string $text
	 * @param int    $byte_offset
	 * @param int    $byte_length
	 * @return void
	 */
	public function test_counts_within_appropriate_offsets( string $text, int $byte_offset, int $byte_length ) {
		$substring = substr( $text, $byte_offset, $byte_length );

		if (
			! mb_check_encoding( $substring, 'UTF-8' ) &&
			// Miscounting bug fixed by removal of “fast path” php/php-src@cca4ca6d3dda8c2e1c5c1b053550f94b3d6fb6bf
			version_compare( PHP_VERSION, '8.3.0', '<' )
		) {
			$this->markTestSkipped( 'Prior to PHP 8.3.0, mb_strlen() misreported lengths of invalid inputs.' );
		}

		$this->assertSame(
			mb_strlen( $substring, 'UTF-8' ),
			_wp_utf8_codepoint_count( $text, $byte_offset, $byte_length ),
			"Miscounted code points from {$byte_length} bytes starting at {$byte_offset} in '{$text}'"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_strings_and_substring_offsets() {
		return array(
			array( 'zero length', 0, 0 ),
			array( 'zero length (in middle)', 5, 0 ),
			array( 'full text', 0, 9 ),
			array( 'prefix', 0, 2 ),
			array( 'middle span', 2, 4 ),
			array( 'suffix', 3, 3 ),
			array( 'overlong', 4, 8 ),

			array( "emoji \u{1F170} partial", 6, 1 ),
			array( "emoji \u{1F170} partial", 6, 2 ),
			array( "emoji \u{1F170} full", 6, 3 ),
			array( "emoji \u{1F170} beyond", 6, 4 ),

			array( "invalid \xF0\x9F before", 8, 5 ),
			array( "invalid \xF0\x9F before", 9, 5 ),
			array( "invalid \x95 whole", 8, 1 ),
			array( "invalid \x95 beyond", 8, 5 ),
			array( "invalid \x85\xB0 after", 8, 4 ),
			array( "invalid \x85\xB0 after", 9, 3 ),
			array( "invalid \x85\xB0\xC0\xF0\x9F subparts", 8, 7 ),
		);
	}
}
