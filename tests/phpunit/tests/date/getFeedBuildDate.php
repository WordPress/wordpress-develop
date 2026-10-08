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
	 * Test that get_feed_build_date() returns the correct modified time
	 * when $wp_query->posts holds objects with only ID and post_parent
	 * (from fields => 'id=>parent').
	 *
	 * @ticket 59956
	 */
	public function test_should_return_correct_build_date_for_id_parent_query() {
		global $wp_query;

		$post_id = self::factory()->post->create(
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

		$wp_query = new WP_Query(
			array(
				'p'      => $post_id,
				'fields' => 'id=>parent',
			)
		);

		$this->assertSame( '2020-01-01T00:00:00+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that post IDs are looked up with a single query rather than one
	 * query per post.
	 *
	 * Code review found that calling {@see get_post()} on each ID from a
	 * fields => 'ids' query caused an N+1, since WP_Query does not prime the
	 * post cache for such queries.
	 *
	 * @ticket 59956
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 * @global wpdb     $wpdb     WordPress database abstraction object.
	 */
	public function test_should_prime_post_caches_for_id_only_query() {
		global $wp_query, $wpdb;

		$post_ids = self::factory()->post->create_many( 3 );

		$wp_query = new WP_Query(
			array(
				'post__in' => $post_ids,
				'fields'   => 'ids',
			)
		);

		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}

		$num_queries = $wpdb->num_queries;
		get_feed_build_date( DATE_RFC3339 );

		$this->assertSame( 1, $wpdb->num_queries - $num_queries, 'Expected the posts to be fetched in a single query.' );
	}

	/**
	 * Test that WP_Post objects in $wp_query->posts are used as-is rather
	 * than being looked up again by ID.
	 *
	 * Code review found that passing every WP_Post through {@see get_post()}
	 * calls {@see WP_Post::filter()}, which re-fetches any post whose filter
	 * is not 'raw'. That drops virtual posts which are not in the database,
	 * and swaps in the database copy of 'display' filtered posts.
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
	 * Test that an empty ID in $wp_query->posts is skipped rather than
	 * resolved to the global post.
	 *
	 * Code review found that {@see get_post()} treats an empty value as a
	 * request for the global $post, so a 0 entry could put the modified time
	 * of an unrelated post into the build date.
	 *
	 * @ticket 59956
	 */
	public function test_should_not_resolve_empty_post_id_to_global_post() {
		global $wp_query;

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		$GLOBALS['post'] = get_post(
			self::factory()->post->create(
				array(
					'post_date'     => '2024-06-15 12:00:00',
					'post_date_gmt' => '2024-06-15 12:00:00',
				)
			)
		);

		$wp_query             = new WP_Query();
		$wp_query->post_count = 2;
		$wp_query->posts      = array( 0, $post_id );

		$this->assertSame( '2020-01-01T00:00:00+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}

	/**
	 * Test that a comment feed uses the comment date when it is newer than
	 * the post, reading it straight from the WP_Comment objects.
	 *
	 * Code review found that resolving each comment through
	 * {@see get_comment()} was unnecessary, since WP_Query always populates
	 * $comments with WP_Comment objects, and it fired the 'get_comment'
	 * filter once per comment.
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

		$get_comment_filter = new MockAction();
		add_filter( 'get_comment', array( $get_comment_filter, 'filter' ) );

		$this->assertSame( '2024-06-15T12:00:00+00:00', get_feed_build_date( DATE_RFC3339 ) );
		$this->assertSame( 0, $get_comment_filter->get_call_count(), 'Expected comments to be read without calling get_comment().' );
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
