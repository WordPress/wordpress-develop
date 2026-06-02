<?php
/**
 * Tests for the Distributed Editing retry-submit proof endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Retry_Submit extends WP_Test_REST_TestCase {

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
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
		delete_option( 'wp_de_rtc_enabled' );

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 */
	public function test_retry_submit_accepts_current_base_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry submit current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit post',
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
		$proposed_hash    = hash( 'sha256', 'synthetic retry submit content' );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'        => '7',
				'rebased_from_version'       => '4',
				'pending_change_count'       => 2,
				'proposed_post_content_hash' => $proposed_hash,
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

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $data['result'] );
		$this->assertTrue( $data['retry_submit_accepted'] );
		$this->assertSame( 'post_retry_submit', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['server_version'] );
		$this->assertSame( '4', $data['rebased_from_version'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertSame( $proposed_hash, $data['proposed_post_content_hash'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertTrue( $data['save_path_required'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'post', $data['permission_contract']['post_type'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @dataProvider data_supported_sync_meta_shapes
	 *
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_retry_submit_accepts_current_base_from_supported_sync_meta_shapes_without_mutating( $shape ) {
		$current_content  = $this->add_sync_meta_to_content_with_shape(
			'<!-- wp:paragraph --><p>Retry submit shaped current content.</p><!-- /wp:paragraph -->',
			7,
			$shape
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit ' . $shape . ' sync meta post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'  => '7',
				'rebased_from_version' => '7',
				'pending_change_count' => 1,
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

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $data['result'] );
		$this->assertTrue( $data['retry_submit_accepted'] );
		$this->assertSame( 'post_retry_submit', $data['rest_route'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '7', $data['server_version'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_get_repaired_automerge_current_post_snapshot
	 */
	public function test_retry_submit_accepts_read_only_repaired_missing_sync_meta_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>Retry submit missing sync meta.</p><!-- /wp:paragraph -->';
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit missing sync meta post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'        => '1',
				'rebased_from_version'       => '1',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => hash( 'sha256', 'retry submit repaired proposed content' ),
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

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $data['result'] );
		$this->assertTrue( $data['retry_submit_accepted'] );
		$this->assertSame( 'post_retry_submit', $data['rest_route'] );
		$this->assertSame( '1', $data['client_base_version'] );
		$this->assertSame( '1', $data['server_version'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_retry_submit_returns_current_sync_meta_parser_error_without_mutating() {
		$script           = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array(
				'version' => 7,
			)
		);
		$current_content  = '<!-- wp:paragraph --><p>Retry submit malformed current content.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>' . $script . '</p><!-- /wp:paragraph -->';
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit malformed current meta post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'  => '7',
				'rebased_from_version' => '7',
				'pending_change_count' => 1,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'sync_meta_not_at_content_edge', $data['detail'] );
		$this->assertSame( 1, $data['sync_meta_script_count'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	public function data_supported_sync_meta_shapes() {
		return array(
			'raw'               => array( 'raw' ),
			'paragraph-wrapped' => array( 'paragraph-wrapped' ),
			'freeform-wrapped'  => array( 'freeform-wrapped' ),
		);
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 */
	public function test_retry_submit_rejects_stale_base_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry submit stale current content.</p><!-- /wp:paragraph -->',
			9
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit stale post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'  => '7',
				'rebased_from_version' => '4',
				'pending_change_count' => 1,
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
		$this->assertSame( 'post_retry_submit_stale_base', $data['rest_route'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '9', $data['server_version'] );
		$this->assertSame( 1, $data['pending_change_count'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['can_attempt_local_rebase'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_submit_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_retry_submit_request_rest_base
	 */
	public function test_retry_submit_supports_pages_without_mutating() {
		$current_content = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry submit page current content.</p><!-- /wp:paragraph -->',
			11
		);
		$page_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$before_page     = get_post( $page_id );
		$request         = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version' => '11',
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_page = get_post( $page_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $data['result'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertSame( $before_page->post_content, $after_page->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_retry_submit_requires_site_enablement_without_mutating() {
		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Disabled retry submit.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled retry submit post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'        => '7',
				'proposed_post_content_hash' => hash( 'sha256', 'disabled site retry submit content' ),
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
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_retry_submit_requires_post_filter_enablement_without_mutating() {
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );

		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Filtered retry submit.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC filtered retry submit post',
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
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params(
			array(
				'client_base_version'        => '7',
				'proposed_post_content_hash' => hash( 'sha256', 'filtered retry submit content' ),
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
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_submit_request_matches_post_type
	 */
	public function test_retry_submit_requires_matching_post_type_rest_base() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit route mismatch post',
				'post_content' => '<!-- wp:paragraph --><p>Retry submit route mismatch.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_param( 'client_base_version', '7' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_permissions_check
	 */
	public function test_retry_submit_requires_edit_post_capability() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry submit permission post',
				'post_content' => '<!-- wp:paragraph --><p>Retry submit permission.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_param( 'client_base_version', '7' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
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
	 * Adds synthetic paragraph-wrapped sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param int    $version Sync metadata version.
	 * @return string Content with paragraph-wrapped sync metadata.
	 */
	private function add_wrapped_sync_meta_to_content( $content, $version ) {
		return $this->add_sync_meta_to_content_with_shape( $content, $version, 'paragraph-wrapped' );
	}

	/**
	 * Adds synthetic sync metadata to content using one of the supported stored shapes.
	 *
	 * @param string $content Post content.
	 * @param int    $version Sync metadata version.
	 * @param string $shape   Stored metadata shape.
	 * @return string Content with sync metadata.
	 */
	private function add_sync_meta_to_content_with_shape( $content, $version, $shape ) {
		if ( 'raw' === $shape ) {
			return $this->add_sync_meta_to_content( $content, $version );
		}

		$script = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $script );

		if ( 'paragraph-wrapped' === $shape ) {
			return $content . "\n\n" . '<p>' . $script . '</p>';
		}

		$this->assertSame( 'freeform-wrapped', $shape );

		return $content . "\n\n" . '<!-- wp:freeform --><p>' . $script . '</p><!-- /wp:freeform -->';
	}

	/**
	 * Asserts that a rejected retry-submit request did not mutate content or revisions.
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
}
