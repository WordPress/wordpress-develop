<?php

/**
 * Tests for wp_kses_normalize_entities3().
 *
 * @group kses
 *
 * @covers ::wp_kses_normalize_entities3
 */
class Tests_Kses_wpKsesNormalizeEntities3 extends WP_UnitTestCase {

	/**
	 * Tests that valid hexadecimal references lose their leading zeros,
	 * and that invalid ones have their ampersand escaped.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_hexadecimal_references
	 *
	 * @param string $hex      Hexadecimal digits captured by the pattern.
	 * @param string $expected Expected replacement.
	 */
	public function test_should_normalize_hexadecimal_references( $hex, $expected ) {
		$this->assertSame( $expected, wp_kses_normalize_entities3( array( "&#x$hex;", $hex ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_hexadecimal_references() {
		return array(
			'tab'                                  => array( '9', '&#x9;' ),
			'line feed'                            => array( 'A', '&#xA;' ),
			'carriage return'                      => array( 'D', '&#xD;' ),
			'space'                                => array( '20', '&#x20;' ),
			'uppercase digits'                     => array( '41', '&#x41;' ),
			'lowercase digits are kept'            => array( 'e9', '&#xe9;' ),
			'uppercase letters are kept'           => array( 'E9', '&#xE9;' ),
			'leading zeros are removed'            => array( '0041', '&#x41;' ),
			'many leading zeros'                   => array( '00000041', '&#x41;' ),
			'emoji'                                => array( '1F600', '&#x1F600;' ),
			'last code point'                      => array( '10FFFF', '&#x10FFFF;' ),
			'start of heading is invalid'          => array( '1', '&amp;#x1;' ),
			'backspace is invalid'                 => array( '8', '&amp;#x8;' ),
			'escape is invalid'                    => array( '1B', '&amp;#x1B;' ),
			'surrogate is invalid'                 => array( 'D800', '&amp;#xD800;' ),
			'noncharacter U+FFFE is invalid'       => array( 'FFFE', '&amp;#xFFFE;' ),
			'code point after the last is invalid' => array( '110000', '&amp;#x110000;' ),
			'zero is invalid'                      => array( '00', '&amp;#x00;' ),
		);
	}

	/**
	 * Tests that an empty replacement is returned when no digits were captured.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_matches_without_digits
	 *
	 * @param array $matches Matches passed to the callback.
	 */
	public function test_should_return_empty_string_without_digits( $matches ) {
		$this->assertSame( '', wp_kses_normalize_entities3( $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matches_without_digits() {
		return array(
			'no matches'      => array( array() ),
			'empty digits'    => array( array( '&#x;', '' ) ),
			'only full match' => array( array( '&#x41;' ) ),
		);
	}

	/**
	 * Tests that the callback works with the pattern used by wp_kses_normalize_entities(),
	 * which is applied after the ampersands were escaped.
	 *
	 * @ticket 66492
	 */
	public function test_should_work_as_preg_replace_callback() {
		$result = preg_replace_callback(
			'/&amp;#[Xx](0*[1-9A-Fa-f][0-9A-Fa-f]{0,5});/',
			'wp_kses_normalize_entities3',
			'&amp;#x41; &amp;#x0042; &amp;#x1; text'
		);

		$this->assertSame( '&#x41; &#x42; &amp;#x1; text', $result );
	}
}
