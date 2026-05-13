<?php
/**
 * Tests for the Distributed Editing REST save-path stale-base probe.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Save_Stale_Base extends WP_Test_REST_TestCase {

	protected static $admin_user_id;

	protected $server;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
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
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 * @covers ::wp_de_rtc_is_rest_save_stale_base_probe_request
	 * @covers ::wp_de_rtc_rest_save_probe_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_save_probe_request_rest_base
	 * @covers ::wp_de_rtc_get_stale_base_rejection_error
	 */
	public function test_post_update_stale_base_probe_rejects_before_persistence() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Save path current content.</p><!-- /wp:paragraph -->',
			6
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC save path stale base post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content'                  => '<!-- wp:paragraph --><p>Rejected save path update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe'  => true,
				'client_base_version'      => '4',
				'pending_change_count'     => 2,
				'remote_change_count'      => 3,
				'can_attempt_local_rebase' => true,
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
		$this->assertSame( 'post_save_stale_base_probe', $data['rest_route'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'post', $data['permission_contract']['post_type'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_post_update_without_stale_base_probe_saves_normally() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC normal save path post',
				'post_content' => '<!-- wp:paragraph --><p>Normal save current content.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content' => '<!-- wp:paragraph --><p>Normal save updated content.</p><!-- /wp:paragraph -->',
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<!-- wp:paragraph --><p>Normal save updated content.</p><!-- /wp:paragraph -->', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_post_update_retry_save_proof_fields_without_stale_base_probe_remains_normal_rest_save() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry proof normal save current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry proof normal save path post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry proof normal REST save content.</p><!-- /wp:paragraph -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array_merge(
				$this->get_retry_save_proof_fields( $proposed_content, 7 ),
				array(
					'content' => $proposed_content,
				)
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $proposed_content, $after_post->post_content );
		$this->assertNull( $parsed['sync_meta'] );
		$this->assertNull( $parsed['sync_meta_format'] );
		$this->assertNull( $parsed['sync_meta_position'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_post_update_stale_base_probe_requires_feature_enablement() {
		update_option( 'wp_de_rtc_enabled', false );

		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled save path post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled save path current content.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content'                 => '<!-- wp:paragraph --><p>Rejected disabled update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe' => true,
				'client_base_version'     => '4',
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 * @covers ::wp_de_rtc_rest_save_probe_request_matches_post_type
	 */
	public function test_page_update_stale_base_probe_rejects_before_persistence() {
		$current_content = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Save path page current content.</p><!-- /wp:paragraph -->',
			9
		);
		$page_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC save path stale base page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$before_page     = get_post( $page_id );
		$request         = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
		$request->set_body_params(
			array(
				'content'                 => '<!-- wp:paragraph --><p>Rejected page save path update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe' => true,
				'client_base_version'     => '7',
				'pending_change_count'    => 1,
				'remote_change_count'     => 2,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$error      = $response->as_error();
		$data       = $error->get_error_data( 'stale_base_version_rejected' );
		$after_page = get_post( $page_id );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_save_stale_base_probe', $data['rest_route'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '9', $data['server_version'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_page->post_content, $after_page->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_page_update_retry_save_proof_fields_without_stale_base_probe_remains_normal_rest_save() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry proof normal page current content.</p><!-- /wp:paragraph -->',
			13
		);
		$page_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry proof normal save path page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry proof normal REST page content.</p><!-- /wp:paragraph -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
		$request->set_body_params(
			array_merge(
				$this->get_retry_save_proof_fields( $proposed_content, 13 ),
				array(
					'content' => $proposed_content,
				)
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_page = get_post( $page_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_page->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $proposed_content, $after_page->post_content );
		$this->assertNull( $parsed['sync_meta'] );
		$this->assertNull( $parsed['sync_meta_format'] );
		$this->assertNull( $parsed['sync_meta_position'] );
	}

	/**
	 * @covers ::WP_REST_Autosaves_Controller::create_item
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 * @covers ::wp_de_rtc_get_rest_save_probe_response_route
	 */
	public function test_autosave_parent_draft_stale_base_probe_rejects_before_parent_update() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Autosave draft current content.</p><!-- /wp:paragraph -->',
			10
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'draft',
				'post_title'   => 'DE-RTC autosave draft stale base post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves' );
		$request->set_body_params(
			array(
				'id'                       => $post_id,
				'content'                  => '<!-- wp:paragraph --><p>Rejected autosave draft update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe'  => true,
				'client_base_version'      => '8',
				'pending_change_count'     => 1,
				'remote_change_count'      => 2,
				'can_attempt_local_rebase' => true,
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
		$this->assertSame( 'post_autosave_stale_base_probe', $data['rest_route'] );
		$this->assertSame( '8', $data['client_base_version'] );
		$this->assertSame( '10', $data['server_version'] );
		$this->assertSame( 1, $data['pending_change_count'] );
		$this->assertSame( 2, $data['remote_change_count'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['can_attempt_local_rebase'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::WP_REST_Autosaves_Controller::create_item
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_autosave_parent_draft_retry_save_proof_fields_without_stale_base_probe_updates_parent_normally() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Autosave retry proof draft current content.</p><!-- /wp:paragraph -->',
			14
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'draft',
				'post_title'   => 'DE-RTC autosave retry proof draft post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Autosave retry proof draft update.</p><!-- /wp:paragraph -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves' );
		$request->set_body_params(
			array_merge(
				$this->get_retry_save_proof_fields( $proposed_content, 14 ),
				array(
					'id'      => $post_id,
					'content' => $proposed_content,
					'title'   => 'DE-RTC autosave retry proof draft post updated',
				)
			)
		);

		$response      = rest_get_server()->dispatch( $request );
		$response_data = $response->get_data();
		$after_post    = get_post( $post_id );
		$parsed        = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $response_data['id'] );
		$this->assertSame( $proposed_content, $after_post->post_content );
		$this->assertSame( 'DE-RTC autosave retry proof draft post updated', $after_post->post_title );
		$this->assertFalse( wp_get_post_autosave( $post_id, self::$admin_user_id ) );
		$this->assertNull( $parsed['sync_meta'] );
		$this->assertNull( $parsed['sync_meta_format'] );
		$this->assertNull( $parsed['sync_meta_position'] );
	}

	/**
	 * @covers ::WP_REST_Autosaves_Controller::create_item
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_autosave_revision_stale_base_probe_rejects_before_revision_creation() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Autosave published current content.</p><!-- /wp:paragraph -->',
			12
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'publish',
				'post_title'   => 'DE-RTC autosave published stale base post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves' );
		$request->set_body_params(
			array(
				'id'                      => $post_id,
				'content'                 => '<!-- wp:paragraph --><p>Rejected autosave revision update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe' => true,
				'client_base_version'     => '11',
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
		$this->assertSame( 'post_autosave_stale_base_probe', $data['rest_route'] );
		$this->assertSame( '11', $data['client_base_version'] );
		$this->assertSame( '12', $data['server_version'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::WP_REST_Autosaves_Controller::create_item
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_autosave_revision_retry_save_proof_fields_without_stale_base_probe_creates_autosave_normally() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Autosave retry proof published current content.</p><!-- /wp:paragraph -->',
			15
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'publish',
				'post_title'   => 'DE-RTC autosave retry proof published post',
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
		$proposed_content = '<!-- wp:paragraph --><p>Autosave retry proof published update.</p><!-- /wp:paragraph -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves' );
		$request->set_body_params(
			array_merge(
				$this->get_retry_save_proof_fields( $proposed_content, 15 ),
				array(
					'id'      => $post_id,
					'content' => $proposed_content,
				)
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$response_data   = $response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$autosave        = wp_get_post_autosave( $post_id, self::$admin_user_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotSame( $post_id, $response_data['id'] );
		$this->assertSame( $post_id, $response_data['parent'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertInstanceOf( 'WP_Post', $autosave );

		$parsed_autosave = wp_de_rtc_parse_post_content_sync_meta( $autosave->post_content );

		$this->assertSame( $proposed_content, $autosave->post_content );
		$this->assertArrayHasKey( $autosave->ID, $after_revisions );
		$this->assertArrayNotHasKey( $autosave->ID, $before_revisions );
		$this->assertNull( $parsed_autosave['sync_meta'] );
		$this->assertNull( $parsed_autosave['sync_meta_format'] );
		$this->assertNull( $parsed_autosave['sync_meta_position'] );
	}

	/**
	 * Adds synthetic sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param int    $version Sync metadata version.
	 * @return string Content with sync metadata.
	 */
	private function add_sync_meta_to_content( $content, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content_with_sync_meta );

		return $content_with_sync_meta;
	}

	/**
	 * Returns retry-save proof-shaped fields for normal REST save requests.
	 *
	 * @param string $proposed_content Proposed post content.
	 * @param int    $version          Sync metadata version.
	 * @return array Retry-save proof-shaped request fields.
	 */
	private function get_retry_save_proof_fields( $proposed_content, $version ) {
		return array(
			'client_base_version'                  => (string) $version,
			'accepted_proof_server_version'        => (string) $version,
			'rebased_from_version'                 => (string) ( $version - 1 ),
			'pending_change_count'                 => 2,
			'proposed_post_content'                => $proposed_content,
			'proposed_post_content_hash'           => hash( 'sha256', $proposed_content ),
			'accepted_proof_saves_post'            => false,
			'accepted_proof_mutates_post_content' => false,
			'accepted_proof_creates_revision'      => false,
			'accepted_proof_claims_saved'          => false,
		);
	}
}
