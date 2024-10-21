<?php

/**
 * @group compat
 * @group security-153
 *
 * @covers ::mb_strlen
 * @covers ::_mb_strlen
 */
class Tests_Compat_mbStrlen extends WP_UnitTestCase {

	/**
	 * Test that mb_strlen() is always available (either from PHP or WP).
	 */
	public function test_mb_strlen_availability() {
		$this->assertTrue( function_exists( 'mb_strlen' ) );
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	public function test_mb_strlen( $string, $expected_character_length ) {
		$this->assertSame( $expected_character_length, _mb_strlen( $string, 'UTF-8' ) );
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	public function test_mb_strlen_via_regex( $string, $expected_character_length ) {
		_wp_can_use_pcre_u( false );
		$this->assertSame( $expected_character_length, _mb_strlen( $string, 'UTF-8' ) );
		_wp_can_use_pcre_u( 'reset' );
	}

	/**
	 * @dataProvider utf8_string_lengths
	 */
	public function test_8bit_mb_strlen( $string, $expected_character_length, $expected_byte_length ) {
		$this->assertSame( $expected_byte_length, _mb_strlen( $string, '8bit' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function utf8_string_lengths() {
		return array(
			array(
				'string'                    => 'баба',
				'expected_character_length' => 4,
				'expected_byte_length'      => 8,
			),
			array(
				'string'                    => 'баб',
				'expected_character_length' => 3,
				'expected_byte_length'      => 6,
			),
			array(
				'string'                    => 'I am your б',
				'expected_character_length' => 11,
				'expected_byte_length'      => 12,
			),
			array(
				'string'                    => '1111111111',
				'expected_character_length' => 10,
				'expected_byte_length'      => 10,
			),
			array(
				'string'                    => '²²²²²²²²²²',
				'expected_character_length' => 10,
				'expected_byte_length'      => 20,
			),
			array(
				'string'                    => '３３３３３３３３３３',
				'expected_character_length' => 10,
				'expected_byte_length'      => 30,
			),
			array(
				'string'                    => '𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜',
				'expected_character_length' => 10,
				'expected_byte_length'      => 40,
			),
			array(
				'string'                    => '1²３𝟜1²３𝟜1²３𝟜',
				'expected_character_length' => 12,
				'expected_byte_length'      => 30,
			),
		);
	}
}
