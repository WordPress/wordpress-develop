<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_CurrentTime extends WP_UnitTestCase {

	/**
	 * @ticket 37440
	 */
	public function test_should_work_with_changed_timezone() {
		$format          = 'Y-m-d H:i:s';
		$timezone_string = 'America/Regina';
		update_option( 'timezone_string', $timezone_string );
		$datetime = new DateTime( 'now', new DateTimeZone( $timezone_string ) );

		date_default_timezone_set( $timezone_string );
		$this->assertEquals( gmdate( $format ), current_time( $format, true ) );
		$this->assertEquals( $datetime->format( $format ), current_time( $format ) );

		date_default_timezone_set( 'UTC' );
		$this->assertEquals( gmdate( $format ), current_time( $format, true ) );
		$this->assertEquals( $datetime->format( $format ), current_time( $format ) );
	}

	/**
	 * @ticket 40653
	 */
	public function test_should_return_wp_timestamp() {
		update_option( 'timezone_string', 'Europe/Kiev' );
		$timestamp = time();
		$datetime  = new DateTime( '@' . $timestamp );
		$datetime->setTimezone( wp_timezone() );
		$wp_timestamp = $timestamp + $datetime->getOffset();

		$this->assertEquals( $timestamp, current_time( 'timestamp', true ), '', 2 );
		$this->assertEquals( $timestamp, current_time( 'U', true ), '', 2 );
		$this->assertEquals( $wp_timestamp, current_time( 'timestamp' ), '', 2 );
		$this->assertEquals( $wp_timestamp, current_time( 'U' ), '', 2 );
		$this->assertInternalType( 'int', current_time( 'timestamp' ) );
	}

	/**
	 * @ticket 40653
	 */
	public function test_should_return_correct_local_time() {
		update_option( 'timezone_string', 'Europe/Kiev' );
		$timestamp      = time();
		$datetime_local = new DateTime( '@' . $timestamp );
		$datetime_local->setTimezone( wp_timezone() );
		$datetime_utc = new DateTime( '@' . $timestamp );
		$datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEquals( $datetime_local->format( DATE_W3C ), current_time( DATE_W3C ), '', 2 );
		$this->assertEquals( $datetime_utc->format( DATE_W3C ), current_time( DATE_W3C, true ), '', 2 );
	}
}
