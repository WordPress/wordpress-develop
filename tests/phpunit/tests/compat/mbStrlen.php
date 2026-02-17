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
	 * Test that the native mb_strlen() is available.
	 */
	public function test_mb_strlen_availability() {
		$this->assertTrue(
			in_array( 'mb_strlen', get_defined_functions()['internal'], true ),
			'Test runner should have `mbstring` extension active but doesn’t.'
		);
	}

	/**
	 * @dataProvider data_utf8_strings
	 */
	public function test_mb_strlen( $input_string ) {
		$this->assertSame(
			mb_strlen( $input_string, 'UTF-8' ),
			_mb_strlen( $input_string, 'UTF-8' )
		);
	}

	/**
	 * @dataProvider data_utf8_strings
	 */
	public function test_mb_strlen_via_regex( $input_string ) {
		$this->assertSame(
			mb_strlen( $input_string, 'UTF-8' ),
			_mb_strlen( $input_string, 'UTF-8' )
		);
	}

	/**
	 * @dataProvider data_utf8_strings
	 */
	public function test_8bit_mb_strlen( $input_string ) {
		$this->assertSame(
			mb_strlen( $input_string, '8bit' ),
			_mb_strlen( $input_string, '8bit' )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_utf8_strings() {
		return array(
			array( 'баба' ),
			array( 'баб' ),
			array( 'I am your б' ),
			array( '1111111111' ),
			array( '²²²²²²²²²²' ),
			array( '３３３３３３３３３３' ),
			array( '𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜𝟜' ),
			array( '1²３𝟜1²３𝟜1²３𝟜' ),
		);
	}
}
