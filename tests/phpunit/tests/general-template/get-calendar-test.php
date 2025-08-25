<?php

/**
 * Class responsible for testing the functionality of the get_calendar() template function
 * in various scenarios, including input validation for invalid parameters.
 *
 * @covers get_calendar()
 */
class Tests_General_Template_GetCalendar extends WP_UnitTestCase {
	/**
	 * Tests if get_calendar() handles invalid week numbers correctly.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_invalid_week_numbers() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$_GET['w']     = 1400;
		$calendar_high = get_calendar( array( 'display' => false ) );

		$_GET['w']         = -5;
		$calendar_negative = get_calendar( array( 'display' => false ) );

		$_GET['w']     = 0;
		$calendar_zero = get_calendar( array( 'display' => false ) );

		$_GET['w']      = 25;
		$calendar_valid = get_calendar( array( 'display' => false ) );

		unset( $_GET['w'] );

		$calendar_normal = get_calendar( array( 'display' => false ) );

		$this->assertEquals( $calendar_normal, $calendar_high, 'High week number should fall back to normal calendar' );
		$this->assertEquals( $calendar_normal, $calendar_negative, 'Negative week number should fall back to normal calendar' );
		$this->assertEquals( $calendar_normal, $calendar_zero, 'Zero week number should fall back to normal calendar' );

		$this->assertNotEquals( $calendar_normal, $calendar_valid, 'Valid week number should produce different calendar' );

		wp_delete_post( $post_id, true );
	}
}
