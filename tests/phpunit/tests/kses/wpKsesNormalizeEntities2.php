<?php

/**
 * Tests for wp_kses_normalize_entities2().
 *
 * @group kses
 *
 * @covers ::wp_kses_normalize_entities2
 */
class Tests_Kses_wpKsesNormalizeEntities2 extends WP_UnitTestCase {

	/**
	 * Tests that valid decimal references are padded to three digits,
	 * and that invalid ones have their ampersand escaped.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_decimal_references
	 *
	 * @param string $digits   Digits captured by the pattern.
	 * @param string $expected Expected replacement.
	 */
	public function test_should_normalize_decimal_references( $digits, $expected ) {
		$this->assertSame( $expected, wp_kses_normalize_entities2( array( "&#$digits;", $digits ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_decimal_references() {
		return array(
			'tab'                                  => array( '9', '&#009;' ),
			'line feed'                            => array( '10', '&#010;' ),
			'carriage return'                      => array( '13', '&#013;' ),
			'space'                                => array( '32', '&#032;' ),
			'two digits are padded'                => array( '65', '&#065;' ),
			'three digits'                         => array( '123', '&#123;' ),
			'four digits'                          => array( '1234', '&#1234;' ),
			'leading zeros are removed'            => array( '0065', '&#065;' ),
			'many leading zeros'                   => array( '000000065', '&#065;' ),
			'emoji'                                => array( '128512', '&#128512;' ),
			'last code point'                      => array( '1114111', '&#1114111;' ),
			'start of heading is invalid'          => array( '1', '&amp;#1;' ),
			'backspace is invalid'                 => array( '8', '&amp;#8;' ),
			'escape is invalid'                    => array( '27', '&amp;#27;' ),
			'surrogate is invalid'                 => array( '55296', '&amp;#55296;' ),
			'noncharacter U+FFFE is invalid'       => array( '65534', '&amp;#65534;' ),
			'code point after the last is invalid' => array( '1114112', '&amp;#1114112;' ),
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
		$this->assertSame( '', wp_kses_normalize_entities2( $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matches_without_digits() {
		return array(
			'no matches'      => array( array() ),
			'empty digits'    => array( array( '&#;', '' ) ),
			'only full match' => array( array( '&#65;' ) ),
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
			'/&amp;#(0*[1-9][0-9]{0,6});/',
			'wp_kses_normalize_entities2',
			'&amp;#65; &amp;#0066; &amp;#1; text'
		);

		$this->assertSame( '&#065; &#066; &amp;#1; text', $result );
	}
}
