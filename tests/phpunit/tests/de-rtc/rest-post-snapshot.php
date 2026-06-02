<?php
/**
 * Tests for the Distributed Editing post snapshot endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Post_Snapshot extends WP_Test_REST_TestCase {

	protected static $admin_user_id;
	protected static $subscriber_user_id;

	protected $server;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_user_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		wp_set_current_user( self::$admin_user_id );
		update_option( 'wp_de_rtc_enabled', true );
	}

	public function tear_down() {
		delete_option( 'wp_de_rtc_enabled' );

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_register_rest_routes
	 * @covers ::wp_de_rtc_get_rest_recovery_post_type_rest_bases
	 */
	public function test_post_snapshot_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_endpoint
	 * @covers ::wp_de_rtc_rest_post_snapshot_permissions_check
	 * @covers ::wp_de_rtc_get_post_snapshot_for_distributed_editing
	 */
	public function test_post_snapshot_returns_raw_content_and_state_hash_without_mutating() {
		$content     = '<!-- wp:paragraph --><p>Snapshot content.</p><!-- /wp:paragraph -->';
		$post_id     = $this->create_snapshot_post( $content, '7' );
		$before_post = get_post( $post_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'post', $data['type'] );
		$this->assertSame( $before_post->post_content, $data['content']['raw'] );
		$this->assertSame( '7', $data['server_version'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $data['state_hash'] );
		$this->assertSame( '"' . $data['state_hash'] . '"', $headers['ETag'] );
		$this->assertSame( 'private, no-store', $headers['Cache-Control'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertTrue( $data['exposes_raw_content'] );
		$this->assertSame( $before_post->post_content, get_post( $post_id )->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_endpoint
	 * @covers ::wp_de_rtc_rest_if_none_match_matches_state_hash
	 */
	public function test_post_snapshot_returns_not_modified_for_matching_state_hash() {
		$post_id       = $this->create_snapshot_post(
			'<!-- wp:paragraph --><p>Conditional snapshot.</p><!-- /wp:paragraph -->',
			'9'
		);
		$first_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );
		$first         = rest_get_server()->dispatch( $first_request );
		$first_data    = $first->get_data();
		$etag          = '"' . $first_data['state_hash'] . '"';
		$second        = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$second->add_header( 'If-None-Match', $etag );

		$response = rest_get_server()->dispatch( $second );
		$headers  = $response->get_headers();

		$this->assertSame( 304, $response->get_status() );
		$this->assertNull( $response->get_data() );
		$this->assertSame( $etag, $headers['ETag'] );
		$this->assertSame( 'private, no-store', $headers['Cache-Control'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_snapshot_for_distributed_editing
	 */
	public function test_post_snapshot_repairs_missing_sync_meta_without_mutating_persisted_post() {
		$content = '<!-- wp:paragraph --><p>Missing sync meta.</p><!-- /wp:paragraph -->';
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC snapshot missing sync meta',
				'post_content' => $content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$parsed   = wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '<!-- wp:sync-meta', $data['content']['raw'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $data['content']['raw'] );
		$this->assertNotWPError( $parsed );
		$this->assertSame( $content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'de-rtc-automerge-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( '1', $parsed['sync_meta']['version'] );
		$this->assertSame( 'empty_import', $parsed['sync_meta']['last_server_update']['external_repair_mode'] );
		$this->assertTrue( $data['distributed_editing']['repair_candidate'] );
		$this->assertSame( 'empty_import', $data['distributed_editing']['external_repair']['mode'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, get_post( $post_id )->post_content );
		$this->assertCount(
			count( $before_revisions ),
			wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			)
		);
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_permissions_check
	 */
	public function test_post_snapshot_requires_edit_post_without_mutating() {
		$post_id     = $this->create_snapshot_post(
			'<!-- wp:paragraph --><p>Permission snapshot.</p><!-- /wp:paragraph -->',
			'3'
		);
		$before_post = get_post( $post_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( rest_authorization_required_code(), $response->get_status() );
		$this->assertSame( $before_post->post_content, get_post( $post_id )->post_content );
	}

	private function create_snapshot_post( $content, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'automerge',
			array(
				'schema'             => 'de-rtc-automerge-v1',
				'version'            => (string) $version,
				'previous_version'   => (string) max( 0, (int) $version - 1 ),
				'last_server_update' => array(
					'type'          => 'retry_save',
					'session_label' => 'Snapshot Test',
				),
			),
			'prefix-block'
		);

		$this->assertIsString( $content_with_sync_meta );

		return self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC snapshot post',
				'post_content' => $content_with_sync_meta,
			)
		);
	}
}
