<?php

/**
 * @group wp
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
}
