<?php
/**
 * Tests for Distributed Editing REST permission and capability flow.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Permission_Flow extends WP_Test_REST_TestCase {

	protected static $admin_user_id;
	protected static $author_user_id;
	protected static $subscriber_user_id;

	protected $server;

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
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
		delete_option( 'wp_de_rtc_enabled' );

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @dataProvider data_proof_endpoint_requests
	 *
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_review_approval_permissions_check
	 *
	 * @param string $endpoint Endpoint suffix.
	 * @param array  $params   Body parameters.
	 */
	public function test_proof_endpoints_require_edit_post_before_mutating( $endpoint, $params ) {
		$post_id          = $this->create_sync_meta_post( 'edit-post gate current content', 7 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_distributed_editing_request( 'posts', $post_id, $endpoint, $params );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @dataProvider data_proof_endpoint_requests
	 *
	 * @covers ::wp_de_rtc_rest_recovery_permissions_check
	 * @covers ::wp_de_rtc_rest_stale_base_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_review_approval_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 *
	 * @param string $endpoint Endpoint suffix.
	 * @param array  $params   Body parameters.
	 */
	public function test_proof_endpoints_require_feature_enablement_before_mutating( $endpoint, $params ) {
		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$post_id          = $this->create_sync_meta_post( 'feature gate current content', 7 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_distributed_editing_request( 'posts', $post_id, $endpoint, $params );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'feature_disabled_for_post', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @dataProvider data_proof_endpoint_requests
	 *
	 * @covers ::wp_de_rtc_rest_recovery_request_matches_post_type
	 * @covers ::wp_de_rtc_rest_stale_base_request_matches_post_type
	 * @covers ::wp_de_rtc_rest_retry_submit_request_matches_post_type
	 * @covers ::wp_de_rtc_rest_retry_save_request_matches_post_type
	 * @covers ::wp_de_rtc_rest_review_approval_request_matches_post_type
	 *
	 * @param string $endpoint Endpoint suffix.
	 * @param array  $params   Body parameters.
	 */
	public function test_proof_endpoints_require_matching_post_type_rest_base_before_mutating( $endpoint, $params ) {
		$post_id          = $this->create_sync_meta_post( 'route gate current content', 7 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_distributed_editing_request( 'pages', $post_id, $endpoint, $params );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @dataProvider data_retry_save_persistence_flags
	 *
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 *
	 * @param string $flag Proof flag.
	 */
	public function test_retry_save_rejects_persistence_proof_tampering_before_mutating( $flag ) {
		$post_id          = $this->create_sync_meta_post( 'tampered proof current content', 7 );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Tampered proof proposed content.</p><!-- /wp:paragraph -->';
		$params           = array(
			'client_base_version'           => '7',
			'accepted_proof_server_version' => '7',
			'pending_change_count'          => 2,
			'proposed_post_content'         => $proposed_content,
			'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			$flag                           => true,
		);
		$request          = $this->create_distributed_editing_request( 'posts', $post_id, 'retry-save', $params );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_proof_claimed_persistence', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_get_rest_recovery_permission_contract
	 */
	public function test_permission_contract_records_unfiltered_html_and_authorship_review_for_author() {
		$post_id          = $this->create_sync_meta_post( 'author capability current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-submit',
			array(
				'client_base_version' => '7',
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['permission_contract']['requires_edit_post'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertTrue( $data['permission_contract']['unfiltered_html_review_required'] );
		$this->assertFalse( $data['permission_contract']['unfiltered_html_allowed'] );
		$this->assertTrue( $data['permission_contract']['authorship_review_required'] );
		$this->assertTrue( $data['permission_contract']['content_capability_review_required'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['permission_contract']['unfiltered_html_rejection_code'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['permission_contract']['unfiltered_html_review_action'] );
		$this->assertSame( 'unfiltered_html', $data['permission_contract']['unfiltered_html_review_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['permission_contract']['unfiltered_html_review_scope'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_escalation_reason
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_rejection_error
	 * @covers ::wp_de_rtc_get_rest_recovery_permission_contract
	 */
	public function test_retry_save_requires_unfiltered_html_review_before_mutating_for_author() {
		$post_id          = $this->create_sync_meta_post( 'author retry-save current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com"></iframe><!-- /wp:html -->';
		$filtered_content = wp_unslash( wp_filter_post_kses( wp_slash( $proposed_content ) ) );
		$request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_unfiltered_html_would_change_content' );

		$this->assertErrorResponse( 'de_rtc_unfiltered_html_would_change_content', $response, 403 );
		$this->assertSame( 'collaborative_unfiltered_html_review_required', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertTrue( $data['requires_unfiltered_html'] );
		$this->assertFalse( $data['unfiltered_html_allowed'] );
		$this->assertTrue( $data['authorship_review_required'] );
		$this->assertTrue( $data['content_capability_review_required'] );
		$this->assertTrue( $data['requires_reviewer_escalation'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_required_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_scope'] );
		$this->assertSame( 'requires_reviewer_escalation', $data['review_status'] );
		$this->assertSame( 'unfiltered_html', $data['reviewer_capability'] );
		$this->assertTrue( $data['escalation_required'] );
		$this->assertSame( 'proposed_content_would_change_by_kses', $data['escalation_reason'] );
		$this->assertSame( 'wp_filter_post_kses', $data['content_filter'] );
		$this->assertSame( 'content_save_pre', $data['content_filter_context'] );
		$this->assertTrue( $data['content_would_change_by_kses'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $data['proposed_content_hash'] );
		$this->assertSame( hash( 'sha256', $filtered_content ), $data['kses_filtered_proposed_content_hash'] );
		$this->assertNotSame( $data['proposed_content_hash'], $data['kses_filtered_proposed_content_hash'] );
		$this->assertIsString( $data['candidate_content_hash'] );
		$this->assertNull( $data['kses_filtered_candidate_content_hash'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertSame( 'requires_reviewer_escalation', $data['review_contract']['status'] );
		$this->assertSame( 'unfiltered_html_content_capability_review', $data['review_contract']['type'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_contract']['review_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_contract']['review_required_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_contract']['review_scope'] );
		$this->assertSame( 'unfiltered_html', $data['review_contract']['reviewer_capability'] );
		$this->assertSame( $data['reviewer_capability'], $data['review_contract']['reviewer_capability'] );
		$this->assertSame( $data['review_required_capability'], $data['review_contract']['review_required_capability'] );
		$this->assertSame( $data['review_scope'], $data['review_contract']['review_scope'] );
		$this->assertTrue( $data['review_contract']['escalation_required'] );
		$this->assertSame( 'proposed_content_would_change_by_kses', $data['review_contract']['escalation_reason'] );
		$this->assertSame( $data['escalation_reason'], $data['review_contract']['escalation_reason'] );
		$this->assertTrue( $data['review_contract']['content_would_change_by_kses'] );
		$this->assertTrue( $data['review_contract']['proposed_content_would_change_by_kses'] );
		$this->assertNull( $data['review_contract']['candidate_content_would_change_by_kses'] );
		$this->assertFalse( $data['review_contract']['raw_content_included'] );
		$this->assertArrayNotHasKey( 'proposed_post_content', $data );
		$this->assertArrayNotHasKey( 'candidate_post_content', $data );
		$this->assertArrayNotHasKey( 'proposed_post_content_kses_review', $data );
		$this->assertArrayNotHasKey( 'candidate_post_content_kses_review', $data );
		$this->assert_review_rejection_omits_raw_content(
			$response->get_data(),
			array(
				'iframe',
				'example.com',
				'wp:html',
				'post-sync-meta',
				'data-sync-meta-format',
			)
		);
		$this->assertSame(
			array(
				'export_local_updates',
				'request_unfiltered_html_reviewer',
				'refetch_server_state',
			),
			$data['recovery_actions']
		);
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['permission_contract']['unfiltered_html_allowed'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['permission_contract']['unfiltered_html_rejection_code'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['permission_contract']['unfiltered_html_review_action'] );
		$this->assertSame( 'unfiltered_html', $data['permission_contract']['unfiltered_html_review_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['permission_contract']['unfiltered_html_review_scope'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_normalized_review_approval_block_items
	 */
	public function test_retry_save_consumes_review_approval_proof_before_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author reviewed retry-save current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'server_version'                => '7',
				'rebased_from_version'          => '7',
				'pending_change_count'          => 1,
			)
		);
		$this->assert_review_approval_proof_has_current_time_site_scope( $proof['review_approval_proof'] );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'rebased_from_version'          => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );

		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$after_post   = get_post( $post_id );
		$parsed_saved = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertTrue( $data['review_approval_proof_consumed'] );
		$this->assertSame( 1, $data['reviewed_block_item_count'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['accepted_proof_server_version'] );
		$this->assertSame( '8', $data['server_version'] );
		$this->assertSame( $proof['candidate_post_content_hash'], $data['saved_post_content_hash'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertNotSame( $before_post->post_content, $after_post->post_content );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '8', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '7', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( self::$admin_user_id, $parsed_saved['sync_meta']['last_server_update']['user_id'] );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 */
	public function test_retry_save_consumes_field_based_review_approval_proof_envelope() {
		$post_id          = $this->create_sync_meta_post( 'author reviewed retry-save enveloped proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-envelope"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = array(
			'proof_envelope_type' => 'field_based_review_approval_proof',
			'proof'               => $proof['review_approval_proof'],
		);
		$request        = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);

		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$after_post   = get_post( $post_id );
		$parsed_saved = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['review_approval_proof_consumed'] );
		$this->assertSame( $proof['candidate_post_content_hash'], $data['saved_post_content_hash'] );
		$this->assertNotSame( $before_post->post_content, $after_post->post_content );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '8', $parsed_saved['sync_meta']['version'] );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 * @covers ::wp_de_rtc_create_opaque_review_approval_proof_token_envelope
	 * @covers ::wp_de_rtc_get_review_approval_proof_from_opaque_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_option_name
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_from_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_public_evidence
	 * @covers ::wp_de_rtc_record_opaque_review_approval_proof_token_audit_event
	 */
	public function test_retry_save_consumes_opaque_review_approval_proof_token_envelope() {
		$post_id          = $this->create_sync_meta_post( 'author reviewed retry-save opaque token current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-token"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = wp_de_rtc_create_opaque_review_approval_proof_token_envelope( $proof['review_approval_proof'] );
		$this->assert_opaque_review_approval_proof_envelope( $proof_envelope, $post_id );
		$mint_audit = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope );
		$this->assert_opaque_review_approval_proof_token_audit_record( $mint_audit, $post_id, 'minted', array( 'minted' ), array( $proposed_content ) );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);

		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$after_post   = get_post( $post_id );
		$parsed_saved = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['review_approval_proof_consumed'] );
		$this->assertTrue( $data['review_approval_proof_token_invalidated'] );
		$this->assertSame( 'consumed', $data['review_approval_proof_token_audit']['status'] );
		$this->assertTrue( $data['review_approval_proof_token_audit']['recorded'] );
		$this->assertSame( 'minted', $data['review_approval_proof_token_audit']['previous_status'] );
		$this->assertSame( $proof['candidate_post_content_hash'], $data['saved_post_content_hash'] );
		$this->assertNotSame( $before_post->post_content, $after_post->post_content );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '8', $parsed_saved['sync_meta']['version'] );
		$this->assertFalse( get_transient( wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $proof_envelope ) ) );
		$consume_audit = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope );
		$this->assert_opaque_review_approval_proof_token_audit_record( $consume_audit, $post_id, 'consumed', array( 'minted', 'consumed' ), array( $proposed_content ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 * @covers ::wp_de_rtc_create_opaque_review_approval_proof_token_envelope
	 * @covers ::wp_de_rtc_get_review_approval_proof_from_opaque_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 * @covers ::wp_de_rtc_record_opaque_review_approval_proof_token_audit_event
	 */
	public function test_retry_save_rejects_replayed_opaque_review_approval_proof_token_after_success_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author replayed opaque token current content', 7, self::$author_user_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/replayed-token"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = wp_de_rtc_create_opaque_review_approval_proof_token_envelope( $proof['review_approval_proof'] );
		$token_key      = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $proof_envelope );
		$this->assert_opaque_review_approval_proof_envelope( $proof_envelope, $post_id );
		$this->assertNotFalse( get_transient( $token_key ) );

		$first_request  = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);
		$first_response = rest_get_server()->dispatch( $first_request );
		$first_data     = $first_response->get_data();

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( 'retry_save_applied', $first_data['result'] );
		$this->assertTrue( $first_data['review_approval_proof_token_invalidated'] );
		$this->assertFalse( get_transient( $token_key ) );

		$before_replay_post      = get_post( $post_id );
		$before_replay_revisions = $this->get_post_revisions( $post_id );
		$replay_request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '8',
				'accepted_proof_server_version'  => '8',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);
		$replay_response         = rest_get_server()->dispatch( $replay_request );
		$replay_error            = $replay_response->as_error();
		$replay_data             = $replay_error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $replay_response, 400 );
		$this->assertSame( 'unknown_retry_save_review_approval_proof_token', $replay_data['detail'] );
		$this->assertSame( 'missing_or_evicted', $replay_data['review_approval_proof_token_storage_status'] );
		$this->assertSame( 'unavailable', $replay_data['review_approval_proof_lifetime_status'] );
		$this->assertSame( 'unavailable', $replay_data['review_approval_proof_token_audit']['status'] );
		$this->assertTrue( $replay_data['review_approval_proof_token_audit']['recorded'] );
		$this->assertSame( 'consumed', $replay_data['review_approval_proof_token_audit']['previous_status'] );
		$this->assertTrue( $replay_data['review_approval_proof_requires_new_review'] );
		$this->assertTrue( $replay_data['can_export_local_updates'] );
		$this->assertFalse( $replay_data['saves_post'] );
		$this->assertFalse( $replay_data['mutates_post_content'] );
		$this->assertFalse( $replay_data['claims_saved'] );
		$replay_audit = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope );
		$this->assert_opaque_review_approval_proof_token_audit_record( $replay_audit, $post_id, 'unavailable', array( 'minted', 'consumed', 'unavailable' ), array( $proposed_content ) );
		$this->assert_post_unchanged( $post_id, $before_replay_post->post_content, $before_replay_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 * @covers ::wp_de_rtc_create_opaque_review_approval_proof_token_envelope
	 * @covers ::wp_de_rtc_get_review_approval_proof_from_opaque_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 * @covers ::wp_de_rtc_record_opaque_review_approval_proof_token_audit_event
	 */
	public function test_retry_save_rejects_evicted_opaque_review_approval_proof_token_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author evicted opaque token current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/evicted-token"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = wp_de_rtc_create_opaque_review_approval_proof_token_envelope( $proof['review_approval_proof'] );
		$token_key      = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $proof_envelope );
		$this->assert_opaque_review_approval_proof_envelope( $proof_envelope, $post_id );
		$this->assertTrue( delete_transient( $token_key ) );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'unknown_retry_save_review_approval_proof_token', $data['detail'] );
		$this->assertSame( 'missing_or_evicted', $data['review_approval_proof_token_storage_status'] );
		$this->assertSame( 'unavailable', $data['review_approval_proof_lifetime_status'] );
		$this->assertSame( 'unavailable', $data['review_approval_proof_token_audit']['status'] );
		$this->assertTrue( $data['review_approval_proof_token_audit']['recorded'] );
		$this->assertSame( 'minted', $data['review_approval_proof_token_audit']['previous_status'] );
		$this->assertTrue( $data['review_approval_proof_requires_new_review'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$evicted_audit = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope );
		$this->assert_opaque_review_approval_proof_token_audit_record( $evicted_audit, $post_id, 'unavailable', array( 'minted', 'unavailable' ), array( $proposed_content ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 */
	public function test_retry_save_rejects_unsupported_review_approval_proof_envelope_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author unsupported review proof envelope current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/unsupported-envelope"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = array(
			'proof_envelope_type' => 'opaque_review_approval_proof',
			'proof'               => $proof['review_approval_proof'],
		);
		$request        = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'unsupported_retry_save_review_approval_proof_envelope', $data['detail'] );
		$this->assertSame( 'opaque_review_approval_proof', $data['review_approval_proof_format'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @dataProvider data_invalid_opaque_review_approval_proof_token_envelopes
	 *
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_accepted_review_approval_proof_from_envelope
	 * @covers ::wp_de_rtc_get_review_approval_proof_from_opaque_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 * @covers ::wp_de_rtc_record_opaque_review_approval_proof_token_audit_event
	 *
	 * @param string $case            Case label.
	 * @param array  $envelope_update Token envelope mutation.
	 * @param string $expected_code   Expected error code.
	 * @param int    $expected_status Expected HTTP status.
	 * @param string $expected_detail Expected error detail.
	 */
	public function test_retry_save_rejects_invalid_opaque_review_approval_proof_token_envelopes_without_mutating( $case, $envelope_update, $expected_code, $expected_status, $expected_detail ) {
		$post_id          = $this->create_sync_meta_post( 'author invalid opaque token current content ' . $case, 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/' . sanitize_key( $case ) . '"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof_envelope = wp_de_rtc_create_opaque_review_approval_proof_token_envelope( $proof['review_approval_proof'] );
		$this->assert_opaque_review_approval_proof_envelope( $proof_envelope, $post_id );

		$proof_envelope = array_merge( $proof_envelope, $envelope_update );
		$request        = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof_envelope,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( $expected_code );

		$this->assertErrorResponse( $expected_code, $response, $expected_status );
		$this->assertSame( $expected_detail, $data['detail'] );
		$this->assertSame( 'opaque_review_approval_proof_token', $data['review_approval_proof_format'] );
		if ( in_array( $expected_detail, array( 'unknown_retry_save_review_approval_proof_token', 'retry_save_review_approval_proof_token_expired' ), true ) ) {
			$this->assertTrue( $data['review_approval_proof_requires_new_review'] );
			$this->assertTrue( $data['can_export_local_updates'] );
		}
		if ( 'unknown_retry_save_review_approval_proof_token' === $expected_detail ) {
			$this->assertSame( 'missing_or_evicted', $data['review_approval_proof_token_storage_status'] );
			$this->assertSame( 'unavailable', $data['review_approval_proof_lifetime_status'] );
			$this->assertSame( 'unavailable', $data['review_approval_proof_token_audit']['status'] );
			$this->assertFalse( $data['review_approval_proof_token_audit']['recorded'] );
			$this->assertFalse( $data['review_approval_proof_token_audit']['record_found'] );
			$this->assertNull( wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope ) );
		}
		if ( 'retry_save_review_approval_proof_token_expired' === $expected_detail ) {
			$this->assertSame( 'expired', $data['review_approval_proof_token_audit']['status'] );
			$this->assertTrue( $data['review_approval_proof_token_audit']['recorded'] );
			$this->assertSame( 'minted', $data['review_approval_proof_token_audit']['previous_status'] );
			$expired_audit = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_envelope );
			$this->assert_opaque_review_approval_proof_token_audit_record( $expired_audit, $post_id, 'expired', array( 'minted', 'expired' ), array( $proposed_content ) );
		}
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	public function data_invalid_opaque_review_approval_proof_token_envelopes() {
		return array(
			'unknown token' => array(
				'unknown-token',
				array(
					'token' => 'unknown-token-value',
				),
				'de_rtc_malformed_sync_payload',
				400,
				'unknown_retry_save_review_approval_proof_token',
			),
			'expired envelope' => array(
				'expired-envelope',
				array(
					'expires_at' => time() - HOUR_IN_SECONDS,
				),
				'de_rtc_sync_meta_tampered',
				403,
				'retry_save_review_approval_proof_token_expired',
			),
			'raw content key' => array(
				'raw-content-key',
				array(
					'raw_content' => 'raw proof token content must reject',
				),
				'de_rtc_sync_meta_tampered',
				403,
				'retry_save_review_approval_raw_content_rejected',
			),
		);
	}

	/**
	 * @covers ::wp_de_rtc_rest_review_approval_endpoint
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_approval_result
	 * @covers ::wp_de_rtc_add_review_approval_proof_time_site_scope
	 * @covers ::wp_de_rtc_get_review_approval_proof_site_scope
	 */
	public function test_review_approval_issues_signed_proof_with_time_and_site_scope() {
		$post_id          = $this->create_sync_meta_post( 'review approval scoped proof current content', 7, self::$author_user_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/scoped-review"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof_evidence = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$request        = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'review-approval',
			array(
				'client_base_version'              => '7',
				'accepted_proof_server_version'    => '7',
				'pending_change_count'             => 1,
				'proposed_post_content_hash'       => $proof_evidence['proposed_post_content_hash'],
				'reviewed_proposed_content_hash'   => $proof_evidence['proposed_post_content_hash'],
				'candidate_post_content_hash'      => $proof_evidence['candidate_post_content_hash'],
				'reviewed_candidate_content_hash'  => $proof_evidence['candidate_post_content_hash'],
				'reviewed_block_items'             => $proof_evidence['review_approval_proof']['reviewed_block_items'],
			)
		);
		$not_before     = time();

		$response  = rest_get_server()->dispatch( $request );
		$not_after = time();
		$data      = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'review_approval_accepted_for_retry_save', $data['result'] );
		$this->assertTrue( $data['review_approval_accepted'] );
		$this->assertIsArray( $data['review_approval_proof'] );
		$this->assertSame( 'opaque_review_approval_proof_token', $data['review_approval_proof_format'] );
		$this->assert_opaque_review_approval_proof_envelope( $data['review_approval_proof'], $post_id );

		$resolved_proof = wp_de_rtc_get_accepted_review_approval_proof_from_envelope( $data['review_approval_proof'], $post_id );

		$this->assertIsArray( $resolved_proof );
		$this->assert_review_approval_proof_has_current_time_site_scope( $resolved_proof, $not_before, $not_after );
		$this->assertSame( $proof_evidence['candidate_post_content_hash'], $resolved_proof['candidate_post_content_hash'] );
		$this->assertSame( $proof_evidence['candidate_post_content_hash'], $resolved_proof['reviewed_candidate_content_hash'] );
		$this->assert_review_rejection_omits_raw_content( $data, array( $proposed_content ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_review_approval_proof_for_author_without_unfiltered_html_before_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author reviewed retry-save cannot persist current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-author"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_requires_unfiltered_html_saver', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertTrue( $data['review_approval_proof_accepted'] );
		$this->assertFalse( $data['review_approval_proof_consumed'] );
		$this->assertTrue( $data['accepted_review_approval_proof_available'] );
		$this->assertIsArray( $data['accepted_review_approval_proof'] );
		$this->assertSame( 'opaque_review_approval_proof_token', $data['accepted_review_approval_proof_format'] );
		$this->assert_opaque_review_approval_proof_envelope( $data['accepted_review_approval_proof'], $post_id );

		$accepted_review_approval_proof = wp_de_rtc_get_accepted_review_approval_proof_from_envelope( $data['accepted_review_approval_proof'], $post_id );

		$this->assertIsArray( $accepted_review_approval_proof );
		$this->assertSame( 1, $data['reviewed_block_item_count'] );
		$this->assertTrue( $data['requires_unfiltered_html'] );
		$this->assertTrue( $data['requires_unfiltered_html_saver'] );
		$this->assertFalse( $data['unfiltered_html_allowed'] );
		$this->assertSame( 'approved_by_unfiltered_html_reviewer', $data['review_status'] );
		$this->assertSame( 'approved_for_retry_save', $data['approval_status'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_action'] );
		$this->assertSame( 'retry_save_with_unfiltered_html_saver', $data['approval_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_required_capability'] );
		$this->assertSame( 'unfiltered_html', $data['reviewer_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_scope'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( 'unfiltered_html_retry_save_review_approval', $accepted_review_approval_proof['type'] );
		$this->assertSame( 'approved_by_unfiltered_html_reviewer', $accepted_review_approval_proof['status'] );
		$this->assertSame( $post_id, $accepted_review_approval_proof['post_id'] );
		$this->assertSame( 'post', $accepted_review_approval_proof['post_type'] );
		$this->assertSame( self::$admin_user_id, $accepted_review_approval_proof['reviewer_user_id'] );
		$this->assertSame( self::$author_user_id, $accepted_review_approval_proof['low_privileged_saver_user_id'] );
		$this->assertIsString( $accepted_review_approval_proof['proof_signature'] );
		$this->assertSame( 'low_privileged_saver_candidate', $accepted_review_approval_proof['candidate_post_content_hash_scope'] );
		$this->assertTrue( $accepted_review_approval_proof['requires_unfiltered_html_saver'] );
		$this->assertSame( $proof['review_approval_proof']['candidate_post_content_hash'], $accepted_review_approval_proof['candidate_post_content_hash'] );
		$this->assertSame( $proof['review_approval_proof']['reviewed_candidate_content_hash'], $accepted_review_approval_proof['reviewed_candidate_content_hash'] );
		$this->assert_review_rejection_omits_raw_content( $data, array( $proposed_content ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_retry_save_candidate_post_content_hash_for_user
	 */
	public function test_admin_retry_save_rejects_cross_post_accepted_review_approval_replay_without_mutating() {
		$source_post_id   = $this->create_sync_meta_post( 'admin continuation source post current content', 7, self::$author_user_id );
		$target_post_id   = $this->create_sync_meta_post( 'admin continuation target post current content', 7, self::$author_user_id );
		$before_target    = get_post( $target_post_id );
		$before_revisions = $this->get_post_revisions( $target_post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-cross-post"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $source_post_id, $proposed_content );
		$author_request = $this->create_distributed_editing_request(
			'posts',
			$source_post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$author_response = rest_get_server()->dispatch( $author_request );
		$author_error    = $author_response->as_error();
		$author_data     = $author_error->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $author_response, 403 );

		wp_set_current_user( self::$admin_user_id );

		$admin_request = $this->create_distributed_editing_request(
			'posts',
			$target_post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $author_data['accepted_review_approval_proof'],
			)
		);

		$admin_response = rest_get_server()->dispatch( $admin_request );
		$error          = $admin_response->as_error();
		$data           = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $admin_response, 403 );
		$this->assertSame( 'retry_save_review_approval_proof_rejected', $data['detail'] );
		$this->assertSame( $source_post_id, $data['proof_post_id'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $target_post_id, $before_target->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_retry_save_candidate_post_content_hash_for_user
	 */
	public function test_admin_retry_save_rejects_low_privileged_saver_identity_tampering_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'admin continuation identity current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-identity"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$author_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$author_response = rest_get_server()->dispatch( $author_request );
		$author_error    = $author_response->as_error();
		$author_data     = $author_error->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );
		$tampered_proof  = wp_de_rtc_get_accepted_review_approval_proof_from_envelope( $author_data['accepted_review_approval_proof'], $post_id );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $author_response, 403 );
		$this->assertIsArray( $tampered_proof );

		$tampered_proof['low_privileged_saver_user_id'] = self::$subscriber_user_id;

		wp_set_current_user( self::$admin_user_id );

		$admin_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $tampered_proof,
			)
		);

		$admin_response = rest_get_server()->dispatch( $admin_request );
		$error          = $admin_response->as_error();
		$data           = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $admin_response, 403 );
		$this->assertSame( 'retry_save_review_approval_proof_signature_mismatch', $data['detail'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_admin_retry_save_consumes_accepted_review_approval_after_author_saver_rejection() {
		$post_id          = $this->create_sync_meta_post( 'admin continuation after author reviewed retry-save content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-author-admin"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$author_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$author_response = rest_get_server()->dispatch( $author_request );
		$author_error    = $author_response->as_error();
		$author_data     = $author_error->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $author_response, 403 );
		$this->assertSame( 'retry_save_review_approval_requires_unfiltered_html_saver', $author_data['detail'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );

		wp_set_current_user( self::$admin_user_id );

		$accepted_review_approval_proof     = wp_de_rtc_get_accepted_review_approval_proof_from_envelope( $author_data['accepted_review_approval_proof'], $post_id );
		$accepted_review_approval_token_key = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $author_data['accepted_review_approval_proof'] );
		$this->assertIsArray( $accepted_review_approval_proof );
		$this->assertNotEmpty( $accepted_review_approval_token_key );
		$this->assertNotFalse( get_transient( $accepted_review_approval_token_key ) );

		$admin_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $author_data['accepted_review_approval_proof'],
			)
		);

		$admin_response = rest_get_server()->dispatch( $admin_request );
		$data           = $admin_response->get_data();
		$after_post     = get_post( $post_id );
		$parsed_saved   = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $admin_response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertTrue( $data['review_approval_proof_consumed'] );
		$this->assertTrue( $data['review_approval_proof_token_invalidated'] );
		$this->assertSame( 1, $data['reviewed_block_item_count'] );
		$this->assertSame( '8', $data['server_version'] );
		$this->assertNotSame( $accepted_review_approval_proof['candidate_post_content_hash'], $data['saved_post_content_hash'] );
		$this->assertFalse( get_transient( $accepted_review_approval_token_key ) );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '8', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '7', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( self::$admin_user_id, $parsed_saved['sync_meta']['last_server_update']['user_id'] );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_admin_retry_save_rejects_changed_content_after_review_continuation_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'admin continuation changed content current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewed-author-original"></iframe><!-- /wp:html -->';
		$changed_content  = '<!-- wp:html --><iframe src="https://example.com/reviewed-author-changed"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof          = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$author_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$author_response = rest_get_server()->dispatch( $author_request );
		$author_error    = $author_response->as_error();
		$author_data     = $author_error->get_error_data( 'de_rtc_review_approval_requires_unfiltered_html' );

		$this->assertErrorResponse( 'de_rtc_review_approval_requires_unfiltered_html', $author_response, 403 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );

		wp_set_current_user( self::$admin_user_id );

		$admin_request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $changed_content,
				'proposed_post_content_hash'     => hash( 'sha256', $changed_content ),
				'accepted_review_approval_proof' => $author_data['accepted_review_approval_proof'],
			)
		);

		$admin_response = rest_get_server()->dispatch( $admin_request );
		$error          = $admin_response->as_error();
		$data           = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $admin_response, 403 );
		$this->assertSame( 'retry_save_review_approval_hash_evidence_mismatch', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertContains( 'review_approval_proof.proposed_post_content_hash', $data['mismatched_hash_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_stale_review_approval_proof_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author stale review proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/stale"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof   = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'server_version' => '6',
			)
		);
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'rebased_from_version'          => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_retry_save_review_approval_consumption_stale_base', $data['rest_route'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['server_version'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_stale_signed_review_approval_after_newer_server_version_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'author stale signed proof current content', 7, self::$author_user_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/stale-signed"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'server_version'                => '7',
				'rebased_from_version'          => '7',
			)
		);

		$newer_content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Server advanced while review proof waited.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => '8',
			)
		);

		$this->assertIsString( $newer_content );
		$this->assertSame(
			$post_id,
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $newer_content,
				)
			)
		);

		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '8',
				'accepted_proof_server_version'  => '8',
				'rebased_from_version'           => '8',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'stale_base_version_rejected' );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_retry_save_review_approval_consumption_stale_base', $data['rest_route'] );
		$this->assertSame( '8', $data['client_base_version'] );
		$this->assertSame( '8', $data['server_version'] );
		$this->assertFalse( $data['review_approval_proof_consumed'] );
		$this->assertSame( 'stale_after_server_version_advanced', $data['review_approval_proof_lifetime_status'] );
		$this->assertSame( '7', $data['review_approval_proof_client_base_version'] );
		$this->assertSame( '7', $data['review_approval_proof_server_version'] );
		$this->assertSame( '7', $data['review_approval_proof_accepted_server_version'] );
		$this->assertSame( '8', $data['review_approval_proof_current_server_version'] );
		$this->assertSame( '8', $data['review_approval_proof_expected_server_version'] );
		$this->assertTrue( $data['review_approval_proof_stale'] );
		$this->assertTrue( $data['review_approval_proof_requires_new_review'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_review_approval_proof_time_site_scope_error
	 */
	public function test_retry_save_rejects_expired_review_approval_proof_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'expired scoped review proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/expired-scope"></iframe><!-- /wp:html -->';
		$issued_at        = time() - HOUR_IN_SECONDS;
		$expires_at       = time() - MINUTE_IN_SECONDS;

		wp_set_current_user( self::$admin_user_id );

		$proof   = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'issued_at'  => $issued_at,
				'expires_at' => $expires_at,
			)
		);
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_proof_expired', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['review_approval_proof_consumed'] );
		$this->assertSame( $issued_at, $data['review_approval_proof_issued_at'] );
		$this->assertSame( $expires_at, $data['review_approval_proof_expires_at'] );
		$this->assertSame( 'expired', $data['review_approval_proof_lifetime_status'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_review_approval_proof_time_site_scope_error
	 */
	public function test_retry_save_rejects_future_issued_review_approval_proof_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'future issued scoped review proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/future-scope"></iframe><!-- /wp:html -->';
		$issued_at        = time() + wp_de_rtc_get_review_approval_proof_clock_skew_seconds() + MINUTE_IN_SECONDS;
		$expires_at       = $issued_at + wp_de_rtc_get_review_approval_proof_lifetime_seconds();

		wp_set_current_user( self::$admin_user_id );

		$proof   = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'issued_at'  => $issued_at,
				'expires_at' => $expires_at,
			)
		);
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_proof_future_issued', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['review_approval_proof_consumed'] );
		$this->assertSame( $issued_at, $data['review_approval_proof_issued_at'] );
		$this->assertSame( $expires_at, $data['review_approval_proof_expires_at'] );
		$this->assertSame( 'future_issued', $data['review_approval_proof_lifetime_status'] );
		$this->assertSame( wp_de_rtc_get_review_approval_proof_clock_skew_seconds(), $data['review_approval_proof_clock_skew_seconds'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_review_approval_proof_time_site_scope_error
	 */
	public function test_retry_save_rejects_foreign_site_scope_review_approval_proof_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'foreign scoped review proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/foreign-scope"></iframe><!-- /wp:html -->';
		$foreign_site_id  = get_current_blog_id() + 100;
		$foreign_site_url = 'https://foreign.example.test';

		wp_set_current_user( self::$admin_user_id );

		$proof   = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'site_id'  => $foreign_site_id,
				'site_url' => $foreign_site_url,
			)
		);
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$error      = $response->as_error();
		$data       = $error->get_error_data( 'de_rtc_sync_meta_tampered' );
		$site_scope = wp_de_rtc_get_review_approval_proof_site_scope();

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_site_scope_mismatch', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['review_approval_proof_consumed'] );
		$this->assertSame( $foreign_site_id, $data['review_approval_proof_site_id'] );
		$this->assertSame( $foreign_site_url, $data['review_approval_proof_site_url'] );
		$this->assertSame( $site_scope['site_id'], $data['expected_site_id'] );
		$this->assertSame( $site_scope['site_url'], $data['expected_site_url'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_signed_review_approval_from_deleted_reviewer_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'deleted reviewer signed proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/deleted-reviewer"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'reviewer_user_id' => $reviewer_id,
			)
		);

		self::delete_user( $reviewer_id );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_reviewer_account_deleted', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $reviewer_id, $data['reviewer_user_id'] );
		$this->assertSame( 'deleted', $data['reviewer_account_status'] );
		$this->assertSame( 'unavailable', $data['reviewer_capability_status'] );
		$this->assertSame( 'unfiltered_html', $data['reviewer_required_capability'] );
		$this->assertContains( 'review_approval_proof.reviewer_user_id', $data['mismatched_identity_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_signed_review_approval_after_reviewer_capability_drift_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'reviewer capability drift signed proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewer-drift"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'reviewer_user_id' => $reviewer_id,
			)
		);

		$reviewer = new WP_User( $reviewer_id );
		$reviewer->set_role( 'author' );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_reviewer_capability_missing', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $reviewer_id, $data['reviewer_user_id'] );
		$this->assertSame( 'active', $data['reviewer_account_status'] );
		$this->assertSame( 'missing', $data['reviewer_capability_status'] );
		$this->assertSame( 'unfiltered_html', $data['reviewer_required_capability'] );
		$this->assertContains( 'review_approval_proof.reviewer_user_id', $data['mismatched_identity_evidence_fields'] );
		$this->assertContains( 'review_approval_proof.reviewer_capability', $data['mismatched_identity_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 */
	public function test_retry_save_rejects_signed_review_approval_after_reviewer_edit_post_drift_without_mutating() {
		$post_id          = $this->create_sync_meta_post( 'reviewer edit-post drift signed proof current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$reviewer_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/reviewer-edit-post-drift"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$proof = $this->create_retry_save_review_approval_proof(
			$post_id,
			$proposed_content,
			array(
				'reviewer_user_id' => $reviewer_id,
			)
		);

		$reviewer = new WP_User( $reviewer_id );
		$reviewer->set_role( 'subscriber' );
		$reviewer->add_cap( 'unfiltered_html' );

		$this->assertTrue( user_can( $reviewer_id, 'unfiltered_html' ) );
		$this->assertFalse( user_can( $reviewer_id, 'edit_post', $post_id ) );

		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'            => '7',
				'accepted_proof_server_version'  => '7',
				'rebased_from_version'           => '7',
				'pending_change_count'           => 1,
				'proposed_post_content'          => $proposed_content,
				'proposed_post_content_hash'     => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_review_approval_reviewer_edit_post_missing', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $reviewer_id, $data['reviewer_user_id'] );
		$this->assertSame( 'active', $data['reviewer_account_status'] );
		$this->assertSame( 'missing_edit_post', $data['reviewer_capability_status'] );
		$this->assertSame( 'edit_post', $data['reviewer_required_capability'] );
		$this->assertContains( 'review_approval_proof.reviewer_user_id', $data['mismatched_identity_evidence_fields'] );
		$this->assertContains( 'review_approval_proof.reviewer_edit_post', $data['mismatched_identity_evidence_fields'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @dataProvider data_invalid_review_approval_proofs
	 *
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_retry_save_review_approval_proof_consumption_result
	 * @covers ::wp_de_rtc_get_normalized_review_approval_block_items
	 *
	 * @param string $case          Case label.
	 * @param array  $proof_updates Proof mutation.
	 * @param string $expected_code Expected error code.
	 * @param string $expected_detail Expected error detail.
	 */
	public function test_retry_save_rejects_invalid_review_approval_proof_without_mutating( $case, $proof_updates, $expected_code, $expected_detail ) {
		$post_id          = $this->create_sync_meta_post( 'author invalid review proof current content ' . $case, 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/' . sanitize_key( $case ) . '"></iframe><!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$proof = $this->create_retry_save_review_approval_proof( $post_id, $proposed_content );
		$proof['review_approval_proof'] = array_replace_recursive(
			$proof['review_approval_proof'],
			$proof_updates
		);
		$request = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'rebased_from_version'          => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proof['proposed_post_content_hash'],
				'accepted_review_approval_proof' => $proof['review_approval_proof'],
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( $expected_code );

		$this->assertErrorResponse( $expected_code, $response, 'de_rtc_malformed_sync_payload' === $expected_code ? 400 : 403 );
		$this->assertSame( $expected_detail, $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_escalation_reason
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_rejection_error
	 */
	public function test_retry_save_rejects_unsafe_proposed_script_before_scoped_sync_meta_allowance() {
		$post_id          = $this->create_sync_meta_post( 'author retry-save current script content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html -->'
			. '<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">alert("unsafe")</script>'
			. '<!-- /wp:html -->';
		$filtered_content = wp_unslash( wp_filter_post_kses( wp_slash( $proposed_content ) ) );
		$request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_unfiltered_html_would_change_content' );

		$this->assertErrorResponse( 'de_rtc_unfiltered_html_would_change_content', $response, 403 );
		$this->assertSame( 'collaborative_unfiltered_html_review_required', $data['detail'] );
		$this->assertSame( 'proposed_content_would_change_by_kses', $data['escalation_reason'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $data['proposed_content_hash'] );
		$this->assertSame( hash( 'sha256', $filtered_content ), $data['kses_filtered_proposed_content_hash'] );
		$this->assertTrue( $data['review_contract']['proposed_content_would_change_by_kses'] );
		$this->assertNull( $data['review_contract']['candidate_content_would_change_by_kses'] );
		$this->assertIsString( $data['candidate_content_hash'] );
		$this->assertNull( $data['kses_filtered_candidate_content_hash'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 */
	public function test_retry_save_allows_author_when_kses_would_not_change_content() {
		$post_id          = $this->create_sync_meta_post( 'author safe retry-save current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$proposed_content = '<!-- wp:paragraph --><p>Author safe retry-save proposed content.</p><!-- /wp:paragraph -->';
		$request          = $this->create_distributed_editing_request(
			'posts',
			$post_id,
			'retry-save',
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );

		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$after_post   = get_post( $post_id );
		$parsed_saved = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['accepted_proof_server_version'] );
		$this->assertSame( '8', $data['server_version'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertFalse( $data['can_export_local_updates'] );
		$this->assertFalse( $data['permission_contract']['unfiltered_html_allowed'] );
		$this->assertTrue( $data['permission_contract']['unfiltered_html_review_required'] );
		$this->assertNotSame( $before_post->post_content, $after_post->post_content );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '8', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '7', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertStringContainsString( '<script', strtolower( $after_post->post_content ) );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @covers ::wp_de_rtc_filter_sync_meta_script_kses_allowance
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_normal_rest_save_does_not_get_scoped_sync_meta_kses_allowance_for_author() {
		$post_id                = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC normal save sync meta allowance post',
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:paragraph --><p>Normal save current content.</p><!-- /wp:paragraph -->',
			)
		);
		$proposed_content       = '<!-- wp:paragraph --><p>Normal save proposed content.</p><!-- /wp:paragraph -->';
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$proposed_content,
			'diff-match-patch',
			array(
				'version' => '99',
			)
		);
		$request                = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content' => $content_with_sync_meta,
			)
		);

		$this->assertIsString( $content_with_sync_meta );
		wp_set_current_user( self::$author_user_id );

		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );

		$response     = rest_get_server()->dispatch( $request );
		$after_post   = get_post( $post_id );
		$parsed_saved = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotSame( $content_with_sync_meta, $after_post->post_content );
		$this->assertStringNotContainsString( '<script', strtolower( $after_post->post_content ) );
		$this->assertIsArray( $parsed_saved );
		$this->assertNull( $parsed_saved['sync_meta'] );
		$this->assertNull( $parsed_saved['sync_meta_format'] );
		$this->assertNull( $parsed_saved['sync_meta_position'] );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_scoped_sync_meta_kses_allowance_has_no_global_save_or_post_lock_hooks() {
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance' ) );

		foreach (
			array(
				'content_save_pre',
				'save_post',
				'wp_insert_post',
				'wp_check_post_lock_window',
				'show_post_locked_dialog',
				'override_post_lock',
				'post_locked_dialog',
				'post_lock_lost_dialog',
			) as $hook_name
		) {
			$this->assert_no_de_rtc_callbacks_attached_to_hook( $hook_name );
		}
	}

	/**
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_escalation_reason
	 * @covers ::wp_de_rtc_get_unfiltered_html_review_rejection_error
	 * @covers ::wp_de_rtc_get_rest_recovery_permission_contract
	 */
	public function test_unfiltered_html_review_rejection_vocabulary_is_inert_and_exportable() {
		$post_id          = $this->create_sync_meta_post( 'unfiltered review current content', 7, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com"></iframe><!-- /wp:html -->';
		$candidate_content = wp_de_rtc_add_sync_meta_to_post_content(
			$proposed_content,
			'diff-match-patch',
			array(
				'version'          => '8',
				'previous_version' => '7',
			)
		);
		$proposed_review  = wp_de_rtc_get_kses_post_content_review_evidence( $proposed_content );
		$candidate_review = wp_de_rtc_get_kses_post_content_review_evidence(
			$candidate_content,
			array(
				'allow_sync_meta_script' => true,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$error = wp_de_rtc_get_unfiltered_html_review_rejection_error(
			$post_id,
			array(
				'pending_change_count'               => 2,
				'rest_route'                         => 'post_retry_save',
				'proposed_post_content_hash'         => hash( 'sha256', $proposed_content ),
				'candidate_post_content_hash'        => hash( 'sha256', $candidate_content ),
				'proposed_post_content_kses_review'  => $proposed_review,
				'candidate_post_content_kses_review' => $candidate_review,
			)
		);
		$data  = $error->get_error_data( 'de_rtc_unfiltered_html_would_change_content' );

		$this->assertWPError( $error );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $error->get_error_code() );
		$this->assertSame( 403, $data['status'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['reason_code'] );
		$this->assertSame( 'collaborative_unfiltered_html_review_required', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertTrue( $data['requires_edit_post'] );
		$this->assertTrue( $data['requires_unfiltered_html'] );
		$this->assertFalse( $data['unfiltered_html_allowed'] );
		$this->assertTrue( $data['authorship_review_required'] );
		$this->assertTrue( $data['content_capability_review_required'] );
		$this->assertTrue( $data['requires_reviewer_escalation'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_required_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_scope'] );
		$this->assertSame( 'requires_reviewer_escalation', $data['review_status'] );
		$this->assertSame( 'unfiltered_html', $data['reviewer_capability'] );
		$this->assertTrue( $data['escalation_required'] );
		$this->assertSame( 'proposed_content_and_retry_save_candidate_would_change_by_kses', $data['escalation_reason'] );
		$this->assertSame( 'wp_filter_post_kses', $data['content_filter'] );
		$this->assertSame( 'content_save_pre', $data['content_filter_context'] );
		$this->assertTrue( $data['content_would_change_by_kses'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $data['proposed_content_hash'] );
		$this->assertSame( $proposed_review['filtered_content_hash'], $data['kses_filtered_proposed_content_hash'] );
		$this->assertSame( hash( 'sha256', $candidate_content ), $data['candidate_content_hash'] );
		$this->assertSame( $candidate_review['filtered_content_hash'], $data['kses_filtered_candidate_content_hash'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertSame( 'requires_reviewer_escalation', $data['review_contract']['status'] );
		$this->assertSame( 'unfiltered_html_content_capability_review', $data['review_contract']['type'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['review_contract']['review_action'] );
		$this->assertSame( 'unfiltered_html', $data['review_contract']['review_required_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['review_contract']['review_scope'] );
		$this->assertSame( 'unfiltered_html', $data['review_contract']['reviewer_capability'] );
		$this->assertSame( $data['reviewer_capability'], $data['review_contract']['reviewer_capability'] );
		$this->assertSame( $data['review_required_capability'], $data['review_contract']['review_required_capability'] );
		$this->assertSame( $data['review_scope'], $data['review_contract']['review_scope'] );
		$this->assertTrue( $data['review_contract']['escalation_required'] );
		$this->assertSame( 'proposed_content_and_retry_save_candidate_would_change_by_kses', $data['review_contract']['escalation_reason'] );
		$this->assertSame( $data['escalation_reason'], $data['review_contract']['escalation_reason'] );
		$this->assertSame( $data['proposed_content_hash'], $data['review_contract']['proposed_content_hash'] );
		$this->assertSame( $data['candidate_content_hash'], $data['review_contract']['candidate_content_hash'] );
		$this->assertFalse( $data['review_contract']['raw_content_included'] );
		$this->assert_review_rejection_omits_raw_content(
			$data,
			array(
				'iframe',
				'example.com',
				'wp:html',
				'post-sync-meta',
				'data-sync-meta-format',
			)
		);
		$this->assertContains( 'export_local_updates', $data['recovery_actions'] );
		$this->assertContains( 'request_unfiltered_html_reviewer', $data['recovery_actions'] );
		$this->assertContains( 'refetch_server_state', $data['recovery_actions'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['permission_contract']['unfiltered_html_allowed'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['permission_contract']['unfiltered_html_rejection_code'] );
		$this->assertSame( 'request_unfiltered_html_reviewer', $data['permission_contract']['unfiltered_html_review_action'] );
		$this->assertSame( 'unfiltered_html', $data['permission_contract']['unfiltered_html_review_capability'] );
		$this->assertSame( 'collaborative_post_content', $data['permission_contract']['unfiltered_html_review_scope'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * Data provider for DE-RTC proof endpoints.
	 *
	 * @return array[]
	 */
	public function data_proof_endpoint_requests() {
		$proposed_content = '<!-- wp:paragraph --><p>Proposed retry-save content.</p><!-- /wp:paragraph -->';

		return array(
			'recovery'     => array(
				'recovery',
				array(),
			),
			'stale-base'   => array(
				'stale-base',
				array(
					'client_base_version' => '6',
				),
			),
			'retry-submit' => array(
				'retry-submit',
				array(
					'client_base_version'        => '7',
					'proposed_post_content_hash' => hash( 'sha256', $proposed_content ),
				),
			),
			'retry-save'   => array(
				'retry-save',
				array(
					'client_base_version'           => '7',
					'accepted_proof_server_version' => '7',
					'proposed_post_content'         => $proposed_content,
					'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				),
			),
			'review-approval' => array(
				'review-approval',
				array(
					'client_base_version'            => '7',
					'accepted_proof_server_version'  => '7',
					'proposed_post_content_hash'     => hash( 'sha256', $proposed_content ),
					'reviewed_proposed_content_hash' => hash( 'sha256', $proposed_content ),
					'candidate_post_content_hash'    => hash( 'sha256', 'candidate post content' ),
					'reviewed_candidate_content_hash' => hash( 'sha256', 'candidate post content' ),
				),
			),
		);
	}

	/**
	 * Data provider for retry-save proof flags that must never claim prior persistence.
	 *
	 * @return array[]
	 */
	public function data_retry_save_persistence_flags() {
		return array(
			'saves post'           => array( 'accepted_proof_saves_post' ),
			'mutates post content' => array( 'accepted_proof_mutates_post_content' ),
			'creates revision'     => array( 'accepted_proof_creates_revision' ),
			'claims saved'         => array( 'accepted_proof_claims_saved' ),
		);
	}

	/**
	 * Data provider for invalid retry-save review approval proof consumption.
	 *
	 * @return array[]
	 */
	public function data_invalid_review_approval_proofs() {
		return array(
			'raw content'       => array(
				'raw content',
				array(
					'raw_content_included' => true,
				),
				'de_rtc_sync_meta_tampered',
				'retry_save_review_approval_raw_content_rejected',
			),
			'camel raw content' => array(
				'camel raw content',
				array(
					'rawContent' => '<!-- wp:html -->raw proof content<!-- /wp:html -->',
				),
				'de_rtc_sync_meta_tampered',
				'retry_save_review_approval_raw_content_rejected',
			),
			'candidate mismatch' => array(
				'candidate mismatch',
				array(
					'candidate_post_content_hash' => hash( 'sha256', 'mismatched candidate content' ),
				),
				'de_rtc_sync_meta_tampered',
				'retry_save_review_approval_proof_signature_mismatch',
			),
			'unapproved block'  => array(
				'unapproved block',
				array(
					'reviewed_block_items' => array(
						array(
							'review_status' => 'rejected',
						),
					),
				),
				'de_rtc_sync_meta_tampered',
				'retry_save_review_approval_proof_signature_mismatch',
			),
			'invalid signature' => array(
				'invalid signature',
				array(
					'proof_signature' => str_repeat( '0', 64 ),
				),
				'de_rtc_sync_meta_tampered',
				'retry_save_review_approval_proof_signature_mismatch',
			),
		);
	}

	/**
	 * Creates a DE-RTC proof REST request.
	 *
	 * @param string $rest_base Post type REST base.
	 * @param int    $post_id   Post ID.
	 * @param string $endpoint  Endpoint suffix.
	 * @param array  $params    Body parameters.
	 * @return WP_REST_Request REST request.
	 */
	private function create_distributed_editing_request( $rest_base, $post_id, $endpoint, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/' . $endpoint );
		$request->set_body_params( $params );

		return $request;
	}

	/**
	 * Creates a post with synthetic DE-RTC sync metadata.
	 *
	 * @param string $label   Content label.
	 * @param mixed  $version Sync metadata version.
	 * @param int    $author  Optional post author. Default admin user.
	 * @return int Post ID.
	 */
	private function create_sync_meta_post( $label, $version, $author = null ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>' . $label . '.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content_with_sync_meta );

		return self::factory()->post->create(
			array(
				'post_author'  => null === $author ? self::$admin_user_id : $author,
				'post_title'   => 'DE-RTC permission flow post',
				'post_status'  => 'draft',
				'post_content' => $content_with_sync_meta,
			)
		);
	}

	/**
	 * Creates the hash-only proof needed to approve a risky retry-save request.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $proposed_content Proposed post content without sync metadata.
	 * @param array  $args             Optional retry-save version/count data.
	 * @return array Proof data and candidate hash evidence.
	 */
	private function create_retry_save_review_approval_proof( $post_id, $proposed_content, $args = array() ) {
		$post                          = get_post( $post_id );
		$current                       = wp_de_rtc_parse_post_content_sync_meta( $post->post_content );
		$client_base_version           = isset( $args['client_base_version'] ) ? (string) $args['client_base_version'] : '7';
		$accepted_proof_server_version = isset( $args['accepted_proof_server_version'] ) ? (string) $args['accepted_proof_server_version'] : '7';
		$server_version                = isset( $args['server_version'] ) ? (string) $args['server_version'] : '7';
		$rebased_from_version          = isset( $args['rebased_from_version'] ) ? (string) $args['rebased_from_version'] : $client_base_version;
		$pending_change_count          = isset( $args['pending_change_count'] ) ? (int) $args['pending_change_count'] : 1;
		$proposed_content_hash         = hash( 'sha256', $proposed_content );
		$next_version                  = wp_de_rtc_get_next_sync_meta_version( $server_version, $proposed_content_hash );
		$next_sync_meta                = $current['sync_meta'];

		$next_sync_meta['version']            = $next_version;
		$next_sync_meta['previous_version']   = $server_version;
		$next_sync_meta['last_server_update'] = array(
			'type'                         => 'retry_save',
			'user_id'                      => get_current_user_id(),
			'client_base_version'          => $client_base_version,
			'accepted_proof_server_version' => $accepted_proof_server_version,
			'rebased_from_version'         => $rebased_from_version,
			'pending_change_count'         => $pending_change_count,
			'proposed_post_content_hash'   => $proposed_content_hash,
		);

		$candidate_content = wp_de_rtc_add_sync_meta_to_post_content(
			$proposed_content,
			$current['sync_meta_format'],
			$next_sync_meta,
			$current['sync_meta_position']
		);
		$block_content_hash = hash( 'sha256', $proposed_content );

		$review_approval_proof = array(
			'type'                            => 'unfiltered_html_retry_save_review_approval',
			'status'                          => 'approved_by_unfiltered_html_reviewer',
			'post_id'                         => (int) $post_id,
			'post_type'                       => $post->post_type,
			'reviewer_user_id'                => isset( $args['reviewer_user_id'] ) ? (int) $args['reviewer_user_id'] : self::$admin_user_id,
			'reviewer_capability'             => 'unfiltered_html',
			'review_scope'                    => 'collaborative_post_content',
			'client_base_version'             => $client_base_version,
			'accepted_proof_server_version'   => $accepted_proof_server_version,
			'server_version'                  => $server_version,
			'rebased_from_version'            => $rebased_from_version,
			'previous_server_version'         => $server_version,
			'proposed_post_content_hash'      => $proposed_content_hash,
			'reviewed_proposed_content_hash'  => $proposed_content_hash,
			'candidate_post_content_hash'     => hash( 'sha256', $candidate_content ),
			'reviewed_candidate_content_hash' => hash( 'sha256', $candidate_content ),
			'reviewed_block_items'            => array(
				array(
					'id'                             => 'risk-html-approved',
					'block_name'                     => 'core/html',
					'change_kind'                    => 'added_block',
					'risk_reason'                    => 'kses_would_remove_script',
					'proposed_content_hash'          => $block_content_hash,
					'reviewed_proposed_content_hash' => $block_content_hash,
					'review_status'                  => 'approved_for_retry_save',
					'review_evidence_type'           => 'kses_block_hash_only_change',
					'content_review_policy'          => 'kses',
				),
			),
			'raw_content_included'            => false,
			'saves_post'                      => false,
			'mutates_post_content'            => false,
			'creates_revision'                => false,
			'claims_saved'                    => false,
		);

		foreach ( array( 'issued_at', 'expires_at', 'site_id', 'site_url' ) as $scoped_field ) {
			if ( array_key_exists( $scoped_field, $args ) ) {
				$review_approval_proof[ $scoped_field ] = $args[ $scoped_field ];
			}
		}

		$review_approval_proof = wp_de_rtc_add_review_approval_proof_signature( $review_approval_proof );

		return array(
			'proposed_post_content_hash' => $proposed_content_hash,
			'candidate_post_content'     => $candidate_content,
			'candidate_post_content_hash' => hash( 'sha256', $candidate_content ),
			'review_approval_proof'      => $review_approval_proof,
		);
	}

	/**
	 * Returns post revisions without checking revision support.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post[] Revisions keyed by revision ID.
	 */
	private function get_post_revisions( $post_id ) {
		return wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
	}

	/**
	 * Asserts that reviewer rejection evidence does not carry raw post content.
	 *
	 * @param mixed    $payload       Error payload to inspect.
	 * @param string[] $raw_fragments Raw content fragments that must be absent.
	 */
	private function assert_review_rejection_omits_raw_content( $payload, $raw_fragments ) {
		$encoded_payload = wp_json_encode( $payload );

		$this->assertIsString( $encoded_payload );

		foreach ( $raw_fragments as $raw_fragment ) {
			$this->assertStringNotContainsString( $raw_fragment, $encoded_payload );
		}

		$this->assert_review_rejection_omits_raw_content_keys( $payload );
	}

	/**
	 * Asserts that a proof handoff is an opaque token envelope, not a field graph.
	 *
	 * @param array $envelope Opaque proof token envelope.
	 * @param int   $post_id  Expected post ID.
	 */
	private function assert_opaque_review_approval_proof_envelope( $envelope, $post_id ) {
		$this->assertIsArray( $envelope );
		$this->assertSame( 'opaque_review_approval_proof_token', $envelope['proof_envelope_type'] );
		$this->assertSame( 1, $envelope['token_version'] );
		$this->assertNotEmpty( $envelope['token'] );
		$this->assertIsInt( $envelope['issued_at'] );
		$this->assertIsInt( $envelope['expires_at'] );
		$this->assertGreaterThan( $envelope['issued_at'], $envelope['expires_at'] );
		$this->assertSame( $post_id, $envelope['post']['id'] );
		$this->assertSame( 'post', $envelope['post']['type'] );
		$this->assertSame( 'option', $envelope['token_audit']['storage'] );
		$this->assertSame( 'minted', $envelope['token_audit']['status'] );
		$this->assertTrue( $envelope['token_audit']['recorded'] );
		$this->assertTrue( $envelope['token_audit']['record_found'] );
		$this->assertArrayNotHasKey( 'proof', $envelope );
		$this->assertArrayNotHasKey( 'field_based_review_approval_proof', $envelope );
		$this->assertArrayNotHasKey( 'reviewed_block_items', $envelope );
		$this->assertArrayNotHasKey( 'proof_signature', $envelope );
		$this->assert_opaque_review_approval_proof_token_audit_omits_private_fields( $envelope['token_audit'] );
	}

	/**
	 * Asserts that an opaque token audit record carries only durable public evidence.
	 *
	 * @param array    $record        Token audit record.
	 * @param int      $post_id       Expected post ID.
	 * @param string   $status        Expected lifecycle status.
	 * @param string[] $events        Expected lifecycle events in order.
	 * @param string[] $raw_fragments Raw content fragments that must be absent.
	 */
	private function assert_opaque_review_approval_proof_token_audit_record( $record, $post_id, $status, $events, $raw_fragments ) {
		$this->assertIsArray( $record );
		$this->assertSame( 'opaque_review_approval_proof_token_audit_record', $record['type'] );
		$this->assertSame( 1, $record['audit_record_version'] );
		$this->assertSame( $post_id, $record['post_id'] );
		$this->assertSame( 'post', $record['post_type'] );
		$this->assertSame( $status, $record['lifecycle_status'] );
		$this->assertSame( 'option', $record['audit_storage_engine'] );
		$this->assertArrayHasKey( $status . '_at', $record );
		$this->assertSame( count( $events ), $record['event_count'] );
		$this->assertSame( $events, array_column( $record['events'], 'event' ) );
		$this->assert_opaque_review_approval_proof_token_audit_omits_private_fields( $record, $raw_fragments );
	}

	/**
	 * Asserts that token audit evidence omits proof internals and raw content.
	 *
	 * @param mixed    $payload       Audit payload.
	 * @param string[] $raw_fragments Raw content fragments that must be absent.
	 */
	private function assert_opaque_review_approval_proof_token_audit_omits_private_fields( $payload, $raw_fragments = array() ) {
		$encoded_payload = wp_json_encode( $payload );

		$this->assertIsString( $encoded_payload );

		foreach ( $raw_fragments as $raw_fragment ) {
			$this->assertStringNotContainsString( $raw_fragment, $encoded_payload );
		}

		$this->assert_payload_omits_keys(
			$payload,
			array(
				'field_based_review_approval_proof',
				'proof',
				'proof_signature',
				'reviewed_block_items',
				'reviewer_user_id',
				'low_privileged_saver_user_id',
				'raw_content',
				'raw_post_content',
				'proposed_post_content',
				'candidate_post_content',
			)
		);
	}

	/**
	 * Asserts that a signed review approval proof carries current site and time scope.
	 *
	 * @param array    $proof      Review approval proof.
	 * @param int|null $not_before Optional lower issued_at bound.
	 * @param int|null $not_after  Optional upper issued_at bound.
	 */
	private function assert_review_approval_proof_has_current_time_site_scope( $proof, $not_before = null, $not_after = null ) {
		$site_scope = wp_de_rtc_get_review_approval_proof_site_scope();

		$this->assertIsArray( $proof );
		$this->assertArrayHasKey( 'issued_at', $proof );
		$this->assertArrayHasKey( 'expires_at', $proof );
		$this->assertArrayHasKey( 'site_id', $proof );
		$this->assertArrayHasKey( 'site_url', $proof );
		$this->assertIsInt( $proof['issued_at'] );
		$this->assertIsInt( $proof['expires_at'] );
		$this->assertSame( $proof['issued_at'] + wp_de_rtc_get_review_approval_proof_lifetime_seconds(), $proof['expires_at'] );
		$this->assertSame( $site_scope['site_id'], $proof['site_id'] );
		$this->assertSame( $site_scope['site_url'], $proof['site_url'] );
		$this->assertTrue( wp_de_rtc_is_review_approval_proof_signature_valid( $proof ) );

		if ( null !== $not_before ) {
			$this->assertGreaterThanOrEqual( $not_before, $proof['issued_at'] );
		}

		if ( null !== $not_after ) {
			$this->assertLessThanOrEqual( $not_after, $proof['issued_at'] );
		}
	}

	/**
	 * Asserts that raw-content fields are absent at every payload nesting level.
	 *
	 * @param mixed $payload Payload value.
	 */
	private function assert_review_rejection_omits_raw_content_keys( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}

		foreach ( $payload as $key => $value ) {
			if ( is_string( $key ) ) {
				$this->assertNotSame( 'proposed_post_content', $key );
				$this->assertNotSame( 'candidate_post_content', $key );
				$this->assertNotSame( 'proposed_post_content_kses_review', $key );
				$this->assertNotSame( 'candidate_post_content_kses_review', $key );
			}

			$this->assert_review_rejection_omits_raw_content_keys( $value );
		}
	}

	/**
	 * Asserts that keys are absent at every payload nesting level.
	 *
	 * @param mixed    $payload        Payload value.
	 * @param string[] $forbidden_keys Forbidden keys.
	 */
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

	/**
	 * Asserts that a hook has no DE-RTC callbacks attached.
	 *
	 * @param string $hook_name Hook name.
	 */
	private function assert_no_de_rtc_callbacks_attached_to_hook( $hook_name ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof WP_Hook ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_string( $function ) && 0 === strpos( $function, 'wp_de_rtc_' ) ) {
					$this->fail( 'Unexpected DE-RTC callback attached to ' . $hook_name . ': ' . $function );
				}
			}
		}

		$this->assertTrue( true );
	}

	/**
	 * Asserts that a rejected request did not mutate content or revisions.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $before_content   Content before request.
	 * @param array  $before_revisions Revisions before request.
	 */
	private function assert_post_unchanged( $post_id, $before_content, $before_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( $before_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}
}
