<?php
/**
 * Tests for the Distributed Editing fresh-review request endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Fresh_Review_Request extends WP_Test_REST_TestCase {

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
	public function test_fresh_review_request_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/fresh-review-request', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/fresh-review-request', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_rest_fresh_review_request_permissions_check
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 * @covers ::wp_de_rtc_get_request_hash_evidence
	 * @covers ::wp_de_rtc_is_sha256_hash
	 */
	public function test_fresh_review_request_accepts_hash_only_evidence_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 21 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review proposed content' );
		$candidate_hash   = hash( 'sha256', 'fresh review candidate content' );
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '21',
				'server_version'             => '21',
				'pending_change_count'       => 2,
				'proposed_post_content_hash' => $proposed_hash,
				'candidate_post_content_hash' => $candidate_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertTrue( $data['fresh_review_request_accepted'] );
		$this->assertSame( 'requested', $data['fresh_review_request_status'] );
		$this->assertSame( 'post_fresh_review_request', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( '21', $data['client_base_version'] );
		$this->assertSame( '21', $data['server_version'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertSame( 'accepted', $data['hash_evidence_status'] );
		$this->assertSame( array( 'proposed_post_content_hash', 'candidate_post_content_hash' ), $data['hash_evidence_fields'] );
		$this->assertTrue( $data['requires_admin_review'] );
		$this->assertSame( 'fresh_review_requested', $data['review_status'] );
		$this->assertSame( 'request_admin_review', $data['review_action'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['reviewed_block_items_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['resolves_proof'] );
		$this->assertFalse( $data['resolves_proof_token'] );
		$this->assertFalse( $data['approves_review_proof'] );
		$this->assertFalse( $data['retry_save_attempted'] );
		$this->assertFalse( $data['normal_save_attempted'] );
		$this->assertFalse( $data['applies_recovery'] );
		$this->assertFalse( $data['changes_locks'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash, $candidate_hash ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_rest_fresh_review_request_permissions_check
	 * @covers ::wp_de_rtc_rest_fresh_review_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_fresh_review_request_rest_base
	 */
	public function test_fresh_review_request_supports_pages_without_mutating() {
		$page_id          = $this->create_sync_meta_post( 'page', 22 );
		$before_post      = get_post( $page_id );
		$before_revisions = $this->get_post_revisions( $page_id );
		$request          = $this->create_fresh_review_request(
			'pages',
			$page_id,
			array(
				'client_base_version'        => '22',
				'server_version'             => '22',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => hash( 'sha256', 'fresh review page proposed content' ),
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review page candidate content' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertSame( 'page', $data['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assert_post_unchanged( $page_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 */
	public function test_fresh_review_request_rejects_stale_version_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 23 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '22',
				'server_version'             => '22',
				'proposed_post_content_hash' => hash( 'sha256', 'fresh review stale proposed content' ),
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review stale candidate content' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_fresh_review_request_stale_base', $data['rest_route'] );
		$this->assertSame( '22', $data['client_base_version'] );
		$this->assertSame( '23', $data['server_version'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 */
	public function test_fresh_review_request_rejects_missing_hash_evidence_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 24 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '24',
				'server_version'             => '24',
				'proposed_post_content_hash' => 'not-a-sha256-hash',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'missing_fresh_review_request_hash_evidence', $data['detail'] );
		$this->assertSame( 'post_fresh_review_request', $data['rest_route'] );
		$this->assertContains( 'proposed_post_content_hash', $data['missing_hash_evidence_fields'] );
		$this->assertContains( 'candidate_post_content_hash', $data['missing_hash_evidence_fields'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['resolves_proof_token'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 * @covers ::wp_de_rtc_find_raw_post_content_param_paths
	 */
	public function test_fresh_review_request_rejects_raw_content_without_exposing_it_or_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 25 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$raw_content      = 'This fresh review raw content must not be echoed.';
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '25',
				'server_version'             => '25',
				'proposed_post_content_hash' => hash( 'sha256', 'fresh review raw proposed content' ),
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review raw candidate content' ),
				'raw_content'                => $raw_content,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'fresh_review_request_raw_post_content_rejected', $data['detail'] );
		$this->assertTrue( $data['request_raw_content_included'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertSame( array( 'raw_content' ), $data['raw_content_param_paths'] );
		$this->assertArrayNotHasKey( 'raw_content', $data );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['resolves_proof_token'] );
		$this->assertStringNotContainsString( $raw_content, wp_json_encode( $data ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_permissions_check
	 */
	public function test_fresh_review_request_requires_feature_enablement_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 26 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		delete_option( 'wp_de_rtc_enabled' );

		$request  = $this->create_valid_fresh_review_request( 'posts', $post_id, '26' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'feature_disabled_for_post', $data['detail'] );
		$this->assertSame( 'post_fresh_review_request', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_permissions_check
	 */
	public function test_fresh_review_request_requires_edit_post_capability_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 27 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$subscriber_user_id );

		$request  = $this->create_valid_fresh_review_request( 'posts', $post_id, '27' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_permissions_check
	 * @covers ::wp_de_rtc_rest_fresh_review_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_fresh_review_request_rest_base
	 */
	public function test_fresh_review_request_requires_matching_post_type_rest_base_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 28 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_valid_fresh_review_request( 'pages', $post_id, '28' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	private function create_valid_fresh_review_request( $rest_base, $post_id, $version ) {
		return $this->create_fresh_review_request(
			$rest_base,
			$post_id,
			array(
				'client_base_version'        => (string) $version,
				'server_version'             => (string) $version,
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => hash( 'sha256', 'fresh review valid proposed content ' . $version ),
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review valid candidate content ' . $version ),
			)
		);
	}

	private function create_fresh_review_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/fresh-review-request' );
		$request->set_body_params( $params );

		return $request;
	}

	private function create_sync_meta_post( $post_type, $version ) {
		$content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Fresh review request current content.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content );

		return self::factory()->post->create(
			array(
				'post_type'    => $post_type,
				'post_title'   => 'DE-RTC fresh review request ' . $post_type,
				'post_author'  => self::$admin_user_id,
				'post_content' => $content,
			)
		);
	}

	private function get_post_revisions( $post_id ) {
		return wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
	}

	private function assert_post_unchanged( $post_id, $before_content, $before_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( $before_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	private function assert_payload_omits_private_fields( $payload, $forbidden_fragments ) {
		$encoded_payload = wp_json_encode( $payload );

		$this->assertIsString( $encoded_payload );

		foreach ( $forbidden_fragments as $forbidden_fragment ) {
			$this->assertStringNotContainsString( $forbidden_fragment, $encoded_payload );
		}

		$this->assert_payload_omits_keys(
			$payload,
			array(
				'token',
				'token_hash',
				'proof',
				'proof_signature',
				'proof_internals',
				'proof_signature_valid',
				'field_based_review_approval_proof',
				'review_approval_proof',
				'accepted_review_approval_proof',
				'reviewed_block_items',
				'reviewer_user_id',
				'low_privileged_saver_user_id',
				'saver_user_id',
				'raw_content',
				'raw_post_content',
				'post_content',
				'proposed_post_content',
				'candidate_post_content',
			)
		);
	}

	private function assert_payload_omits_keys( $payload, $forbidden_keys ) {
		if ( is_object( $payload ) ) {
			$payload = get_object_vars( $payload );
		}

		if ( ! is_array( $payload ) ) {
			return;
		}

		foreach ( $payload as $key => $value ) {
			if ( is_string( $key ) ) {
				$this->assertNotContains( $key, $forbidden_keys );
			}

			$this->assert_payload_omits_keys( $value, $forbidden_keys );
		}
	}
}
