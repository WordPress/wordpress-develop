<?php

/**
 * @group date
 * @group datetime
 *
 * @covers ::current_time
 */
class Tests_Date_CurrentTime extends WP_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset changed options to their default value.
		update_option( 'gmt_offset', 0 );
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_date_format_string() {
		update_option( 'gmt_offset', 6 );

		$format       = 'F j, Y, g:i a';
		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		$this->assertEqualsWithDelta( strtotime( gmdate( $format ) ), strtotime( current_time( $format, true ) ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( gmdate( $format, $wp_timestamp ) ), strtotime( current_time( $format ) ), 2, 'The dates should be equal' );
	}

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_mysql_format() {
		update_option( 'gmt_offset', 6 );

		$format       = 'Y-m-d H:i:s';
		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		$this->assertEqualsWithDelta( strtotime( gmdate( $format ) ), strtotime( current_time( 'mysql', true ) ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( gmdate( $format, $wp_timestamp ) ), strtotime( current_time( 'mysql' ) ), 2, 'The dates should be equal' );
	}

	/**
	 * @ticket 34378
	 */
	public function test_current_time_with_timestamp() {
		update_option( 'gmt_offset', 6 );

		$timestamp    = time();
		$wp_timestamp = $timestamp + 6 * HOUR_IN_SECONDS;

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEqualsWithDelta( $timestamp, current_time( 'timestamp', true ), 2, 'The dates should be equal' );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEqualsWithDelta( $wp_timestamp, current_time( 'timestamp' ), 2, 'The dates should be equal' );
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

		$this->assertEqualsWithDelta( strtotime( gmdate( $format ) ), strtotime( $current_time_custom_timezone_gmt ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( $datetime->format( $format ) ), strtotime( $current_time_custom_timezone ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( gmdate( $format ) ), strtotime( $current_time_gmt ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( $datetime->format( $format ) ), strtotime( $current_time ), 2, 'The dates should be equal' );
	}

	/**
	 * @ticket 40653
	 * @ticket 57998
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone The timezone to test.
	 */
	public function test_should_return_wp_timestamp( $timezone ) {
		update_option( 'timezone_string', $timezone );

		$timestamp = time();
		$datetime  = new DateTime( '@' . $timestamp );
		$datetime->setTimezone( wp_timezone() );
		$wp_timestamp = $timestamp + $datetime->getOffset();

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEqualsWithDelta( $timestamp, current_time( 'timestamp', true ), 2, 'When passing "timestamp", the date should be equal to time()' );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
		$this->assertEqualsWithDelta( $timestamp, current_time( 'U', true ), 2, 'When passing "U", the date should be equal to time()' );

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEqualsWithDelta( $wp_timestamp, current_time( 'timestamp' ), 2, 'When passing "timestamp", the date should be equal to calculated timestamp' );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEqualsWithDelta( $wp_timestamp, current_time( 'U' ), 2, 'When passing "U", the date should be equal to calculated timestamp' );

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertIsInt( current_time( 'timestamp' ), 'The returned timestamp should be an integer' );
	}

	/**
	 * @ticket 40653
	 * @ticket 57998
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone The timezone to test.
	 */
	public function test_should_return_correct_local_time( $timezone ) {
		update_option( 'timezone_string', $timezone );

		$timestamp      = time();
		$datetime_local = new DateTime( '@' . $timestamp );
		$datetime_local->setTimezone( wp_timezone() );
		$datetime_utc = new DateTime( '@' . $timestamp );
		$datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEqualsWithDelta( strtotime( $datetime_local->format( DATE_W3C ) ), strtotime( current_time( DATE_W3C ) ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( $datetime_utc->format( DATE_W3C ) ), strtotime( current_time( DATE_W3C, true ) ), 2, 'When passing "timestamp", the dates should be equal' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_timezones() {
		return array(
			array( 'Europe/Helsinki' ),
			array( 'Indian/Antananarivo' ),
			array( 'Australia/Adelaide' ),
		);
	}

	/**
	 * Ensures that deprecated timezone strings are handled correctly.
	 *
	 * @ticket 56468
	 */
	public function test_should_work_with_deprecated_timezone() {
		$format          = 'Y-m-d H:i';
		$timezone_string = 'America/Buenos_Aires'; // This timezone was deprecated pre-PHP 5.6.
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

		$this->assertSame( gmdate( $format ), $current_time_custom_timezone_gmt, 'The dates should be equal [1]' );
		$this->assertSame( $datetime->format( $format ), $current_time_custom_timezone, 'The dates should be equal [2]' );
		$this->assertSame( gmdate( $format ), $current_time_gmt, 'The dates should be equal [3]' );
		$this->assertSame( $datetime->format( $format ), $current_time, 'The dates should be equal [4]' );
	}

	/**
	 * Ensures an empty offset does not cause a type error.
	 *
	 * @ticket 57998
	 */
	public function test_empty_offset_does_not_cause_a_type_error() {
		// Ensure `wp_timezone_override_offset()` doesn't override offset.
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', '' );

		$expected = time();

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEqualsWithDelta( $expected, current_time( 'timestamp' ), 2, 'The timestamps should be equal' );
	}

	/**
	 * Ensures the offset applied in current_time() is correct.
	 *
	 * @ticket 57998
	 *
	 * @dataProvider data_partial_hour_timezones_with_timestamp
	 *
	 * @param float $partial_hour Partial hour GMT offset to test.
	 */
	public function test_partial_hour_timezones_with_timestamp( $partial_hour ) {
		// Ensure `wp_timezone_override_offset()` doesn't override offset.
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', $partial_hour );

		$expected = time() + (int) ( $partial_hour * HOUR_IN_SECONDS );

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$this->assertEqualsWithDelta( $expected, current_time( 'timestamp' ), 2, 'The timestamps should be equal' );
	}

	/**
	 * Tests the tests.
	 *
	 * Ensures the offsets match the stated timezones in the data provider.
	 *
	 * @ticket 57998
	 *
	 * @dataProvider data_partial_hour_timezones_with_timestamp
	 *
	 * @param float $partial_hour     Partial hour GMT offset to test.
	 * @param string $timezone_string Timezone string to test.
	 */
	public function test_partial_hour_timezones_match_datetime_offset( $partial_hour, $timezone_string ) {
		$timezone   = new DateTimeZone( $timezone_string );
		$datetime   = new DateTime( 'now', $timezone );
		$dst_offset = (int) $datetime->format( 'I' );

		// Timezone offset in hours.
		$offset = $timezone->getOffset( $datetime ) / HOUR_IN_SECONDS;

		/*
		 * Adjust for daylight saving time.
		 *
		 * DST adds an hour to the offset, the partial hour offset
		 * is set the the standard time offset so this removes the
		 * DST offset to avoid false negatives.
		 */
		$offset -= $dst_offset;

		$this->assertSame( $partial_hour, $offset, 'The offset should match to timezone.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_partial_hour_timezones_with_timestamp() {
		return array(
			'+12:45' => array( 12.75, 'Pacific/Chatham' ), // New Zealand, Chatham Islands.
			'+9:30'  => array( 9.5, 'Australia/Darwin' ), // Australian Northern Territory.
			'+05:30' => array( 5.5, 'Asia/Kolkata' ), // India and Sri Lanka.
			'+05:45' => array( 5.75, 'Asia/Kathmandu' ), // Nepal.
			'-03:30' => array( -3.50, 'Canada/Newfoundland' ), // Canada, Newfoundland.
			'-09:30' => array( -9.50, 'Pacific/Marquesas' ), // French Polynesia, Marquesas Islands.
		);
	}
}
