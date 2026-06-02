<?php
/**
 * Tests for the Distributed Editing document history endpoints.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_History extends WP_Test_REST_TestCase {

	protected static $admin_user_id;
	protected static $subscriber_user_id;

	protected $server;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id      = $factory->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Mira History',
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
	public function test_history_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/history', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/history', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/history/plan', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/history/plan', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_history_endpoint
	 * @covers ::wp_de_rtc_rest_history_permissions_check
	 * @covers ::wp_de_rtc_get_post_history_timeline
	 * @covers ::wp_de_rtc_get_history_row_from_post
	 */
	public function test_history_endpoint_returns_renderable_revision_timeline_without_mutating() {
		$previous_content = '<!-- wp:paragraph --><p>Previous history content.</p><!-- /wp:paragraph -->';
		$current_content  = '<!-- wp:paragraph --><p>Current history content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_history_post( $current_content, '2' );
		$revision_id      = $this->insert_revision(
			$post_id,
			$this->add_sync_meta_to_content( $previous_content, '1' ),
			'2026-05-20 12:00:00'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/history' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'history_loaded', $data['result'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( 2, $data['count'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $revision_id, $data['history_items'][0]['revision_id'] );
		$this->assertFalse( $data['history_items'][0]['is_current'] );
		$this->assertSame( $previous_content, $data['history_items'][0]['content'] );
		$this->assertTrue( $data['history_items'][0]['preview_available'] );
		$this->assertTrue( $data['history_items'][0]['can_restore'] );
		$this->assertFalse( $data['history_items'][0]['can_revert'] );
		$this->assertSame( 0, $data['history_items'][1]['revision_id'] );
		$this->assertTrue( $data['history_items'][1]['is_current'] );
		$this->assertSame( $current_content, $data['history_items'][1]['content'] );
		$this->assertTrue( $data['history_items'][1]['can_restore'] );
		$this->assertTrue( $data['history_items'][1]['can_revert'] );
		$this->assertSame( '2', $data['history_items'][1]['sync_version'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_history_plan_endpoint
	 * @covers ::wp_de_rtc_plan_post_history_action
	 */
	public function test_history_plan_restore_returns_candidate_without_saving() {
		$previous_content = '<!-- wp:paragraph --><p>Restore history content.</p><!-- /wp:paragraph -->';
		$current_content  = '<!-- wp:paragraph --><p>Current history content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_history_post( $current_content, '2' );
		$revision_id      = $this->insert_revision(
			$post_id,
			$this->add_sync_meta_to_content( $previous_content, '1' ),
			'2026-05-20 12:00:00'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/history/plan' );
		$request->set_param( 'history_action', 'restore' );
		$request->set_param( 'revision_id', $revision_id );
		$request->set_param( 'selected_content_hash', wp_de_rtc_hash_content( $previous_content ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'history_restore_planned', $data['result'] );
		$this->assertSame( 'restore', $data['history_action'] );
		$this->assertSame( $revision_id, $data['revision_id'] );
		$this->assertSame( $previous_content, $data['candidate_post_content'] );
		$this->assertSame( wp_de_rtc_hash_content( $previous_content ), $data['candidate_post_content_hash'] );
		$this->assertTrue( $data['requires_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_history_plan_endpoint
	 * @covers ::wp_de_rtc_plan_post_history_action
	 */
	public function test_history_plan_reverts_current_state_to_previous_revision_without_saving() {
		$previous_content = '<!-- wp:paragraph --><p>Previous history content.</p><!-- /wp:paragraph -->';
		$current_content  = '<!-- wp:paragraph --><p>Current history content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_history_post( $current_content, '2' );
		$this->insert_revision(
			$post_id,
			$this->add_sync_meta_to_content( $previous_content, '1' ),
			'2026-05-20 12:00:00'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/history/plan' );
		$request->set_param( 'history_action', 'revert' );
		$request->set_param( 'revision_id', 0 );
		$request->set_param( 'selected_content_hash', wp_de_rtc_hash_content( $current_content ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'history_revert_planned', $data['result'] );
		$this->assertSame( 'revert', $data['history_action'] );
		$this->assertSame( 0, $data['revision_id'] );
		$this->assertSame( $previous_content, $data['candidate_post_content'] );
		$this->assertSame( wp_de_rtc_hash_content( $previous_content ), $data['candidate_post_content_hash'] );
		$this->assertTrue( $data['requires_save'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_history_permissions_check
	 */
	public function test_history_endpoint_requires_edit_post_without_mutating() {
		$post_id          = $this->create_history_post(
			'<!-- wp:paragraph --><p>Protected history content.</p><!-- /wp:paragraph -->',
			'1'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/history' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( rest_authorization_required_code(), $response->get_status() );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	private function create_history_post( $content, $version ) {
		return self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC history post',
				'post_content' => $this->add_sync_meta_to_content( $content, $version ),
			)
		);
	}

	private function add_sync_meta_to_content( $content, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'automerge',
			array(
				'schema'             => 'de-rtc-automerge-v1',
				'version'            => (string) $version,
				'previous_version'   => (string) max( 0, (int) $version - 1 ),
				'last_server_update' => array(
					'type'          => 'retry_save',
					'session_label' => 'Mira History',
				),
			),
			'prefix-block'
		);

		$this->assertIsString( $content_with_sync_meta );

		return $content_with_sync_meta;
	}

	private function insert_revision( $post_id, $post_content, $date_gmt ) {
		$post                 = (array) get_post( $post_id );
		$post_revision_fields = _wp_post_revision_data( $post );

		$post_revision_fields['post_content']  = $post_content;
		$post_revision_fields['post_date']     = $date_gmt;
		$post_revision_fields['post_date_gmt'] = $date_gmt;

		$revision_id = wp_insert_post( wp_slash( $post_revision_fields ), true );

		$this->assertNotWPError( $revision_id );
		$this->assertIsInt( $revision_id );

		return $revision_id;
	}

	private function get_post_revisions( $post_id ) {
		return wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
	}

	private function assert_post_unchanged( $post_id, $expected_content, $expected_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( $expected_content, $after_post->post_content );
		$this->assertSame( array_map( 'intval', array_keys( $expected_revisions ) ), array_map( 'intval', array_keys( $after_revisions ) ) );
	}
}
