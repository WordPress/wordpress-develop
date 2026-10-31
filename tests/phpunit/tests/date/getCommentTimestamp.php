<?php

/**
 * @group date
 * @group datetime
 * @group comment
 *
 * @covers ::get_comment_timestamp
 */
class Tests_Date_GetCommentTimestamp extends WP_UnitTestCase {

	/**
	 * Tests that get_comment_timestamp() returns false when get_comment_datetime() returns false.
	 *
	 * This test function adds a filter to 'get_comment' that returns a comment object
	 * with an invalid date, then checks if get_comment_timestamp() returns false as expected.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_returns_false_when_get_comment_datetime_returns_false() {
		add_filter(
			'get_comment',
			function () {
				return (object) array( 'comment_date' => '0000-00-00 00:00:00' );
			}
		);

		$this->assertFalse( get_comment_timestamp() );

		remove_all_filters( 'get_comment' );
	}

	/**
	 * Tests that get_comment_timestamp() returns a valid timestamp for a valid comment.
	 *
	 * This test creates a comment with a specific date and time, then verifies that
	 * get_comment_timestamp() returns the correct Unix timestamp for that date.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_returns_valid_timestamp_for_valid_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date'     => '2023-01-01 12:00:00',
				'comment_date_gmt' => '2023-01-01 12:00:00',
			)
		);

		$timestamp = get_comment_timestamp( $comment_id );

		$this->assertIsInt( $timestamp );
		$this->assertEquals( 1672574400, $timestamp );
	}

	/**
	 * Tests that get_comment_timestamp() correctly handles different timestamp sources.
	 *
	 * This test creates a comment with different local and GMT dates, then verifies
	 * that get_comment_timestamp() returns the correct timestamps for both local
	 * and GMT sources.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_handles_different_sources() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date'     => '2023-01-01 12:00:00',
				'comment_date_gmt' => '2023-01-01 10:00:00',
			)
		);

		$local_timestamp = get_comment_timestamp( $comment_id, 'local' );
		$gmt_timestamp   = get_comment_timestamp( $comment_id, 'gmt' );

		$this->assertNotEquals( $local_timestamp, $gmt_timestamp );
		$this->assertEquals( 1672574400, $local_timestamp ); // 2023-01-01 12:00:00 UTC
		$this->assertEquals( 1672567200, $gmt_timestamp );   // 2023-01-01 10:00:00 UTC
	}


	/**
	 * Tests the get_comment_timestamp() function with different timezone settings.
	 *
	 * This test creates a comment with different local and GMT dates, then verifies
	 * that get_comment_timestamp() returns the correct timestamps for both local
	 * and GMT sources. It also ensures proper cleanup after the test.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_with_different_timezones() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date'     => '2023-01-01 12:00:00',
				'comment_date_gmt' => '2023-01-01 10:00:00',
			)
		);

		// Test with local time
		$local_timestamp = get_comment_timestamp( $comment_id, 'local' );
		$this->assertEquals( strtotime( '2023-01-01 12:00:00' ), $local_timestamp );

		// Test with GMT time
		$gmt_timestamp = get_comment_timestamp( $comment_id, 'gmt' );
		$this->assertEquals( strtotime( '2023-01-01 10:00:00' ), $gmt_timestamp );

		// Clean up
		wp_delete_comment( $comment_id, true );
	}

	public function test_get_comment_timestamp_handles_null_comment() {
		$this->assertFalse( get_comment_timestamp( null ) );
	}


	/**
	 * Tests that get_comment_timestamp() correctly handles future dates.
	 *
	 * This test creates a comment with a future date (one week from now) and verifies
	 * that get_comment_timestamp() returns the correct timestamp for that future date.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_handles_future_dates() {
		$future_date = gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
		$comment_id  = self::factory()->comment->create( array( 'comment_date' => $future_date ) );

		$timestamp = get_comment_timestamp( $comment_id );

		$this->assertIsInt( $timestamp );
		$this->assertGreaterThan( time(), $timestamp );
		$this->assertEquals( strtotime( $future_date ), $timestamp );
	}

	/**
	 * Tests the behavior of get_comment_timestamp() during Daylight Saving Time (DST) transition.
	 *
	 * This test function creates comments just before and after the DST transition
	 * and verifies that get_comment_timestamp() returns the correct Unix timestamps
	 * for both local and GMT times.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_at_dst_transition() {
		$timezone = 'America/New_York';
		update_option( 'timezone_string', $timezone );

		// Create a comment just before DST transition
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date' => '2023-03-12 01:59:59',
			)
		);

		// Test local time
		$timestamp = get_comment_timestamp( $comment_id, 'local' );
		$this->assertEquals( 1678604399, $timestamp );

		// Test GMT time
		$timestamp_gmt = get_comment_timestamp( $comment_id, 'gmt' );
		$this->assertEquals( 1678604399, $timestamp_gmt );

		// Create a comment just after DST transition
		$comment_id_after = self::factory()->comment->create(
			array(
				'comment_date' => '2023-03-12 03:00:01',
			)
		);

		// Test local time after DST transition
		$timestamp_after = get_comment_timestamp( $comment_id_after, 'local' );
		$this->assertEquals( 1678604401, $timestamp_after );

		// Test GMT time after DST transition
		$timestamp_after_gmt = get_comment_timestamp( $comment_id_after, 'gmt' );
		$this->assertEquals( 1678604401, $timestamp_after_gmt );
	}

	/**
	 * Tests that get_comment_timestamp() correctly handles very old dates.
	 *
	 * This test creates a comment with a date from the year 1800 and verifies that
	 * get_comment_timestamp() returns the correct Unix timestamp for that date.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_handles_very_old_dates() {
		$old_date = '1800-01-01 12:00:00';
		$comment  = $this->factory->comment->create_and_get( array( 'comment_date' => $old_date ) );

		$timestamp = get_comment_timestamp( $comment );

		$this->assertIsInt( $timestamp );
		$this->assertLessThan( 0, $timestamp );
		$this->assertEquals( strtotime( $old_date ), $timestamp );
	}

	/**
	 * Tests that get_comment_timestamp() returns consistent results for multiple calls.
	 *
	 * This test creates a comment with a specific date and time, then calls
	 * get_comment_timestamp() multiple times to ensure it returns the same
	 * timestamp value for each call.
	 *
	 * @return void
	 */
	public function test_get_comment_timestamp_returns_consistent_results() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_date'     => '2023-01-01 12:00:00',
				'comment_date_gmt' => '2023-01-01 12:00:00',
			)
		);

		$timestamp1 = get_comment_timestamp( $comment_id );
		$timestamp2 = get_comment_timestamp( $comment_id );
		$timestamp3 = get_comment_timestamp( $comment_id );

		$this->assertSame( $timestamp1, $timestamp2 );
		$this->assertSame( $timestamp2, $timestamp3 );
		$this->assertSame( $timestamp1, $timestamp3 );
	}
}
