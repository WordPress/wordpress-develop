<?php

/**
 * Tests for the sanitize_hex_color_no_hash() function.
 *
 * @group formatting
 *
 * @covers ::sanitize_hex_color_no_hash
 */
class Tests_Formatting_SanitizeHexColorNoHash extends WP_UnitTestCase {

	/**
	 * @ticket 60271
	 *
	 * @dataProvider data_sanitize_hex_color_no_hash
	 *
	 * @param string $color    Color.
	 * @param string $expected Expected.
	 */
	public function test_sanitize_hex_color_no_hash( $color, $expected ) {
		$this->assertSame( $expected, sanitize_hex_color_no_hash( $color ) );
	}

	/**
	 * Data provider for data_sanitize_hex_color_no_hash().
	 *
	 * @return array[]
	 */
	public function data_sanitize_hex_color_no_hash() {
		return array(
			'$maybe_alpha = false, 3 digit'               => array(
				'color'    => '#123',
				'expected' => '123',
			),
			'$maybe_alpha = false, 3 letter'              => array(
				'color'    => '#abc',
				'expected' => 'abc',
			),
			'$maybe_alpha = false, 3 mixed'               => array(
				'color'    => '#0ab',
				'expected' => '0ab',
			),
			'$maybe_alpha = false, 6 digit'               => array(
				'color'    => '#123456',
				'expected' => '123456',
			),
			'$maybe_alpha = false, 6 letter'              => array(
				'color'    => '#abcdef',
				'expected' => 'abcdef',
			),
			'$maybe_alpha = false, 6 mixed'               => array(
				'color'    => '#abc123',
				'expected' => 'abc123',
			),
			'empty string'                                => array(
				'color'    => '',
				'expected' => '',
			),
			'just #'                                      => array(
				'color'    => '#',
				'expected' => '',
			),
			'no hash'                                     => array(
				'color'    => '123',
				'expected' => '123',
			),
			'not a-f'                                     => array(
				'color'    => '#hjg',
				'expected' => null,
			),
			'not upper A-F'                               => array(
				'color'    => '#HJG',
				'expected' => null,
			),
			'$maybe_alpha = false, 3 digit with 1 alpha'  => array(
				'color'    => '#123f',
				'expected' => null,
			),
			'$maybe_alpha = false, 3 letter with 1 alpha' => array(
				'color'    => '#abcf',
				'expected' => null,
			),
			'$maybe_alpha = false, 3 mixed with 1 alpha'  => array(
				'color'    => '#0abf',
				'expected' => null,
			),
			'$maybe_alpha = false, 6 digit with 2 alpha'  => array(
				'color'    => '#123456ff',
				'expected' => null,
			),
			'$maybe_alpha = false, 6 letter with 2 alpha' => array(
				'color'    => '#abcdefff',
				'expected' => null,
			),
			'$maybe_alpha = false, 6 mixed with 2 alpha'  => array(
				'color'    => '#abc123ff',
				'expected' => null,
			),
			// Happy.
			'$maybe_alpha = true, 3 digit'                => array(
				'color'    => '#123',
				'expected' => '123',
			),
			'$maybe_alpha = true, 3 letter'               => array(
				'color'    => '#abc',
				'expected' => 'abc',
			),
			'$maybe_alpha = true, 3 mixed'                => array(
				'color'    => '#0ab',
				'expected' => '0ab',
			),
			'$maybe_alpha = true, 6 digit'                => array(
				'color'    => '#123456',
				'expected' => '123456',
			),
			'$maybe_alpha = true, 6 letter'               => array(
				'color'    => '#abcdef',
				'expected' => 'abcdef',
			),
			'$maybe_alpha = true, 6 mixed'                => array(
				'color'    => '#abc123',
				'expected' => 'abc123',
			),
			'$maybe_alpha = true, 3 digit with 1 alpha'   => array(
				'color'    => '#123f',
				'expected' => null,
			),
			'$maybe_alpha = true, 3 letter with 1 alpha'  => array(
				'color'    => '#abcf',
				'expected' => null,
			),
			'$maybe_alpha = true, 3 mixed with 1 alpha'   => array(
				'color'    => '#0abf',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 digit with 2 alpha'   => array(
				'color'    => '#123456ff',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 letter with 2 alpha'  => array(
				'color'    => '#abcdefff',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 mixed with 2 alpha'   => array(
				'color'    => '#abc123ff',
				'expected' => null,
			),
			'$maybe_alpha = true, 3 digit with 2 alpha'   => array(
				'color'    => '#123ff',
				'expected' => null,
			),
			'$maybe_alpha = true, 3 letter with 2 alpha'  => array(
				'color'    => '#abcff',
				'expected' => null,
			),
			'$maybe_alpha = true, 3 mixed with 2 alpha'   => array(
				'color'    => '#0abff',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 digit with 1 alpha'   => array(
				'color'    => '#123456f',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 letter with 1 alpha'  => array(
				'color'    => '#abcff',
				'expected' => null,
			),
			'$maybe_alpha = true, 6 mixed with 1 alpha'   => array(
				'color'    => '#0abff',
				'expected' => null,
			),
		);
	}
}
