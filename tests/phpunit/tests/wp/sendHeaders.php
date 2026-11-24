<?php

/**
 * @group wp
 *
 * @covers WP::send_headers
 */
class Tests_WP_SendHeaders extends WP_UnitTestCase {
	protected $headers_sent = array();

	public function tear_down() {
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		parent::tear_down();
	}

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

	/**
	 * @ticket 66791
	 */
	public function test_feed_etag_changes_when_wordpress_version_changes() {
		global $wp_version;

		$original_wp_version        = $wp_version;
		$original_if_none_match     = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
		$original_if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
		$captured_headers           = array();

		$headers_filter = static function ( $headers ) use ( &$captured_headers ) {
			$captured_headers = $headers;
			return $headers;
		};

		add_filter( 'wp_headers', $headers_filter );

		// Keep the feed's last modified date fixed while simulating a Core update.
		self::factory()->post->create(
			array(
				'post_date' => '2024-01-01 00:00:00',
			)
		);

		unset( $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

		try {
			$wp_version = '7.1.3';
			$this->go_to( '/?feed=rss2' );
			$headers_before_update = $captured_headers;

			$wp_version = '7.1.4';
			$this->go_to( '/?feed=rss2' );
			$headers_after_update = $captured_headers;
		} finally {
			remove_filter( 'wp_headers', $headers_filter );
			$wp_version = $original_wp_version;

			if ( null === $original_if_none_match ) {
				unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
			} else {
				$_SERVER['HTTP_IF_NONE_MATCH'] = $original_if_none_match;
			}

			if ( null === $original_if_modified_since ) {
				unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
			} else {
				$_SERVER['HTTP_IF_MODIFIED_SINCE'] = $original_if_modified_since;
			}
		}

		$this->assertNotSame( $headers_before_update['ETag'], $headers_after_update['ETag'] );
		$this->assertSame( $headers_before_update['Last-Modified'], $headers_after_update['Last-Modified'] );
	}
}
