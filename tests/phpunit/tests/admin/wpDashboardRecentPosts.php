<?php

/**
 * Tests for wp_dashboard_recent_posts().
 *
 * @group admin
 * @group dashboard
 *
 * @covers ::wp_dashboard_recent_posts
 */
class Tests_Admin_wpDashboardRecentPosts extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
	}

	/**
	 * @ticket 37578
	 */
	public function test_query_args_filter_receives_container_id() {
		$received_ids = array();

		add_filter(
			'dashboard_recent_posts_query_args',
			static function ( $query_args, $id ) use ( &$received_ids ) {
				$received_ids[] = $id;
				return $query_args;
			},
			10,
			2
		);

		ob_start();
		wp_dashboard_recent_posts(
			array(
				'max'    => 5,
				'status' => 'future',
				'order'  => 'ASC',
				'title'  => 'Publishing Soon',
				'id'     => 'future-posts',
			)
		);
		wp_dashboard_recent_posts(
			array(
				'max'    => 5,
				'status' => 'publish',
				'order'  => 'DESC',
				'title'  => 'Recently Published',
				'id'     => 'published-posts',
			)
		);
		ob_end_clean();

		$this->assertSame( array( 'future-posts', 'published-posts' ), $received_ids );
	}
}
