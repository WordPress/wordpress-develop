<?php

defined( 'IS_32_BIT_SYSTEM' ) || define( 'IS_32_BIT_SYSTEM', 2147483647 === PHP_INT_MAX );

/**
 * Tests for PHP compatability functions.
 *
 * @group php-compat.php
 * @covers ::wp_ini_bytes
 */
class Tests_PHP_Compat_wpIniBytes extends WP_UnitTestCase {
	public function test_unset_limit_is_no_limit() {
		$this->assertEquals( 0, wp_ini_bytes( false ) );
	}

	public function test_absent_limit_is_no_limit() {
		$this->assertEquals( 0, wp_ini_bytes( -1 ) );
	}

	public function test_invalid_data_is_no_limit() {
		$this->assertEquals( 0, wp_ini_bytes( true ) );
		$this->assertEquals( 0, wp_ini_bytes( false ) );
		$this->assertEquals( 0, wp_ini_bytes( array( 1, 2, 3 ) ) );
		$this->assertEquals( 0, wp_ini_bytes( new stdClass ) );
	}

	public function test_returns_already_parsed_values() {
		$this->assertEquals( 15, wp_ini_bytes( 15 ) );
	}

	public function test_clamped_to_max_int_before_suffix() {
		if ( IS_32_BIT_SYSTEM ) {
			$this->assertEquals( PHP_INT_MAX, wp_ini_bytes( '2147483648' ) );
			$this->assertEquals( PHP_INT_MIN, wp_ini_bytes( '-2147483649' ) );
		} else {
			$this->assertEquals( PHP_INT_MAX, wp_ini_bytes( '9223372036854775808' ) );
			$this->assertEquals( PHP_INT_MIN, wp_ini_bytes( '-9223372036854775809' ) );
		}
	}

	public function test_suffix_math_may_overflow() {
		if ( IS_32_BIT_SYSTEM ) {
			$this->assertNotEquals( PHP_INT_MAX, wp_ini_bytes( '2147483648g' ) );
			$this->assertNotEquals( PHP_INT_MIN, wp_ini_bytes( '-2147483648g' ) );
		} else {
			$this->assertNotEquals( PHP_INT_MAX, wp_ini_bytes( '9223372036854775807g' ) );
			$this->assertNotEquals( PHP_INT_MIN, wp_ini_bytes( '-9223372036854775807g' ) );
		}
	}

	/**
	 * Tests converting numeric php.ini directive strings into their scalar equivalents.
	 *
	 * @ticket 55635
	 *
	 * @dataProvider data_php_numeric_strings
	 *
	 * @param $value
	 * @param $expected
	 */
	public function test_parse_matches_php_internal_value( $value, $expected ) {
		$this->assertEquals( $expected, wp_ini_bytes( $value ) );
	}

	public function data_php_numeric_strings() {
		return array(
			// Decimal integer input.
			array( '0', 0 ),
			array( '100', 100 ),
			array( '-14', -14 ),

			// Octal integer input.
			array( '0100', 64 ),
			array( '-0654', -428 ),

			// Hex input.
			array( '0x14', 20 ),
			array( '0X14', 20 ),
			array( '-0xAA', -170 ),

			// Size suffixes.
			array( '1g', 1073741824 ),
			array( '1gb', 0 ),
			array( '32k', 32768 ),
			array( '64K', 65536 ),
			array( '07k', 7168 ),
			array( '-0xF3d7m', -65455259648 ),
			array( '128m', 134217728 ),
			array( '128m ', 128 ),
			array( '128mk', 131072 ),
			array( '128km', 134217728 ),
			array( '1.28 kmg', 1073741824 ),
			array( '256M', 268435456 ),

			// Leading characters.
			array( '    68', 68 ),
			array( '+1', 1 ),
			array( '    -0xdeadbeef', -3735928559 ),
			array( ' 00000077', 63 ),

			// Things that don't look valid but are still possible.
			array( '', 0 ),
			array( '3km', 3145728 ),
			array( '1mg', 1073741824 ),
			array( 'boat', 0 ),
			array( '-14chairsk', -14336 ),
			array( '0xt', 0 ),
			array( '++3', 0 ),
			array( '0x5ome 🅰🅱🅲 attack', 5120 ),
		);
	}
}
