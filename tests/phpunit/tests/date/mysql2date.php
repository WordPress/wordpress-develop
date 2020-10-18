<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_mysql2date extends WP_UnitTestCase {

	function tearDown() {
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
		date_default_timezone_set( 'UTC' );

		parent::tearDown();
	}

	/**
	 * @ticket 28310
	 */
	function test_mysql2date_returns_false_with_no_date() {
		$this->assertFalse( mysql2date( 'F j, Y H:i:s', '' ) );
	}

	/**
	 * @ticket 28310
	 */
	function test_mysql2date_returns_gmt_or_unix_timestamp() {
		$this->assertSame( 441013392, mysql2date( 'G', '1983-12-23 07:43:12' ) );
		$this->assertSame( 441013392, mysql2date( 'U', '1983-12-23 07:43:12' ) );
	}

	/**
	 * @ticket 28992
	 */
	function test_mysql2date_should_format_time() {
		$timezone = 'Europe/Kiev';
		update_option( 'timezone_string', $timezone );
		$datetime = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$rfc3339  = $datetime->format( DATE_RFC3339 );
		$mysql    = $datetime->format( 'Y-m-d H:i:s' );

		$this->assertSame( $rfc3339, mysql2date( DATE_RFC3339, $mysql ) );
		$this->assertSame( $rfc3339, mysql2date( DATE_RFC3339, $mysql, false ) );
	}

	/**
	 * @ticket 28992
	 */
	function test_mysql2date_should_format_time_with_changed_time_zone() {
		$timezone = 'Europe/Kiev';
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
		date_default_timezone_set( $timezone );
		update_option( 'timezone_string', $timezone );
		$datetime = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$rfc3339  = $datetime->format( DATE_RFC3339 );
		$mysql    = $datetime->format( 'Y-m-d H:i:s' );

		$this->assertSame( $rfc3339, mysql2date( DATE_RFC3339, $mysql ) );
		$this->assertSame( $rfc3339, mysql2date( DATE_RFC3339, $mysql, false ) );
	}

	/**
	 * @ticket 28992
	 */
	function test_mysql2date_should_return_wp_timestamp() {
		$timezone = 'Europe/Kiev';
		update_option( 'timezone_string', $timezone );
		$datetime     = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$wp_timestamp = $datetime->getTimestamp() + $datetime->getOffset();
		$mysql        = $datetime->format( 'Y-m-d H:i:s' );

		$this->assertSame( $wp_timestamp, mysql2date( 'U', $mysql, false ) );
		$this->assertSame( $wp_timestamp, mysql2date( 'G', $mysql, false ) );
	}

	/**
	 * @ticket 28992
	 */
	function test_mysql2date_should_return_unix_timestamp_for_gmt_time() {
		$timezone = 'Europe/Kiev';
		update_option( 'timezone_string', $timezone );
		$datetime  = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$timestamp = $datetime->getTimestamp();
		$mysql     = $datetime->format( 'Y-m-d H:i:s' );

		$this->assertSame( $timestamp, mysql2date( 'U', $mysql, false ) );
		$this->assertSame( $timestamp, mysql2date( 'G', $mysql, false ) );
	}
}
