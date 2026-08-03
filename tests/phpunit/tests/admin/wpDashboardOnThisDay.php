<?php
/**
 * Tests for the On This Day dashboard widget functions.
 *
 * @group admin
 */
class Tests_Admin_wpDashboardOnThisDay extends WP_UnitTestCase {

	protected static int $user_id;

	protected static int $other_user_id;

	protected static int $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/dashboard-on-this-day.php';

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
		self::$subscriber_id = $factory->user->create(
			array(
				'display_name' => 'Reader',
				'role'         => 'subscriber',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
		self::delete_user( self::$other_user_id );
		self::delete_user( self::$subscriber_id );
	}

	public function set_up() {
		parent::set_up();

		set_current_screen( 'dashboard' );
	}

	/**
	 * Creates a published post on the widget's prior-year calendar day.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Post title.
	 * @param int    $years_ago Number of years before today.
	 * @param string $time      Post time.
	 * @param array  $post_args Additional post arguments.
	 * @return int Post ID.
	 */
	private function create_matching_post(
		int $author_id,
		string $title = 'A memory from last year',
		int $years_ago = 1,
		string $time = '12:00:00',
		array $post_args = array()
	): int {
		$post_date = current_datetime()->modify( '-' . $years_ago . ' years' )->format( 'Y-m-d' ) . ' ' . $time;

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
	 * Creates a published post near, but not on, today's prior-year calendar day.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Post title.
	 * @param int    $day_offset Number of days from today's prior-year calendar day.
	 * @return int Post ID.
	 */
	private function create_nearby_post( int $author_id, string $title = 'Almost a memory', int $day_offset = 1 ): int {
		$post_date = current_datetime()
			->modify( '-1 year' )
			->modify( ( $day_offset >= 0 ? '+' : '' ) . $day_offset . ' days' )
			->format( 'Y-m-d' ) . ' 12:00:00';

		return self::factory()->post->create(
			array(
				'post_author'   => $author_id,
				'post_date'     => $post_date,
				'post_date_gmt' => get_gmt_from_date( $post_date ),
				'post_status'   => 'publish',
				'post_title'    => $title,
			)
		);
	}

	/**
	 * Invokes _wp_dashboard_on_this_day_date_query_clause().
	 *
	 * @param string $date Date string.
	 * @return array Date query clause.
	 */
	private static function get_date_query_clause( string $date ): array {
		return _wp_dashboard_on_this_day_date_query_clause( new DateTimeImmutable( $date, wp_timezone() ) );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::_wp_dashboard_on_this_day_date_query_clause
	 */
	public function test_get_date_query_clause_includes_february_29_on_february_28_in_non_leap_year() {
		$clause = self::get_date_query_clause( '2023-02-28 12:00:00' );

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
			$clause
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::_wp_dashboard_on_this_day_date_query_clause
	 */
	public function test_get_date_query_clause_does_not_include_february_29_on_february_28_in_leap_year() {
		$clause = self::get_date_query_clause( '2024-02-28 12:00:00' );

		$this->assertSame(
			array(
				'month' => 2,
				'day'   => 28,
			),
			$clause
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::_wp_dashboard_on_this_day_date_query_clause
	 */
	public function test_get_date_query_clause_matches_february_29_on_leap_day() {
		$clause = self::get_date_query_clause( '2024-02-29 12:00:00' );

		$this->assertSame(
			array(
				'month' => 2,
				'day'   => 29,
			),
			$clause
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_outputs_placeholder_without_matching_posts() {
		wp_set_current_user( self::$user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No posts were published on this day in previous years.', $output );
		$this->assertStringContainsString( 'Write one today', $output );
		$this->assertStringContainsString( admin_url( 'post-new.php' ), $output );
		$this->assertStringNotContainsString( '<ul>', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_placeholder_omits_link_without_edit_posts_capability() {
		wp_set_current_user( self::$subscriber_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No posts were published on this day in previous years.', $output );
		$this->assertStringNotContainsString( 'Write one today', $output );
		$this->assertStringNotContainsString( admin_url( 'post-new.php' ), $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_ignores_nearby_prior_year_posts() {
		wp_set_current_user( self::$user_id );
		$this->create_nearby_post( self::$user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Almost a memory', $output );
		$this->assertStringContainsString( 'No posts were published on this day in previous years.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_uses_singular_copy_for_a_single_post() {
		wp_set_current_user( self::$user_id );
		$this->create_matching_post( self::$user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'One post has been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_labels_posts_from_other_authors() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post( self::$user_id, 'A note from me' );
		$this->create_matching_post( self::$other_user_id, 'A note from someone else' );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'A note from me', $output );
		$this->assertStringNotContainsString( 'by Current Writer', $output );
		$this->assertStringContainsString( 'A note from someone else', $output );
		$this->assertStringContainsString( 'by Guest Writer', $output );
		$this->assertStringContainsString( '<span class="wp-on-this-day-post-author">by Guest Writer</span>', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_groups_posts_by_year() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post( self::$user_id, 'Pretending to meditate', 1, '12:00:00' );
		$this->create_matching_post( self::$user_id, 'Slow internet and good books', 1, '11:00:00' );
		$this->create_matching_post( self::$user_id, 'Late-night shipping log', 2, '12:00:00' );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$last_year     = current_datetime()->modify( '-1 year' )->format( 'Y' );
		$two_years_ago = current_datetime()->modify( '-2 years' )->format( 'Y' );

		$this->assertStringContainsString( '3 posts have been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );
		$this->assertStringContainsString( '<h3>' . $last_year . '</h3>', $output );
		$this->assertStringContainsString( '<h3>' . $two_years_ago . '</h3>', $output );
		$this->assertStringContainsString( 'Pretending to meditate', $output );
		$this->assertStringContainsString( 'Slow internet and good books', $output );
		$this->assertStringContainsString( 'Late-night shipping log', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_includes_trimmed_excerpt_for_untitled_posts() {
		wp_set_current_user( self::$user_id );

		$words = array();
		for ( $n = 1; $n <= 20; $n++ ) {
			$words[] = 'word' . $n;
		}

		$this->create_matching_post(
			self::$user_id,
			'',
			1,
			'12:00:00',
			array(
				'post_excerpt' => implode( ' ', $words ),
			)
		);

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringContainsString( 'word15', $output, 'The 15th word should be present.' );
		$this->assertStringNotContainsString( 'word16', $output, 'The 16th word should be trimmed.' );
		$this->assertStringContainsString( '&hellip;', $output, 'The excerpt should end with an ellipsis.' );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_does_not_append_excerpt_to_titled_posts() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post(
			self::$user_id,
			'A titled anniversary memory',
			1,
			'12:00:00',
			array(
				'post_excerpt' => 'This excerpt should not be shown.',
			)
		);

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'A titled anniversary memory', $output );
		$this->assertStringNotContainsString( 'This excerpt should not be shown.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_includes_trimmed_excerpt_for_untitled_private_posts_authored_by_current_user() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post(
			self::$user_id,
			'',
			1,
			'12:00:00',
			array(
				'post_excerpt' => 'Readable private anniversary memory.',
				'post_status'  => 'private',
			)
		);

		add_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );

		ob_start();
		try {
			wp_dashboard_on_this_day();
			$output = ob_get_clean();
		} finally {
			remove_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );
		}

		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringContainsString( 'Readable private anniversary memory.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_hides_untitled_post_excerpt_for_unreadable_posts() {
		wp_set_current_user( self::$user_id );

		$post_id = $this->create_matching_post(
			self::$other_user_id,
			'',
			1,
			'12:00:00',
			array(
				'post_excerpt' => 'Unreadable private anniversary memory.',
				'post_status'  => 'private',
			)
		);

		add_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );

		ob_start();
		try {
			wp_dashboard_on_this_day();
			$output = ob_get_clean();
		} finally {
			remove_filter( 'wp_dashboard_on_this_day_query_args', array( $this, 'filter_on_this_day_query_private_posts' ) );
		}

		$this->assertFalse( current_user_can( 'read_post', $post_id ) );
		$this->assertStringContainsString( '(no title)', $output );
		$this->assertStringNotContainsString( 'Unreadable private anniversary memory.', $output );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_hides_untitled_post_excerpt_for_password_protected_posts() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post(
			self::$user_id,
			'',
			1,
			'12:00:00',
			array(
				'post_excerpt'  => 'Private anniversary memory.',
				'post_password' => 'secret',
			)
		);

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Private anniversary memory.', $output );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_widget_limits_posts_to_ten() {
		wp_set_current_user( self::$user_id );

		for ( $years_ago = 1; $years_ago <= 11; $years_ago++ ) {
			$this->create_matching_post( self::$user_id, 'Anniversary post ' . $years_ago, $years_ago );
		}

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( '10 posts have been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );
		$this->assertMatchesRegularExpression( '/>\s*Anniversary post 1\s*<\/a>/', $output );
		$this->assertMatchesRegularExpression( '/>\s*Anniversary post 10\s*<\/a>/', $output );
		$this->assertStringNotContainsString( 'Anniversary post 11', $output );
	}

	/**
	 * Filters the On This Day query to include private posts.
	 *
	 * @param array $args WP_Query arguments.
	 * @return array Filtered query arguments.
	 */
	public function filter_on_this_day_query_private_posts( $args ) {
		$args['post_status'] = array( 'private' );

		return $args;
	}
}
