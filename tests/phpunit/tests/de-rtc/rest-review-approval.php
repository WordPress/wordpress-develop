<?php
/**
 * Tests for the Distributed Editing retry-save review approval endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Review_Approval extends WP_Test_REST_TestCase {

	protected static $admin_user_id;
	protected static $author_user_id;

	protected $server;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_user_id = $factory->user->create( array( 'role' => 'author' ) );
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
	public function test_review_approval_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/review-approval', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/review-approval', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_rest_review_approval_permissions_check
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_approval_result
	 * @covers ::wp_de_rtc_get_request_hash_evidence
	 * @covers ::wp_de_rtc_is_sha256_hash
	 */
	public function test_review_approval_accepts_hash_only_proof_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 12, self::$admin_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_hash    = hash( 'sha256', 'review approval proposed content' );
		$candidate_hash   = hash( 'sha256', 'review approval candidate content' );
		$request          = $this->create_review_approval_request(
			'posts',
			$post_id,
			array(
				'client_base_version'              => '12',
				'accepted_proof_server_version'    => '12',
				'pending_change_count'             => 2,
				'proposed_post_content_hash'       => $proposed_hash,
				'reviewed_proposed_content_hash'   => $proposed_hash,
				'candidate_post_content_hash'      => $candidate_hash,
				'reviewed_candidate_content_hash'  => $candidate_hash,
				'kses_filtered_proposed_content_hash' => hash( 'sha256', 'review approval filtered proposed content' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'review_approval_accepted_for_retry_save', $data['result'] );
		$this->assertTrue( $data['review_approval_accepted'] );
		$this->assertSame( 'post_retry_save_review_approval', $data['rest_route'] );
		$this->assertSame( 'approved_for_retry_save', $data['approval_status'] );
		$this->assertSame( 'retry_save_with_reviewer_approval', $data['approval_action'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_required_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_scope'] );
		$this->assertSame( $proposed_hash, $data['proposed_post_content_hash'] );
		$this->assertSame( $candidate_hash, $data['candidate_post_content_hash'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertTrue( $data['save_path_required'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_rest_review_approval_permissions_check
	 */
	public function test_review_approval_requires_unfiltered_html_reviewer_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 12, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_hash    = hash( 'sha256', 'review approval proposed content' );
		$candidate_hash   = hash( 'sha256', 'review approval candidate content' );

		wp_set_current_user( self::$author_user_id );

		$request = $this->create_review_approval_request(
			'posts',
			$post_id,
			array(
				'client_base_version'             => '12',
				'accepted_proof_server_version'   => '12',
				'proposed_post_content_hash'      => $proposed_hash,
				'reviewed_proposed_content_hash'  => $proposed_hash,
				'candidate_post_content_hash'     => $candidate_hash,
				'reviewed_candidate_content_hash' => $candidate_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $response, 403 );
		$this->assertSame( 'post_retry_save_review_approval', $data['rest_route'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_action'] );
		$this->assertSame( 'retry_save_with_reviewer_approval', $data['approval_action'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_approval_result
	 */
	public function test_review_approval_rejects_stale_version_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 13, self::$admin_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_hash    = hash( 'sha256', 'review approval proposed content' );
		$candidate_hash   = hash( 'sha256', 'review approval candidate content' );
		$request          = $this->create_review_approval_request(
			'posts',
			$post_id,
			array(
				'client_base_version'             => '12',
				'accepted_proof_server_version'   => '12',
				'proposed_post_content_hash'      => $proposed_hash,
				'reviewed_proposed_content_hash'  => $proposed_hash,
				'candidate_post_content_hash'     => $candidate_hash,
				'reviewed_candidate_content_hash' => $candidate_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_retry_save_review_approval_stale_base', $data['rest_route'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_approval_result
	 */
	public function test_review_approval_rejects_hash_mismatch_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 12, self::$admin_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_hash    = hash( 'sha256', 'review approval proposed content' );
		$candidate_hash   = hash( 'sha256', 'review approval candidate content' );
		$request          = $this->create_review_approval_request(
			'posts',
			$post_id,
			array(
				'client_base_version'             => '12',
				'accepted_proof_server_version'   => '12',
				'proposed_post_content_hash'      => $proposed_hash,
				'reviewed_proposed_content_hash'  => $proposed_hash,
				'candidate_post_content_hash'     => $candidate_hash,
				'reviewed_candidate_content_hash' => hash( 'sha256', 'different candidate content' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'post_retry_save_review_approval', $data['rest_route'] );
		$this->assertSame( 'review_approval_hash_evidence_mismatch', $data['detail'] );
		$this->assertContains( 'reviewed_candidate_content_hash', $data['mismatched_hash_evidence_fields'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_approval_result
	 * @covers ::wp_de_rtc_find_raw_post_content_param_paths
	 */
	public function test_review_approval_rejects_raw_content_without_exposing_it_or_mutating() {
		$post_id          = $this->create_sync_meta_post( 12, self::$admin_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proposed_hash    = hash( 'sha256', 'review approval proposed content' );
		$candidate_hash   = hash( 'sha256', 'review approval candidate content' );
		$request          = $this->create_review_approval_request(
			'posts',
			$post_id,
			array(
				'client_base_version'             => '12',
				'accepted_proof_server_version'   => '12',
				'proposed_post_content_hash'      => $proposed_hash,
				'reviewed_proposed_content_hash'  => $proposed_hash,
				'candidate_post_content_hash'     => $candidate_hash,
				'reviewed_candidate_content_hash' => $candidate_hash,
				'raw_content'                     => 'This raw post content must not be echoed.',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'review_approval_raw_post_content_rejected', $data['detail'] );
		$this->assertTrue( $data['request_raw_content_included'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertSame( array( 'raw_content' ), $data['raw_content_param_paths'] );
		$this->assertArrayNotHasKey( 'raw_content', $data );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertStringNotContainsString( 'This raw post content', wp_json_encode( $data ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	private function create_review_approval_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/review-approval' );
		$request->set_body_params( $params );

		return $request;
	}

	private function create_sync_meta_post( $version, $post_author ) {
		$content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Review approval current content.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content );

		return self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC review approval post',
				'post_author'  => $post_author,
				'post_content' => $content,
			)
		);
	}

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
}
