<?php

/**
 * @group date
 * @group datetime
 * @group feed
 * @covers ::get_feed_build_date
 */
class Tests_Date_GetFeedBuildDate extends WP_UnitTestCase {

	function tear_down() {
		global $wp_query;

		update_option( 'timezone_string', 'UTC' );

		unset( $wp_query );

		parent::tear_down();
	}

	/**
	 * @ticket 48675
	 */
	public function test_should_return_correct_feed_build_date() {
		global $wp_query;

		$timezone = 'America/Chicago';
		update_option( 'timezone_string', $timezone );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2018-07-22 21:13:23',
				'post_date_gmt' => '2018-07-23 03:13:23',
			)
		);

		$wp_query = new WP_Query( array( 'p' => $post_id ) );

		$this->assertSame( '2018-07-23T03:13:23+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that get_feed_build_date() works with invalid post dates.
	 *
	 * @ticket 48957
	 */
	public function test_should_fall_back_to_last_post_modified() {
		global $wp_query;

		update_option( 'timezone_string', 'Europe/Kiev' );
		$datetime     = new DateTimeImmutable( 'now', wp_timezone() );
		$datetime_utc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		$wp_query->posts = array();

		$this->assertFalse( get_feed_build_date( DATE_RFC3339 ), 'False when unable to determine valid time' );

		$this->factory->post->create(
			array(
				'post_date' => $datetime->format( 'Y-m-d H:i:s' ),
			)
		);

		$this->assertEqualsWithDelta(
			strtotime( $datetime_utc->format( DATE_RFC3339 ) ),
			strtotime( get_feed_build_date( DATE_RFC3339 ) ),
			2,
			'Fall back to time of last post modified with no posts'
		);

		$post_id_broken = $this->factory->post->create();
		$post_broken    = get_post( $post_id_broken );

		$post_broken->post_modified_gmt = 0;

		$wp_query->posts = array( $post_broken );

		$this->assertEqualsWithDelta(
			strtotime( $datetime_utc->format( DATE_RFC3339 ) ),
			strtotime( get_feed_build_date( DATE_RFC3339 ) ),
			2,
			'Fall back to time of last post modified with broken post object'
		);
	}
}
