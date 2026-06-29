<?php
/**
 * Tests for the WP_Dashboard_Widget_On_This_Day class.
 *
 * @group admin
 *
 * @coversDefaultClass WP_Dashboard_Widget_On_This_Day
 */
class Tests_Admin_wpOnThisDay extends WP_UnitTestCase {
	/**
	 * Reflection method for invoking WP_Dashboard_Widget_On_This_Day::get_date_query_clause().
	 *
	 * @var ReflectionMethod
	 */
	private static $get_date_query_clause;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-dashboard-widget-on-this-day.php';

		self::$get_date_query_clause = new ReflectionMethod( 'WP_Dashboard_Widget_On_This_Day', 'get_date_query_clause' );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$get_date_query_clause->setAccessible( true );
		}
	}

	public function tear_down() {
		unset( $GLOBALS['wp_meta_boxes']['dashboard'] );
		wp_dequeue_style( 'on-this-day' );

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

		wp_dequeue_style( 'on-this-day' );
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
	 * Invokes WP_Dashboard_Widget_On_This_Day::get_date_query_clause().
	 *
	 * @param string $date Date string.
	 * @return array Date query clause.
	 */
	private static function get_date_query_clause( $date ) {
		return self::$get_date_query_clause->invoke( null, new DateTimeImmutable( $date, wp_timezone() ) );
	}

	/**
	 * @covers ::register_widget
	 */
	public function test_register_widget_does_not_add_dashboard_widget_without_matching_posts() {
		$this->set_up_dashboard_screen();

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		WP_Dashboard_Widget_On_This_Day::register_widget();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayNotHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
		$this->assertFalse( wp_style_is( 'on-this-day', 'enqueued' ) );
	}

	/**
	 * @covers ::register_widget
	 * @covers ::get_window_label
	 */
	public function test_register_widget_adds_dashboard_widget_with_matching_posts() {
		$this->set_up_dashboard_screen();

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_matching_post( $user_id );

		WP_Dashboard_Widget_On_This_Day::register_widget();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
		$this->assertStringContainsString(
			'data-wp-otd-window-label="' . esc_attr( WP_Dashboard_Widget_On_This_Day::get_window_label() ) . '"',
			$dashboard_widgets['wp_dashboard_on_this_day']['title']
		);
		$this->assertTrue( wp_style_is( 'on-this-day', 'enqueued' ) );
	}

	/**
	 * @covers ::register_widget
	 */
	public function test_register_widget_adds_dashboard_widget_with_matching_post_from_another_author() {
		$this->set_up_dashboard_screen();

		$user_id       = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_matching_post( $other_user_id );

		WP_Dashboard_Widget_On_This_Day::register_widget();

		$dashboard_widgets = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] ?? array();

		$this->assertArrayHasKey( 'wp_dashboard_on_this_day', $dashboard_widgets );
		$this->assertTrue( wp_style_is( 'on-this-day', 'enqueued' ) );
	}

	/**
	 * @covers ::get_date_query_clause
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
	 * @covers ::get_date_query_clause
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
	 * @covers ::get_date_query_clause
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
	 * @covers ::render_dashboard_widget
	 */
	public function test_render_dashboard_widget_outputs_nothing_without_matching_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		ob_start();
		WP_Dashboard_Widget_On_This_Day::render_dashboard_widget();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @covers ::render_dashboard_widget
	 */
	public function test_render_dashboard_widget_ignores_nearby_prior_year_posts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->create_nearby_post( $user_id );

		ob_start();
		WP_Dashboard_Widget_On_This_Day::render_dashboard_widget();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @covers ::render_dashboard_widget
	 */
	public function test_render_dashboard_widget_labels_posts_from_other_authors() {
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
		WP_Dashboard_Widget_On_This_Day::render_dashboard_widget();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'A note from me', $output );
		$this->assertStringNotContainsString( 'by Current Writer', $output );
		$this->assertStringContainsString( 'A note from someone else', $output );
		$this->assertStringContainsString( 'by Guest Writer', $output );
	}

	/**
	 * @covers ::render_dashboard_widget
	 */
	public function test_render_dashboard_widget_groups_posts_by_year() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$this->create_matching_post( $user_id, 'Pretending to meditate', 1, '12:00:00' );
		$this->create_matching_post( $user_id, 'Slow internet and good books', 1, '11:00:00' );
		$this->create_matching_post( $user_id, 'Late-night shipping log', 2, '12:00:00' );

		ob_start();
		WP_Dashboard_Widget_On_This_Day::render_dashboard_widget();
		$output = ob_get_clean();

		$last_year     = current_datetime()->modify( '-1 year' )->format( 'Y' );
		$two_years_ago = current_datetime()->modify( '-2 years' )->format( 'Y' );

		$this->assertStringContainsString( '3 posts have been published in previous years:', $output );
		$this->assertStringContainsString( '<ul class="wp-on-this-day-years">', $output );
		$this->assertStringNotContainsString( 'wp-on-this-day-scroll', $output );
		$this->assertStringContainsString( '<h3 class="wp-on-this-day-year-heading">' . $last_year . '</h3>', $output );
		$this->assertStringContainsString( '<h3 class="wp-on-this-day-year-heading">' . $two_years_ago . '</h3>', $output );
		$this->assertStringContainsString( 'Pretending to meditate', $output );
		$this->assertStringContainsString( 'Slow internet and good books', $output );
		$this->assertStringContainsString( 'Late-night shipping log', $output );
		$this->assertStringNotContainsString( 'wp-on-this-day-carousel', $output );
		$this->assertStringNotContainsString( 'wp-on-this-day-post-share', $output );
	}
}
