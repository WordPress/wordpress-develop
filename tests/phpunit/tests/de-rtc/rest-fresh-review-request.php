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
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/fresh-review-consume', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/fresh-review-consume', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/fresh-review-lifecycle', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/fresh-review-lifecycle', $routes );
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
		$this->assertSame( self::$admin_user_id, $record['reviewer_user_id'] );
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
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_permissions_check
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_fresh_review_consume_rest_base
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 */
	public function test_fresh_review_consume_accepts_approved_decision_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 36 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume proposed content' );
		$candidate_hash   = hash( 'sha256', 'fresh review consume candidate content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '36', $proposed_hash, $candidate_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$request          = $this->create_fresh_review_consume_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '36',
				'server_version'                 => '36',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
				'candidate_post_content_hash'    => $candidate_hash,
				'reviewed_candidate_content_hash' => $candidate_hash,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_decision_eligible_for_retry_save_handoff', $data['result'] );
		$this->assertTrue( $data['fresh_review_decision_consumption_validated'] );
		$this->assertTrue( $data['fresh_review_decision_eligible_for_retry_save'] );
		$this->assertSame( 'approved', $data['fresh_review_decision_status'] );
		$this->assertSame( 'decision_recorded', $data['fresh_review_request_status'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'post_fresh_review_consume', $data['rest_route'] );
		$this->assertSame( 'approved_by_unfiltered_html_reviewer', $data['review_status'] );
		$this->assertSame( 'consume_fresh_review_decision', $data['review_action'] );
		$this->assertSame( 'eligible_for_retry_save_handoff', $data['approval_status'] );
		$this->assertSame( 'prepare_retry_save_handoff', $data['approval_action'] );
		$this->assertSame( array( 'proposed_post_content_hash', 'reviewed_proposed_content_hash', 'candidate_post_content_hash', 'reviewed_candidate_content_hash' ), $data['hash_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['resolves_proof'] );
		$this->assertFalse( $data['resolves_proof_token'] );
		$this->assertFalse( $data['approves_review_proof'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assertFalse( $data['retry_save_attempted'] );
		$this->assertFalse( $data['normal_save_attempted'] );
		$this->assertFalse( $data['applies_recovery'] );
		$this->assertFalse( $data['changes_locks'] );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash, $candidate_hash ) );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 */
	public function test_fresh_review_consume_rejects_rejected_decision_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 37 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume rejected proposed content' );
		$request_data     = $this->create_rejected_fresh_review_decision_record( $post_id, '37', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$request          = $this->create_valid_fresh_review_consume_request( 'posts', $post_id, '37', $request_data['fresh_review_request_record_id'], $proposed_hash );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'fresh_review_decision_not_approved_for_retry_save', $data['detail'] );
		$this->assertSame( 'rejected', $data['fresh_review_decision_status'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 */
	public function test_fresh_review_consume_rejects_stale_decision_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 38 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume stale proposed content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '38', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$request          = $this->create_valid_fresh_review_consume_request( 'posts', $post_id, '37', $request_data['fresh_review_request_record_id'], $proposed_hash );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_fresh_review_consume_stale_base', $data['rest_route'] );
		$this->assertSame( '37', $data['client_base_version'] );
		$this->assertSame( '38', $data['server_version'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 */
	public function test_fresh_review_consume_rejects_missing_record_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 39 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume missing record proposed content' );
		$request          = $this->create_valid_fresh_review_consume_request( 'posts', $post_id, '39', 'de-rtc-fresh-review-000000000000000000000000', $proposed_hash );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'fresh_review_decision_record_unavailable', $data['detail'] );
		$this->assertSame( 'post_fresh_review_consume', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 */
	public function test_fresh_review_consume_rejects_hash_mismatch_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 40 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume hash proposed content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '40', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$request          = $this->create_fresh_review_consume_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '40',
				'server_version'                 => '40',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => hash( 'sha256', 'fresh review consume mismatched reviewed content' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'fresh_review_consume_hash_evidence_mismatch', $data['detail'] );
		$this->assertContains( 'reviewed_proposed_content_hash', $data['mismatched_hash_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 * @covers ::wp_de_rtc_get_fresh_review_reviewer_authority_recheck_error
	 */
	public function test_fresh_review_consume_rejects_deleted_original_reviewer_without_mutating_or_updating_record() {
		$post_id          = $this->create_sync_meta_post( 'post', 59 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume deleted reviewer proposed content' );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $reviewer_id );

		$request_data  = $this->create_approved_fresh_review_decision_record( $post_id, '59', $proposed_hash );
		$record_before = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( $reviewer_id, $record_before['reviewer_user_id'] );

		self::delete_user( $reviewer_id );
		wp_set_current_user( self::$admin_user_id );

		$response = rest_get_server()->dispatch(
			$this->create_valid_fresh_review_consume_request( 'posts', $post_id, '59', $request_data['fresh_review_request_record_id'], $proposed_hash )
		);
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'fresh_review_reviewer_account_deleted', $data['detail'] );
		$this->assertSame( 'post_fresh_review_consume', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'capability_drift', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'request_new_fresh_review', $data['fresh_review_decision_lifecycle_action'] );
		$this->assertSame( 'reviewer_account_deleted', $data['fresh_review_reviewer_authority_status'] );
		$this->assertSame( 'deleted', $data['fresh_review_reviewer_account_status'] );
		$this->assertSame( 'unavailable', $data['fresh_review_reviewer_capability_status'] );
		$this->assertTrue( $data['fresh_review_reviewer_identity_retained'] );
		$this->assertFalse( $data['fresh_review_reviewer_identity_exposed'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_consume_result
	 * @covers ::wp_de_rtc_find_raw_post_content_param_paths
	 */
	public function test_fresh_review_consume_rejects_raw_content_without_exposing_it_or_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 41 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume raw proposed content' );
		$raw_content      = 'This fresh review consume raw content must not be echoed.';
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '41', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$request          = $this->create_fresh_review_consume_request(
			'posts',
			$post_id,
			array(
				'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
				'client_base_version'            => '41',
				'server_version'                 => '41',
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
				'raw_content'                    => $raw_content,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'fresh_review_consume_raw_post_content_rejected', $data['detail'] );
		$this->assertTrue( $data['request_raw_content_included'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertSame( array( 'raw_content' ), $data['raw_content_param_paths'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['consumes_review_decision'] );
		$this->assertStringNotContainsString( $raw_content, wp_json_encode( $data ) );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_permissions_check
	 */
	public function test_fresh_review_consume_requires_feature_enablement_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 42 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume disabled proposed content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '42', $proposed_hash );

		delete_option( 'wp_de_rtc_enabled' );

		$request  = $this->create_valid_fresh_review_consume_request( 'posts', $post_id, '42', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'feature_disabled_for_post', $data['detail'] );
		$this->assertSame( 'post_fresh_review_consume', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_permissions_check
	 */
	public function test_fresh_review_consume_requires_permission_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 43, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume permission proposed content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '43', $proposed_hash );

		wp_set_current_user( self::$author_user_id );

		$request  = $this->create_valid_fresh_review_consume_request( 'posts', $post_id, '43', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->as_error()->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $response, 403 );
		$this->assertSame( 'fresh_review_consume_requires_unfiltered_html_reviewer', $data['detail'] );
		$this->assertSame( 'post_fresh_review_consume', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_permissions_check
	 * @covers ::wp_de_rtc_rest_fresh_review_consume_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_fresh_review_consume_rest_base
	 */
	public function test_fresh_review_consume_requires_matching_post_type_rest_base_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 44 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_hash    = hash( 'sha256', 'fresh review consume route proposed content' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '44', $proposed_hash );
		$request          = $this->create_valid_fresh_review_consume_request( 'pages', $post_id, '44', $request_data['fresh_review_request_record_id'], $proposed_hash );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_record_opaque_fresh_review_decision_retry_save_consumed
	 */
	public function test_retry_save_consumes_approved_fresh_review_decision_and_records_revision_evidence() {
		$post_id          = $this->create_sync_meta_post( 'post', 45 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review retry-save approved content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '45', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '45', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$before_revisions = $this->get_post_revisions( $post_id );
		$save_request     = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '45',
				'accepted_proof_server_version' => '45',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $consume_data,
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$record          = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumed'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumption_recorded'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $save_data['fresh_review_request_record_id'] );
		$this->assertSame( '45', $save_data['previous_server_version'] );
		$this->assertSame( '46', $save_data['server_version'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $save_data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $save_data['revision_ids_after_save'] );
		$this->assertSame(
			array_values( array_diff( $save_data['revision_ids_after_save'], $save_data['revision_ids_before_save'] ) ),
			$save_data['created_revision_ids']
		);
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertNotEmpty( $save_data['created_revision_ids'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '46', $parsed_saved['sync_meta']['version'] );
		$this->assertIsArray( $record );
		$this->assertTrue( $record['fresh_review_decision_consumed'] );
		$this->assertTrue( $record['consumes_review_decision'] );
		$this->assertTrue( $record['retry_save_applied'] );
		$this->assertSame( 'retry_save_consumed', $record['status'] );
		$this->assertSame( '46', $record['saved_server_version'] );
		$this->assertSame( 'retry_save_consumed', $record['fresh_review_lifecycle_status'] );
		$this->assertSame( 'consumed', $record['fresh_review_lifecycle_event'] );
		$this->assertSame( 'guarded_retry_save_applied', $record['fresh_review_lifecycle_reason'] );
		$this->assertSame( 'retry_save_consumed', $save_data['fresh_review_request_record']['lifecycle_status'] );
		$this->assertSame( 'consumed', $save_data['fresh_review_request_record']['lifecycle_event'] );
		$this->assertSame( '45', $save_data['fresh_review_request_record']['previous_server_version'] );
		$this->assertSame( '46', $save_data['fresh_review_request_record']['saved_server_version'] );
		$this->assert_payload_omits_private_fields( $save_data, array( 'Fresh review retry-save approved content.' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_record_opaque_fresh_review_decision_retry_save_consumed
	 */
	public function test_retry_save_consumes_imported_fresh_review_handoff_against_current_server_version() {
		$post_id          = $this->create_sync_meta_post( 'post', 52 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review imported retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_imported_approved_fresh_review_decision_record( $post_id, '51', '52', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '52', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$before_revisions = $this->get_post_revisions( $post_id );
		$save_request     = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'            => '52',
				'accepted_proof_server_version'  => '52',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proposed_hash,
				'accepted_fresh_review_decision' => $consume_data,
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$record          = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumed'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumption_recorded'] );
		$this->assertSame( '52', $save_data['previous_server_version'] );
		$this->assertSame( '53', $save_data['server_version'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $save_data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $save_data['revision_ids_after_save'] );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '53', $parsed_saved['sync_meta']['version'] );
		$this->assertIsArray( $record );
		$this->assertTrue( $record['imported_fresh_review_handoff'] );
		$this->assertSame( '51', $record['client_base_version_at_decision'] );
		$this->assertSame( '52', $record['server_version_at_decision'] );
		$this->assertSame( 'retry_save_consumed', $record['fresh_review_lifecycle_status'] );
		$this->assertSame( '53', $record['saved_server_version'] );
		$this->assertSame( 'retry_save_consumed', $save_data['fresh_review_request_record']['lifecycle_status'] );
		$this->assertSame( '53', $save_data['fresh_review_request_record']['saved_server_version'] );
		$this->assert_payload_omits_private_fields( $save_data, array( 'Fresh review imported retry-save content.' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 */
	public function test_retry_save_rejects_fresh_review_replay_after_success_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 54 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review replay retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '54', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '54', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$save_request     = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'            => '54',
				'accepted_proof_server_version'  => '54',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proposed_hash,
				'accepted_fresh_review_decision' => $consume_data,
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$save_data     = $save_response->get_data();

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );

		$record_after_success = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$after_success_post   = get_post( $post_id );
		$after_success_revisions = $this->get_post_revisions( $post_id );
		$replay_evidence      = $consume_data;
		$replay_evidence['client_base_version'] = $save_data['server_version'];
		$replay_evidence['server_version']      = $save_data['server_version'];
		$replay_request       = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'            => $save_data['server_version'],
				'accepted_proof_server_version'  => $save_data['server_version'],
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proposed_hash,
				'accepted_fresh_review_decision' => $replay_evidence,
			)
		);

		$replay_response = rest_get_server()->dispatch( $replay_request );
		$replay_data     = $replay_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $replay_response, 403 );
		$this->assertSame( 'fresh_review_decision_already_consumed_for_retry_save', $replay_data['detail'] );
		$this->assertSame( 'post_retry_save', $replay_data['rest_route'] );
		$this->assertTrue( $replay_data['fresh_review_decision_record_consumed'] );
		$this->assertFalse( $replay_data['fresh_review_decision_consumed'] );
		$this->assertSame( 'already_consumed', $replay_data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'request_new_fresh_review', $replay_data['fresh_review_decision_lifecycle_action'] );
		$this->assertTrue( $replay_data['fresh_review_support_evidence_available'] );
		$this->assertSame( 'retry_save_consumed', $replay_data['fresh_review_request_status'] );
		$this->assertSame( 'retry_save_consumed', $replay_data['fresh_review_request_record']['status'] );
		$this->assertSame( 'retry_save_consumed', $replay_data['fresh_review_request_record']['lifecycle_status'] );
		$this->assertTrue( $replay_data['fresh_review_request_record']['decision_consumed'] );
		$this->assertSame( '54', $replay_data['fresh_review_previous_server_version'] );
		$this->assertSame( '55', $replay_data['fresh_review_saved_server_version'] );
		$this->assertFalse( $replay_data['saves_post'] );
		$this->assertFalse( $replay_data['mutates_post_content'] );
		$this->assertFalse( $replay_data['creates_revision'] );
		$this->assertFalse( $replay_data['claims_saved'] );
		$this->assertSame( $record_after_success, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $after_success_post->post_content, $after_success_revisions );
		$this->assert_payload_omits_private_fields( $replay_data, array( 'Fresh review replay retry-save content.' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption
	 * @covers ::wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result
	 * @covers ::wp_de_rtc_get_fresh_review_decision_consumption_claim_stale_after_seconds
	 * @covers ::wp_de_rtc_get_fresh_review_decision_consumption_conflict_error
	 */
	public function test_retry_save_rejects_fresh_review_consumption_claim_race_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 62 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review claimed retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '62', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '62', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$claim            = wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption(
			$record_before,
			get_post( $post_id ),
			array(
				'previous_server_version'     => '62',
				'proposed_post_content_hash'  => $proposed_hash,
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review claimed candidate content' ),
			)
		);

		$this->assertIsArray( $claim );
		$this->assertTrue( $claim['fresh_review_decision_consumption_claimed'] );

		$consume_replay_response = rest_get_server()->dispatch(
			$this->create_valid_fresh_review_consume_request( 'posts', $post_id, '62', $request_data['fresh_review_request_record_id'], $proposed_hash )
		);
		$consume_replay_data     = $consume_replay_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $consume_replay_response, 403 );
		$this->assertSame( 'fresh_review_decision_consumption_already_claimed_for_retry_save', $consume_replay_data['detail'] );
		$this->assertSame( 'post_fresh_review_consume', $consume_replay_data['rest_route'] );
		$this->assertSame( 'consumption_in_progress', $consume_replay_data['fresh_review_decision_lifecycle_status'] );
		$this->assertTrue( $consume_replay_data['fresh_review_decision_consumption_claimed'] );
		$this->assertFalse( $consume_replay_data['consumes_review_decision'] );
		$this->assertFalse( $consume_replay_data['saves_post'] );
		$this->assertFalse( $consume_replay_data['mutates_post_content'] );

		$save_response = rest_get_server()->dispatch(
			$this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'            => '62',
					'accepted_proof_server_version'  => '62',
					'pending_change_count'           => 1,
					'proposed_post_content'          => $proposed_content,
					'proposed_post_content_hash'     => $proposed_hash,
					'accepted_fresh_review_decision' => $consume_data,
				)
			)
		);
		$data          = $save_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );
		$claimed_record = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $save_response, 403 );
		$this->assertSame( 'fresh_review_decision_consumption_already_claimed_for_retry_save', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'consumption_in_progress', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'consume_claimed', $data['fresh_review_decision_lifecycle_event'] );
		$this->assertTrue( $data['fresh_review_decision_consumption_claimed'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertIsArray( $claimed_record );
		$this->assertTrue( $claimed_record['fresh_review_decision_consumption_claimed'] );
		$this->assertFalse( $claimed_record['fresh_review_decision_consumed'] ?? false );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( 'Fresh review claimed retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_lifecycle_endpoint
	 * @covers ::wp_de_rtc_get_fresh_review_lifecycle_debug_result
	 * @covers ::wp_de_rtc_get_fresh_review_lifecycle_debug_contract
	 * @covers ::wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption
	 * @covers ::wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result
	 * @covers ::wp_de_rtc_get_fresh_review_decision_consumption_claim_stale_after_seconds
	 */
	public function test_fresh_review_lifecycle_classifies_stale_consumption_claim_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 64 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review stale claim support content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$candidate_hash   = hash( 'sha256', 'fresh review stale claim support candidate' );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '64', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$claim            = wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption(
			$record_before,
			get_post( $post_id ),
			array(
				'previous_server_version'     => '64',
				'proposed_post_content_hash'  => $proposed_hash,
				'candidate_post_content_hash' => $candidate_hash,
			)
		);

		$this->assertIsArray( $claim );
		$this->assertTrue( $claim['fresh_review_decision_consumption_claimed'] );

		$stale_record = $this->backdate_fresh_review_consumption_claim(
			$request_data['fresh_review_request_record_id'],
			wp_de_rtc_get_fresh_review_decision_consumption_claim_stale_after_seconds() + 30
		);

		$lifecycle_response = rest_get_server()->dispatch(
			$this->create_fresh_review_lifecycle_request( 'posts', $post_id, $request_data['fresh_review_request_record_id'] )
		);
		$lifecycle_data     = $lifecycle_response->get_data();

		$this->assertSame( 200, $lifecycle_response->get_status() );
		$this->assertSame( 'fresh_review_lifecycle_debug_available', $lifecycle_data['result'] );
		$this->assertSame( 'retry_save_consuming', $lifecycle_data['fresh_review_request_status'] );
		$this->assertTrue( $lifecycle_data['fresh_review_decision_consumption_claimed'] );
		$this->assertSame( 'stale_claim_reclaim_available', $lifecycle_data['fresh_review_claim_cleanup_status'] );
		$this->assertTrue( $lifecycle_data['fresh_review_claim_is_stale'] );
		$this->assertTrue( $lifecycle_data['fresh_review_claim_can_reclaim'] );
		$this->assertSame( 'stale_claim_reclaim_available', $lifecycle_data['fresh_review_claim_cleanup']['fresh_review_claim_cleanup_status'] );
		$this->assertSame( 'interrupted_guarded_retry_save_write', $lifecycle_data['fresh_review_claim_cleanup']['fresh_review_claim_cleanup_reason'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['fresh_review_claim_reclaimed'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['saves_post'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['mutates_post_content'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['creates_revision'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['claims_saved'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['changes_locks'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['affects_normal_save_paths'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['reviewer_identity_exposed'] );
		$this->assertFalse( $lifecycle_data['fresh_review_claim_cleanup']['proof_internals_exposed'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_raw_content'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_reviewer_identity'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_proof_internals'] );
		$this->assertSame( $stale_record, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $lifecycle_data, array( 'Fresh review stale claim support content.', $proposed_hash, $candidate_hash, $claim['fresh_review_decision_consumption_claim_id'] ) );
	}

	/**
	 * @covers ::wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption
	 * @covers ::wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result
	 * @covers ::wp_de_rtc_get_fresh_review_decision_consumption_claim_stale_after_seconds
	 */
	public function test_fresh_review_consumption_claim_reclaim_keeps_active_claim_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 66 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review active claim support content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '66', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$claim            = wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption(
			$record_before,
			get_post( $post_id ),
			array(
				'previous_server_version'     => '66',
				'proposed_post_content_hash'  => $proposed_hash,
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review active claim support candidate' ),
			)
		);
		$claimed_record   = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertIsArray( $claim );
		$this->assertTrue( $claim['fresh_review_decision_consumption_claimed'] );

		$cleanup = wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result(
			$claimed_record,
			get_post( $post_id ),
			array(
				'mode' => 'reclaim',
			)
		);

		$this->assertIsArray( $cleanup );
		$this->assertSame( 'active_claim', $cleanup['fresh_review_claim_cleanup_status'] );
		$this->assertFalse( $cleanup['fresh_review_claim_is_stale'] );
		$this->assertFalse( $cleanup['fresh_review_claim_can_reclaim'] );
		$this->assertFalse( $cleanup['fresh_review_claim_reclaimed'] );
		$this->assertFalse( $cleanup['saves_post'] );
		$this->assertFalse( $cleanup['mutates_post_content'] );
		$this->assertFalse( $cleanup['creates_revision'] );
		$this->assertFalse( $cleanup['claims_saved'] );
		$this->assertFalse( $cleanup['changes_locks'] );
		$this->assertFalse( $cleanup['affects_normal_save_paths'] );
		$this->assertSame( $claimed_record, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $cleanup, array( 'Fresh review active claim support content.', $proposed_hash, $claim['fresh_review_decision_consumption_claim_id'] ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption
	 * @covers ::wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result
	 * @covers ::wp_de_rtc_record_opaque_fresh_review_decision_retry_save_consumed
	 */
	public function test_stale_fresh_review_consumption_claim_can_be_reclaimed_before_retry_save_without_cleanup_side_effects() {
		$post_id          = $this->create_sync_meta_post( 'post', 65 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review reclaimed retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '65', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '65', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$claim            = wp_de_rtc_claim_opaque_fresh_review_decision_retry_save_consumption(
			$record_before,
			get_post( $post_id ),
			array(
				'previous_server_version'     => '65',
				'proposed_post_content_hash'  => $proposed_hash,
				'candidate_post_content_hash' => hash( 'sha256', 'fresh review reclaimed candidate' ),
			)
		);

		$this->assertIsArray( $claim );
		$this->assertTrue( $claim['fresh_review_decision_consumption_claimed'] );

		$stale_record = $this->backdate_fresh_review_consumption_claim(
			$request_data['fresh_review_request_record_id'],
			wp_de_rtc_get_fresh_review_decision_consumption_claim_stale_after_seconds() + 60
		);
		$cleanup      = wp_de_rtc_get_stale_fresh_review_decision_consumption_claim_cleanup_result(
			$stale_record,
			get_post( $post_id ),
			array(
				'mode' => 'reclaim',
			)
		);
		$reclaimed_record = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertIsArray( $cleanup );
		$this->assertSame( 'stale_claim_reclaimed', $cleanup['fresh_review_claim_cleanup_status'] );
		$this->assertSame( 'interrupted_guarded_retry_save_write_cleanup', $cleanup['fresh_review_claim_cleanup_reason'] );
		$this->assertTrue( $cleanup['fresh_review_claim_is_stale'] );
		$this->assertFalse( $cleanup['fresh_review_claim_can_reclaim'] );
		$this->assertTrue( $cleanup['fresh_review_claim_reclaimed'] );
		$this->assertFalse( $cleanup['fresh_review_decision_consumption_claimed'] );
		$this->assertFalse( $cleanup['saves_post'] );
		$this->assertFalse( $cleanup['mutates_post_content'] );
		$this->assertFalse( $cleanup['creates_revision'] );
		$this->assertFalse( $cleanup['claims_saved'] );
		$this->assertFalse( $cleanup['changes_locks'] );
		$this->assertFalse( $cleanup['affects_normal_save_paths'] );
		$this->assertIsArray( $reclaimed_record );
		$this->assertSame( 'decision_recorded', $reclaimed_record['status'] );
		$this->assertSame( 'decision_recorded', $reclaimed_record['fresh_review_request_status'] );
		$this->assertSame( 'retry_save_claim_reclaimed', $reclaimed_record['fresh_review_lifecycle_status'] );
		$this->assertSame( 'consume_claim_reclaimed', $reclaimed_record['fresh_review_lifecycle_event'] );
		$this->assertTrue( $reclaimed_record['fresh_review_stale_consumption_claim_reclaimed'] );
		$this->assertFalse( $reclaimed_record['fresh_review_decision_consumption_claimed'] ?? false );
		$this->assertFalse( $reclaimed_record['fresh_review_decision_consumed'] ?? false );
		$this->assertArrayNotHasKey( 'fresh_review_decision_consumption_claim_id', $reclaimed_record );
		$this->assertArrayNotHasKey( 'retry_save_proposed_post_content_hash', $reclaimed_record );
		$this->assertArrayNotHasKey( 'retry_save_candidate_post_content_hash', $reclaimed_record );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $cleanup, array( 'Fresh review reclaimed retry-save content.', $proposed_hash, $claim['fresh_review_decision_consumption_claim_id'] ) );

		$save_response = rest_get_server()->dispatch(
			$this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'            => '65',
					'accepted_proof_server_version'  => '65',
					'pending_change_count'           => 1,
					'proposed_post_content'          => $proposed_content,
					'proposed_post_content_hash'     => $proposed_hash,
					'accepted_fresh_review_decision' => $consume_data,
				)
			)
		);
		$save_data     = $save_response->get_data();

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumed'] );
		$this->assertTrue( $save_data['fresh_review_decision_consumption_recorded'] );
		$this->assertSame( '66', $save_data['server_version'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_fresh_review_lifecycle_endpoint
	 * @covers ::wp_de_rtc_rest_fresh_review_lifecycle_permissions_check
	 * @covers ::wp_de_rtc_rest_fresh_review_lifecycle_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_fresh_review_lifecycle_rest_base
	 * @covers ::wp_de_rtc_get_fresh_review_lifecycle_debug_result
	 * @covers ::wp_de_rtc_get_fresh_review_lifecycle_debug_contract
	 * @covers ::wp_de_rtc_get_fresh_review_public_decision_counts
	 */
	public function test_fresh_review_lifecycle_debug_returns_consumed_support_evidence_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'post', 56 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review lifecycle debug retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '56', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '56', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$save_response    = rest_get_server()->dispatch(
			$this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'            => '56',
					'accepted_proof_server_version'  => '56',
					'pending_change_count'           => 1,
					'proposed_post_content'          => $proposed_content,
					'proposed_post_content_hash'     => $proposed_hash,
					'accepted_fresh_review_decision' => $consume_data,
				)
			)
		);

		$save_data = $save_response->get_data();

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );

		$before_lifecycle_post      = get_post( $post_id );
		$before_lifecycle_revisions = $this->get_post_revisions( $post_id );
		$lifecycle_response         = rest_get_server()->dispatch(
			$this->create_fresh_review_lifecycle_request( 'posts', $post_id, $request_data['fresh_review_request_record_id'] )
		);
		$lifecycle_data             = $lifecycle_response->get_data();

		$this->assertSame( 200, $lifecycle_response->get_status() );
		$this->assertSame( 'fresh_review_lifecycle_debug_available', $lifecycle_data['result'] );
		$this->assertTrue( $lifecycle_data['fresh_review_lifecycle_debug_available'] );
		$this->assertTrue( $lifecycle_data['fresh_review_support_evidence_available'] );
		$this->assertSame( 'post_fresh_review_lifecycle', $lifecycle_data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $lifecycle_data['fresh_review_request_record_id'] );
		$this->assertSame( 'retry_save_consumed', $lifecycle_data['fresh_review_request_status'] );
		$this->assertTrue( $lifecycle_data['fresh_review_decision_recorded'] );
		$this->assertTrue( $lifecycle_data['fresh_review_decision_consumed'] );
		$this->assertTrue( $lifecycle_data['fresh_review_retry_save_applied'] );
		$this->assertSame( 'retry_save_consumed', $lifecycle_data['fresh_review_lifecycle_status'] );
		$this->assertSame( 'consumed', $lifecycle_data['fresh_review_lifecycle_event'] );
		$this->assertSame( 'guarded_retry_save_applied', $lifecycle_data['fresh_review_lifecycle_reason'] );
		$this->assertSame( '56', $lifecycle_data['fresh_review_previous_server_version'] );
		$this->assertSame( '57', $lifecycle_data['fresh_review_saved_server_version'] );
		$this->assertSame( 'support_safe_fresh_review_lifecycle_debug', $lifecycle_data['fresh_review_debug_contract']['contract'] );
		$this->assertSame( 1, $lifecycle_data['fresh_review_debug_contract']['contract_version'] );
		$this->assertTrue( $lifecycle_data['fresh_review_debug_contract']['reviewer_identity_retained'] );
		$this->assertTrue( $lifecycle_data['fresh_review_debug_contract']['reviewer_capability_drift_recheck_supported'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['requires_new_review_if_reviewer_authority_cannot_be_rechecked'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_raw_content'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_reviewer_identity'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_saver_identity'] );
		$this->assertFalse( $lifecycle_data['fresh_review_debug_contract']['exposes_proof_internals'] );
		$this->assertContains( 'saved_post_content_hash', $lifecycle_data['fresh_review_debug_contract']['hash_evidence_fields'] );
		$this->assertFalse( $lifecycle_data['saves_post'] );
		$this->assertFalse( $lifecycle_data['mutates_post_content'] );
		$this->assertFalse( $lifecycle_data['creates_revision'] );
		$this->assertFalse( $lifecycle_data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_lifecycle_post->post_content, $before_lifecycle_revisions );
		$this->assert_payload_omits_private_fields( $lifecycle_data, array( 'Fresh review lifecycle debug retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 */
	public function test_retry_save_rejects_fresh_review_capability_drift_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 58, self::$author_user_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review capability drift retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '58', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '58', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		$save_response = rest_get_server()->dispatch(
			$this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'            => '58',
					'accepted_proof_server_version'  => '58',
					'pending_change_count'           => 1,
					'proposed_post_content'          => $proposed_content,
					'proposed_post_content_hash'     => $proposed_hash,
					'accepted_fresh_review_decision' => $consume_data,
				)
			)
		);
		$data          = $save_response->as_error()->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $save_response, 403 );
		$this->assertSame( 'retry_save_fresh_review_requires_unfiltered_html_saver', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'capability_drift', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'request_new_fresh_review', $data['fresh_review_decision_lifecycle_action'] );
		$this->assertTrue( $data['fresh_review_support_evidence_available'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( 'Fresh review capability drift retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_get_fresh_review_reviewer_authority_recheck_error
	 */
	public function test_retry_save_rejects_fresh_review_after_original_reviewer_loses_unfiltered_html_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 60 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review reviewer unfiltered drift retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $reviewer_id );

		$request_data  = $this->create_approved_fresh_review_decision_record( $post_id, '60', $proposed_hash );
		$consume_data  = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '60', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( $reviewer_id, $record_before['reviewer_user_id'] );
		$this->assertTrue( user_can( $reviewer_id, 'unfiltered_html' ) );

		wp_set_current_user( self::$admin_user_id );

		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$capability_drift = function ( $caps, $cap, $user_id, $args ) use ( $reviewer_id ) {
			if ( 'unfiltered_html' === $cap && (int) $user_id === (int) $reviewer_id ) {
				return array( 'do_not_allow' );
			}

			return $caps;
		};

		add_filter( 'map_meta_cap', $capability_drift, 10, 4 );

		try {
			$save_response = rest_get_server()->dispatch(
				$this->create_retry_save_request(
					'posts',
					$post_id,
					array(
						'client_base_version'            => '60',
						'accepted_proof_server_version'  => '60',
						'pending_change_count'           => 1,
						'proposed_post_content'          => $proposed_content,
						'proposed_post_content_hash'     => $proposed_hash,
						'accepted_fresh_review_decision' => $consume_data,
					)
				)
			);
		} finally {
			remove_filter( 'map_meta_cap', $capability_drift, 10 );
		}

		$data = $save_response->as_error()->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $save_response, 403 );
		$this->assertSame( 'fresh_review_reviewer_unfiltered_html_missing', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'capability_drift', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'reviewer_unfiltered_html_missing', $data['fresh_review_reviewer_authority_status'] );
		$this->assertSame( 'missing_unfiltered_html', $data['fresh_review_reviewer_capability_status'] );
		$this->assertSame( 'unfiltered_html', $data['fresh_review_reviewer_required_capability'] );
		$this->assertTrue( $data['fresh_review_reviewer_identity_retained'] );
		$this->assertFalse( $data['fresh_review_reviewer_identity_exposed'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( 'Fresh review reviewer unfiltered drift retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_get_fresh_review_reviewer_authority_recheck_error
	 */
	public function test_retry_save_rejects_fresh_review_after_original_reviewer_loses_edit_post_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 61 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review reviewer edit-post drift retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $reviewer_id );

		$request_data  = $this->create_approved_fresh_review_decision_record( $post_id, '61', $proposed_hash );
		$consume_data  = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '61', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( $reviewer_id, $record_before['reviewer_user_id'] );
		$this->assertTrue( user_can( $reviewer_id, 'unfiltered_html' ) );
		$this->assertTrue( user_can( $reviewer_id, 'edit_post', $post_id ) );

		wp_set_current_user( self::$admin_user_id );

		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$edit_post_drift  = function ( $caps, $cap, $user_id, $args ) use ( $reviewer_id, $post_id ) {
			if ( 'edit_post' === $cap && (int) $user_id === (int) $reviewer_id && ! empty( $args ) && (int) $args[0] === (int) $post_id ) {
				return array( 'do_not_allow' );
			}

			return $caps;
		};

		add_filter( 'map_meta_cap', $edit_post_drift, 10, 4 );

		try {
			$save_response = rest_get_server()->dispatch(
				$this->create_retry_save_request(
					'posts',
					$post_id,
					array(
						'client_base_version'            => '61',
						'accepted_proof_server_version'  => '61',
						'pending_change_count'           => 1,
						'proposed_post_content'          => $proposed_content,
						'proposed_post_content_hash'     => $proposed_hash,
						'accepted_fresh_review_decision' => $consume_data,
					)
				)
			);
		} finally {
			remove_filter( 'map_meta_cap', $edit_post_drift, 10 );
		}

		$data = $save_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $save_response, 403 );
		$this->assertSame( 'fresh_review_reviewer_edit_post_missing', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'capability_drift', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'reviewer_edit_post_missing', $data['fresh_review_reviewer_authority_status'] );
		$this->assertSame( 'missing_edit_post', $data['fresh_review_reviewer_capability_status'] );
		$this->assertSame( 'edit_post', $data['fresh_review_reviewer_required_capability'] );
		$this->assertTrue( $data['fresh_review_reviewer_identity_retained'] );
		$this->assertFalse( $data['fresh_review_reviewer_identity_exposed'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( 'Fresh review reviewer edit-post drift retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_get_fresh_review_reviewer_authority_recheck_error
	 */
	public function test_retry_save_rejects_fresh_review_after_original_reviewer_is_deleted_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 63 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review deleted reviewer retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $reviewer_id );

		$request_data  = $this->create_approved_fresh_review_decision_record( $post_id, '63', $proposed_hash );
		$consume_data  = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '63', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$record_before = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );

		$this->assertSame( $reviewer_id, $record_before['reviewer_user_id'] );

		self::delete_user( $reviewer_id );
		wp_set_current_user( self::$admin_user_id );

		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$save_response    = rest_get_server()->dispatch(
			$this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'            => '63',
					'accepted_proof_server_version'  => '63',
					'pending_change_count'           => 1,
					'proposed_post_content'          => $proposed_content,
					'proposed_post_content_hash'     => $proposed_hash,
					'accepted_fresh_review_decision' => $consume_data,
				)
			)
		);
		$data             = $save_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $save_response, 403 );
		$this->assertSame( 'fresh_review_reviewer_account_deleted', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );
		$this->assertSame( 'capability_drift', $data['fresh_review_decision_lifecycle_status'] );
		$this->assertSame( 'request_new_fresh_review', $data['fresh_review_decision_lifecycle_action'] );
		$this->assertSame( 'reviewer_account_deleted', $data['fresh_review_reviewer_authority_status'] );
		$this->assertSame( 'deleted', $data['fresh_review_reviewer_account_status'] );
		$this->assertSame( 'unavailable', $data['fresh_review_reviewer_capability_status'] );
		$this->assertTrue( $data['fresh_review_reviewer_identity_retained'] );
		$this->assertFalse( $data['fresh_review_reviewer_identity_exposed'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assert_payload_omits_private_fields( $data, array( 'Fresh review deleted reviewer retry-save content.', $proposed_hash ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 */
	public function test_retry_save_rejects_fresh_review_decision_stale_after_validation_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 46 );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review retry-save stale content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '46', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '46', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$advanced_content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Fresh review intervening server content.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version'          => 47,
				'previous_version' => '46',
			)
		);

		$this->assertIsString( $advanced_content );
		$this->assertSame(
			$post_id,
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $advanced_content,
					)
				)
			)
		);

		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = $this->get_post_revisions( $post_id );
		$record_before          = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '47',
				'accepted_proof_server_version' => '47',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $consume_data,
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$data          = $save_response->as_error()->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $save_response, 409 );
		$this->assertSame( 'post_retry_save_fresh_review_consumption_stale_base', $data['rest_route'] );
		$this->assertSame( '47', $data['client_base_version'] );
		$this->assertSame( '47', $data['server_version'] );
		$this->assertTrue( $data['fresh_review_decision_stale'] );
		$this->assertTrue( $data['fresh_review_requires_new_review'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 */
	public function test_retry_save_rejects_rejected_fresh_review_decision_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 48 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review rejected retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_rejected_fresh_review_decision_record( $post_id, '48', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$save_request     = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '48',
				'accepted_proof_server_version' => '48',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $this->get_fresh_review_retry_save_evidence(
					$request_data['fresh_review_request_record_id'],
					'48',
					$proposed_hash
				),
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$data          = $save_response->as_error()->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $save_response, 400 );
		$this->assertSame( 'fresh_review_decision_not_approved_for_retry_save', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 */
	public function test_retry_save_fresh_review_decision_requires_matching_route_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 49 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review retry-save route mismatch content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '49', $proposed_hash );
		$consume_data     = $this->get_validated_fresh_review_consume_evidence( 'posts', $post_id, '49', $request_data['fresh_review_request_record_id'], $proposed_hash );
		$save_request     = $this->create_retry_save_request(
			'pages',
			$post_id,
			array(
				'client_base_version'           => '49',
				'accepted_proof_server_version' => '49',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $consume_data,
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $save_response, 404 );
		$this->assertFalse( wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] )['fresh_review_decision_consumed'] ?? false );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 */
	public function test_retry_save_rejects_fresh_review_hash_mismatch_without_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 50 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$reviewed_content = '<!-- wp:paragraph --><p>Fresh review reviewed retry-save content.</p><!-- /wp:paragraph -->';
		$reviewed_hash    = hash( 'sha256', $reviewed_content );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review tampered retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '50', $reviewed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$save_request     = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '50',
				'accepted_proof_server_version' => '50',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $this->get_fresh_review_retry_save_evidence(
					$request_data['fresh_review_request_record_id'],
					'50',
					$proposed_hash
				),
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$data          = $save_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $save_response, 403 );
		$this->assertSame( 'retry_save_fresh_review_hash_evidence_mismatch', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertContains( 'accepted_fresh_review_decision.proposed_post_content_hash', $data['mismatched_hash_evidence_fields'] );
		$this->assertFalse( $data['fresh_review_decision_consumed'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_fresh_review_decision_consumption_result
	 * @covers ::wp_de_rtc_find_raw_post_content_param_paths
	 * @covers ::wp_de_rtc_find_private_proof_param_paths
	 */
	public function test_retry_save_rejects_fresh_review_raw_content_and_proof_leakage_without_exposing_or_consuming() {
		$post_id          = $this->create_sync_meta_post( 'post', 51 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Fresh review leakage retry-save content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_data     = $this->create_approved_fresh_review_decision_record( $post_id, '51', $proposed_hash );
		$record_before    = wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] );
		$secret_proof     = 'fresh-review-proof-secret-must-not-echo';
		$proof_evidence   = array_merge(
			$this->get_fresh_review_retry_save_evidence( $request_data['fresh_review_request_record_id'], '51', $proposed_hash ),
			array(
				'proof_signature' => $secret_proof,
			)
		);
		$proof_request    = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '51',
				'accepted_proof_server_version' => '51',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $proof_evidence,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $proof_response, 403 );
		$this->assertSame( 'retry_save_fresh_review_proof_leakage_rejected', $proof_data['detail'] );
		$this->assertSame( array( 'accepted_fresh_review_decision.proof_signature' ), $proof_data['proof_leakage_param_paths'] );
		$this->assertFalse( $proof_data['fresh_review_decision_consumed'] );
		$this->assertStringNotContainsString( $secret_proof, wp_json_encode( $proof_data ) );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );

		$raw_content  = 'fresh-review-raw-content-must-not-echo';
		$raw_evidence = array_merge(
			$this->get_fresh_review_retry_save_evidence( $request_data['fresh_review_request_record_id'], '51', $proposed_hash ),
			array(
				'raw_content' => $raw_content,
			)
		);
		$raw_request  = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '51',
				'accepted_proof_server_version' => '51',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'accepted_fresh_review_decision' => $raw_evidence,
			)
		);

		$raw_response = rest_get_server()->dispatch( $raw_request );
		$raw_data     = $raw_response->as_error()->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $raw_response, 403 );
		$this->assertSame( 'retry_save_fresh_review_raw_content_rejected', $raw_data['detail'] );
		$this->assertSame( array( 'accepted_fresh_review_decision.raw_content' ), $raw_data['raw_content_param_paths'] );
		$this->assertFalse( $raw_data['fresh_review_decision_consumed'] );
		$this->assertStringNotContainsString( $raw_content, wp_json_encode( $raw_data ) );
		$this->assertSame( $record_before, wp_de_rtc_get_opaque_fresh_review_request_record( $request_data['fresh_review_request_record_id'] ) );
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

	private function create_approved_fresh_review_decision_record( $post_id, $version, $proposed_hash, $candidate_hash = null ) {
		return $this->create_fresh_review_decision_record( $post_id, $version, $proposed_hash, $candidate_hash, 'approved' );
	}

	private function create_rejected_fresh_review_decision_record( $post_id, $version, $proposed_hash, $candidate_hash = null ) {
		return $this->create_fresh_review_decision_record( $post_id, $version, $proposed_hash, $candidate_hash, 'rejected' );
	}

	private function create_imported_approved_fresh_review_decision_record( $post_id, $client_version, $server_version, $proposed_hash, $candidate_hash = null ) {
		$request_params = array(
			'client_base_version'         => (string) $client_version,
			'server_version'              => (string) $server_version,
			'pending_change_count'        => 1,
			'proposed_post_content_hash'  => $proposed_hash,
			'local_updates_import_status' => 'blocked',
			'local_updates_import_reason' => 'fresh_review_required',
			'fresh_review_request_status' => 'fresh_review_required',
			'fresh_review_request_action' => 'request_admin_review',
		);

		if ( null !== $candidate_hash ) {
			$request_params['candidate_post_content_hash'] = $candidate_hash;
		}

		$request_response = rest_get_server()->dispatch( $this->create_fresh_review_request( 'posts', $post_id, $request_params ) );
		$request_data     = $request_response->get_data();

		$this->assertSame( 200, $request_response->get_status() );
		$this->remember_fresh_review_request_record( $request_data );

		$decision_params = array(
			'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
			'client_base_version'            => (string) $client_version,
			'server_version'                 => (string) $server_version,
			'fresh_review_decision'          => 'approved',
			'proposed_post_content_hash'     => $proposed_hash,
			'reviewed_proposed_content_hash' => $proposed_hash,
		);

		if ( null !== $candidate_hash ) {
			$decision_params['candidate_post_content_hash']     = $candidate_hash;
			$decision_params['reviewed_candidate_content_hash'] = $candidate_hash;
		}

		$decision_response = rest_get_server()->dispatch( $this->create_fresh_review_decision_request( 'posts', $post_id, $decision_params ) );
		$decision_data     = $decision_response->get_data();

		$this->assertSame( 200, $decision_response->get_status() );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $decision_data['fresh_review_request_record_id'] );

		return $decision_data;
	}

	private function create_fresh_review_decision_record( $post_id, $version, $proposed_hash, $candidate_hash, $decision ) {
		$request_data = $this->create_fresh_review_request_record( $post_id, $version, $proposed_hash, $candidate_hash );
		$params       = array(
			'fresh_review_request_record_id' => $request_data['fresh_review_request_record_id'],
			'client_base_version'            => (string) $version,
			'server_version'                 => (string) $version,
			'fresh_review_decision'          => $decision,
			'proposed_post_content_hash'     => $proposed_hash,
			'reviewed_proposed_content_hash' => $proposed_hash,
		);

		if ( null !== $candidate_hash ) {
			$params['candidate_post_content_hash']     = $candidate_hash;
			$params['reviewed_candidate_content_hash'] = $candidate_hash;
		}

		$response = rest_get_server()->dispatch( $this->create_fresh_review_decision_request( 'posts', $post_id, $params ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $request_data['fresh_review_request_record_id'], $data['fresh_review_request_record_id'] );

		return $data;
	}

	private function create_valid_fresh_review_consume_request( $rest_base, $post_id, $version, $record_id, $proposed_hash ) {
		return $this->create_fresh_review_consume_request(
			$rest_base,
			$post_id,
			array(
				'fresh_review_request_record_id' => $record_id,
				'client_base_version'            => (string) $version,
				'server_version'                 => (string) $version,
				'proposed_post_content_hash'     => $proposed_hash,
				'reviewed_proposed_content_hash' => $proposed_hash,
			)
		);
	}

	private function get_validated_fresh_review_consume_evidence( $rest_base, $post_id, $version, $record_id, $proposed_hash ) {
		$response = rest_get_server()->dispatch(
			$this->create_valid_fresh_review_consume_request( $rest_base, $post_id, $version, $record_id, $proposed_hash )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'fresh_review_decision_eligible_for_retry_save_handoff', $data['result'] );
		$this->assertTrue( $data['fresh_review_decision_consumption_validated'] );

		return $this->get_fresh_review_retry_save_evidence( $record_id, $version, $proposed_hash );
	}

	private function get_fresh_review_retry_save_evidence( $record_id, $version, $proposed_hash ) {
		return array(
			'result'                                      => 'fresh_review_decision_eligible_for_retry_save_handoff',
			'fresh_review_decision_consumption_validated' => true,
			'fresh_review_decision_eligible_for_retry_save' => true,
			'fresh_review_decision_status'                => 'approved',
			'fresh_review_request_status'                 => 'decision_recorded',
			'fresh_review_request_record_id'              => $record_id,
			'rest_route'                                  => 'post_fresh_review_consume',
			'client_base_version'                         => (string) $version,
			'server_version'                              => (string) $version,
			'proposed_post_content_hash'                  => $proposed_hash,
			'reviewed_proposed_content_hash'              => $proposed_hash,
		);
	}

	private function create_retry_save_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params( $params );

		return $request;
	}

	private function create_fresh_review_decision_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/fresh-review-decision' );
		$request->set_body_params( $params );

		return $request;
	}

	private function create_fresh_review_consume_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/fresh-review-consume' );
		$request->set_body_params( $params );

		return $request;
	}

	private function create_fresh_review_lifecycle_request( $rest_base, $post_id, $record_id ) {
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/fresh-review-lifecycle' );
		$request->set_query_params(
			array(
				'fresh_review_request_record_id' => $record_id,
			)
		);

		return $request;
	}

	private function backdate_fresh_review_consumption_claim( $record_id, $age_seconds ) {
		$record      = wp_de_rtc_get_opaque_fresh_review_request_record( $record_id );
		$option_name = wp_de_rtc_get_opaque_fresh_review_request_record_option_name( $record_id );
		$claimed_at  = time() - max( 1, (int) $age_seconds );

		$this->assertIsArray( $record );
		$this->assertNotSame( '', $option_name );
		$this->assertTrue( $record['fresh_review_decision_consumption_claimed'] );

		$record['fresh_review_decision_consumption_claimed_at'] = $claimed_at;
		$record['updated_at']                                  = $claimed_at;

		$this->assertTrue( update_option( $option_name, $record, false ) );

		return wp_de_rtc_get_opaque_fresh_review_request_record( $record_id );
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
