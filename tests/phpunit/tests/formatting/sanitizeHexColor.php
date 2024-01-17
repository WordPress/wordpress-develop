<?php

/**
 * Tests for the sanitize_hex_color function.
 *
 * @group formating
 *
 * @covers ::sanitize_hex_color
 */
class Tests_formating_sanitizeHexColor extends WP_UnitTestCase {

	/**
	 * @ticket 55962
	 *
	 * @dataProvider date_sanitize_hex_color
	 */
	public function test_sanitize_hex_color( $color, $expected, $maybe_alpha ) {
		$this->assertSame( $expected, sanitize_hex_color( $color, $maybe_alpha ) );
	}

	/**
	 * @return array
	 */
	public function date_sanitize_hex_color() {
		return array(
			'$maybe_alpha = false, 3 digit'               => array(
				'color'       => '#123',
				'expected'    => '#123',
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 3 letter'              => array(
				'color'       => '#abc',
				'expected'    => '#abc',
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 3 mixed'               => array(
				'color'       => '#0ab',
				'expected'    => '#0ab',
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 digit'               => array(
				'color'       => '#123456',
				'expected'    => '#123456',
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 letter'              => array(
				'color'       => '#abcdef',
				'expected'    => '#abcdef',
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 mixed'               => array(
				'color'       => '#abc123',
				'expected'    => '#abc123',
				'maybe_alpha' => false,
			),
			'empty string'                                => array(
				'color'       => '',
				'expected'    => '',
				'maybe_alpha' => false,
			),
			'no hash'                                     => array(
				'color'       => '123',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'not a-f'                                     => array(
				'color'       => '#hjg',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'not upper A-F'                               => array(
				'color'       => '#HJG',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 3 digit with 1 alpha'  => array(
				'color'       => '#123f',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 3 letter with 1 alpha' => array(
				'color'       => '#abcf',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 3 mixed with 1 alpha'  => array(
				'color'       => '#0abf',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 digit with 2 alpha'  => array(
				'color'       => '#123456ff',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 letter with 2 alpha' => array(
				'color'       => '#abcdefff',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = false, 6 mixed with 2 alpha'  => array(
				'color'       => '#abc123ff',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			// Happy.
			'$maybe_alpha = true, 3 digit'                => array(
				'color'       => '#123',
				'expected'    => '#123',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 letter'               => array(
				'color'       => '#abc',
				'expected'    => '#abc',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 mixed'                => array(
				'color'       => '#0ab',
				'expected'    => '#0ab',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 digit'                => array(
				'color'       => '#123456',
				'expected'    => '#123456',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 letter'               => array(
				'color'       => '#abcdef',
				'expected'    => '#abcdef',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 mixed'                => array(
				'color'       => '#abc123',
				'expected'    => '#abc123',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 digit with 1 alpha'   => array(
				'color'       => '#123f',
				'expected'    => '#123f',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 letter with 1 alpha'  => array(
				'color'       => '#abcf',
				'expected'    => '#abcf',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 mixed with 1 alpha'   => array(
				'color'       => '#0abf',
				'expected'    => '#0abf',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 digit with 2 alpha'   => array(
				'color'       => '#123456ff',
				'expected'    => '#123456ff',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 letter with 2 alpha'  => array(
				'color'       => '#abcdefff',
				'expected'    => '#abcdefff',
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 mixed with 2 alpha'   => array(
				'color'       => '#abc123ff',
				'expected'    => '#abc123ff',
				'maybe_alpha' => true,
			),
			'not A-F'                                     => array(
				'color'       => '#HJG',
				'expected'    => null,
				'maybe_alpha' => false,
			),
			'$maybe_alpha = true, 3 digit with 2 alpha'   => array(
				'color'       => '#123ff',
				'expected'    => null,
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 letter with 2 alpha'  => array(
				'color'       => '#abcff',
				'expected'    => null,
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 3 mixed with 2 alpha'   => array(
				'color'       => '#0abff',
				'expected'    => null,
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 digit with 1 alpha'   => array(
				'color'       => '#123456f',
				'expected'    => null,
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 letter with 1 alpha'  => array(
				'color'       => '#abcff',
				'expected'    => null,
				'maybe_alpha' => true,
			),
			'$maybe_alpha = true, 6 mixed with 1 alpha'   => array(
				'color'       => '#0abff',
				'expected'    => null,
				'maybe_alpha' => true,
			),
		);
	}
}

