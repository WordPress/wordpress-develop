<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_Current_Time extends WP_UnitTestCase {

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_date_format_string() {
		update_option( 'gmt_offset', 6 );

		$format       = 'F j, Y, g:i a';
		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( current_time( $format, true ) ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( gmdate( $format, $wp_timestamp ) ), strtotime( current_time( $format ) ), 'The dates should be equal', 2 );
	}

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_mysql_format() {
		update_option( 'gmt_offset', 6 );

		$format       = 'Y-m-d H:i:s';
		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( current_time( 'mysql', true ) ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( gmdate( $format, $wp_timestamp ) ), strtotime( current_time( 'mysql' ) ), 'The dates should be equal', 2 );
	}

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_timestamp() {
		update_option( 'gmt_offset', 6 );

		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEquals( $timestamp, current_time( 'timestamp', true ), 'The dates should be equal', 2 );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEquals( $wp_timestamp, current_time( 'timestamp' ), 'The dates should be equal', 2 );
	}

	/**
	 * @ticket 37440
	 */
	public function test_should_work_with_changed_timezone() {
		$format          = 'Y-m-d H:i:s';
		$timezone_string = 'America/Regina';
		update_option( 'timezone_string', $timezone_string );
		$datetime = new DateTime( 'now', new DateTimeZone( $timezone_string ) );

		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
		date_default_timezone_set( $timezone_string );

		$current_time_custom_timezone_gmt = current_time( $format, true );
		$current_time_custom_timezone     = current_time( $format );

		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
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

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEquals( $timestamp, current_time( 'timestamp', true ), 'The dates should be equal', 2 );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEquals( $timestamp, current_time( 'U', true ), 'The dates should be equal', 2 );

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEquals( $wp_timestamp, current_time( 'timestamp' ), 'The dates should be equal', 2 );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEquals( $wp_timestamp, current_time( 'U' ), 'The dates should be equal', 2 );

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
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
