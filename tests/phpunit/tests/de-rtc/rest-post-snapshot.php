<?php
/**
 * Tests for the Distributed Editing post snapshot endpoint.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Post_Snapshot extends WP_Test_REST_TestCase {

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
	public function test_post_snapshot_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_endpoint
	 * @covers ::wp_de_rtc_rest_post_snapshot_permissions_check
	 * @covers ::wp_de_rtc_get_post_snapshot_for_distributed_editing
	 */
	public function test_post_snapshot_returns_raw_content_and_state_hash_without_mutating() {
		$content     = '<!-- wp:paragraph --><p>Snapshot content.</p><!-- /wp:paragraph -->';
		$post_id     = $this->create_snapshot_post( $content, '7' );
		$before_post = get_post( $post_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'post', $data['type'] );
		$this->assertSame( $before_post->post_content, $data['content']['raw'] );
		$this->assertSame( '7', $data['server_version'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $data['state_hash'] );
		$this->assertSame( '"' . $data['state_hash'] . '"', $headers['ETag'] );
		$this->assertSame( 'private, no-store', $headers['Cache-Control'] );
		$this->assertTrue( $data['read_only'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertTrue( $data['exposes_raw_content'] );
		$this->assertSame( $before_post->post_content, get_post( $post_id )->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_endpoint
	 * @covers ::wp_de_rtc_rest_if_none_match_matches_state_hash
	 */
	public function test_post_snapshot_returns_not_modified_for_matching_state_hash() {
		$post_id       = $this->create_snapshot_post(
			'<!-- wp:paragraph --><p>Conditional snapshot.</p><!-- /wp:paragraph -->',
			'9'
		);
		$first_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );
		$first         = rest_get_server()->dispatch( $first_request );
		$first_data    = $first->get_data();
		$etag          = '"' . $first_data['state_hash'] . '"';
		$second        = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$second->add_header( 'If-None-Match', $etag );

		$response = rest_get_server()->dispatch( $second );
		$headers  = $response->get_headers();

		$this->assertSame( 304, $response->get_status() );
		$this->assertNull( $response->get_data() );
		$this->assertSame( $etag, $headers['ETag'] );
		$this->assertSame( 'private, no-store', $headers['Cache-Control'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_snapshot_for_distributed_editing
	 */
	public function test_post_snapshot_applies_missing_sync_meta_repair_to_persisted_post() {
		$content = '<!-- wp:paragraph --><p>Missing sync meta.</p><!-- /wp:paragraph -->';
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC snapshot missing sync meta',
				'post_content' => $content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$parsed   = wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '<!-- wp:sync-meta', $data['content']['raw'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $data['content']['raw'] );
		$this->assertNotWPError( $parsed );
		$this->assertSame( $content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'de-rtc-automerge-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( '1', $parsed['sync_meta']['version'] );
		$this->assertSame( 'empty_import', $parsed['sync_meta']['last_server_update']['external_repair_mode'] );
		$this->assert_system_recovery_attribution( $parsed['sync_meta'], 'empty_import' );
		$this->assertTrue( $data['distributed_editing']['repair_candidate'] );
		$this->assertTrue( $data['distributed_editing']['repair_applied'] );
		$this->assertSame( 'empty_import', $data['distributed_editing']['external_repair']['mode'] );
		$this->assertSame( 'system', $data['distributed_editing']['external_repair']['repair_actor']['actor_type'] );
		$this->assertSame( 'candidate_update_applied', $data['distributed_editing']['external_repair']['apply_result'] );
		$this->assertFalse( $data['read_only'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['mutates_persisted_post_content'] );
		$this->assertTrue( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $data['content']['raw'], get_post( $post_id )->post_content );
		$this->assertNotSame( $before_post->post_content, get_post( $post_id )->post_content );
		$this->assertGreaterThan(
			count( $before_revisions ),
			count(
				wp_get_post_revisions(
					$post_id,
					array(
						'check_enabled' => false,
					)
				)
			)
		);
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_endpoint
	 * @covers ::wp_de_rtc_get_post_snapshot_for_distributed_editing
	 * @covers ::wp_de_rtc_get_automerge_external_repair_update
	 */
	public function test_post_snapshot_applies_external_html_repair_from_latest_automerge_revision() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Known Automerge base content.</p><!-- /wp:paragraph --><!-- wp:list --><ul><!-- wp:list-item --><li>First item</li><!-- /wp:list-item --><!-- wp:list-item --><li>Second item</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$current_content = '<h2>Google Docs and AbiWord Collaboration</h2><p>Google Docs offers browser-native collaboration with low setup costs. AbiWord showed how lightweight desktop collaboration could work, but deployment and interoperability are harder for modern teams.</p>';
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC snapshot external HTML repair',
				'post_content' => $current_content,
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content(
			$base_content,
			'automerge',
			$this->create_automerge_sync_meta( $base_content, '10' ),
			'prefix-block'
		);

		$this->assertIsString( $revision_content );

		$revision_id      = $this->insert_revision( $post_id, $revision_content, '2026-06-05 12:00:00' );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

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
		$this->assertIsArray( $parsed );
		$this->assertSame( $current_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'de-rtc-automerge-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( '11', $parsed['sync_meta']['version'] );
		$this->assertSame( '10', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'native-automerge-php-v1', $parsed['sync_meta']['automerge_encoding'] );
		$this->assertGreaterThan( 0, $parsed['sync_meta']['automerge_operation_count'] );
		$this->assertSame( wp_de_rtc_hash_content( $current_content ), $parsed['sync_meta']['post_content_hash'] );
		$this->assertSame( 'external_repair', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'missing_sync_meta_revision', $parsed['sync_meta']['last_server_update']['external_repair_mode'] );
		$this->assertSame( 'native_automerge_external_repair_v1', $parsed['sync_meta']['last_server_update']['merge_strategy'] );
		$this->assertSame( $revision_id, $parsed['sync_meta']['last_server_update']['base_revision_id'] );
		$this->assert_system_recovery_attribution( $parsed['sync_meta'], 'missing_sync_meta_revision' );
		$this->assertSame( $after_post->post_content, $data['content']['raw'] );
		$this->assertSame( '11', $data['server_version'] );
		$this->assertTrue( $data['distributed_editing']['repair_candidate'] );
		$this->assertTrue( $data['distributed_editing']['repair_applied'] );
		$this->assertSame( 'missing_sync_meta_revision', $data['distributed_editing']['external_repair']['mode'] );
		$this->assertSame( '10', $data['distributed_editing']['external_repair']['base_version'] );
		$this->assertSame( '11', $data['distributed_editing']['external_repair']['repaired_version'] );
		$this->assertSame( $revision_id, $data['distributed_editing']['external_repair']['base_revision_id'] );
		$this->assertSame( 'system', $data['distributed_editing']['external_repair']['repair_actor']['actor_type'] );
		$this->assertFalse( $data['read_only'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['mutates_persisted_post_content'] );
		$this->assertTrue( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertGreaterThan( count( $before_revisions ), count( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_post_snapshot_permissions_check
	 */
	public function test_post_snapshot_requires_edit_post_without_mutating() {
		$post_id     = $this->create_snapshot_post(
			'<!-- wp:paragraph --><p>Permission snapshot.</p><!-- /wp:paragraph -->',
			'3'
		);
		$before_post = get_post( $post_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing' );

		wp_set_current_user( self::$subscriber_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( rest_authorization_required_code(), $response->get_status() );
		$this->assertSame( $before_post->post_content, get_post( $post_id )->post_content );
	}

	private function create_snapshot_post( $content, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'automerge',
			array(
				'schema'             => 'de-rtc-automerge-v1',
				'version'            => (string) $version,
				'previous_version'   => (string) max( 0, (int) $version - 1 ),
				'last_server_update' => array(
					'type'          => 'retry_save',
					'session_label' => 'Snapshot Test',
				),
			),
			'prefix-block'
		);

		$this->assertIsString( $content_with_sync_meta );

		return self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC snapshot post',
				'post_content' => $content_with_sync_meta,
			)
		);
	}

	/**
	 * Builds current Automerge sync metadata for snapshot fixtures.
	 *
	 * @param string $content Stripped post content.
	 * @param string $version Sync version.
	 * @return array Sync metadata.
	 */
	private function create_automerge_sync_meta( $content, $version ) {
		$update = array(
			'format'      => 'native-automerge-blocks-v1',
			'schema'      => 'de-rtc-automerge-v1',
			'operations'  => array(),
			'stateVector' => array(),
		);

		return array(
			'schema'                    => 'de-rtc-automerge-v1',
			'version'                   => (string) $version,
			'previous_version'          => (string) max( 0, (int) $version - 1 ),
			'automerge_encoding'        => 'native-automerge-blocks-v1',
			'automerge_state_vector'    => array(),
			'automerge_update'          => base64_encode( wp_json_encode( $update, JSON_UNESCAPED_SLASHES ) ),
			'automerge_operation_count' => 0,
			'post_content_hash'         => wp_de_rtc_hash_content( $content ),
			'document_uuid'             => 'de-rtc-snapshot-test',
		);
	}

	/**
	 * Inserts a revision for a post with controlled content and dates.
	 *
	 * @param int    $post_id      Parent post ID.
	 * @param string $post_content Revision content.
	 * @param string $date_gmt     GMT date.
	 * @return int Revision ID.
	 */
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

	/**
	 * Skips tests when the native PHP Automerge port cannot load.
	 */
	private function require_automerge_runtime() {
		$status = wp_de_rtc_get_automerge_runtime_status();

		if ( empty( $status['available'] ) ) {
			$this->markTestSkipped( 'Native PHP Automerge runtime is not available.' );
		}
	}

	private function assert_system_recovery_attribution( $sync_meta, $expected_mode ) {
		$this->assertIsArray( $sync_meta );
		$this->assertArrayHasKey( 'last_sync_meta_recovery', $sync_meta );
		$this->assertSame( $expected_mode, $sync_meta['last_sync_meta_recovery']['mode'] );
		$this->assertSame( 'system', $sync_meta['last_sync_meta_recovery']['actor']['actor_type'] );
		$this->assertSame( 'system:distributed-editing-recovery', $sync_meta['last_sync_meta_recovery']['actor']['attribution_key'] );
		$this->assertSame( 'Recovered external changes', $sync_meta['last_sync_meta_recovery']['actor']['display_name'] );
		$this->assertFalse( $sync_meta['last_sync_meta_recovery']['actor']['human_actor'] );
		$this->assertFalse( $sync_meta['last_sync_meta_recovery']['actor']['exposes_user_id'] );
		$this->assertArrayNotHasKey( 'user_id', $sync_meta['last_server_update'] );
	}
}
