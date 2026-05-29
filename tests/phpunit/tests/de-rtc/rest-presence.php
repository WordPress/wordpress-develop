<?php
/**
 * Tests for the Distributed Editing presence snapshot endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Presence extends WP_Test_REST_TestCase {

	protected static $admin_user_id;
	protected static $author_user_id;
	protected static $subscriber_user_id;

	protected $server;
	protected $presence_table_name;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_user_id     = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Mira Presence',
			)
		);
		self::$subscriber_user_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		$this->presence_table_name = wp_de_rtc_get_presence_table_name();
		$this->drop_presence_table();
		delete_option( 'wp_de_rtc_presence_schema_version' );

		wp_set_current_user( self::$admin_user_id );
		update_option( 'wp_de_rtc_enabled', true );
	}

	public function tear_down() {
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
		delete_option( 'wp_de_rtc_enabled' );
		delete_option( 'wp_de_rtc_presence_schema_version' );
		$this->drop_presence_table();

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_register_rest_routes
	 * @covers ::wp_de_rtc_get_rest_recovery_post_type_rest_bases
	 */
	public function test_presence_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/presence', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence/heartbeat', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/presence/heartbeat', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence/storage-readiness', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/presence/storage-readiness', $routes );
		$this->assertArrayHasKey( 'limit', $routes['/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence'][0]['args'] );
		$this->assertSame( 1, $routes['/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence'][0]['args']['limit']['minimum'] );
		$this->assertSame( 100, $routes['/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence'][0]['args']['limit']['maximum'] );
		$heartbeat_route_args = $routes['/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/presence/heartbeat'][0]['args'];

		$this->assertArrayHasKey( 'confirmed_base_version', $heartbeat_route_args );
		$this->assertArrayHasKey( 'confirmed_state_hash', $heartbeat_route_args );
		$this->assertArrayHasKey( 'has_pending_changes', $heartbeat_route_args );
		$this->assertArrayHasKey( 'confirmed_at_gmt', $heartbeat_route_args );
		$this->assertArrayHasKey( 'selection_state', $heartbeat_route_args );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_endpoint
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_permissions_check
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_presence_storage_readiness_request_rest_base
	 * @covers ::wp_de_rtc_get_presence_storage_readiness
	 */
	public function test_presence_storage_readiness_endpoint_reports_setup_required_without_installing_or_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence storage readiness setup post',
				'post_content' => '<!-- wp:paragraph --><p>Readiness setup required.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/storage-readiness' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_storage_setup_required', $data['result'] );
		$this->assertSame( 'setup_required', $data['status'] );
		$this->assertSame( 'post_presence_storage_readiness', $data['rest_route'] );
		$this->assertSame( 'GET', $data['method'] );
		$this->assertSame( '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/storage-readiness', $data['route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( 'posts', $data['post_type_rest_base'] );
		$this->assertFalse( $data['tableExists'] );
		$this->assertFalse( $data['schemaCurrent'] );
		$this->assertTrue( $data['setupRequired'] );
		$this->assertSame( 'degraded', $data['expectedStartupHeartbeatStatus'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertTrue( $data['diagnosticOnly'] );
		$this->assertTrue( $data['contentFree'] );
		$this->assertFalse( $data['installsPresenceTable'] );
		$this->assertFalse( $data['automaticPerRequestInstall'] );
		$this->assertFalse( $data['writesPresence'] );
		$this->assertFalse( $data['recordsPresenceHeartbeat'] );
		$this->assertFalse( $data['startsPolling'] );
		$this->assertFalse( $data['callsSave'] );
		$this->assertFalse( $data['calls_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutatesPostContent'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['mutates_persisted_post_content'] );
		$this->assertFalse( $data['createsRevision'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changesPostLock'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claimsAbsence'] );
		$this->assertFalse( $data['claims_absence'] );
		$this->assertFalse( $data['claimsSaved'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['containsRawContent'] );
		$this->assertFalse( $data['contains_raw_content'] );
		$this->assertFalse( $data['exposesRawContent'] );
		$this->assertFalse( $data['exposes_raw_content'] );
		$this->assertFalse( $data['exposesUserIds'] );
		$this->assertFalse( $data['exposes_user_ids'] );
		$this->assertFalse( $data['exposesCursorOffset'] );
		$this->assertFalse( $data['exposes_cursor_offset'] );
		$this->assertFalse( $data['exposesSelection'] );
		$this->assertFalse( $data['exposes_selection'] );
		$this->assertTrue( $data['exposes_selection_presence'] );
		$this->assertFalse( $data['exposes_raw_selected_text'] );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'userId',
				'user_id',
				'userLogin',
				'user_login',
				'email',
				'user_email',
				'cursorOffset',
				'cursor_offset',
				'selection',
				'rawContent',
				'raw_content',
			)
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_endpoint
	 * @covers ::wp_de_rtc_get_presence_storage_readiness
	 */
	public function test_presence_storage_readiness_endpoint_reports_ready_after_explicit_setup_without_presence_or_post_writes() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence storage readiness ready post',
				'post_content' => '<!-- wp:paragraph --><p>Readiness ready.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/storage-readiness' );

		wp_de_rtc_install_presence_table();
		$before_rows = $this->get_presence_row_count_for_post( $post_id );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_storage_ready', $data['result'] );
		$this->assertSame( 'ready', $data['status'] );
		$this->assertSame( 'post_presence_storage_readiness', $data['rest_route'] );
		$this->assertTrue( $data['tableExists'] );
		$this->assertTrue( $data['schemaCurrent'] );
		$this->assertFalse( $data['setupRequired'] );
		$this->assertSame( 'sent', $data['expectedStartupHeartbeatStatus'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertTrue( $data['diagnosticOnly'] );
		$this->assertTrue( $data['contentFree'] );
		$this->assertFalse( $data['installsPresenceTable'] );
		$this->assertFalse( $data['automaticPerRequestInstall'] );
		$this->assertFalse( $data['writesPresence'] );
		$this->assertFalse( $data['recordsPresenceHeartbeat'] );
		$this->assertFalse( $data['callsSave'] );
		$this->assertFalse( $data['mutatesPostContent'] );
		$this->assertFalse( $data['changesPostLock'] );
		$this->assertFalse( $data['claimsSaved'] );
		$this->assertSame( $before_rows, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_permissions_check
	 */
	public function test_presence_storage_readiness_endpoint_requires_feature_enablement_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled presence storage readiness post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled readiness.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/storage-readiness' );

		update_option( 'wp_de_rtc_enabled', false );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'post_presence_storage_readiness', $data['rest_route'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['writes_presence'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['installs_presence_table'] );
		$this->assertFalse( $data['calls_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_permissions_check
	 * @covers ::wp_de_rtc_rest_presence_storage_readiness_request_matches_post_type
	 */
	public function test_presence_storage_readiness_endpoint_requires_matching_post_type_route_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC route presence storage readiness post',
				'post_content' => '<!-- wp:paragraph --><p>Route readiness.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $post_id . '/distributed-editing/presence/storage-readiness' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_endpoint
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_permissions_check
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_presence_heartbeat_request_rest_base
	 * @covers ::wp_de_rtc_record_presence_heartbeat
	 * @covers ::wp_de_rtc_get_presence_heartbeat_interval_seconds
	 * @covers ::wp_de_rtc_get_presence_heartbeat_expires_after_seconds
	 */
	public function test_presence_heartbeat_records_content_free_row_without_post_mutation() {
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC presence heartbeat post',
				'post_content' => '<!-- wp:paragraph --><p>Heartbeat endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$session_key      = 'session-key-171';
		$confirmed_hash   = str_repeat( 'a', 64 );
		$confirmed_at_gmt = '2026-05-20 12:00:00';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );

		wp_set_current_user( self::$author_user_id );
		wp_de_rtc_install_presence_table();

		$request->set_param( 'session_key', $session_key );
		$request->set_param( 'confirmed_base_version', '12' );
		$request->set_param( 'confirmed_state_hash', $confirmed_hash );
		$request->set_param( 'has_pending_changes', true );
		$request->set_param( 'confirmed_at_gmt', $confirmed_at_gmt );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$row      = $this->get_presence_row_for_post( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_heartbeat_recorded', $data['result'] );
		$this->assertSame( 'post_presence_heartbeat', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( 'posts', $data['post_type_rest_base'] );
		$this->assertTrue( $data['storage_table_ready'] );
		$this->assertTrue( $data['writes_presence'] );
		$this->assertTrue( $data['records_presence_heartbeat'] );
		$this->assertTrue( $data['heartbeat_writes_enabled_now'] );
		$this->assertTrue( $data['session_key_hash_recorded'] );
		$this->assertTrue( $data['actor_hash_recorded'] );
		$this->assertTrue( $data['display_name_recorded'] );
		$this->assertTrue( $data['permission_summary_recorded'] );
		$this->assertTrue( $data['permissions']['canEdit'] );
		$this->assertTrue( $data['permissions']['canPublish'] );
		$this->assertFalse( $data['permissions']['canSaveDangerousHtml'] );
		$this->assertTrue( $data['document_state_recorded'] );
		$this->assertSame( '12', $data['document_state']['confirmedBaseVersion'] );
		$this->assertSame(
			$confirmed_hash,
			$data['document_state']['confirmedStateHash']
		);
		$this->assertTrue( $data['document_state']['hasPendingChanges'] );
		$this->assertSame(
			$confirmed_at_gmt,
			$data['document_state']['confirmedAtGmt']
		);
		$this->assertSame(
			'client_reported_presence',
			$data['document_state']['source']
		);
		$this->assertFalse( $data['document_state']['authoritativeForSave'] );
		$this->assertFalse( $data['document_state']['claimsSaved'] );
		$this->assertFalse( $data['document_state']['exposesRawContent'] );
		$this->assertFalse( $data['selection_state_recorded'] );
		$this->assertFalse( $data['selection_state']['available'] );
		$this->assertFalse( $data['selection_state']['exposesRawSelectedText'] );
		$this->assertFalse( $data['selection_state']['exposesClientId'] );
		$this->assertTrue( $data['last_seen_recorded'] );
		$this->assertTrue( $data['expires_at_recorded'] );
		$this->assertSame( 0, $data['session_duration_seconds'] );
		$this->assertSame( 'active', $data['freshness'] );
		$this->assertSame( 'nominal', $data['server_contact'] );
		$this->assertSame( 120, $data['heartbeat_interval_seconds'] );
		$this->assertSame( 600, $data['expires_after_seconds'] );
		$this->assertTrue( $data['repeated_refresh_optional'] );
		$this->assertTrue( $data['correctness_independent_of_transport'] );
		$this->assertFalse( $data['transport_required_for_correctness'] );
		$this->assertFalse( $data['table_exists_required_for_save_correctness'] );
		$this->assertFalse( $data['installs_presence_table'] );
		$this->assertFalse( $data['automatic_table_install'] );
		$this->assertFalse( $data['enables_repeated_client_refresh'] );
		$this->assertFalse( $data['runtime_polling_enabled_now'] );
		$this->assertFalse( $data['calls_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['mutates_persisted_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_absence'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['exposes_raw_content'] );
		$this->assertFalse( $data['exposes_user_ids'] );
		$this->assertFalse( $data['exposes_logins'] );
		$this->assertFalse( $data['exposes_email'] );
		$this->assertFalse( $data['exposes_cursor_offset'] );
		$this->assertFalse( $data['exposes_selection'] );
		$this->assertTrue( $data['exposes_selection_presence'] );
		$this->assertFalse( $data['exposes_raw_selected_text'] );
		$this->assertFalse( $data['raw_session_key_included'] );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'session_key',
				'sessionKey',
				'userId',
				'user_id',
				'userLogin',
				'user_login',
				'email',
				'user_email',
				'cursorOffset',
				'cursor_offset',
				'selection',
				'rawContent',
				'raw_content',
			)
		);
		$this->assertIsArray( $row );
		$this->assertSame( (string) $post_id, $row['post_id'] );
		$this->assertSame( 64, strlen( $row['session_key_hash'] ) );
		$this->assertNotSame( $session_key, $row['session_key_hash'] );
		$this->assertSame( 64, strlen( $row['actor_hash'] ) );
		$this->assertSame( 'Mira Presence', $row['display_name'] );
		$this->assertSame( '1', $row['can_edit_post'] );
		$this->assertSame( '1', $row['can_publish_post'] );
		$this->assertSame( '0', $row['can_save_dangerous_html'] );
		$this->assertSame( '12', $row['confirmed_base_version'] );
		$this->assertSame( $confirmed_hash, $row['confirmed_state_hash'] );
		$this->assertSame( '1', $row['has_pending_changes'] );
		$this->assertSame( $confirmed_at_gmt, $row['confirmed_at_gmt'] );
		$this->assertEmpty( $row['selection_state_json'] );
		$this->assertSame( 'active', $row['freshness'] );
		$this->assertSame( 1, $this->get_presence_row_count_for_post( $post_id ) );

		$second_response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $second_response->get_status() );
		$this->assertSame( 1, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_endpoint
	 * @covers ::wp_de_rtc_record_presence_heartbeat
	 * @covers ::wp_de_rtc_get_sanitized_presence_selection_state
	 */
	public function test_presence_heartbeat_records_content_free_selection_state_without_raw_content() {
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC presence selection heartbeat post',
				'post_content' => '<!-- wp:paragraph --><p>Selection heartbeat endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );

		wp_set_current_user( self::$author_user_id );
		wp_de_rtc_install_presence_table();

		$request->set_param( 'session_key', 'selection-session' );
		$request->set_param(
			'selection_state',
			array(
				'available'     => true,
				'schema'        => 'de-rtc-selection-presence-v1',
				'kind'          => 'caret',
				'isCollapsed'   => true,
				'anchor'        => array(
					'blockPath'    => array( 0 ),
					'attributeKey' => 'content',
					'offset'       => 6,
				),
				'focus'         => array(
					'blockPath'    => array( 0 ),
					'attributeKey' => 'content',
					'offset'       => 6,
				),
				'reportedAtGmt' => '2026-05-20 12:00:00',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$row      = $this->get_presence_row_for_post( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_heartbeat_recorded', $data['result'] );
		$this->assertTrue( $data['selection_state_recorded'] );
		$this->assertTrue( $data['selection_state']['available'] );
		$this->assertSame( 'caret', $data['selection_state']['kind'] );
		$this->assertSame( array( 0 ), $data['selection_state']['anchor']['blockPath'] );
		$this->assertSame( 6, $data['selection_state']['anchor']['offset'] );
		$this->assertFalse( $data['selection_state']['exposesRawSelectedText'] );
		$this->assertFalse( $data['selection_state']['exposesClientId'] );
		$this->assertNotEmpty( $row['selection_state_json'] );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'clientId',
				'client_id',
				'rawContent',
				'raw_content',
				'selectedText',
				'selected_text',
				'selection',
			)
		);
		$this->assertStringNotContainsString( 'clientId', $row['selection_state_json'] );
		$this->assertStringNotContainsString( 'rawContent', $row['selection_state_json'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_endpoint
	 * @covers ::wp_de_rtc_record_presence_heartbeat
	 * @covers ::wp_de_rtc_get_sanitized_presence_selection_state
	 */
	public function test_presence_heartbeat_records_v2_selection_sender_facts_without_render_authority() {
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC presence selection v2 heartbeat post',
				'post_content' => '<!-- wp:paragraph --><p>Selection heartbeat v2 endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$base_hash        = str_repeat( 'a', 64 );

		wp_set_current_user( self::$author_user_id );
		wp_de_rtc_install_presence_table();

		$request->set_param( 'session_key', 'selection-v2-session' );
		$request->set_param(
			'selection_state',
			array(
				'available'             => true,
				'schema'                => 'de-rtc-selection-presence-v2',
				'kind'                  => 'multi_block',
				'isCollapsed'           => false,
				'baseVersion'           => '12',
				'baseStateHash'         => $base_hash,
				'selectionSourceStatus' => 'base_aligned',
				'mappingStatus'         => 'exact',
				'anchor'                => array(
					'blockPath'    => array( 0, 1 ),
					'blockUid'     => 'de-rtc-block-a',
					'attributeKey' => 'content',
					'offset'       => 1,
				),
				'focus'                 => array(
					'blockPath'    => array( 0, 2 ),
					'blockUid'     => 'de-rtc-block-b',
					'attributeKey' => 'content',
					'offset'       => 4,
				),
				'reportedAtGmt'         => '2026-05-20 12:00:00',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$row      = $this->get_presence_row_for_post( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['selection_state_recorded'] );
		$this->assertSame( 'de-rtc-selection-presence-v2', $data['selection_state']['schema'] );
		$this->assertSame( '12', $data['selection_state']['baseVersion'] );
		$this->assertSame( $base_hash, $data['selection_state']['baseStateHash'] );
		$this->assertSame( 'base_aligned', $data['selection_state']['selectionSourceStatus'] );
		$this->assertSame( array( 0, 1 ), $data['selection_state']['anchor']['blockPath'] );
		$this->assertSame( 'de-rtc-block-a', $data['selection_state']['anchor']['blockUid'] );
		$this->assertArrayNotHasKey( 'mappingStatus', $data['selection_state'] );
		$this->assertArrayNotHasKey( 'resolvedMappingStatus', $data['selection_state'] );
		$this->assertStringNotContainsString( 'mappingStatus', $row['selection_state_json'] );
		$this->assertFalse( $data['selection_state']['authoritativeForSave'] );
		$this->assertFalse( $data['selection_state']['claimsSaved'] );
		$mapping_status_only = wp_de_rtc_get_sanitized_presence_selection_state(
			array(
				'available'     => true,
				'schema'        => 'de-rtc-selection-presence-v2',
				'kind'          => 'block',
				'baseVersion'   => '12',
				'baseStateHash' => $base_hash,
				'mappingStatus' => 'local_pending_only',
				'anchor'        => array(
					'blockPath' => array( 0 ),
				),
			),
			'2026-05-20 12:00:00'
		);
		$this->assertSame( 'unknown', $mapping_status_only['selectionSourceStatus'] );
		$this->assertArrayNotHasKey( 'mappingStatus', $mapping_status_only );
		$updated_at_alias = wp_de_rtc_get_sanitized_presence_selection_state(
			array(
				'available'     => true,
				'schema'        => 'de-rtc-selection-presence-v2',
				'kind'          => 'block',
				'baseVersion'   => '12',
				'baseStateHash' => $base_hash,
				'updatedAt'     => '2026-05-20T12:03:00Z',
				'anchor'        => array(
					'blockPath' => array( 0 ),
				),
			),
			'2026-05-20 12:05:00'
		);
		$this->assertSame( '2026-05-20 12:03:00', $updated_at_alias['reportedAtGmt'] );
		$forbidden_nested_selection = wp_de_rtc_get_sanitized_presence_selection_state(
			array(
				'available'     => true,
				'schema'        => 'de-rtc-selection-presence-v2',
				'kind'          => 'block',
				'baseVersion'   => '12',
				'baseStateHash' => $base_hash,
				'anchor'        => array(
					'blockPath'    => array( 0 ),
					'cursorOffset' => 4,
				),
			),
			'2026-05-20 12:00:00'
		);
		$this->assertFalse( $forbidden_nested_selection['available'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_endpoint
	 * @covers ::wp_de_rtc_record_presence_heartbeat
	 */
	public function test_presence_heartbeat_requires_explicit_storage_without_installing_or_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC heartbeat missing table post',
				'post_content' => '<!-- wp:paragraph --><p>Missing heartbeat table.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );

		$request->set_param( 'session_key', 'missing-table-session' );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_presence_storage_unavailable' );

		$this->assertErrorResponse( 'de_rtc_presence_storage_unavailable', $response, 503 );
		$this->assertSame( 'presence_storage_unavailable', $data['result'] );
		$this->assertSame( 'post_presence_heartbeat', $data['rest_route'] );
		$this->assertFalse( $data['storage_table_ready'] );
		$this->assertFalse( $data['writes_presence'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['heartbeat_writes_enabled_now'] );
		$this->assertSame( 'degraded', $data['server_contact'] );
		$this->assertTrue( $data['can_retry_after_install'] );
		$this->assertTrue( $data['repeated_refresh_optional'] );
		$this->assertTrue( $data['correctness_independent_of_transport'] );
		$this->assertFalse( $data['transport_required_for_correctness'] );
		$this->assertFalse( $data['table_exists_required_for_save_correctness'] );
		$this->assertFalse( $data['installs_presence_table'] );
		$this->assertFalse( $data['automatic_table_install'] );
		$this->assertFalse( $data['enables_repeated_client_refresh'] );
		$this->assertFalse( $data['runtime_polling_enabled_now'] );
		$this->assertFalse( $data['calls_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['mutates_persisted_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_absence'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertFalse( $data['raw_session_key_included'] );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_heartbeat_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_presence_heartbeat_requires_feature_enablement_without_writing_or_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled heartbeat post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled heartbeat endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );

		wp_de_rtc_install_presence_table();
		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
		$request->set_param( 'session_key', 'disabled-session' );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'post_presence_heartbeat', $data['rest_route'] );
		$this->assertTrue( $data['storage_table_ready'] );
		$this->assertFalse( $data['writes_presence'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['installs_presence_table'] );
		$this->assertFalse( $data['automatic_table_install'] );
		$this->assertFalse( $data['enables_repeated_client_refresh'] );
		$this->assertFalse( $data['runtime_polling_enabled_now'] );
		$this->assertFalse( $data['calls_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['mutates_persisted_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_absence'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( 0, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_storage_entries
	 * @covers ::wp_de_rtc_get_presence_storage_row_freshness
	 * @covers ::wp_de_rtc_get_presence_actor_hash
	 */
	public function test_presence_snapshot_reads_heartbeat_rows_without_private_fields_or_mutating() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC heartbeat-backed presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Heartbeat-backed presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);

		wp_de_rtc_install_presence_table();
		wp_set_current_user( self::$author_user_id );

		$heartbeat_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$heartbeat_request->set_param( 'session_key', 'heartbeat-backed-session' );
		$heartbeat_request->set_param( 'confirmed_base_version', '12' );
		$heartbeat_request->set_param(
			'confirmed_state_hash',
			str_repeat( 'b', 64 )
		);
		$heartbeat_request->set_param( 'has_pending_changes', false );
		$heartbeat_request->set_param( 'confirmed_at_gmt', '2026-05-20 12:00:00' );
		$heartbeat_request->set_param(
			'selection_state',
			array(
				'available'     => true,
				'schema'        => 'de-rtc-selection-presence-v1',
				'kind'          => 'rich_text',
				'isCollapsed'   => false,
				'anchor'        => array(
					'blockPath'    => array( 0 ),
					'attributeKey' => 'content',
					'offset'       => 3,
				),
				'focus'         => array(
					'blockPath'    => array( 0 ),
					'attributeKey' => 'content',
					'offset'       => 8,
				),
				'reportedAtGmt' => '2026-05-20 12:00:00',
			)
		);

		$heartbeat_response = rest_get_server()->dispatch( $heartbeat_request );

		$this->assertSame( 200, $heartbeat_response->get_status() );

		wp_set_current_user( self::$admin_user_id );

		$before_post       = get_post( $post_id );
		$before_revisions  = $this->get_post_revisions( $post_id );
		$before_row_count  = $this->get_presence_row_count_for_post( $post_id );
		$snapshot_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );
		$snapshot_response = rest_get_server()->dispatch( $snapshot_request );
		$data              = $snapshot_response->get_data();
		$roster            = $data['presence_roster'];
		$contract          = $data['presence_read_contract'];
		$entry             = $roster['entries'][0];

		$this->assertSame( 200, $snapshot_response->get_status() );
		$this->assertSame( 'presence_roster_snapshot', $data['result'] );
		$this->assertSame( 'post_presence_roster', $data['rest_route'] );
		$this->assertSame( 'active', $roster['status'] );
		$this->assertSame( 'current', $roster['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $roster['source'] );
		$this->assertTrue( $roster['storageBacked'] );
		$this->assertSame( 1, $roster['visibleCount'] );
		$this->assertSame( 1, $roster['totalKnownCount'] );
		$this->assertSame( 0, $roster['expiredCount'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertSame( 'Mira Presence', $entry['displayName'] );
		$this->assertSame( 'other_user', $entry['relationship'] );
		$this->assertSame( 'editing_post', $entry['activity'] );
		$this->assertSame( 'current', $entry['freshness'] );
		$this->assertArrayHasKey( 'sessionStartedAtGmt', $entry );
		$this->assertIsInt( $entry['sessionDurationSeconds'] );
		$this->assertGreaterThanOrEqual( 0, $entry['sessionDurationSeconds'] );
		$this->assertTrue( $entry['permissionsAvailable'] );
		$this->assertTrue( $entry['permissions']['canEdit'] );
		$this->assertTrue( $entry['permissions']['canPublish'] );
		$this->assertFalse( $entry['permissions']['canSaveDangerousHtml'] );
		$this->assertArrayHasKey( 'presenceUpdatedAtGmt', $entry );
		$this->assertTrue( $entry['documentState']['available'] );
		$this->assertSame( '12', $entry['documentState']['confirmedBaseVersion'] );
		$this->assertSame(
			str_repeat( 'b', 64 ),
			$entry['documentState']['confirmedStateHash']
		);
		$this->assertFalse( $entry['documentState']['hasPendingChanges'] );
		$this->assertSame(
			'2026-05-20 12:00:00',
			$entry['documentState']['confirmedAtGmt']
		);
		$this->assertSame(
			'client_reported_presence',
			$entry['documentState']['source']
		);
		$this->assertFalse( $entry['documentState']['authoritativeForSave'] );
		$this->assertFalse( $entry['documentState']['claimsSaved'] );
		$this->assertFalse( $entry['documentState']['exposesRawContent'] );
		$this->assertTrue( $entry['selectionState']['available'] );
		$this->assertSame( 'rich_text', $entry['selectionState']['kind'] );
		$this->assertSame( array( 0 ), $entry['selectionState']['anchor']['blockPath'] );
		$this->assertSame( 3, $entry['selectionState']['anchor']['offset'] );
		$this->assertFalse( $entry['selectionState']['exposesRawSelectedText'] );
		$this->assertFalse( $entry['selectionState']['exposesClientId'] );
		$this->assertSame( 'de_rtc_presence_storage', $entry['source'] );
		$this->assertFalse( $entry['exposesUserId'] );
		$this->assertFalse( $entry['exposesLogin'] );
		$this->assertFalse( $entry['exposesEmail'] );
		$this->assertFalse( $entry['exposesCursorOffset'] );
		$this->assertFalse( $entry['exposesSelection'] );
		$this->assertTrue( $entry['exposesSelectionPresence'] );
		$this->assertFalse( $entry['exposesRawSelectedText'] );
		$this->assertFalse( $entry['exposesRawContent'] );
		$this->assertFalse( $entry['rawSessionKeyIncluded'] );
		$this->assertArrayNotHasKey( 'userId', $entry );
		$this->assertArrayNotHasKey( 'userLogin', $entry );
		$this->assertArrayNotHasKey( 'email', $entry );
		$this->assertArrayNotHasKey( 'actorHash', $entry );
		$this->assertArrayNotHasKey( 'actor_hash', $entry );
		$this->assertArrayNotHasKey( 'sessionKeyHash', $entry );
		$this->assertArrayNotHasKey( 'session_key_hash', $entry );
		$this->assertSame( 'de_rtc_presence_storage', $contract['current_snapshot_source'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'userId',
				'user_id',
				'userLogin',
				'user_login',
				'email',
				'user_email',
				'actorHash',
				'actor_hash',
				'sessionKeyHash',
				'session_key_hash',
				'cursorOffset',
				'cursor_offset',
				'selection',
				'rawContent',
				'raw_content',
			)
		);
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_generate_presence_response_local_roster_key_prefix
	 * @covers ::wp_de_rtc_get_presence_response_local_roster_key
	 */
	public function test_presence_snapshot_uses_response_local_opaque_keys_for_storage_rows() {
		$post_id     = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC opaque storage presence key post',
				'post_content' => '<!-- wp:paragraph --><p>Opaque storage presence keys.</p><!-- /wp:paragraph -->',
			)
		);
		$session_key = 'opaque-storage-session';

		wp_de_rtc_install_presence_table();
		wp_set_current_user( self::$author_user_id );

		$heartbeat_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$heartbeat_request->set_param( 'session_key', $session_key );
		$this->assertSame( 200, rest_get_server()->dispatch( $heartbeat_request )->get_status() );

		wp_set_current_user( self::$admin_user_id );

		$before_post       = get_post( $post_id );
		$before_revisions  = $this->get_post_revisions( $post_id );
		$before_row_count  = $this->get_presence_row_count_for_post( $post_id );
		$session_key_hash  = hash_hmac( 'sha256', $post_id . ':' . $session_key, wp_salt( 'nonce' ) );
		$forbidden_values  = array( $session_key, $session_key_hash, substr( $session_key_hash, 0, 12 ) );
		$first_response    = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' ) );
		$second_response   = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' ) );
		$first_data        = $first_response->get_data();
		$second_data       = $second_response->get_data();
		$first_entry       = $first_data['presence_roster']['entries'][0];
		$second_entry      = $second_data['presence_roster']['entries'][0];

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( 200, $second_response->get_status() );
		$this->assertSame( 'de_rtc_presence_storage', $first_entry['source'] );
		$this->assertSame( 'de_rtc_presence_storage', $second_entry['source'] );
		$this->assert_presence_roster_key_is_response_local_opaque( $first_entry['key'], $forbidden_values );
		$this->assert_presence_roster_key_is_response_local_opaque( $second_entry['key'], $forbidden_values );
		$this->assertNotSame( $first_entry['key'], $second_entry['key'] );
		$this->assert_payload_omits_fragments( $first_data, $forbidden_values );
		$this->assert_payload_omits_fragments( $second_data, $forbidden_values );
		$this->assertFalse( $first_data['records_presence_heartbeat'] );
		$this->assertFalse( $second_data['records_presence_heartbeat'] );
		$this->assertFalse( $first_data['saves_post'] );
		$this->assertFalse( $second_data['saves_post'] );
		$this->assertFalse( $first_data['mutates_post_content'] );
		$this->assertFalse( $second_data['mutates_post_content'] );
		$this->assertFalse( $first_data['changes_post_lock'] );
		$this->assertFalse( $second_data['changes_post_lock'] );
		$this->assertFalse( $first_data['presence_roster']['claimsAbsence'] );
		$this->assertFalse( $second_data['presence_roster']['claimsAbsence'] );
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_storage_entries
	 * @covers ::wp_de_rtc_get_presence_storage_row_freshness
	 */
	public function test_presence_snapshot_uses_current_session_key_to_show_same_user_other_tab_without_showing_this_tab() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC same-user other-tab presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Same user other tab.</p><!-- /wp:paragraph -->',
			)
		);

		wp_de_rtc_install_presence_table();
		wp_set_current_user( self::$author_user_id );

		$current_tab_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$current_tab_request->set_param( 'session_key', 'author-current-tab-session' );
		$this->assertSame( 200, rest_get_server()->dispatch( $current_tab_request )->get_status() );

		$other_tab_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$other_tab_request->set_param( 'session_key', 'author-other-tab-session' );
		$this->assertSame( 200, rest_get_server()->dispatch( $other_tab_request )->get_status() );

		wp_set_current_user( self::$admin_user_id );

		$admin_tab_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence/heartbeat' );
		$admin_tab_request->set_param( 'session_key', 'admin-remote-tab-session' );
		$this->assertSame( 200, rest_get_server()->dispatch( $admin_tab_request )->get_status() );

		wp_set_current_user( self::$author_user_id );

		$before_post       = get_post( $post_id );
		$before_revisions  = $this->get_post_revisions( $post_id );
		$before_row_count  = $this->get_presence_row_count_for_post( $post_id );
		$snapshot_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );
		$snapshot_request->set_param( 'session_key', 'author-current-tab-session' );
		$snapshot_response = rest_get_server()->dispatch( $snapshot_request );
		$data              = $snapshot_response->get_data();
		$roster            = $data['presence_roster'];
		$contract          = $data['presence_read_contract'];
		$relationships     = wp_list_pluck( $roster['entries'], 'relationship' );
		$same_user_entry   = null;
		$remote_entry      = null;

		foreach ( $roster['entries'] as $entry ) {
			if ( 'same_user_other_tab' === $entry['relationship'] ) {
				$same_user_entry = $entry;
			} elseif ( 'other_user' === $entry['relationship'] ) {
				$remote_entry = $entry;
			}
		}

		$this->assertSame( 200, $snapshot_response->get_status() );
		$this->assertSame( 'presence_roster_snapshot', $data['result'] );
		$this->assertSame( 'post_presence_roster', $data['rest_route'] );
		$this->assertTrue( $data['accepts_current_session_key'] );
		$this->assertTrue( $data['current_session_key_compared_by_hash'] );
		$this->assertFalse( $data['raw_session_key_included'] );
		$this->assertSame( 'active', $roster['status'] );
		$this->assertSame( 'de_rtc_presence_storage', $roster['source'] );
		$this->assertSame( 2, $roster['visibleCount'] );
		$this->assertSame( 2, $roster['totalKnownCount'] );
		$this->assertSame( 3, $before_row_count );
		$this->assertContains( 'same_user_other_tab', $relationships );
		$this->assertContains( 'other_user', $relationships );
		$this->assertSame( 'self', $same_user_entry['identityVisibility'] );
		$this->assertSame( 'current', $same_user_entry['freshness'] );
		$this->assertFalse( $same_user_entry['exposesUserId'] );
		$this->assertFalse( $same_user_entry['rawSessionKeyIncluded'] );
		$this->assertSame( 'named', $remote_entry['identityVisibility'] );
		$this->assertSame( 'current', $remote_entry['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $contract['current_snapshot_source'] );
		$this->assertTrue( $contract['session_identity']['accepts_current_session_key'] );
		$this->assertTrue( $contract['session_identity']['current_session_key_compared_by_hash'] );
		$this->assertFalse( $contract['session_identity']['raw_session_key_included'] );
		$this->assertSame( 'same_user_other_tab', $contract['session_identity']['same_actor_other_session_relationship'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'session_key',
				'sessionKey',
				'sessionKeyHash',
				'session_key_hash',
				'userId',
				'user_id',
				'userLogin',
				'user_login',
				'email',
				'user_email',
				'actorHash',
				'actor_hash',
				'cursorOffset',
				'cursor_offset',
				'selection',
				'rawContent',
				'raw_content',
			)
		);
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_get_presence_storage_row_freshness
	 */
	public function test_presence_snapshot_derives_freshness_and_expired_count_from_storage_timestamps_without_cleanup() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC timestamp-derived presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Timestamp-derived presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$current_time_gmt = '2026-05-15 12:00:00';
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_de_rtc_install_presence_table();
		$this->insert_presence_row_for_post(
			$post_id,
			array(
				'session_key'    => 'timestamp-current-session',
				'actor_key'      => 'timestamp-current-actor',
				'display_name'   => 'Current Timestamp',
				'freshness'      => 'stale',
				'last_seen_gmt'  => '2026-05-15 11:59:30',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			)
		);
		$this->insert_presence_row_for_post(
			$post_id,
			array(
				'session_key'    => 'timestamp-recent-session',
				'actor_key'      => 'timestamp-recent-actor',
				'display_name'   => 'Recent Timestamp',
				'freshness'      => 'active',
				'last_seen_gmt'  => '2026-05-15 11:57:00',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			)
		);
		$this->insert_presence_row_for_post(
			$post_id,
			array(
				'session_key'    => 'timestamp-expired-session',
				'actor_key'      => 'timestamp-expired-actor',
				'display_name'   => 'Expired Timestamp',
				'freshness'      => 'active',
				'last_seen_gmt'  => '2026-05-15 11:56:00',
				'expires_at_gmt' => '2026-05-15 11:59:59',
			)
		);

		$before_row_count = $this->get_presence_row_count_for_post( $post_id );
		$snapshot         = wp_de_rtc_get_post_presence_read_snapshot(
			$post_id,
			array(
				'current_time_gmt' => $current_time_gmt,
				'host_profile'     => 'cheap_shared_host',
			)
		);
		$roster           = $snapshot['presence_roster'];
		$contract         = $snapshot['presence_read_contract'];
		$entries_by_name  = array();

		foreach ( $roster['entries'] as $entry ) {
			$entries_by_name[ $entry['displayName'] ] = $entry;
		}

		$this->assertSame( 3, $before_row_count );
		$this->assertSame( 'presence_roster_snapshot', $snapshot['result'] );
		$this->assertSame( 'recent', $roster['status'] );
		$this->assertSame( 'recent', $roster['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $roster['source'] );
		$this->assertTrue( $roster['storageBacked'] );
		$this->assertSame( 2, $roster['visibleCount'] );
		$this->assertSame( 3, $roster['totalKnownCount'] );
		$this->assertSame( 1, $roster['expiredCount'] );
		$this->assertArrayHasKey( 'Current Timestamp', $entries_by_name );
		$this->assertArrayHasKey( 'Recent Timestamp', $entries_by_name );
		$this->assertArrayNotHasKey( 'Expired Timestamp', $entries_by_name );
		$this->assertSame( 'current', $entries_by_name['Current Timestamp']['freshness'] );
		$this->assertSame( 'recent', $entries_by_name['Recent Timestamp']['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $contract['current_snapshot_source'] );
		$this->assertSame( 1, $contract['freshness_model']['expired_count'] );
		$this->assertSame( 120, $contract['freshness_model']['current_after_seconds'] );
		$this->assertSame( 600, $contract['freshness_model']['expires_after_seconds'] );
		$this->assertContains( 'expiredCount', $contract['schema_fields']['roster'] );
		$this->assertFalse( $snapshot['records_presence_heartbeat'] );
		$this->assertFalse( $snapshot['saves_post'] );
		$this->assertFalse( $snapshot['mutates_post_content'] );
		$this->assertFalse( $snapshot['creates_revision'] );
		$this->assertFalse( $snapshot['changes_post_lock'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_get_presence_storage_row_freshness
	 */
	public function test_presence_snapshot_reports_hidden_count_when_storage_limit_truncates_rows_without_leaking_names() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC bounded presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Bounded presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$current_time_gmt = '2026-05-15 12:00:00';
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_de_rtc_install_presence_table();

		$rows = array(
			array(
				'session_key'    => 'bounded-visible-alpha',
				'actor_key'      => 'bounded-visible-alpha-actor',
				'display_name'   => 'Visible Alpha',
				'last_seen_gmt'  => '2026-05-15 11:59:50',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			),
			array(
				'session_key'    => 'bounded-visible-beta',
				'actor_key'      => 'bounded-visible-beta-actor',
				'display_name'   => 'Visible Beta',
				'last_seen_gmt'  => '2026-05-15 11:59:40',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			),
			array(
				'session_key'    => 'bounded-visible-gamma',
				'actor_key'      => 'bounded-visible-gamma-actor',
				'display_name'   => 'Visible Gamma',
				'last_seen_gmt'  => '2026-05-15 11:57:30',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			),
			array(
				'session_key'    => 'bounded-hidden-delta',
				'actor_key'      => 'bounded-hidden-delta-actor',
				'display_name'   => 'Hidden Delta',
				'last_seen_gmt'  => '2026-05-15 11:57:20',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			),
			array(
				'session_key'    => 'bounded-hidden-epsilon',
				'actor_key'      => 'bounded-hidden-epsilon-actor',
				'display_name'   => 'Hidden Epsilon',
				'last_seen_gmt'  => '2026-05-15 11:57:10',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			),
			array(
				'session_key'    => 'bounded-expired-zeta',
				'actor_key'      => 'bounded-expired-zeta-actor',
				'display_name'   => 'Expired Zeta',
				'last_seen_gmt'  => '2026-05-15 11:56:00',
				'expires_at_gmt' => '2026-05-15 11:59:59',
			),
		);

		foreach ( $rows as $row ) {
			$this->insert_presence_row_for_post( $post_id, $row );
		}

		$before_row_count = $this->get_presence_row_count_for_post( $post_id );
		$snapshot         = wp_de_rtc_get_post_presence_read_snapshot(
			$post_id,
			array(
				'current_time_gmt' => $current_time_gmt,
				'host_profile'     => 'cheap_shared_host',
				'limit'            => 3,
			)
		);
		$roster           = $snapshot['presence_roster'];
		$contract         = $snapshot['presence_read_contract'];
		$payload          = wp_json_encode( $snapshot );
		$visible_names    = wp_list_pluck( $roster['entries'], 'displayName' );

		$this->assertSame( 6, $before_row_count );
		$this->assertSame( 'presence_roster_snapshot', $snapshot['result'] );
		$this->assertSame( 'recent', $roster['status'] );
		$this->assertSame( 'recent', $roster['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $roster['source'] );
		$this->assertTrue( $roster['storageBacked'] );
		$this->assertSame( 3, $roster['visibleCount'] );
		$this->assertSame( 6, $roster['totalKnownCount'] );
		$this->assertSame( 2, $roster['hiddenCount'] );
		$this->assertSame( 1, $roster['expiredCount'] );
		$this->assertSame( array( 'Visible Alpha', 'Visible Beta', 'Visible Gamma' ), $visible_names );
		$this->assertSame( 2, $contract['freshness_model']['hidden_count'] );
		$this->assertSame( 1, $contract['freshness_model']['expired_count'] );
		$this->assertFalse( $snapshot['records_presence_heartbeat'] );
		$this->assertFalse( $snapshot['saves_post'] );
		$this->assertFalse( $snapshot['mutates_post_content'] );
		$this->assertFalse( $snapshot['creates_revision'] );
		$this->assertFalse( $snapshot['changes_post_lock'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertStringNotContainsString( 'Hidden Delta', $payload );
		$this->assertStringNotContainsString( 'Hidden Epsilon', $payload );
		$this->assertStringNotContainsString( 'Expired Zeta', $payload );
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_storage_snapshot
	 * @covers ::wp_de_rtc_get_presence_storage_row_freshness
	 */
	public function test_presence_snapshot_reports_expired_only_storage_evidence_without_absence_claim_or_cleanup() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC expired-only presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Expired-only presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$now              = time();
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );

		wp_de_rtc_install_presence_table();
		$this->insert_presence_row_for_post(
			$post_id,
			array(
				'session_key'    => 'expired-only-session',
				'actor_key'      => 'expired-only-actor',
				'display_name'   => 'Expired Only Editor',
				'freshness'      => 'active',
				'last_seen_gmt'  => gmdate( 'Y-m-d H:i:s', $now - 900 ),
				'expires_at_gmt' => gmdate( 'Y-m-d H:i:s', $now - 60 ),
			)
		);
		$before_row_count = $this->get_presence_row_count_for_post( $post_id );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$roster   = $data['presence_roster'];
		$contract = $data['presence_read_contract'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_roster_snapshot', $data['result'] );
		$this->assertSame( 'post_presence_roster', $data['rest_route'] );
		$this->assertSame( 'recent', $roster['status'] );
		$this->assertSame( 'recent', $roster['freshness'] );
		$this->assertSame( 'de_rtc_presence_storage', $roster['source'] );
		$this->assertTrue( $roster['storageBacked'] );
		$this->assertSame( 0, $roster['visibleCount'] );
		$this->assertSame( 1, $roster['totalKnownCount'] );
		$this->assertSame( 1, $roster['expiredCount'] );
		$this->assertSame( array(), $roster['entries'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertSame( 'de_rtc_presence_storage', $contract['current_snapshot_source'] );
		$this->assertSame( 1, $contract['freshness_model']['expired_count'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertStringNotContainsString( 'Expired Only Editor', wp_json_encode( $data ) );
		$this->assertSame( $before_row_count, $this->get_presence_row_count_for_post( $post_id ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_rest_presence_permissions_check
	 * @covers ::wp_de_rtc_rest_presence_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_presence_request_rest_base
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_read_contract
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_lock_presence_entry
	 */
	public function test_presence_snapshot_reads_known_other_editor_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );

		update_post_meta( $post_id, '_edit_lock', time() . ':' . self::$author_user_id );

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$roster          = $data['presence_roster'];
		$contract        = $data['presence_read_contract'];
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'presence_roster_snapshot', $data['result'] );
		$this->assertSame( 'post_presence_roster', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( 'posts', $data['post_type_rest_base'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['enables_repeated_client_refresh'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( 'recent', $roster['status'] );
		$this->assertSame( 'recent', $roster['freshness'] );
		$this->assertSame( 1, $roster['visibleCount'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertSame( 'Mira Presence', $roster['entries'][0]['displayName'] );
		$this->assertSame( 'other_user', $roster['entries'][0]['relationship'] );
		$this->assertFalse( $roster['entries'][0]['exposesUserId'] );
		$this->assertArrayHasKey( 'presenceUpdatedAtGmt', $roster['entries'][0] );
		$this->assertFalse( $roster['entries'][0]['documentState']['available'] );
		$this->assertFalse(
			$roster['entries'][0]['documentState']['authoritativeForSave']
		);
		$this->assertFalse( $roster['entries'][0]['documentState']['claimsSaved'] );
		$this->assertFalse(
			$roster['entries'][0]['documentState']['exposesRawContent']
		);
		$this->assertArrayNotHasKey( 'userId', $roster['entries'][0] );
		$this->assertSame( 'de-rtc-presence-roster-v2', $contract['schema'] );
		$this->assertSame( 'de_rtc_presence_read_snapshot', $contract['source'] );
		$this->assertSame( 'wordpress_post_lock_snapshot', $contract['current_snapshot_source'] );
		$this->assertSame( 'GET', $contract['method'] );
		$this->assertSame( '/wp/v2/posts/' . $post_id . '/distributed-editing/presence', $contract['route'] );
		$this->assertTrue( $contract['requires_edit_post'] );
		$this->assertTrue( $contract['requires_feature_enabled'] );
		$this->assertTrue( $contract['read_only'] );
		$this->assertFalse( $contract['writes_presence'] );
		$this->assertFalse( $contract['records_presence_heartbeat'] );
		$this->assertFalse( $contract['enables_repeated_client_refresh'] );
		$this->assertFalse( $contract['freshness_model']['claims_absence'] );
		$this->assertSame( 30, $contract['cheap_host_polling_guidance']['suggested_polling_interval_seconds'] );
		$this->assertSame( 120, $contract['cheap_host_polling_guidance']['cheap_host_polling_interval_seconds'] );
		$this->assertFalse( $contract['cheap_host_polling_guidance']['repeated_client_refresh_enabled_now'] );
		$this->assertFalse( $contract['privacy_filters']['raw_content_included'] );
		$this->assertFalse( $contract['privacy_filters']['exposes_user_ids'] );
		$this->assertFalse( $contract['privacy_filters']['exposes_cursor_offset'] );
		$this->assertFalse( $contract['privacy_filters']['exposes_selection'] );
		$this->assertTrue( $contract['privacy_filters']['exposes_selection_presence'] );
		$this->assertFalse( $contract['privacy_filters']['exposes_raw_selected_text'] );
		$this->assert_payload_omits_keys(
			$data,
			array(
				'userId',
				'user_id',
				'userLogin',
				'user_login',
				'email',
				'user_email',
				'cursorOffset',
				'cursor_offset',
				'selection',
				'rawContent',
				'raw_content',
			)
		);
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_lock_presence_entry
	 * @covers ::wp_de_rtc_generate_presence_response_local_roster_key_prefix
	 * @covers ::wp_de_rtc_get_presence_response_local_roster_key
	 */
	public function test_presence_snapshot_uses_response_local_opaque_keys_for_post_lock_fallback() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC opaque post-lock presence key post',
				'post_content' => '<!-- wp:paragraph --><p>Opaque post-lock presence keys.</p><!-- /wp:paragraph -->',
			)
		);

		update_post_meta( $post_id, '_edit_lock', time() . ':' . self::$author_user_id );

		$before_post         = get_post( $post_id );
		$before_revisions    = $this->get_post_revisions( $post_id );
		$before_lock         = get_post_meta( $post_id, '_edit_lock', true );
		$stable_key_fragment = substr( wp_hash( $post_id . ':' . self::$author_user_id . ':de-rtc-presence' ), 0, 12 );
		$forbidden_values    = array( 'wp-post-lock-', $stable_key_fragment );
		$first_response      = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' ) );
		$second_response     = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' ) );
		$first_data          = $first_response->get_data();
		$second_data         = $second_response->get_data();
		$first_entry         = $first_data['presence_roster']['entries'][0];
		$second_entry        = $second_data['presence_roster']['entries'][0];

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( 200, $second_response->get_status() );
		$this->assertSame( 'wordpress_post_lock_snapshot', $first_entry['source'] );
		$this->assertSame( 'wordpress_post_lock_snapshot', $second_entry['source'] );
		$this->assert_presence_roster_key_is_response_local_opaque( $first_entry['key'], $forbidden_values );
		$this->assert_presence_roster_key_is_response_local_opaque( $second_entry['key'], $forbidden_values );
		$this->assertNotSame( $first_entry['key'], $second_entry['key'] );
		$this->assert_payload_omits_fragments( $first_data, $forbidden_values );
		$this->assert_payload_omits_fragments( $second_data, $forbidden_values );
		$this->assertFalse( $first_data['records_presence_heartbeat'] );
		$this->assertFalse( $second_data['records_presence_heartbeat'] );
		$this->assertFalse( $first_data['saves_post'] );
		$this->assertFalse( $second_data['saves_post'] );
		$this->assertFalse( $first_data['mutates_post_content'] );
		$this->assertFalse( $second_data['mutates_post_content'] );
		$this->assertFalse( $first_data['changes_post_lock'] );
		$this->assertFalse( $second_data['changes_post_lock'] );
		$this->assertFalse( $first_data['presence_roster']['claimsAbsence'] );
		$this->assertFalse( $second_data['presence_roster']['claimsAbsence'] );
		$this->assertSame( $before_lock, get_post_meta( $post_id, '_edit_lock', true ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_endpoint
	 * @covers ::wp_de_rtc_get_post_presence_read_snapshot
	 */
	public function test_presence_snapshot_empty_roster_does_not_claim_absence() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC empty presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Empty presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$roster   = $data['presence_roster'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'empty', $roster['status'] );
		$this->assertSame( 0, $roster['visibleCount'] );
		$this->assertSame( 0, $roster['totalKnownCount'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertFalse( $data['enables_repeated_client_refresh'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_presence_snapshot_requires_feature_enablement_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );

		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( 'post_presence_roster', $data['rest_route'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['records_presence_heartbeat'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_permissions_check
	 */
	public function test_presence_snapshot_requires_edit_post_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_title'   => 'DE-RTC permission presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Permission presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/presence' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_presence_permissions_check
	 * @covers ::wp_de_rtc_rest_presence_request_matches_post_type
	 */
	public function test_presence_snapshot_requires_matching_post_type_route_without_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC route presence snapshot post',
				'post_content' => '<!-- wp:paragraph --><p>Route presence endpoint.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $post_id . '/distributed-editing/presence' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * Drops the test presence table when it exists.
	 */
	private function drop_presence_table() {
		global $wpdb;

		if ( ! $this->presence_table_name ) {
			return;
		}

		$table_sql = '`' . str_replace( '`', '``', $this->presence_table_name ) . '`';
		$wpdb->query( "DROP TABLE IF EXISTS $table_sql" );
	}

	/**
	 * Returns the first presence row recorded for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Presence row, or null when none exists.
	 */
	private function get_presence_row_for_post( $post_id ) {
		global $wpdb;

		$table_sql = '`' . str_replace( '`', '``', $this->presence_table_name ) . '`';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_sql WHERE post_id = %d ORDER BY presence_id ASC LIMIT 1",
				$post_id
			),
			ARRAY_A
		);
	}

	/**
	 * Inserts a presence storage row for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Presence row arguments.
	 */
	private function insert_presence_row_for_post( $post_id, $args ) {
		global $wpdb;

		$args      = wp_parse_args(
			$args,
			array(
				'session_key'    => 'test-session',
				'actor_key'      => 'test-actor',
				'display_name'   => 'Presence Test',
				'freshness'      => 'active',
				'can_edit_post'  => 1,
				'can_publish_post' => 0,
				'can_save_dangerous_html' => 0,
				'last_seen_gmt'  => '2026-05-15 12:00:00',
				'expires_at_gmt' => '2026-05-15 12:10:00',
			)
		);
		$table_sql = '`' . str_replace( '`', '``', $this->presence_table_name ) . '`';

		$this->assertNotFalse(
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table_sql ( post_id, session_key_hash, actor_hash, display_name, freshness, can_edit_post, can_publish_post, can_save_dangerous_html, last_seen_gmt, expires_at_gmt, created_at_gmt, updated_at_gmt ) VALUES ( %d, %s, %s, %s, %s, %d, %d, %d, %s, %s, %s, %s )",
					$post_id,
					hash_hmac( 'sha256', $post_id . ':' . $args['session_key'], wp_salt( 'nonce' ) ),
					hash_hmac( 'sha256', $args['actor_key'], wp_salt( 'auth' ) ),
					$args['display_name'],
					$args['freshness'],
					$args['can_edit_post'],
					$args['can_publish_post'],
					$args['can_save_dangerous_html'],
					$args['last_seen_gmt'],
					$args['expires_at_gmt'],
					$args['last_seen_gmt'],
					$args['last_seen_gmt']
				)
			)
		);
	}

	/**
	 * Returns the number of presence rows recorded for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Presence row count.
	 */
	private function get_presence_row_count_for_post( $post_id ) {
		global $wpdb;

		$table_sql = '`' . str_replace( '`', '``', $this->presence_table_name ) . '`';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_sql WHERE post_id = %d",
				$post_id
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

	/**
	 * Asserts that a roster key is response-local and does not expose stable source material.
	 *
	 * @param string   $key                 Presence roster key.
	 * @param string[] $forbidden_fragments Stable fragments that must not appear in the key.
	 */
	private function assert_presence_roster_key_is_response_local_opaque( $key, $forbidden_fragments ) {
		$this->assertIsString( $key );
		$this->assertStringStartsWith( 'de-rtc-presence-entry-', $key );

		foreach ( $forbidden_fragments as $fragment ) {
			$fragment = (string) $fragment;

			if ( '' === $fragment ) {
				continue;
			}

			$this->assertStringNotContainsString( $fragment, $key );
		}
	}

	/**
	 * Asserts that stable source fragments are absent from an encoded payload.
	 *
	 * @param mixed    $payload             Payload value.
	 * @param string[] $forbidden_fragments Stable fragments that must not appear in the payload.
	 */
	private function assert_payload_omits_fragments( $payload, $forbidden_fragments ) {
		$encoded_payload = wp_json_encode( $payload );

		$this->assertIsString( $encoded_payload );

		foreach ( $forbidden_fragments as $fragment ) {
			$fragment = (string) $fragment;

			if ( '' === $fragment ) {
				continue;
			}

			$this->assertStringNotContainsString( $fragment, $encoded_payload );
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
}
