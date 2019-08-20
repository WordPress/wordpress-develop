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

		$current_time_custom_timezone_gmt = current_time( $format, true );
		$current_time_custom_timezone     = current_time( $format );

		date_default_timezone_set( 'UTC' );

		$current_time_gmt = current_time( $format, true );
		$current_time     = current_time( $format );

		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( $current_time_custom_timezone_gmt ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( $datetime->format( $format ) ), strtotime( $current_time_custom_timezone ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( $current_time_gmt ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( $datetime->format( $format ) ), strtotime( $current_time ), 'The dates should be equal', 2 );
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

		$this->assertEquals( $timestamp, current_time( 'timestamp', true ), 'The dates should be equal', 2 );
		$this->assertEquals( $timestamp, current_time( 'U', true ), 'The dates should be equal', 2 );

		$this->assertEquals( $wp_timestamp, current_time( 'timestamp' ), 'The dates should be equal', 2 );
		$this->assertEquals( $wp_timestamp, current_time( 'U' ), 'The dates should be equal', 2 );

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

		$this->assertEquals( strtotime( $datetime_local->format( DATE_W3C ) ), strtotime( current_time( DATE_W3C ) ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( $datetime_utc->format( DATE_W3C ) ), strtotime( current_time( DATE_W3C, true ) ), 'The dates should be equal', 2 );
	}
}
