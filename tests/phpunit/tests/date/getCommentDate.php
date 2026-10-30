<?php

/**
 * @group date
 * @group datetime
 * @group comment
 *
 * @covers ::get_comment_date
 */
class Tests_Date_GetCommentDate extends WP_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset the timezone option to the default value.
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_date_returns_correct_time_with_comment_id() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( 'August 29, 2020', get_comment_date( 'F j, Y', $c ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_date_returns_correct_time_with_empty_format() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( 'August 29, 2020', get_comment_date( '', $c ) );
		$this->assertSame( 'August 29, 2020', get_comment_date( false, $c ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_time_returns_correct_time() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$GLOBALS['comment'] = get_comment( $c );
		$this->assertSame( '1:51 am', get_comment_time( 'g:i a' ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_time_returns_correct_time_with_empty_format() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$GLOBALS['comment'] = get_comment( $c );
		$this->assertSame( '1:51 am', get_comment_time( '' ) );
		$this->assertSame( '1:51 am', get_comment_time( false ) );
	}

	/**
	 * @ticket 48936
	 *
	 * @covers ::get_comment_time
	 */
	public function test_get_comment_time_with_gmt_uses_utc_time_zone() {
		update_option( 'timezone_string', 'Europe/Helsinki' );

		$c = self::factory()->comment->create(
			array(
				'comment_date'     => '2020-08-29 04:51:00',
				'comment_date_gmt' => '2020-08-29 01:51:00',
			)
		);

		$this->assertSame( '2020-08-29T01:51:00+00:00', get_comment_time( DATE_RFC3339, true, false, $c ), 'GMT time, not translated' );
		$this->assertSame( '2020-08-29T01:51:00+00:00', get_comment_time( DATE_RFC3339, true, true, $c ), 'GMT time, translated' );
		$this->assertSame( '2020-08-29T04:51:00+03:00', get_comment_time( DATE_RFC3339, false, false, $c ), 'Local time, not translated' );
		$this->assertSame( '2020-08-29T04:51:00+03:00', get_comment_time( DATE_RFC3339, false, true, $c ), 'Local time, translated' );
	}

	/**
	 * @ticket 48936
	 *
	 * @covers ::get_comment_time
	 * @covers ::get_comment_date
	 */
	public function test_get_comment_time_and_date_return_timestamps() {
		update_option( 'timezone_string', 'Europe/Helsinki' );

		$c = self::factory()->comment->create(
			array(
				'comment_date'     => '2020-08-29 04:51:00',
				'comment_date_gmt' => '2020-08-29 01:51:00',
			)
		);

		$timestamp    = strtotime( '2020-08-29 01:51:00 +0000' );
		$wp_timestamp = $timestamp + 3 * HOUR_IN_SECONDS;

		$this->assertSame( $timestamp, get_comment_time( 'U', true, false, $c ), 'GMT Unix timestamp' );
		$this->assertSame( $timestamp, get_comment_time( 'G', true, false, $c ), 'GMT Unix timestamp with G format' );
		$this->assertSame( $wp_timestamp, get_comment_time( 'U', false, false, $c ), 'Local timestamp' );
		$this->assertSame( $wp_timestamp, get_comment_date( 'U', $c ), 'Local timestamp from get_comment_date()' );
	}

	/**
	 * @ticket 48936
	 *
	 * @covers ::get_comment_time
	 */
	public function test_get_comment_time_returns_feed_date_formats() {
		update_option( 'timezone_string', 'Europe/Helsinki' );

		$c = self::factory()->comment->create(
			array(
				'comment_date'     => '2020-08-29 04:51:00',
				'comment_date_gmt' => '2020-08-29 01:51:00',
			)
		);

		$this->assertSame( '2020-08-29T01:51:00Z', get_comment_time( 'Y-m-d\TH:i:s\Z', true, false, $c ) );
		$this->assertSame( 'Sat, 29 Aug 2020 01:51:00 +0000', get_comment_time( 'D, d M Y H:i:s +0000', true, false, $c ) );
	}
}
