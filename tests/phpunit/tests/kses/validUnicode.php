<?php

/**
 * Tests for valid_unicode().
 *
 * @group kses
 *
 * @covers ::valid_unicode
 */
class Tests_Kses_ValidUnicode extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_unicode
	 *
	 * @param int|string $codepoint Unicode codepoint.
	 * @param bool       $expected  Whether the codepoint is valid.
	 */
	public function test_valid_unicode( $codepoint, $expected ) {
		$this->assertSame( $expected, valid_unicode( $codepoint ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_valid_unicode() {
		return array(
			'horizontal tab'               => array( 0x9, true ),
			'line feed'                    => array( 0xA, true ),
			'carriage return'              => array( 0xD, true ),
			'space'                        => array( 0x20, true ),
			'bmp letter A'                 => array( 0x41, true ),
			'end of bmp before surrogates' => array( 0xD7FF, true ),
			'private use area start'       => array( 0xE000, true ),
			'near end of bmp'              => array( 0xFFFD, true ),
			'supplementary plane start'    => array( 0x10000, true ),
			'max unicode'                  => array( 0x10FFFF, true ),
			'backspace control'            => array( 0x8, false ),
			'null'                         => array( 0x0, false ),
			'surrogate start'              => array( 0xD800, false ),
			'surrogate end'                => array( 0xDFFF, false ),
			'noncharacter FFFE'            => array( 0xFFFE, false ),
			'noncharacter FFFF'            => array( 0xFFFF, false ),
			'above unicode max'            => array( 0x110000, false ),
			'negative'                     => array( -1, false ),
			'string codepoint casts'       => array( '65', true ),
		);
	}
}
