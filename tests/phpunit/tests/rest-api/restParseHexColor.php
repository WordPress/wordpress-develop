<?php

/**
 * Tests for rest_parse_hex_color().
 *
 * @group restapi
 *
 * @covers ::rest_parse_hex_color
 */
class Tests_REST_API_RestParseHexColor extends WP_UnitTestCase {

	/**
	 * Tests that valid 3- and 6-digit hex colors are returned unchanged.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_hex_colors
	 *
	 * @param string $color Valid hex color.
	 */
	public function test_returns_valid_hex_color( $color ) {
		$this->assertSame( $color, rest_parse_hex_color( $color ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_valid_hex_colors() {
		return array(
			'3-digit lowercase'  => array( '#abc' ),
			'3-digit uppercase'  => array( '#ABC' ),
			'3-digit mixed case' => array( '#aBc' ),
			'6-digit lowercase'  => array( '#abcdef' ),
			'6-digit uppercase'  => array( '#ABCDEF' ),
			'6-digit mixed case' => array( '#aBcDeF' ),
			'numeric digits'     => array( '#012345' ),
		);
	}

	/**
	 * Tests that invalid color values return false.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_hex_colors
	 *
	 * @param mixed $color Invalid color value.
	 */
	public function test_returns_false_for_invalid_hex_color( $color ) {
		$this->assertFalse( rest_parse_hex_color( $color ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_hex_colors() {
		return array(
			'missing hash'           => array( 'abcdef' ),
			'empty string'           => array( '' ),
			'hash only'              => array( '#' ),
			'too short'              => array( '#ab' ),
			'4 digits'               => array( '#abcd' ),
			'5 digits'               => array( '#abcde' ),
			'too long'               => array( '#abcdef0' ),
			'non-hex characters'     => array( '#ggg' ),
			'invalid 6-digit chars'  => array( '#abcdeg' ),
			'spaces'                 => array( '#abc def' ),
			'rgb function'           => array( 'rgb(0,0,0)' ),
			'named color'            => array( 'red' ),
		);
	}
}
