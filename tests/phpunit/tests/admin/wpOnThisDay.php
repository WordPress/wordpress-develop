<?php
/**
 * Tests for the On This Day dashboard widget functions.
 *
 * @group admin
 */
class Tests_Admin_wpOnThisDay extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/dashboard-on-this-day.php';
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
		$author_id,
		$title = 'A memory from last year',
		$years_ago = 1,
		$time = '12:00:00'
	) {
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
	private function create_nearby_post( $author_id, $title = 'Almost a memory', $day_offset = 1 ) {
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
	private static function get_date_query_clause( $date ) {
		return _wp_dashboard_on_this_day_date_query_clause( new DateTimeImmutable( $date, wp_timezone() ) );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day_setup
	 */
	public function test_setup_always_registers_widget_and_postbox_class_filter() {
		$this->set_up_dashboard_screen();

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

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
	 * @covers ::wp_dashboard_on_this_day_postbox_classes
	 */
	public function test_postbox_classes_hides_widget_without_matching_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$this->assertContains( 'hidden', wp_dashboard_on_this_day_postbox_classes( array( '' ) ) );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day_postbox_classes
	 */
	public function test_postbox_classes_does_not_hide_widget_with_matching_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_matching_post( $user_id );

		$this->assertNotContains( 'hidden', wp_dashboard_on_this_day_postbox_classes( array( '' ) ) );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day_setup
	 */
	public function test_setup_adds_dashboard_widget_with_matching_post_from_another_author() {
		$this->set_up_dashboard_screen();

		$user_id       = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_matching_post( $other_user_id );

		wp_dashboard_on_this_day_setup();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
	}

	/**
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
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_outputs_placeholder_without_matching_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No posts were published on this day in previous years.', $output );
		$this->assertStringNotContainsString( '<ul>', $output );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_ignores_nearby_prior_year_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_nearby_post( $user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Almost a memory', $output );
		$this->assertStringContainsString( 'No posts were published on this day in previous years.', $output );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_uses_singular_copy_for_a_single_post() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_matching_post( $user_id );

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'One post has been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );
	}

	/**
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_labels_posts_from_other_authors() {
		$user_id       = self::factory()->user->create(
			array(
				'display_name' => 'Current Writer',
				'role'         => 'author',
			)
		);
		$other_user_id = self::factory()->user->create(
			array(
				'display_name' => 'Guest Writer',
				'role'         => 'author',
			)
		);
		wp_set_current_user( $user_id );

		$this->create_matching_post( $user_id, 'A note from me' );
		$this->create_matching_post( $other_user_id, 'A note from someone else' );

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
	 * @covers ::wp_dashboard_on_this_day
	 */
	public function test_widget_groups_posts_by_year() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$this->create_matching_post( $user_id, 'Pretending to meditate', 1, '12:00:00' );
		$this->create_matching_post( $user_id, 'Slow internet and good books', 1, '11:00:00' );
		$this->create_matching_post( $user_id, 'Late-night shipping log', 2, '12:00:00' );

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
	 * @covers ::wp_dashboard_on_this_day
	 * @covers ::wp_dashboard_on_this_day_get_posts
	 */
	public function test_widget_limits_posts_to_ten() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		for ( $years_ago = 1; $years_ago <= 11; $years_ago++ ) {
			$this->create_matching_post( $user_id, 'Anniversary post ' . $years_ago, $years_ago );
		}

		ob_start();
		wp_dashboard_on_this_day();
		$output = ob_get_clean();

		$this->assertStringContainsString( '10 posts have been published on <strong>' . wp_date( 'F jS' ) . '</strong>:', $output );
		$this->assertStringContainsString( 'Anniversary post 1<', $output );
		$this->assertStringContainsString( 'Anniversary post 10<', $output );
		$this->assertStringNotContainsString( 'Anniversary post 11', $output );
	}
}
