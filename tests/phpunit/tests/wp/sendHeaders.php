<?php

/**
 * @group wp
 *
 * @covers WP::send_headers
 */
class Tests_WP_SendHeaders extends WP_UnitTestCase {
	protected $headers_sent = array();

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

	/**
	 * @ticket 61711
	 */
	public function test_send_headers_sets_cache_control_header_for_password_protected_posts() {
		$password = 'password';

		add_filter(
			'wp_headers',
			function ( $headers ) {
				$this->headers_sent = $headers;
				return $headers;
			}
		);

		$post_id = self::factory()->post->create(
			array(
				'post_password' => $password,
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		$headers_without_password         = $this->headers_sent;
		$password_status_without_password = post_password_required( $post_id );

		require_once ABSPATH . WPINC . '/class-phpass.php';

		$hash = ( new PasswordHash( 8, true ) )->HashPassword( $password );

		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hash;

		$this->go_to( get_permalink( $post_id ) );

		$headers_with_password         = $this->headers_sent;
		$password_status_with_password = post_password_required( $post_id );

		$this->assertTrue( $password_status_without_password );
		$this->assertArrayHasKey( 'Cache-Control', $headers_without_password );

		$this->assertFalse( $password_status_with_password );
		$this->assertArrayHasKey( 'Cache-Control', $headers_with_password );
	}
}
