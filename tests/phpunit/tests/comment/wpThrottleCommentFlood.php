<?php

/**
 * Tests for wp_throttle_comment_flood().
 *
 * @group comment
 *
 * @covers ::wp_throttle_comment_flood
 */
class Tests_Comment_WpThrottleCommentFlood extends WP_UnitTestCase {

	/**
	 * Tests that a comment is blocked when it comes less than 15 seconds after the last one.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_seconds_between_comments
	 *
	 * @param int  $seconds  Seconds between the last comment and the new one.
	 * @param bool $expected Whether the new comment should be blocked.
	 */
	public function test_should_block_comments_posted_less_than_15_seconds_apart( $seconds, $expected ) {
		$time_lastcomment = 1700000000;

		$this->assertSame( $expected, wp_throttle_comment_flood( false, $time_lastcomment, $time_lastcomment + $seconds ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_seconds_between_comments() {
		return array(
			'same second'      => array( 0, true ),
			'one second'       => array( 1, true ),
			'seven seconds'    => array( 7, true ),
			'fourteen seconds' => array( 14, true ),
			'fifteen seconds'  => array( 15, false ),
			'sixteen seconds'  => array( 16, false ),
			'one minute'       => array( 60, false ),
			'one hour'         => array( HOUR_IN_SECONDS, false ),
		);
	}

	/**
	 * Tests that a decision to block that was made earlier is kept.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_seconds_between_comments
	 *
	 * @param int $seconds Seconds between the last comment and the new one.
	 */
	public function test_should_keep_an_earlier_decision_to_block( $seconds ) {
		$time_lastcomment = 1700000000;

		$this->assertTrue( wp_throttle_comment_flood( true, $time_lastcomment, $time_lastcomment + $seconds ) );
	}

	/**
	 * Tests that the function is the default callback of the `comment_flood_filter` filter.
	 *
	 * @ticket 66494
	 */
	public function test_should_be_hooked_to_comment_flood_filter_by_default() {
		$this->assertSame( 10, has_filter( 'comment_flood_filter', 'wp_throttle_comment_flood' ) );

		$this->assertTrue( apply_filters( 'comment_flood_filter', false, 100, 105 ), 'A comment 5 seconds after the last one should be blocked.' );
		$this->assertFalse( apply_filters( 'comment_flood_filter', false, 100, 200 ), 'A comment 100 seconds after the last one should be allowed.' );
	}
}
