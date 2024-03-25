<?php

/**
 * Tests for PHP compatability functions.
 *
 * @group php-compat.php
 * @covers ::wp_ini_parse_quantity
 */
class Tests_PHP_Compat_wpIniParseQuantity extends WP_UnitTestCase {
	public function test_unset_limit_is_no_limit() {
		$this->assertEquals( 0, wp_ini_parse_quantity( false ) );
	}

	public function test_absent_limit_is_no_limit() {
		$this->assertEquals( 0, wp_ini_parse_quantity( '' ) );
	}

	public function test_unlimited_is_unlimited() {
		$this->assertEquals( -1, wp_ini_parse_quantity( '-1' ) );
	}

	public function test_unlimited_is_same_as_missing_limit() {
		$this->assertEqual( '', wp_ini_greater_quantity( '', '-1' ) );
		$this->assertEqual( '-1', wp_ini_greater_quantity( '-1', '' ) );
		$this->assertEqual( '', wp_ini_lesser_quantity( '', '-1' ) );
		$this->assertEqual( '-1', wp_ini_lesser_quantity( '-1', '' ) );
	}

	public function test_unlimited_is_greater_than_hard_limit() {
		$this->assertEqual( 1, wp_ini_quantity_cmp( -1, 1348 ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( -1, '1348g' ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( '', 1348 ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( '', '1348g' ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( 0, 1348 ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( 0, '1348g' ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( false, 1348 ) );
		$this->assertEqual( 1, wp_ini_quantity_cmp( false, '1348g' ) );
	}

	public function test_invalid_data_is_no_limit() {
		$this->assertEquals( 0, wp_ini_parse_quantity( true ) );
		$this->assertEquals( 0, wp_ini_parse_quantity( false ) );
		$this->assertEquals( 0, wp_ini_parse_quantity( array( 1, 2, 3 ) ) );
		$this->assertEquals( 0, wp_ini_parse_quantity( new stdClass ) );
	}

	public function test_returns_already_parsed_values() {
		$this->assertEquals( 15, wp_ini_parse_quantity( 15 ) );
		$this->assertEquals( -1543, wp_ini_parse_quantity( -1543 ) );
	}

	public function test_clamped_to_max_int_before_suffix() {
		if ( IS_32_BIT_SYSTEM ) {
			$this->assertEquals( PHP_INT_MAX, wp_ini_parse_quantity( '2147483648' ) );
			$this->assertEquals( PHP_INT_MIN, wp_ini_parse_quantity( '-2147483649' ) );
		} else {
			$this->assertEquals( PHP_INT_MAX, wp_ini_parse_quantity( '9223372036854775808' ) );
			$this->assertEquals( PHP_INT_MIN, wp_ini_parse_quantity( '-9223372036854775809' ) );
		}
	}

	public function test_suffix_math_may_overflow() {
		if ( IS_32_BIT_SYSTEM ) {
			$this->assertNotEquals( PHP_INT_MAX, wp_ini_parse_quantity( '2147483648g' ) );
			$this->assertNotEquals( PHP_INT_MIN, wp_ini_parse_quantity( '-2147483648g' ) );
		} else {
			$this->assertNotEquals( PHP_INT_MAX, wp_ini_parse_quantity( '9223372036854775807g' ) );
			$this->assertNotEquals( PHP_INT_MIN, wp_ini_parse_quantity( '-9223372036854775807g' ) );
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
		$this->assertEquals( $expected, wp_ini_parse_quantity( $value ) );
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
