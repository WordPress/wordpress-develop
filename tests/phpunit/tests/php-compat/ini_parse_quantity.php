<?php

/**
 * Tests for PHP compatability functions.
 *
 * @group php-compat.php
 * @covers ::wp_ini_parse_quantity
 */
class Tests_PHP_Compat_wpIniParseQuantity extends WP_UnitTestCase {
	/**
	 * Ensures that the shorthand INI syntax is properly parsed.
	 *
	 * @ticket 55635
	 *
	 * @dataProvider data_ini_shorthand_values
	 *
	 * @param mixed $ini_value Shorthand value from {@see \ini_get()} or of a bad data type.
	 * @param int   $expected  Expected parsed value from input.
	 */
	public function test_fallback_parses_expected_value( $ini_value, $expected ) {
		if ( function_exists( '\ini_parse_quantity' ) ) {
			$this->assertSame(
				ini_parse_quantity( $ini_value ),
				wp_ini_parse_quantity( $ini_value),
				'Failed to match PHP’s internal reporting.'
			);
		} else {
			$this->assertSame(
				$expected,
				wp_ini_parse_quantity( $ini_value ),
				'Failed to match expected quantity.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ini_shorthand_values() {
		return array(
			// Empty, unset, and unlimited values.
			array( false, 0 ),
			array( '', 0 ),
			array( '-1', -1 ),

			// Already-parsed values.
			array( 15, 15 ),
			array( -1543, -1543 ),

			// Invalid data types.
			array( true, 0 ),
			array( array( 1, 2, 3 ), 0 ),
			array( new stdClass, 0 ),

			// Non-suffixes clamp.
			array( 8 === PHP_INT_SIZE ? '9223372036854775808' : '2147483648', PHP_INT_MAX ),
			array( 8 === PHP_INT_SIZE ? '-9223372036854775809' : '-2147483649', -1 ),

			// Suffixes might overflow.
			array( 8 === PHP_INT_SIZE ? '9223372036854775808g' : '2147483648g', PHP_INT_MAX ),
			array( 8 === PHP_INT_SIZE ? '-9223372036854775809g' : '-2147483649g', -1 ),

			// Decimal integer input.
			array( '0', 0 ),
			array( '100', 100 ),
			array( '-14', -1 ),

			// Octal integer input.
			array( '0100', 64 ),
			array( '-0654', -1 ),

			// Hex input.
			array( '0x14', 20 ),
			array( '0X14', 20 ),
			array( '-0xAA', -1 ),

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
			array( '    -0xdeadbeef', -1 ),
			array( ' 00000077', 63 ),

			// Things that don't look valid but are still possible.
			array( '', 0 ),
			array( '3km', 3145728 ),
			array( '1mg', 1073741824 ),
			array( 'boat', 0 ),
			array( '-14chairsk', -1 ),
			array( '0xt', 0 ),
			array( '++3', 0 ),
			array( '0x5ome 🅰🅱🅲 attack', 5120 ),
		);
	}

	/**
	 * Ensures that INI quantity values compare properly.
	 *
	 * @ticket 55635
	 *
	 * @dataProvider data_compared_ini_values
	 *
	 * @param string      $a   First INI shorthand value to compare.
	 * @param '<'|'='|'>' $cmp Relationship of first to second value.
	 * @param string      $b   Second INI shorthand value to compare.
	 */
	public function test_compares_properly( string $a, string $cmp, string $b ) {
		switch ( $cmp ) {
			case '<':
				$this->assertSame(
					-1,
					wp_ini_quantity_cmp( $a, $b ),
					'Should have determined that the first value is smaller.'
				);

				$this->assertSame(
					$a,
					wp_ini_lesser_quantity( $a, $b ),
					'Should have returned the first value as the smaller of the two.'
				);

				$this->assertSame(
					$b,
					wp_ini_greater_quantity( $a, $b ),
					'Should have returned the second value as the greater of the two.'
				);

				break;

			case '=':
				$this->assertSame(
					0,
					wp_ini_quantity_cmp( $a, $b ),
					'Should have determined that the values are equal.'
				);

				$this->assertSame(
					$a,
					wp_ini_lesser_quantity( $a, $b ),
					'Should have returned the first value when they are equal.'
				);

				$this->assertSame(
					$a,
					wp_ini_greater_quantity( $a, $b ),
					'Should have returned the first value when they are equal.'
				);

				break;

			case '>':
				$this->assertSame(
					1,
					wp_ini_quantity_cmp( $a, $b ),
					'Should have determined that the second value is greater.'
				);

				$this->assertSame(
					$b,
					wp_ini_lesser_quantity( $a, $b ),
					'Should have returned the second value as the smaller of the two.'
				);

				$this->assertSame(
					$a,
					wp_ini_greater_quantity( $a, $b ),
					'Should have returned the first value as the greater of the two.'
				);

				break;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_compared_ini_values() {
		return array(
			// No limit vs. unlimited.
			array( '', '=', '-1' ),
			array( '-1', '=', '' ),

			// Unlimited vs. hard limit.
			array( -1, '>', 1348 ),
			array( -1, '>', '1348g' ),
			array( '', '>', 1348 ),
			array( '', '>', '1348g' ),
			array( 0, '>', 1348 ),
			array( 0, '>', '1348g' ),
			array( false, '>', 1348 ),
			array( false, '>', '1348g' ),
		);
	}
}
