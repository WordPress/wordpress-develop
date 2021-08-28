<?php

/**
 * @group compat
 * @group security-153
 */
class Tests_Compat_mbStrlen extends WP_UnitTestCase {

	function utf8_string_lengths() {
		return array(
			// String, character_length, byte_length.
			array( 'баба', 4, 8 ),
			array( 'баб', 3, 6 ),
			array( 'I am your б', 11, 12 ),
			array( '1111111111', 10, 10 ),
			array( '²²²²²²²²²²', 10, 20 ),
			array( '３３３３３３３３３３', 10, 30 ),
			array( '𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜', 10, 40 ),
			array( '1²３𝟜1²３𝟜1²３𝟜', 12, 30 ),
		);
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	function test_mb_strlen( $string, $expected_character_length ) {
		$this->assertSame( $expected_character_length, _mb_strlen( $string, 'UTF-8' ) );
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	function test_mb_strlen_via_regex( $string, $expected_character_length ) {
		_wp_can_use_pcre_u( false );
		$this->assertSame( $expected_character_length, _mb_strlen( $string, 'UTF-8' ) );
		_wp_can_use_pcre_u( 'reset' );
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	function test_8bit_mb_strlen( $string, $expected_character_length, $expected_byte_length ) {
		$this->assertSame( $expected_byte_length, _mb_strlen( $string, '8bit' ) );
	}
}
