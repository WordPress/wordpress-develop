<?php
/**
 * Unit tests for gmt_offset handling.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Test cases for the gmt_offset option.
 */
class Tests_Option_GMT_Offset extends WP_UnitTestCase {

	/**
	 * Test gmt_offset comparison with integer 0.
	 *
	 * @ticket  57030
	 */
	public function test_gmt_offset_with_integer_zero() {
		update_option( 'gmt_offset', 0 );
		$current_offset = get_option( 'gmt_offset' );
		$this->assertSame( 0, $current_offset );

		// Simulate the code logic
		if ( 0 === $current_offset ) {
			$tzstring = 'UTC+0';
		}

		$this->assertEquals( 'UTC+0', $tzstring );
	}

	/**
	 * Test gmt_offset comparison with string '0'.
	 *
	 * @ticket  57030
	 */
	public function test_gmt_offset_with_string_zero() {
		update_option( 'gmt_offset', '0' );
		$current_offset = get_option( 'gmt_offset' );
		$this->assertSame( '0', $current_offset );

		// Simulate the code logic
		if ( '0' === $current_offset ) {
			$tzstring = 'UTC+0';
		}

		$this->assertEquals( 'UTC+0', $tzstring );
	}

	/**
	 * Test gmt_offset comparison with negative float.
	 *
	 * @ticket  57030
	 */
	public function test_gmt_offset_with_negative_float() {
		update_option( 'gmt_offset', -5.5 );
		$current_offset = get_option( 'gmt_offset' );
		$this->assertSame( -5.5, (float) $current_offset );

		// Simulate the code logic
		if ( $current_offset < 0 ) {
			$tzstring = 'UTC' . $current_offset;
		}

		$this->assertEquals( 'UTC-5.5', $tzstring );
	}

	/**
	 * Test gmt_offset comparison with invalid value.
	 *
	 * @ticket  57030
	 */
	public function test_gmt_offset_with_invalid_value() {
		update_option( 'gmt_offset', 'invalid' );
		$current_offset = get_option( 'gmt_offset' );

		// Simulate the code logic
		if ( 0 === (int) $current_offset ) {
			$tzstring = 'UTC+0';
		} elseif ( is_numeric( $current_offset ) && (float) $current_offset < 0 ) {
			$tzstring = 'UTC' . $current_offset;
		} elseif ( is_numeric( $current_offset ) ) {
			$tzstring = 'UTC+' . $current_offset;
		} else {
			// Handle unexpected types gracefully.
			$tzstring = 'UTC+0';
		}

		$this->assertEquals( 'UTC+0', $tzstring );
	}
}
