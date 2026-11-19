<?php
/**
 * Unit tests covering fallback UTF-8 code-point span detection.
 *
 * @package    WordPress
 * @subpackage Charset
 *
 * @group      compat
 *
 * @covers ::_wp_utf8_codepoint_span()
 */
class Tests_Compat_wpUtf8CodePointSpan extends WP_UnitTestCase {
	/**
	 * Ensures that the span accounts for the requested number of code points.
	 *
	 * @dataProvider data_codepoint_spans
	 *
	 * @ticket 65483
	 * @ticket 63863
	 *
	 * @param string $text
	 * @param int    $byte_offset
	 * @param int    $max_code_points
	 * @param int    $expected_span
	 * @param int    $expected_found
	 */
	public function test_finds_codepoint_spans( string $text, int $byte_offset, int $max_code_points, int $expected_span, int $expected_found ) {
		$found_code_points = null;

		$this->assertSame(
			$expected_span,
			_wp_utf8_codepoint_span( $text, $byte_offset, $max_code_points, $found_code_points ),
			'Should have found the expected byte span.'
		);

		$this->assertSame(
			$expected_found,
			$found_code_points,
			'Should have reported the expected number of code points.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: int, 2: int, 3: int, 4: int}>
	 */
	public static function data_codepoint_spans() {
		$long_ascii_run = str_repeat( 'a', 1024 );

		return array(
			'zero code point budget'                  => array(
				'abcdef',
				0,
				0,
				0,
				0,
			),
			'long ASCII run at start'                 => array(
				$long_ascii_run,
				0,
				5,
				5,
				5,
			),
			'long ASCII run from non-zero offset'     => array(
				"zz{$long_ascii_run}",
				2,
				5,
				5,
				5,
			),
			'multibyte character before the boundary' => array(
				"ab\u{1F170}cd",
				0,
				2,
				2,
				2,
			),
			'multibyte character at the boundary'     => array(
				"ab\u{1F170}cd",
				0,
				3,
				strlen( "ab\u{1F170}" ),
				3,
			),
			'invalid span after the boundary'         => array(
				"ab\xF0\x9Fzz",
				0,
				2,
				2,
				2,
			),
			'invalid span at the boundary'            => array(
				"ab\xF0\x9Fzz",
				0,
				3,
				4,
				3,
			),
		);
	}
}
