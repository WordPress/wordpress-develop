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
	 * Test that get_feed_build_date() does not throw a ValueError when no
	 * entry in $wp_query->posts resolves to a post, and falls back to the
	 * last modified time of any post instead.
	 *
	 * @ticket 59956
	 */
	public function test_should_not_error_when_modified_times_is_empty() {
		global $wp_query;

		self::factory()->post->create(
			array(
				'post_date'     => '2024-06-15 12:00:00',
				'post_date_gmt' => '2024-06-15 12:00:00',
			)
		);

		$deleted_post_id = self::factory()->post->create(
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		$wp_query = new WP_Query(
			array(
				'p'      => $deleted_post_id,
				'fields' => 'ids',
			)
		);
		$this->assertTrue( $wp_query->have_posts(), 'Expected the query to find the post.' );

		// Delete the post after the query runs, so its ID no longer resolves to a post.
		wp_delete_post( $deleted_post_id, true );

		$this->assertSame(
			'2024-06-15T12:00:00+00:00',
			get_feed_build_date( DATE_RFC3339 ),
			'Should fall back to last post modified when modified_times is empty.'
		);
	}

	/**
	 * Test that get_feed_build_date() returns the correct modified time
	 * when $wp_query->posts is an array of post IDs (from fields => 'ids')
	 * instead of WP_Post objects.
	 *
	 * Before this fix, {@see wp_list_pluck()} could not read post_modified_gmt
	 * from the integer IDs, so it triggered {@see _doing_it_wrong()} for each
	 * one and returned an empty array, on which {@see max()} threw a
	 * ValueError on PHP 8.
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
	 * Cold ID-only and partial results must retain the feed's latest date.
	 *
	 * @ticket 59956
	 * @dataProvider data_cold_post_query_fields
	 *
	 * @param non-falsy-string $fields Fields arg.
	 */
	public function test_should_return_correct_build_date_for_cold_query_fields( string $fields ) {
		global $wp_query;

		$post_ids = array();
		foreach ( array( '2020-01-01 00:00:00', '2022-01-01 00:00:00' ) as $date ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_date'     => $date,
					'post_date_gmt' => $date,
				)
			);
		}
		self::factory()->post->create(
			array(
				'post_date'     => '2024-06-15 12:00:00',
				'post_date_gmt' => '2024-06-15 12:00:00',
			)
		);
		$wp_query = new WP_Query(
			array(
				'post__in' => $post_ids,
				'fields'   => $fields,
			)
		);
		$this->assertSame( 2, $wp_query->post_count );
		foreach ( $post_ids as $post_id ) {
			wp_cache_delete( $post_id, 'posts' );
		}
		$num_queries = get_num_queries();

		$this->assertSame( '2022-01-01T00:00:00+00:00', get_feed_build_date( DATE_RFC3339 ) );
		$this->assertSame( 1, get_num_queries() - $num_queries, 'Expected one bulk post query and no metadata or term queries.' );
	}

	/**
	 * Data provider for {@see self::test_should_return_correct_build_date_for_cold_query_fields()}.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string }>
	 */
	public function data_cold_post_query_fields(): array {
		return array(
			'IDs'     => array( 'ids' ),
			'partial' => array( 'id=>parent' ),
		);
	}

	/**
	 * Test that WP_Post objects in $wp_query->posts are used as-is rather
	 * than being looked up again by ID.
	 *
	 * Passing a WP_Post through {@see get_post()} would re-fetch it by ID when
	 * its filter is not 'raw', dropping a virtual post that is not in the
	 * database.
	 *
	 * @ticket 59956
	 */
	public function test_should_use_virtual_post_objects_as_is() {
		global $wp_query;

		$virtual_post = new WP_Post(
			(object) array(
				'ID'                => -1,
				'post_modified_gmt' => '2021-03-04 05:06:07',
			)
		);

		$wp_query             = new WP_Query();
		$wp_query->post_count = 1;
		$wp_query->posts      = array( $virtual_post );

		$this->assertSame( '2021-03-04T05:06:07+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that a 'display' filtered WP_Post in $wp_query->posts is used
	 * as-is, keeping its in-memory modified time.
	 *
	 * @ticket 59956
	 */
	public function test_should_use_display_filtered_post_objects_as_is() {
		global $wp_query;

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		$post = get_post( $post_id, OBJECT, 'display' );
		$this->assertInstanceOf( WP_Post::class, $post );
		$post->post_modified_gmt = '2021-03-04 05:06:07';

		$wp_query             = new WP_Query();
		$wp_query->post_count = 1;
		$wp_query->posts      = array( $post );

		$this->assertSame( '2021-03-04T05:06:07+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that a comment feed uses the comment date when it is newer than
	 * the post.
	 *
	 * @ticket 59956
	 */
	public function test_should_use_comment_date_in_comment_feed() {
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_date'     => '2024-06-15 12:00:00',
				'comment_date_gmt' => '2024-06-15 12:00:00',
			)
		);

		$this->go_to( get_post_comments_feed_link( $post_id ) );
		$this->assertTrue( is_comment_feed(), 'Expected a comment feed.' );

		$this->assertSame( '2024-06-15T12:00:00+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that get_feed_build_date() works with invalid post dates.
	 *
	 * @ticket 48957
	 *
	 * @global WP_Query $wp_query WordPress Query object.
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

		$feed_build_date = get_feed_build_date( DATE_RFC3339 );
		$this->assertIsString( $feed_build_date );
		$this->assertEqualsWithDelta(
			strtotime( $datetime_utc->format( DATE_RFC3339 ) ),
			strtotime( $feed_build_date ),
			2,
			'Fall back to time of last post modified with no posts'
		);

		$post_broken = self::factory()->post->create_and_get();

		$post_broken->post_modified_gmt = '0';

		$wp_query->posts = array( $post_broken );

		$broken_feed_build_date = get_feed_build_date( DATE_RFC3339 );
		$this->assertIsString( $broken_feed_build_date );
		$this->assertEqualsWithDelta(
			strtotime( $datetime_utc->format( DATE_RFC3339 ) ),
			strtotime( $broken_feed_build_date ),
			2,
			'Fall back to time of last post modified with broken post object'
		);
	}
}
