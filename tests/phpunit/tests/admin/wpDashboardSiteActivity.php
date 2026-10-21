<?php
/**
 * Tests for the Activity and Recent Comments dashboard widgets.
 *
 * @group admin
 */
class Tests_Admin_wpDashboardSiteActivity extends WP_UnitTestCase {

	protected static int $user_id;

	protected static int $other_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		self::$user_id       = $factory->user->create(
			array(
				'display_name' => 'Current Writer',
				'role'         => 'author',
			)
		);
		self::$other_user_id = $factory->user->create(
			array(
				'display_name' => 'Guest Writer',
				'role'         => 'author',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
		self::delete_user( self::$other_user_id );
	}

	public function set_up() {
		parent::set_up();

		set_current_screen( 'dashboard' );
		wp_set_current_user( self::$user_id );
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	public function tear_down() {
		delete_option( 'dashboard_recent_comments_widget_registered' );
		unset( $GLOBALS['wp_meta_boxes']['dashboard'] );

		parent::tear_down();
	}

	/**
	 * Creates a published post on today's calendar day in a previous year.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Post title.
	 * @param int    $years_ago Number of years before today.
	 * @param array  $post_args Additional post arguments.
	 * @return int Post ID.
	 */
	private function create_matching_post( $author_id, $title = 'A memory from last year', $years_ago = 1, $post_args = array() ) {
		$today = current_datetime();

		if ( '02-29' === $today->format( 'm-d' ) ) {
			$years_ago *= 4;
		}

		$post_date = ( (int) $today->format( 'Y' ) - $years_ago ) . '-' . $today->format( 'm-d' ) . ' 12:00:00';

		return self::factory()->post->create(
			array_merge(
				array(
					'post_author'   => $author_id,
					'post_date'     => $post_date,
					'post_date_gmt' => get_gmt_from_date( $post_date ),
					'post_status'   => 'publish',
					'post_title'    => $title,
				),
				$post_args
			)
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::_wp_dashboard_on_this_day_date_query_clause
	 */
	public function test_date_query_includes_february_29_on_february_28_in_non_leap_year() {
		$date = new DateTimeImmutable( '2023-02-28 12:00:00', wp_timezone() );

		$this->assertSame(
			array(
				'relation' => 'OR',
				array(
					'month' => 2,
					'day'   => 28,
				),
				array(
					'month' => 2,
					'day'   => 29,
				),
			),
			_wp_dashboard_on_this_day_date_query_clause( $date )
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_query_returns_only_published_posts_from_this_day_in_previous_years() {
		$matching_id = $this->create_matching_post( self::$user_id );
		$this->create_matching_post( self::$user_id, 'A draft memory', 2, array( 'post_status' => 'draft' ) );

		$today       = current_datetime();
		$current_day = $today->format( 'Y-m-d' ) . ' 12:00:00';
		self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_date'   => $current_day,
				'post_status' => 'publish',
				'post_title'  => 'A memory from this year',
			)
		);

		$nearby_date = $today->modify( '-1 year +1 day' )->format( 'Y-m-d' ) . ' 12:00:00';
		self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_date'   => $nearby_date,
				'post_status' => 'publish',
				'post_title'  => 'A nearby memory',
			)
		);

		$posts = wp_dashboard_on_this_day_get_posts();

		$this->assertSame( array( $matching_id ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_query_defaults_to_ten_posts_and_limit_is_filterable() {
		for ( $years_ago = 1; $years_ago <= 11; $years_ago++ ) {
			$this->create_matching_post( self::$user_id, 'Anniversary post ' . $years_ago, $years_ago );
		}

		$this->assertCount( 10, wp_dashboard_on_this_day_get_posts() );

		add_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_limit' ) );
		try {
			$this->assertCount( 3, wp_dashboard_on_this_day_get_posts() );
		} finally {
			remove_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_limit' ) );
		}
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_section_renders_posts_from_all_authors_and_untitled_excerpts() {
		$this->create_matching_post( self::$user_id, 'A note from me' );
		$this->create_matching_post( self::$other_user_id, 'A note from someone else', 2 );
		$this->create_matching_post(
			self::$user_id,
			'',
			3,
			array( 'post_excerpt' => 'word1 word2 word3 word4 word5 word6 word7 word8 word9 word10 word11 word12 word13 word14 word15 word16' )
		);

		ob_start();
		$result = wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Published On This Day', $output );
		$this->assertStringContainsString( 'A note from me', $output );
		$this->assertStringNotContainsString( 'by Current Writer', $output );
		$this->assertStringContainsString( 'A note from someone else', $output );
		$this->assertStringContainsString( 'by Guest Writer', $output );
		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringContainsString( 'word15', $output );
		$this->assertStringNotContainsString( 'word16', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_section_hides_untitled_excerpt_for_unreadable_posts() {
		$this->create_matching_post(
			self::$other_user_id,
			'',
			1,
			array(
				'post_excerpt' => 'Unreadable private anniversary memory.',
				'post_status'  => 'private',
			)
		);

		add_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );
		try {
			ob_start();
			wp_dashboard_on_this_day();
			$output = ob_get_clean();
		} finally {
			remove_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );
		}

		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringNotContainsString( 'Unreadable private anniversary memory.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_section_hides_untitled_excerpt_for_password_protected_posts() {
		$this->create_matching_post(
			self::$user_id,
			'',
			1,
			array(
				'post_excerpt'  => 'Password-protected anniversary memory.',
				'post_password' => 'secret',
			)
		);

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringNotContainsString( 'Password-protected anniversary memory.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_site_activity
	 */
	public function test_activity_renders_no_activity_message_without_posts() {
		ob_start();
		wp_dashboard_site_activity();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No activity yet!', $output );
		$this->assertStringNotContainsString( 'Published On This Day', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_site_activity
	 * @covers ::wp_dashboard_recent_posts
	 */
	public function test_activity_does_not_repeat_on_this_day_posts_in_recently_published() {
		$this->create_matching_post( self::$user_id, 'One anniversary mention' );
		self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
				'post_title'  => 'A recent post',
			)
		);

		ob_start();
		wp_dashboard_site_activity();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Published On This Day', $output );
		$this->assertSame( 1, preg_match( '/<div id="published-posts".*?<\/div>/s', $output, $recent_posts ) );
		$this->assertStringNotContainsString( 'One anniversary mention', $recent_posts[0] );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_site_activity
	 * @covers ::wp_dashboard_recent_comments_widget
	 * @covers ::wp_dashboard_recent_comments
	 */
	public function test_recent_comments_are_rendered_only_in_their_dedicated_widget() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'draft',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'A comment in its own widget.',
			)
		);

		ob_start();
		wp_dashboard_site_activity();
		$activity_output = ob_get_clean();

		ob_start();
		wp_dashboard_recent_comments_widget();
		$comments_output = ob_get_clean();

		ob_start();
		wp_dashboard_recent_comments();
		$direct_output = ob_get_clean();

		$this->assertStringNotContainsString( 'A comment in its own widget.', $activity_output );
		$this->assertStringContainsString( 'No activity yet!', $activity_output );
		$this->assertStringContainsString( 'A comment in its own widget.', $comments_output );
		$this->assertStringNotContainsString( '<h3>Recent Comments</h3>', $comments_output );
		$this->assertStringContainsString( '<h3>Recent Comments</h3>', $direct_output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_recent_comments_widget
	 */
	public function test_recent_comments_widget_renders_empty_state_when_user_has_no_visible_comments() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '0',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		wp_dashboard_recent_comments_widget();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No comments to show.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::_wp_dashboard_has_received_comments
	 * @covers ::wp_dashboard_setup
	 */
	public function test_recent_comments_widget_remains_registered_after_comments_are_deleted() {
		add_filter( 'pre_http_request', array( $this, 'mock_http_request_failure' ) );

		try {
			wp_dashboard_setup();
			$this->assertArrayNotHasKey( 'dashboard_recent_comments', $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] );

			unset( $GLOBALS['wp_meta_boxes']['dashboard'] );

			$post_id    = self::factory()->post->create();
			$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

			wp_dashboard_setup();
			$this->assertArrayHasKey( 'dashboard_recent_comments', $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] );
			$this->assertSame(
				'wp_dashboard_recent_comments_widget',
				$GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['dashboard_recent_comments']['callback']
			);
			$this->assertSame( 1, get_option( 'dashboard_recent_comments_widget_registered' ) );

			wp_delete_comment( $comment_id, true );
			unset( $GLOBALS['wp_meta_boxes']['dashboard'] );

			wp_dashboard_setup();
			$this->assertArrayHasKey( 'dashboard_recent_comments', $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] );
		} finally {
			remove_filter( 'pre_http_request', array( $this, 'mock_http_request_failure' ) );
		}
	}

	/**
	 * Mocks an HTTP request failure during dashboard setup.
	 *
	 * @return WP_Error Mock request error.
	 */
	public function mock_http_request_failure() {
		return new WP_Error( 'http_request_failed' );
	}

	/**
	 * Sets the On This Day query limit to three.
	 *
	 * @param array $args Query arguments.
	 * @return array Filtered query arguments.
	 */
	public function filter_on_this_day_query_limit( $args ) {
		$args['posts_per_page'] = 3;

		return $args;
	}

	/**
	 * Filters the On This Day query to include private posts.
	 *
	 * @param array $args Query arguments.
	 * @return array Filtered query arguments.
	 */
	public function filter_on_this_day_query_private_posts( $args ) {
		$args['post_status'] = array( 'private' );

		return $args;
	}
}
