<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests On This Day dashboard widget Ajax functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_dashboard_on_this_day_load_more
 */
class Tests_Ajax_wpAjaxDashboardOnThisDay extends WP_Ajax_UnitTestCase {

	/**
	 * Creates a published post on the widget's prior-year calendar day.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Post title.
	 * @param int    $years_ago Number of years before today.
	 * @return int Post ID.
	 */
	private function create_matching_post( $author_id, $title, $years_ago ) {
		$post_date = current_datetime()->modify( '-' . $years_ago . ' years' )->format( 'Y-m-d' ) . ' 12:00:00';

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
	 * @covers ::_wp_dashboard_on_this_day_get_posts_query
	 * @covers ::_wp_dashboard_on_this_day_render_posts
	 */
	public function test_load_more_posts_returns_remaining_posts() {
		$this->_setRole( 'author' );
		$user_id = get_current_user_id();

		for ( $years_ago = 1; $years_ago <= 12; $years_ago++ ) {
			$this->create_matching_post( $user_id, 'Anniversary post ' . $years_ago, $years_ago );
		}

		$_POST['_ajax_nonce'] = wp_create_nonce( 'wp_dashboard_on_this_day_load_more' );
		$_POST['offset']      = 10;

		try {
			$this->_handleAjax( 'dashboard-on-this-day-load-more' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['post_count'] );
		$this->assertStringContainsString( 'Anniversary post 11', $response['data']['html'] );
		$this->assertStringContainsString( 'Anniversary post 12', $response['data']['html'] );
		$this->assertStringNotContainsString( 'Anniversary post 10', $response['data']['html'] );
	}
}
