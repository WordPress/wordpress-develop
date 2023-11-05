<?php

/**
 * @group wp
 *
 * @covers WP::send_headers
 */
class Tests_WP_SendHeaders extends WP_UnitTestCase {

	/**
	 * @ticket 56068
	 */
	public function test_send_headers_runs_after_posts_have_been_queried() {
		add_action(
			'send_headers',
			function ( $wp ) {
				$this->assertQueryTrue( 'is_front_page', 'is_home' );
			}
		);

		$this->go_to( home_url() );
	}

	/**
	 * @ticket 56840
	 */
	public function test_send_headers_sets_x_pingback_for_single_posts_that_allow_pings() {
		add_action(
			'wp_headers',
			function ( $headers ) {
				$this->assertArrayHasKey( 'X-Pingback', $headers );
			}
		);

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
	}
}
