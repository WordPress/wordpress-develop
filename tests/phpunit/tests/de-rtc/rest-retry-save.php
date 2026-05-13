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
	 * Adds synthetic sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param int    $version Sync metadata version.
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
