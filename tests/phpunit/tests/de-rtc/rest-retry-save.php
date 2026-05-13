<?php
/**
 * Tests for the Distributed Editing retry-save endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Retry_Save extends WP_Test_REST_TestCase {

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
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
	}

	public function tear_down() {
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_register_rest_routes
	 * @covers ::wp_de_rtc_get_rest_recovery_post_type_rest_bases
	 */
	public function test_retry_save_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/retry-save', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/retry-save', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_applies_current_base_and_server_owned_sync_meta() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save current content.</p><!-- /wp:paragraph -->',
			7,
			array(
				'hash' => 'retry-save-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'rebased_from_version'          => '4',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
			)
		);

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
		$this->assertSame( 'retry_save', $data['mode'] );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( $post_id, $data['updated_post_id'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['accepted_proof_server_version'] );
		$this->assertSame( '7', $data['previous_server_version'] );
		$this->assertSame( '8', $data['server_version'] );
		$this->assertSame( '4', $data['rebased_from_version'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertSame( $proposed_hash, $data['proposed_post_content_hash'] );
		$this->assertSame( hash( 'sha256', $after_post->post_content ), $data['saved_post_content_hash'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['requires_manual_conflict_resolution'] );
		$this->assertFalse( $data['can_export_local_updates'] );
		$this->assertFalse( $data['save_path_required'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['creates_revision'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertTrue( $data['revision_created'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $data['revision_ids_after_save'] );
		$this->assertCount( 1, $data['created_revision_ids'] );
		$this->assertContains( $data['created_revision_ids'][0], array_map( 'intval', array_keys( $after_revisions ) ) );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( 'diff-match-patch', $parsed['sync_meta_format'] );
		$this->assertSame( '8', $parsed['sync_meta']['version'] );
		$this->assertSame( '7', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry-save-base', $parsed['sync_meta']['hash'] );
		$this->assertSame( 'retry_save', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( self::$admin_user_id, $parsed['sync_meta']['last_server_update']['user_id'] );
		$this->assertSame( $proposed_hash, $parsed['sync_meta']['last_server_update']['proposed_post_content_hash'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_retry_save_request_rest_base
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_applies_pages_with_page_permission_contract() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save page current content.</p><!-- /wp:paragraph -->',
			11
		);
		$page_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save page proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$before_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'pages',
			$page_id,
			array(
				'client_base_version'           => '11',
				'accepted_proof_server_version' => '11',
				'rebased_from_version'          => '8',
				'pending_change_count'          => 3,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$after_page      = get_post( $page_id );
		$after_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_page->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $page_id, $data['post_id'] );
		$this->assertSame( $page_id, $data['updated_post_id'] );
		$this->assertSame( '11', $data['previous_server_version'] );
		$this->assertSame( '12', $data['server_version'] );
		$this->assertSame( '8', $data['rebased_from_version'] );
		$this->assertSame( 3, $data['pending_change_count'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $data['revision_ids_after_save'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '12', $parsed['sync_meta']['version'] );
		$this->assertSame( '11', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save', $parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_stale_accepted_proof_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save stale current content.</p><!-- /wp:paragraph -->',
			9
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save stale post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save stale proposed content.</p><!-- /wp:paragraph -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '9',
				'accepted_proof_server_version' => '7',
				'rebased_from_version'          => '4',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
			)
		);

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
		$this->assertSame( 'post_retry_save_stale_base', $data['rest_route'] );
		$this->assertSame( '9', $data['client_base_version'] );
		$this->assertSame( '9', $data['server_version'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_advances_non_numeric_sync_versions_deterministically() {
		$current_version  = 'server-alpha';
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save non numeric current content.</p><!-- /wp:paragraph -->',
			$current_version
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save non numeric post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save non numeric proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$expected_version = substr( hash( 'sha256', $current_version . '|' . $proposed_hash ), 0, 16 );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => $current_version,
				'accepted_proof_server_version' => $current_version,
				'rebased_from_version'          => 'offline-alpha',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertSame( $current_version, $data['previous_server_version'] );
		$this->assertSame( $expected_version, $data['server_version'] );
		$this->assertSame( 'offline-alpha', $data['rebased_from_version'] );
		$this->assertSame( $expected_version, $parsed['sync_meta']['version'] );
		$this->assertSame( $current_version, $parsed['sync_meta']['previous_version'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_missing_proposed_content_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save missing proposed current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save missing proposed post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 2,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'missing_retry_save_proposed_content', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_proposed_content_hash_mismatch_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save hash current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save hash mismatch post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save hash proposed content.</p><!-- /wp:paragraph -->';
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => str_repeat( '0', 64 ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_proposed_content_hash_mismatch', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $data['proposed_post_content_hash'] );
		$this->assertSame( str_repeat( '0', 64 ), $data['expected_post_content_hash'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_contradictory_proof_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save proof current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save proof post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save proof proposed content.</p><!-- /wp:paragraph -->',
				'accepted_proof_claims_saved'   => true,
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$error           = $response->as_error();
		$data            = $error->get_error_data( 'de_rtc_sync_meta_tampered' );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_proof_claimed_persistence', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_client_submitted_sync_meta_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save current content before client metadata.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save client meta post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_content = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save proposed content with client metadata.</p><!-- /wp:paragraph -->',
			8
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => $proposed_content,
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$error           = $response->as_error();
		$data            = $error->get_error_data( 'de_rtc_sync_meta_tampered' );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_client_submitted_sync_meta', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 */
	public function test_retry_save_requires_edit_post_capability() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save permission post',
				'post_content' => '<!-- wp:paragraph --><p>Retry save permission.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save proposed permission.</p><!-- /wp:paragraph -->',
			)
		);

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 */
	public function test_retry_save_requires_feature_enablement() {
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save disabled current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled retry save post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save disabled proposed content.</p><!-- /wp:paragraph -->',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'feature_disabled_for_post', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_retry_save_request_rest_base
	 */
	public function test_retry_save_requires_matching_post_type_rest_base() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save route mismatch current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save route mismatch post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'pages',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save route mismatch proposed content.</p><!-- /wp:paragraph -->',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * Creates a retry-save REST request.
	 *
	 * @param string $rest_base Post type REST base.
	 * @param int    $post_id   Post ID.
	 * @param array  $params    Body parameters.
	 * @return WP_REST_Request REST request.
	 */
	private function create_retry_save_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params( $params );

		return $request;
	}

	/**
	 * Asserts that a rejected retry-save request did not mutate content or revisions.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $before_content   Content before request.
	 * @param array  $before_revisions Revisions before request.
	 */
	private function assert_post_unchanged( $post_id, $before_content, $before_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertSame( $before_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * Adds synthetic sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param mixed  $version Sync metadata version.
	 * @param array  $extra   Optional extra sync metadata.
	 * @return string Content with sync metadata.
	 */
	private function add_sync_meta_to_content( $content, $version, $extra = array() ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'diff-match-patch',
			array_merge(
				$extra,
				array(
					'version' => $version,
				)
			)
		);

		$this->assertIsString( $content_with_sync_meta );

		return $content_with_sync_meta;
	}
}
