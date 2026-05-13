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
