<?php

/**
 * @group date
 * @group datetime
 * @group feed
 *
 * @covers ::get_feed_build_date
 */
class Tests_Date_GetFeedBuildDate extends WP_UnitTestCase {

	public function tear_down() {
		global $wp_query;

		update_option( 'timezone_string', '' );

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
	 * Test that get_feed_build_date() does not throw a ValueError
	 * when $wp_query->posts contains no entries that resolve to a
	 * WP_Post (e.g. invalid IDs that get_post() returns null for).
	 *
	 * @ticket 59956
	 */
	public function test_should_not_error_when_modified_times_is_empty() {
		global $wp_query;

		$datetime     = new DateTimeImmutable( 'now', wp_timezone() );
		$datetime_utc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		self::factory()->post->create(
			array(
				'post_date' => $datetime->format( 'Y-m-d H:i:s' ),
			)
		);

		/*
		 * Build a WP_Query where have_posts() is true but no entry can be
		 * resolved to a WP_Post. Setting post_count without populating posts
		 * with valid data exercises the empty $modified_times fallback path.
		 */
		$wp_query             = new WP_Query();
		$wp_query->post_count = 1;
		$wp_query->posts      = array( PHP_INT_MAX ); // Non-existent post ID.

		$result = get_feed_build_date( DATE_RFC3339 );
		$this->assertIsString( $result );

		$this->assertEqualsWithDelta(
			strtotime( $datetime_utc->format( DATE_RFC3339 ) ),
			strtotime( $result ),
			2,
			'Should fall back to last post modified when modified_times is empty.'
		);
	}

	/**
	 * Test that get_feed_build_date() returns the correct modified time
	 * when $wp_query->posts is an array of post IDs (from fields => 'ids')
	 * instead of WP_Post objects.
	 *
	 * Before this test, the function would fall back to get_lastpostmodified()
	 * and silently return the site-wide latest modified time instead of the
	 * latest modified time of the posts actually in the feed.
	 *
	 * @ticket 59956
	 */
	public function test_should_return_correct_build_date_for_id_only_query() {
		// Create two posts with different modified times. Post B is newer than Post A.
		$older_post_id = self::factory()->post->create(
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		self::factory()->post->create(
			array(
				'post_date'     => '2024-06-15 12:00:00',
				'post_date_gmt' => '2024-06-15 12:00:00',
			)
		);

		/*
		 * Query for ONLY the older post using fields => 'ids'. The feed's
		 * <lastBuildDate> must reflect the modified time of the older post,
		 * not the site-wide latest (which would be the newer post that is
		 * not in the feed).
		 */
		global $wp_query;
		$wp_query = new WP_Query(
			array(
				'p'      => $older_post_id,
				'fields' => 'ids',
			)
		);

		$this->assertSame(
			'2020-01-01T00:00:00+00:00',
			get_feed_build_date( DATE_RFC3339 ),
			'Build date should match the modified time of the post in the feed, not the site-wide latest.'
		);
	}

	/**
	 * Test that get_feed_build_date() works with invalid post dates.
	 *
	 * @ticket 48957
	 */
	public function test_should_fall_back_to_last_post_modified() {
		global $wp_query;

		update_option( 'timezone_string', 'Europe/Helsinki' );
		$datetime     = new DateTimeImmutable( 'now', wp_timezone() );
		$datetime_utc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		$wp_query->posts = array();

		$this->assertFalse( get_feed_build_date( DATE_RFC3339 ), 'False when unable to determine valid time' );

		self::factory()->post->create(
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

		$post_id_broken = self::factory()->post->create();
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
