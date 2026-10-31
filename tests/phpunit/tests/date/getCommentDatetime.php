<?php

/**
 * @group date
 * @group datetime
 * @group comment
 *
 * @covers ::get_comment_datetime
 */
class Test_Date_GetCommentDatetime extends WP_UnitTestCase {

	/**
	 * Tests that get_comment_datetime() returns false when the comment is not found.
	 *
	 * This test ensures that the get_comment_datetime() function correctly handles
	 * cases where an invalid or non-existent comment ID is provided.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_returns_false_when_comment_not_found() {
		$result = get_comment_datetime( 999999 ); // Non-existent comment ID
		$this->assertFalse( $result );
	}

	/**
	 * Tests that get_comment_datetime() returns a valid DateTimeImmutable object for a valid comment.
	 *
	 * This test creates a comment with a specific date and time, then verifies that
	 * get_comment_datetime() returns a DateTimeImmutable object with the correct date and time.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_returns_datetime_for_valid_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$result = get_comment_datetime( $comment_id );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );
		$this->assertEquals( '2020-08-29 01:51:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Tests that get_comment_datetime() returns false for a comment with a zero date.
	 *
	 * This test creates a comment with a zero date ('0000-00-00 00:00:00') and verifies
	 * that get_comment_datetime() returns false when attempting to retrieve the datetime
	 * for this comment.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_returns_false_for_zero_date() {
		$comment_id = self::factory()->comment->create( array( 'comment_date' => '0000-00-00 00:00:00' ) );

		$result = get_comment_datetime( $comment_id );

		$this->assertFalse( $result );
	}


	/**
	 * Tests that get_comment_datetime() correctly handles the local timezone.
	 *
	 * This test creates a comment with a specific date, sets a custom timezone,
	 * and verifies that get_comment_datetime() returns a DateTimeImmutable object
	 * with the correct timezone and date.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_handles_local_timezone() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date' => '2023-05-15 10:30:00',
			)
		);

		$timezone = get_option( 'timezone_string' );
		// Set a custom timezone for testing
		update_option( 'timezone_string', 'America/New_York' );

		$datetime = get_comment_datetime( $comment_id );

		$this->assertInstanceOf( 'DateTimeImmutable', $datetime );
		$this->assertEquals( 'America/New_York', $datetime->getTimezone()->getName() );
		$this->assertEquals( '2023-05-15 10:30:00', $datetime->format( 'Y-m-d H:i:s' ) );

		// Reset timezone
		update_option( 'timezone_string', $timezone );
	}

	/**
	 * Tests that get_comment_datetime() correctly handles GMT timezone.
	 *
	 * This test creates a comment with a specific GMT date and time, then verifies
	 * that get_comment_datetime() returns a DateTimeImmutable object with the correct
	 * GMT timezone and date when the 'gmt' parameter is used.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_handles_gmt_timezone() {
		$gmt_time   = '2023-07-15 10:30:00';
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date_gmt' => $gmt_time,
				'comment_date'     => '2023-07-15 05:30:00', // Assuming -5 hours offset
			)
		);

		$result = get_comment_datetime( $comment_id, 'gmt' );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );
		$this->assertEquals( '+00:00', $result->getTimezone()->getName() );
		$this->assertEquals( $gmt_time, $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Tests that get_comment_datetime() correctly converts GMT time to local timezone.
	 *
	 * This test creates a comment with a GMT time, sets the WordPress timezone to
	 * America/New_York, and verifies that get_comment_datetime() returns a
	 * DateTimeImmutable object with the correct local time and timezone.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_converts_gmt_to_local_timezone() {
		// Set up a mock comment with GMT time
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date_gmt' => '2023-05-15 10:00:00',
			)
		);

		$timezone = get_option( 'timezone_string' );
		// Set a specific timezone for WordPress
		update_option( 'timezone_string', 'America/New_York' );

		// Get the comment datetime with 'gmt' source
		$result = get_comment_datetime( $comment_id, 'gmt' );

		// Assert that the result is a DateTimeImmutable object
		$this->assertInstanceOf( DateTimeImmutable::class, $result );

		// Assert that the timezone is set to the WordPress timezone
		$this->assertEquals( 'America/New_York', $result->getTimezone()->getName() );

		// Assert that the time is correctly converted (10:00 UTC to 06:00 EDT)
		$this->assertEquals( '2023-05-15 06:00:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Tests that get_comment_datetime() correctly handles Daylight Saving Time transitions.
	 *
	 * This test creates comments with dates during and outside of Daylight Saving Time,
	 * and verifies that get_comment_datetime() returns the correct timezone abbreviation
	 * (EDT or EST) for each case.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_handles_daylight_saving_time_transitions() {
		// Set up a comment with a date during DST
		$dst_date   = '2023-07-15 12:00:00';
		$comment_id = self::factory()->comment->create( array( 'comment_date' => $dst_date ) );

		$timezone = get_option( 'timezone_string' );
		// Set WordPress timezone to a location that observes DST
		update_option( 'timezone_string', 'America/New_York' );

		// Get the datetime for the comment
		$datetime = get_comment_datetime( $comment_id );

		// Assert that the datetime is correct and in EDT
		$this->assertInstanceOf( DateTimeImmutable::class, $datetime );
		$this->assertEquals( 'EDT', $datetime->format( 'T' ) );

		// Change the comment date to a non-DST date
		$non_dst_date = '2023-01-15 12:00:00';
		wp_update_comment(
			array(
				'comment_ID'   => $comment_id,
				'comment_date' => $non_dst_date,
			)
		);

		// Get the datetime for the updated comment
		$datetime = get_comment_datetime( $comment_id );

		// Assert that the datetime is correct and in EST
		$this->assertInstanceOf( DateTimeImmutable::class, $datetime );
		$this->assertEquals( 'EST', $datetime->format( 'T' ) );

		// Reset timezone
		update_option( 'timezone_string', $timezone );
	}

	/**
	 * Tests that get_comment_datetime() returns consistent results across different timezones.
	 *
	 * This test creates two comments with the same UTC time but in different timezones,
	 * and verifies that get_comment_datetime() returns equivalent DateTimeImmutable objects
	 * for both comments, regardless of the site's timezone setting.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_consistent_across_timezones() {
		// Create a comment with a specific date in UTC
		$utc_comment_id = self::factory()->comment->create(
			array(
				'comment_date_gmt' => '2023-05-15 10:00:00',
				'comment_date'     => get_date_from_gmt( '2023-05-15 10:00:00' ),
			)
		);

		$utc_datetime = get_comment_datetime( $utc_comment_id );

		$timezone = get_option( 'timezone_string' );
		// Change the timezone to New York
		update_option( 'timezone_string', 'America/New_York' );

		// Create another comment with the same UTC time
		$ny_comment_id = self::factory()->comment->create(
			array(
				'comment_date_gmt' => '2023-05-15 10:00:00',
				'comment_date'     => get_date_from_gmt( '2023-05-15 10:00:00' ),
			)
		);

		$ny_datetime = get_comment_datetime( $ny_comment_id );

		// Assert that both datetime objects are equal
		$this->assertEquals( $utc_datetime, $ny_datetime );

		// Assert that the timezone is set to the site's timezone for both
		$this->assertEquals( '+00:00', $utc_datetime->getTimezone()->getName() );
		$this->assertEquals( wp_timezone()->getName(), $ny_datetime->getTimezone()->getName() );

		// Reset timezone
		update_option( 'timezone_string', $timezone );
	}


	/**
	 * Tests that get_comment_datetime() handles invalid dates gracefully.
	 *
	 * This test creates a comment with an invalid date and verifies that
	 * get_comment_datetime() returns false when attempting to retrieve
	 * the datetime for this comment.
	 *
	 * @return void
	 */
	public function test_get_comment_datetime_handles_invalid_date_gracefully() {
		$comment = self::factory()->comment->create( array( 'comment_date' => 'invalid-date' ) );

		$result = get_comment_datetime( $comment );

		$this->assertFalse( $result, 'get_comment_datetime should return false for invalid date' );
	}
}
