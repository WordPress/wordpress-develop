<?php

/**
 * Tests for the sanitize_hex_color function.
 *
 * @group formatting
 *
 * @covers ::maybe_hash_hex_color
 */
class Tests_Formatting_MaybeHashHexColor extends WP_UnitTestCase {

	/**
	 * @ticket 60272
	 *
	 * @dataProvider data_sanitize_hex_color_no_hash
	 *
	 * @param string $color    Color.
	 * @param string $expected Expected.
	 */
	public function test_maybe_hash_hex_color( $color, $expected ) {
		$this->assertSame( $expected, maybe_hash_hex_color( $color ) );
	}

	/**
	 * Data provider for test_maybe_hash_hex_color().
	 *
	 * @return array[]
	 */
	public function data_sanitize_hex_color_no_hash() {
		return array(
			'$maybe_alpha = false, 3 digit'               => array(
				'color'    => '#123',
				'expected' => '#123',
			),
			'$maybe_alpha = false, 3 letter'              => array(
				'color'    => '#abc',
				'expected' => '#abc',
			),
			'$maybe_alpha = false, 3 mixed'               => array(
				'color'    => '#0ab',
				'expected' => '#0ab',
			),
			'$maybe_alpha = false, 6 digit'               => array(
				'color'    => '#123456',
				'expected' => '#123456',
			),
			'$maybe_alpha = false, 6 letter'              => array(
				'color'    => '#abcdef',
				'expected' => '#abcdef',
			),
			'$maybe_alpha = false, 6 mixed'               => array(
				'color'    => '#abc123',
				'expected' => '#abc123',
			),
			'empty string'                                => array(
				'color'    => '',
				'expected' => '',
			),
			'just #'                                      => array(
				'color'    => '#',
				'expected' => '#',
			),
			'no hash'                                     => array(
				'color'    => '123',
				'expected' => '#123',
			),
			'not a-f'                                     => array(
				'color'    => '#hjg',
				'expected' => '#hjg',
			),
			'not upper A-F'                               => array(
				'color'    => '#HJG',
				'expected' => '#HJG',
			),
			'$maybe_alpha = false, 3 digit with 1 alpha'  => array(
				'color'    => '#123f',
				'expected' => '#123f',
			),
			'$maybe_alpha = false, 3 letter with 1 alpha' => array(
				'color'    => '#abcf',
				'expected' => '#abcf',
			),
			'$maybe_alpha = false, 3 mixed with 1 alpha'  => array(
				'color'    => '#0abf',
				'expected' => '#0abf',
			),
			'$maybe_alpha = false, 6 digit with 2 alpha'  => array(
				'color'    => '#123456ff',
				'expected' => '#123456ff',
			),
			'$maybe_alpha = false, 6 letter with 2 alpha' => array(
				'color'    => '#abcdefff',
				'expected' => '#abcdefff',
			),
			'$maybe_alpha = false, 6 mixed with 2 alpha'  => array(
				'color'    => '#abc123ff',
				'expected' => '#abc123ff',
			),
			// Happy.
			'$maybe_alpha = true, 3 digit'                => array(
				'color'    => '#123',
				'expected' => '#123',
			),
			'$maybe_alpha = true, 3 letter'               => array(
				'color'    => '#abc',
				'expected' => '#abc',
			),
			'$maybe_alpha = true, 3 mixed'                => array(
				'color'    => '0ab',
				'expected' => '#0ab',
			),
			'$maybe_alpha = true, 6 digit'                => array(
				'color'    => '123456',
				'expected' => '#123456',
			),
			'$maybe_alpha = true, 6 letter'               => array(
				'color'    => 'abcdef',
				'expected' => '#abcdef',
			),
			'$maybe_alpha = true, 6 mixed'                => array(
				'color'    => 'abc123',
				'expected' => '#abc123',
			),
			'$maybe_alpha = true, 3 digit with 1 alpha'   => array(
				'color'    => '123f',
				'expected' => '123f',
			),
			'$maybe_alpha = true, 3 letter with 1 alpha'  => array(
				'color'    => 'abcf',
				'expected' => 'abcf',
			),
			'$maybe_alpha = true, 3 mixed with 1 alpha'   => array(
				'color'    => '0abf',
				'expected' => '0abf',
			),
			'$maybe_alpha = true, 6 digit with 2 alpha'   => array(
				'color'    => '123456ff',
				'expected' => '123456ff',
			),
			'$maybe_alpha = true, 6 letter with 2 alpha'  => array(
				'color'    => 'abcdefff',
				'expected' => 'abcdefff',
			),
			'$maybe_alpha = true, 6 mixed with 2 alpha'   => array(
				'color'    => 'abc123ff',
				'expected' => 'abc123ff',
			),
			'$maybe_alpha = true, 3 digit with 2 alpha'   => array(
				'color'    => '123ff',
				'expected' => '123ff',
			),
			'$maybe_alpha = true, 3 letter with 2 alpha'  => array(
				'color'    => 'abcff',
				'expected' => 'abcff',
			),
			'$maybe_alpha = true, 3 mixed with 2 alpha'   => array(
				'color'    => '0abff',
				'expected' => '0abff',
			),
			'$maybe_alpha = true, 6 digit with 1 alpha'   => array(
				'color'    => '123456f',
				'expected' => '123456f',
			),
			'$maybe_alpha = true, 6 letter with 1 alpha'  => array(
				'color'    => 'abcff',
				'expected' => 'abcff',
			),
			'$maybe_alpha = true, 6 mixed with 1 alpha'   => array(
				'color'    => '0abff',
				'expected' => '0abff',
			),
		);
	}
}
