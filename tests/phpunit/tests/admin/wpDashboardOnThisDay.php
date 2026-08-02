<?php
/**
 * Tests for the On This Day dashboard widget functions.
 *
 * @group admin
 */
class Tests_Admin_wpDashboardOnThisDay extends WP_UnitTestCase {

	protected static int $user_id;

	protected static int $other_user_id;

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
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
		self::delete_user( self::$other_user_id );
	}

	public function tear_down() {
		unset( $GLOBALS['wp_meta_boxes']['dashboard'] );

		parent::tear_down();
	}

	/**
	 * Sets up the globals needed to register dashboard widgets.
	 */
	private function set_up_dashboard_screen() {
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		}

		set_current_screen( 'dashboard' );

		$GLOBALS['wp_meta_boxes']['dashboard'] = array();
	}

	/**
	 * Creates a published post on the widget's prior-year calendar day.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Post title.
	 * @param int    $years_ago Number of years before today.
	 * @param string $time      Post time.
	 * @return int Post ID.
	 */
	private function create_matching_post(
		int $author_id,
		string $title = 'A memory from last year',
		int $years_ago = 1,
		string $time = '12:00:00'
	): int {
		$post_date = current_datetime()->modify( '-' . $years_ago . ' years' )->format( 'Y-m-d' ) . ' ' . $time;

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
	 * @covers ::wp_dashboard_on_this_day_setup
	 */
	public function test_setup_always_registers_widget_and_postbox_class_filter() {
		$this->set_up_dashboard_screen();

		wp_set_current_user( self::$user_id );

		wp_dashboard_on_this_day_setup();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
		$this->assertSame( 'On This Day', $dashboard_widgets['wp_dashboard_on_this_day']['title'] );
		$this->assertNotFalse(
			has_filter(
				'postbox_classes_dashboard_wp_dashboard_on_this_day',
				'wp_dashboard_on_this_day_postbox_classes'
			)
		);
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day_postbox_classes
	 */
	public function test_postbox_classes_hides_widget_without_matching_posts() {
		wp_set_current_user( self::$user_id );

		$this->assertContains( 'hidden', wp_dashboard_on_this_day_postbox_classes( array( '' ) ) );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day_postbox_classes
	 */
	public function test_postbox_classes_does_not_hide_widget_with_matching_posts() {
		wp_set_current_user( self::$user_id );
		$this->create_matching_post( self::$user_id );

		$this->assertNotContains( 'hidden', wp_dashboard_on_this_day_postbox_classes( array( '' ) ) );
	}

	/**
	 * @ticket 65116
	 *
	 * @covers ::wp_dashboard_on_this_day_setup
	 */
	public function test_setup_adds_dashboard_widget_with_matching_post_from_another_author() {
		$this->set_up_dashboard_screen();

		wp_set_current_user( self::$user_id );
		$this->create_matching_post( self::$other_user_id );

		wp_dashboard_on_this_day_setup();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
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
		$this->assertStringNotContainsString( '<ul>', $output );
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
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_widget_shows_every_year_that_has_posts() {
		wp_set_current_user( self::$user_id );

		for ( $years_ago = 1; $years_ago <= 11; $years_ago++ ) {
			$this->create_matching_post( self::$user_id, 'Anniversary post ' . $years_ago, $years_ago );
		}

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( '11 posts have been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );

		for ( $years_ago = 1; $years_ago <= 11; $years_ago++ ) {
			$this->assertStringContainsString( 'Anniversary post ' . $years_ago . '<', $output );
			$this->assertStringContainsString(
				'<h3>' . current_datetime()->modify( "-$years_ago years" )->format( 'Y' ) . '</h3>',
				$output
			);
		}
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_widget_limits_posts_per_year() {
		wp_set_current_user( self::$user_id );

		for ( $hour = 1; $hour <= 7; $hour++ ) {
			$this->create_matching_post( self::$user_id, 'Busy day post ' . $hour, 1, sprintf( '%02d:00:00', $hour ) );
		}

		$this->create_matching_post( self::$user_id, 'A quiet older day', 2 );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		// The header reports every matching post, not just the displayed ones.
		$this->assertStringContainsString( '8 posts have been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );

		// The five newest posts of the busy year are shown, the two oldest are not.
		foreach ( array( 7, 6, 5, 4, 3 ) as $hour ) {
			$this->assertStringContainsString( 'Busy day post ' . $hour . '<', $output );
		}
		$this->assertStringNotContainsString( 'Busy day post 2<', $output );
		$this->assertStringNotContainsString( 'Busy day post 1<', $output );

		// The remainder is summarized, and the quieter year is untouched.
		$this->assertStringContainsString( '2 more posts', $output );
		$this->assertStringContainsString( 'A quiet older day', $output );
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_posts_per_year_is_filterable_via_query_args() {
		wp_set_current_user( self::$user_id );

		for ( $hour = 1; $hour <= 3; $hour++ ) {
			$this->create_matching_post( self::$user_id, 'Busy day post ' . $hour, 1, sprintf( '%02d:00:00', $hour ) );
		}

		add_filter(
			'wp_dashboard_on_this_day_query_args',
			static function ( $args ) {
				$args['posts_per_year'] = 2;

				return $args;
			}
		);

		$posts_by_year = wp_dashboard_on_this_day_get_posts();
		$year          = (int) current_datetime()->modify( '-1 year' )->format( 'Y' );

		$this->assertSame( array( $year ), array_keys( $posts_by_year ) );
		$this->assertCount( 2, $posts_by_year[ $year ]['posts'] );
		$this->assertSame( 3, $posts_by_year[ $year ]['total'] );
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_widget_does_not_summarize_a_year_that_is_fully_shown() {
		wp_set_current_user( self::$user_id );

		$this->create_matching_post( self::$user_id, 'The only memory' );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'wp-on-this-day-more', $output );
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_get_posts_groups_years_newest_first_with_accurate_totals() {
		$this->create_matching_post( self::$user_id, 'Two years ago', 2 );
		$this->create_matching_post( self::$user_id, 'Last year, morning', 1, '09:00:00' );
		$this->create_matching_post( self::$user_id, 'Last year, evening', 1, '19:00:00' );
		$this->create_nearby_post( self::$user_id, 'Not today' );

		$today         = current_datetime();
		$last_year     = (int) $today->modify( '-1 year' )->format( 'Y' );
		$two_years_ago = (int) $today->modify( '-2 years' )->format( 'Y' );

		$posts_by_year = wp_dashboard_on_this_day_get_posts();

		$this->assertSame( array( $last_year, $two_years_ago ), array_keys( $posts_by_year ) );
		$this->assertSame( 2, $posts_by_year[ $last_year ]['total'] );
		$this->assertSame( 1, $posts_by_year[ $two_years_ago ]['total'] );
		$this->assertSame(
			'Last year, evening',
			get_the_title( $posts_by_year[ $last_year ]['posts'][0] ),
			'Posts within a year should be newest first.'
		);
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_get_posts_excludes_the_current_year() {
		$post_date = current_datetime()->format( 'Y-m-d' ) . ' 00:00:01';

		self::factory()->post->create(
			array(
				'post_author'   => self::$user_id,
				'post_date'     => $post_date,
				'post_date_gmt' => get_gmt_from_date( $post_date ),
				'post_status'   => 'publish',
				'post_title'    => 'Published today',
			)
		);

		$this->assertSame( array(), wp_dashboard_on_this_day_get_posts() );
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_get_posts_ignores_unpublished_posts() {
		$post_date = current_datetime()->modify( '-1 year' )->format( 'Y-m-d' ) . ' 12:00:00';

		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			self::factory()->post->create(
				array(
					'post_author'   => self::$user_id,
					'post_date'     => $post_date,
					'post_date_gmt' => get_gmt_from_date( $post_date ),
					'post_status'   => $status,
					'post_title'    => "A $status memory",
				)
			);
		}

		$this->assertSame( array(), wp_dashboard_on_this_day_get_posts() );
	}

	/**
	 * @ticket 65783
	 *
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_get_posts_scans_once_regardless_of_year_count() {
		global $wpdb;

		for ( $years_ago = 1; $years_ago <= 6; $years_ago++ ) {
			$this->create_matching_post( self::$user_id, 'Anniversary ' . $years_ago, $years_ago );
		}

		wp_cache_flush();

		$post_queries = array();

		add_filter(
			'query',
			static function ( $query ) use ( &$post_queries, $wpdb ) {
				if ( str_contains( $query, $wpdb->posts ) ) {
					$post_queries[] = $query;
				}

				return $query;
			}
		);

		$posts_by_year = wp_dashboard_on_this_day_get_posts();

		$this->assertCount( 6, $posts_by_year );
		$this->assertCount(
			2,
			$post_queries,
			"Gathering posts should take one scan plus one cache prime, not one query per year. Ran:\n" . implode( "\n", $post_queries )
		);
		$this->assertStringContainsString( 'ORDER BY', $post_queries[0] );
		$this->assertStringNotContainsString( 'YEAR(', $post_queries[0], 'The scan must stay sargable on type_status_date.' );
	}
}
