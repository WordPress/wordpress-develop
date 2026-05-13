<?php
/**
 * Tests for the Distributed Editing sync-meta recovery REST endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Recovery extends WP_Test_REST_TestCase {

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
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
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
	public function test_registers_post_and_page_recovery_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/recovery', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/recovery', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/stale-base', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/stale-base', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/retry-submit', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/retry-submit', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_stale_base_endpoint
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 */
	public function test_stale_base_route_returns_rejection_contract_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC stale base REST post',
				'post_content' => '<!-- wp:paragraph --><p>Stale base current content.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/stale-base' );
		$request->set_param( 'client_base_version', '4' );
		$request->set_param( 'server_version', '6' );
		$request->set_param( 'pending_change_count', 2 );
		$request->set_param( 'remote_change_count', 3 );
		$request->set_param( 'can_attempt_local_rebase', true );

		$response        = rest_get_server()->dispatch( $request );
		$error           = $response->as_error();
		$data            = $error->get_error_data( 'stale_base_version_rejected' );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'stale_base_version_rejected', $data['reason_code'] );
		$this->assertSame( 'stale_base_version_rejected', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( '4', $data['client_base_version'] );
		$this->assertSame( '6', $data['server_version'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertSame( 3, $data['remote_change_count'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['can_attempt_local_rebase'] );
		$this->assertFalse( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertSame( 'post_stale_base_rejection', $data['rest_route'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'post', $data['permission_contract']['post_type'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 * @covers ::wp_de_rtc_rest_stale_base_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_stale_base_request_rest_base
	 */
	public function test_stale_base_route_requires_matching_post_type_rest_base() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC stale base route mismatch post',
				'post_content' => '<!-- wp:paragraph --><p>Stale base route mismatch.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $post_id . '/distributed-editing/stale-base' );
		$request->set_param( 'client_base_version', '4' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 */
	public function test_stale_base_route_requires_feature_enablement() {
		update_option( 'wp_de_rtc_enabled', false );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled stale base REST post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled stale base feature.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/stale-base' );
		$request->set_param( 'client_base_version', '4' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 */
	public function test_stale_base_route_requires_edit_post_capability() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC stale base permission REST post',
				'post_content' => '<!-- wp:paragraph --><p>Stale base permission denied.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/stale-base' );
		$request->set_param( 'client_base_version', '4' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_endpoint
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 */
	public function test_default_request_dry_runs_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>REST dry-run current content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>REST dry-run base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 31,
			),
			'diff-match-patch'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/recovery' );

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'dry_run', $data['mode'] );
		$this->assertSame( 'candidate_update_valid', $data['result'] );
		$this->assertSame( 'post_sync_meta_recovery', $data['rest_route'] );
		$this->assertFalse( $data['would_apply'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'post', $data['permission_contract']['post_type'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_endpoint
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 * @covers ::wp_de_rtc_rest_recovery_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_recovery_request_rest_base
	 * @covers ::wp_de_rtc_get_post_type_rest_base
	 */
	public function test_page_request_dry_runs_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>REST page dry-run current content.</p><!-- /wp:paragraph -->';
		$page_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>REST page dry-run base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 34,
			),
			'diff-match-patch',
			'page'
		);
		$before_page      = get_post( $page_id );
		$before_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id . '/distributed-editing/recovery' );

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$after_page      = get_post( $page_id );
		$after_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'dry_run', $data['mode'] );
		$this->assertSame( 'candidate_update_valid', $data['result'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertFalse( $data['would_apply'] );
		$this->assertSame( $before_page->post_content, $after_page->post_content );
		$this->assertSame( $current_content, $after_page->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 * @covers ::wp_de_rtc_rest_recovery_request_matches_post_type
	 */
	public function test_recovery_route_requires_matching_post_type_rest_base() {
		$post_id = $this->create_restorable_post(
			'<!-- wp:paragraph --><p>REST route mismatch current content.</p><!-- /wp:paragraph -->',
			'<!-- wp:paragraph --><p>REST route mismatch base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 35,
			),
			'automerge'
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $post_id . '/distributed-editing/recovery' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_endpoint
	 */
	public function test_apply_request_persists_valid_recovery_candidate() {
		$current_content  = '<!-- wp:paragraph --><p>REST apply current content.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 32,
			'hash'    => 'rest-apply-base',
		);
		$post_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>REST apply base content.</p><!-- /wp:paragraph -->',
			$base_metadata,
			'automerge'
		);
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/recovery' );
		$request->set_param( 'mode', 'apply' );

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'apply', $data['mode'] );
		$this->assertSame( 'candidate_update_applied', $data['result'] );
		$this->assertTrue( $data['applied'] );
		$this->assertTrue( $data['revision_created'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $data['revision_ids_before_apply'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $data['revision_ids_after_apply'] );
		$this->assertCount( 1, $data['created_revision_ids'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $current_content, $parsed['content'] );
		$this->assertSame( $base_metadata, $parsed['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_endpoint
	 */
	public function test_apply_request_rejects_tampered_candidate_hash_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>REST tampered current content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>REST tampered base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 33,
			),
			'yjs'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/recovery' );
		$request->set_param( 'mode', 'apply' );
		$request->set_param( 'candidate_post_content_hash', hash( 'sha256', 'invalid rest candidate' ) );

		$response        = rest_get_server()->dispatch( $request );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 */
	public function test_recovery_route_requires_feature_enablement() {
		update_option( 'wp_de_rtc_enabled', false );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled REST post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled feature.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/recovery' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 */
	public function test_recovery_route_requires_edit_post_capability() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC permission REST post',
				'post_content' => '<!-- wp:paragraph --><p>Permission denied.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/recovery' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * Creates a post whose current content is missing sync metadata but whose
	 * latest revision contains recoverable sync metadata.
	 *
	 * @param string $current_content Current parent post content.
	 * @param string $base_content    Base revision content.
	 * @param array  $base_metadata   Base revision sync metadata.
	 * @param string $format          Sync-meta format.
	 * @param string $post_type       Optional post type. Default 'post'.
	 * @return int Post ID.
	 */
	private function create_restorable_post( $current_content, $base_content, $base_metadata, $format, $post_type = 'post' ) {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC REST recovery post',
				'post_type'     => $post_type,
				'post_content'  => $current_content,
				'post_modified' => '2026-05-13 16:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, $format, $base_metadata );

		$this->assertIsString( $revision_content );
		$this->insert_revision( $post_id, $revision_content, '2026-05-13 16:10:00' );

		return $post_id;
	}

	/**
	 * Inserts a revision for a post with controlled content and dates.
	 *
	 * @param int    $post_id      Parent post ID.
	 * @param string $post_content Revision content.
	 * @param string $date_gmt     GMT date.
	 * @return int Revision ID.
	 */
	private function insert_revision( $post_id, $post_content, $date_gmt ) {
		$post                 = (array) get_post( $post_id );
		$post_revision_fields = _wp_post_revision_data( $post );

		$post_revision_fields['post_content']  = $post_content;
		$post_revision_fields['post_date']     = $date_gmt;
		$post_revision_fields['post_date_gmt'] = $date_gmt;

		$revision_id = wp_insert_post( wp_slash( $post_revision_fields ), true );

		$this->assertNotWPError( $revision_id );
		$this->assertIsInt( $revision_id );

		return $revision_id;
	}
}
