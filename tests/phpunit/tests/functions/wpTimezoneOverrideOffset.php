<?php

/**
 * Tests for the wp_timezone_override_offset function.
 *
 * @group Functions.php
 *
 * @covers ::wp_timezone_override_offset
 */
class Tests_Functions_wpTimezoneOverrideOffset extends WP_UnitTestCase {
	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_no_timezone_string_option_set() {
		$this->assertSame( '', get_option( 'timezone_string' ) );
		$this->assertFalse( wp_timezone_override_offset() );
	}

	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_bad_option_set() {
		update_option( 'timezone_string', 'BAD_TIME_ZONE' );
		$this->assertFalse( wp_timezone_override_offset() );
	}

	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_UTC_option_set() {
		update_option( 'timezone_string', 'UTC' );
		$offset = wp_timezone_override_offset();
		$this->assertSame( 0.0, $offset );
	}

	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_EST_option_set() {
		update_option( 'timezone_string', 'EST' );
		$offset = wp_timezone_override_offset();
		$this->assertSame( -5.0, $offset );
	}

	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_NST_option_set() {
		update_option( 'timezone_string', 'America/St_Johns' );
		$offset = wp_timezone_override_offset();
		$this->assertSame( -3.5, $offset );
	}
}
