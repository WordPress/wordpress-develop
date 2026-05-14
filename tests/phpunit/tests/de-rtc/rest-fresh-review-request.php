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
	protected static $author_user_id;
	protected static $subscriber_user_id;

	protected $server;
	private $fresh_review_record_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_user_id     = $factory->user->create( array( 'role' => 'author' ) );
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
		foreach ( array_unique( $this->fresh_review_record_ids ) as $record_id ) {
			$option_name = wp_de_rtc_get_opaque_fresh_review_request_record_option_name( $record_id );

			if ( '' !== $option_name ) {
				delete_option( $option_name );
			}
		}

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
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/fresh-review-decision', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/fresh-review-decision', $routes );
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
		$this->remember_fresh_review_request_record( $data );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertTrue( $data['fresh_review_request_accepted'] );
		$this->assertSame( 'requested', $data['fresh_review_request_status'] );
		$this->assertTrue( $data['fresh_review_request_recorded'] );
		$this->assertSame( 1, preg_match( '/^de-rtc-fresh-review-[a-f0-9]{24}$/', $data['fresh_review_request_record_id'] ) );
		$this->assertSame( 'option', $data['fresh_review_request_record']['storage'] );
		$this->assertSame( 'requested', $data['fresh_review_request_record']['status'] );
		$this->assertTrue( $data['fresh_review_request_record']['recorded'] );
		$this->assertFalse( $data['fresh_review_request_record']['decision_recorded'] );
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
		$record = wp_de_rtc_get_opaque_fresh_review_request_record( $data['fresh_review_request_record_id'] );
		$this->assertIsArray( $record );
		$this->assertSame( 'opaque_fresh_review_request_record', $record['type'] );
		$this->assertSame( 'requested', $record['status'] );
		$this->assertSame( $post_id, $record['post_id'] );
		$this->assertSame( '21', $record['server_version'] );
		$this->assertSame( $proposed_hash, $record['proposed_post_content_hash'] );
		$this->assertSame( $candidate_hash, $record['candidate_post_content_hash'] );
		$this->assertFalse( $record['decision_recorded'] );
		$this->assertFalse( $record['saves_post'] );
		$this->assertFalse( $record['mutates_post_content'] );
		$this->assertFalse( $record['creates_revision'] );
		$this->assertFalse( $record['claims_saved'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash, $candidate_hash ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 */
	public function test_fresh_review_request_accepts_proposed_content_hash_only_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 29 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review imported local updates' );
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '29',
				'server_version'             => '29',
				'pending_change_count'       => 2,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->remember_fresh_review_request_record( $data );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertSame( 'requested', $data['fresh_review_request_status'] );
		$this->assertSame( array( 'proposed_post_content_hash' ), $data['hash_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['resolves_proof_token'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_request_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_request_result
	 */
	public function test_fresh_review_request_accepts_import_handoff_with_current_server_version_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 30 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review imported stale-base local updates' );
		$request          = $this->create_fresh_review_request(
			'posts',
			$post_id,
			array(
				'client_base_version'         => '29',
				'server_version'              => '30',
				'pending_change_count'        => 2,
				'proposed_post_content_hash'  => $proposed_hash,
				'local_updates_import_status' => 'blocked',
				'local_updates_import_reason' => 'fresh_review_required',
				'fresh_review_request_status' => 'fresh_review_required',
				'fresh_review_request_action' => 'request_admin_review',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->remember_fresh_review_request_record( $data );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertSame( 'requested', $data['fresh_review_request_status'] );
		$this->assertSame( 'post_fresh_review_request', $data['rest_route'] );
		$this->assertSame( '29', $data['client_base_version'] );
		$this->assertSame( '30', $data['server_version'] );
		$this->assertSame( array( 'proposed_post_content_hash' ), $data['hash_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash ) );
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
		$this->remember_fresh_review_request_record( $data );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_request_accepted_for_admin_review', $data['result'] );
		$this->assertSame( 'page', $data['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assert_post_unchanged( $page_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_endpoint
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_permissions_check
	 * @covers ::wp_de_rtc_get_fresh_review_decision_result
	 * @covers ::wp_de_rtc_record_opaque_fresh_review_decision
	 * @covers ::wp_de_rtc_get_normalized_fresh_review_decision_block_items
	 * @covers ::wp_de_rtc_count_fresh_review_decision_block_items
	 */
	public function test_fresh_review_decision_accepts_approved_hash_only_decision_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 31 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review approved proposed content' );
		$candidate_hash   = hash( 'sha256', 'fresh review approved candidate content' );
		$block_hash       = hash( 'sha256', 'fresh review approved risky block' );
		$filtered_hash    = hash( 'sha256', 'fresh review approved risky block filtered' );
		$request_data     = $this->create_fresh_review_request_record( $post_id, '31', $proposed_hash, $candidate_hash );
		$request          = $this->create_fresh_review_decision_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '31',
				'server_version'                 => '31',
				'fresh_review_decision'          => 'approved',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
				'candidate_post_content_hash'    => $candidate_hash,
				'reviewed_candidate_content_hash' => $candidate_hash,
				'reviewed_block_items'           => array(
					$this->get_fresh_review_block_decision_item(
						array(
							'id'                         => 'fresh-risk-approve',
							'review_status'              => 'approved',
							'proposed_content_hash'      => $block_hash,
							'reviewed_proposed_content_hash' => $block_hash,
							'kses_filtered_content_hash' => $filtered_hash,
						)
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_decision_approved_for_retry_save', $data['result'] );
		$this->assertTrue( $data['fresh_review_decision_accepted'] );
		$this->assertSame( 'approved', $data['fresh_review_decision'] );
		$this->assertSame( 'decision_recorded', $data['fresh_review_request_status'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertTrue( $data['fresh_review_request_record']['decision_recorded'] );
		$this->assertSame( 'approved', $data['fresh_review_request_record']['decision_status'] );
		$this->assertSame( 'post_fresh_review_decision', $data['rest_route'] );
		$this->assertSame( 'approved_by_unfiltered_html_reviewer', $data['review_status'] );
		$this->assertTrue( $data['reviewed_block_items_included'] );
		$this->assertSame( 1, $data['reviewed_block_item_count'] );
		$this->assertSame( 1, $data['reviewed_block_decision_counts']['approved'] );
		$this->assertSame( 0, $data['reviewed_block_decision_counts']['rejected'] );
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

		$record = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertIsArray( $record );
		$this->assertTrue( $record['decision_recorded'] );
		$this->assertSame( 'approved', $record['fresh_review_decision'] );
		$this->assertSame( $block_hash, $record['reviewed_block_items'][0]['proposed_content_hash'] );
		$this->assertArrayNotHasKey( 'reviewer_user_id', $record );
		$this->assertArrayNotHasKey( 'saver_user_id', $record );
		$this->assertArrayNotHasKey( 'low_privileged_saver_user_id', $record );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash, $candidate_hash, $block_hash, $filtered_hash ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_decision_result
	 * @covers ::wp_de_rtc_record_opaque_fresh_review_decision
	 * @covers ::wp_de_rtc_get_normalized_fresh_review_decision_block_items
	 */
	public function test_fresh_review_decision_accepts_rejected_hash_only_decision_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 32 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review rejected proposed content' );
		$block_hash       = hash( 'sha256', 'fresh review rejected risky block' );
		$request_data     = $this->create_fresh_review_request_record( $post_id, '32', $proposed_hash );
		$request          = $this->create_fresh_review_decision_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '32',
				'server_version'                 => '32',
				'fresh_review_decision'          => 'rejected',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
				'reviewed_block_items'           => array(
					$this->get_fresh_review_block_decision_item(
						array(
							'id'                         => 'fresh-risk-reject',
							'review_status'              => 'rejected',
							'proposed_content_hash'      => $block_hash,
							'reviewed_proposed_content_hash' => $block_hash,
						)
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_decision_rejected_for_author_revision', $data['result'] );
		$this->assertSame( 'rejected', $data['fresh_review_decision'] );
		$this->assertSame( 'rejected_by_unfiltered_html_reviewer', $data['review_status'] );
		$this->assertSame( 0, $data['reviewed_block_decision_counts']['approved'] );
		$this->assertSame( 1, $data['reviewed_block_decision_counts']['rejected'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['approves_review_proof'] );

		$record = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertIsArray( $record );
		$this->assertSame( 'rejected', $record['fresh_review_decision'] );
		$this->assertSame( 'rejected', $record['reviewed_block_items'][0]['review_status'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash, $block_hash ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_decision_result
	 */
	public function test_fresh_review_decision_rejects_stale_server_mismatch_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 33 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review stale decision proposed content' );
		$request_data     = $this->create_fresh_review_request_record( $post_id, '33', $proposed_hash );
		$request          = $this->create_fresh_review_decision_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '33',
				'server_version'                 => '32',
				'fresh_review_decision'          => 'approved',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_fresh_review_decision_stale_base', $data['rest_route'] );
		$this->assertSame( '33', $data['client_base_version'] );
		$this->assertSame( '33', $data['server_version'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );

		$record = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertIsArray( $record );
		$this->assertSame( 'requested', $record['status'] );
		$this->assertFalse( $record['decision_recorded'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_permissions_check
	 */
	public function test_fresh_review_decision_requires_unfiltered_html_reviewer_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 34, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review decision capability proposed content' );
		$request_data     = $this->create_fresh_review_request_record( $post_id, '34', $proposed_hash );

		wp_set_current_user( self::$author_user_id );

		$request  = $this->create_valid_fresh_review_decision_request( 'posts', $post_id, '34', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $response, 403 );
		$this->assertSame( 'fresh_review_decision_requires_unfiltered_html_reviewer', $data['detail'] );
		$this->assertSame( 'post_fresh_review_decision', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_decision_permissions_check
	 */
	public function test_fresh_review_decision_requires_edit_post_capability_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 35 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review decision edit capability proposed content' );
		$request_data     = $this->create_fresh_review_request_record( $post_id, '35', $proposed_hash );

		wp_set_current_user( self::$subscriber_user_id );

		$request  = $this->create_valid_fresh_review_decision_request( 'posts', $post_id, '35', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
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
		$this->assertNotContains( 'candidate_post_content_hash', $data['missing_hash_evidence_fields'] );
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

	private function create_fresh_review_request_record( $post_id, $version, $proposed_hash, $candidate_hash = null ) {
		$params = array(
			'client_base_version'        => (string) $version,
			'server_version'             => (string) $version,
			'pending_change_count'       => 1,
			'proposed_post_content_hash' => $proposed_hash,
		);

		if ( null !== $candidate_hash ) {
			$params['candidate_post_content_hash'] = $candidate_hash;
		}

		$response = rest_get_server()->dispatch( $this->create_fresh_review_request( 'posts', $post_id, $params ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->remember_fresh_review_request_record( $data );

		return $data;
	}

	private function create_valid_fresh_review_decision_request( $rest_base, $post_id, $version, $record_id, $proposed_hash ) {
		return $this->create_fresh_review_decision_request(
			$rest_base,
			$post_id,
			array(
				'fresh_review_request_record_id' => $record_id,
				'client_base_version'            => (string) $version,
				'server_version'                 => (string) $version,
				'fresh_review_decision'          => 'approved',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
			)
		);
	}

	private function create_fresh_review_decision_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/fresh-review-decision' );
		$request->set_body_params( $params );

		return $request;
	}

	private function get_fresh_review_block_decision_item( $overrides = array() ) {
		return array_merge(
			array(
				'id'                           => 'fresh-risk-item',
				'review_status'                => 'approved',
				'review_evidence_type'         => 'kses_block_hash_only_change',
				'content_review_policy'        => 'kses',
				'proposed_content_hash'        => hash( 'sha256', 'fresh review block proposed content' ),
				'reviewed_proposed_content_hash' => hash( 'sha256', 'fresh review block proposed content' ),
				'kses_filtered_content_hash'   => hash( 'sha256', 'fresh review block filtered content' ),
			),
			$overrides
		);
	}

	private function remember_fresh_review_request_record( $payload ) {
		if ( is_array( $payload ) && ! empty( $payload['fresh_review_request_record_id'] ) ) {
			$this->fresh_review_record_ids[] = $payload['fresh_review_request_record_id'];
		}
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

	private function create_sync_meta_post( $post_type, $version, $post_author = null ) {
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
				'post_author'  => null === $post_author ? self::$admin_user_id : (int) $post_author,
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
