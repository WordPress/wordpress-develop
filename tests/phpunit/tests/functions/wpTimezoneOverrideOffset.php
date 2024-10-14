<?php

/**
 * Tests for the wp_timezone_override_offset function.
 *
 * @group Functions.php
 *
 * @covers ::wp_timezone_override_offset
 */
class Tests_Functions_wpTimezoneOverrideOffset extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'timezone_string' );
		parent::tear_down();
	}

	/**
 * @ticket 59980
 */
	public function test_wp_timezone_override_offset() {
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
	public function test_wp_timezone_override_offset_with_EST_option_set() {
		update_option( 'timezone_string', 'EST' );
		$offset = wp_timezone_override_offset();
		$this->assertIsFloat( $offset );
		$this->assertEquals( -5, $offset );
	}
	/**
	 * @ticket 59980
	 */
	public function test_wp_timezone_override_offset_with_NST_option_set() {
		update_option( 'timezone_string', 'America/St_Johns' );
		$offset = wp_timezone_override_offset();
		$this->assertIsFloat( $offset );
		$this->assertEquals( -3.5, $offset );
	}
}
