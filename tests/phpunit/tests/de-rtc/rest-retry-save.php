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
	 * @covers ::wp_de_rtc_register_rest_routes
	 * @covers ::wp_de_rtc_get_rest_recovery_post_type_rest_bases
	 */
	public function test_retry_save_routes_are_registered_for_posts_and_pages() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/distributed-editing/retry-save', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/distributed-editing/retry-save', $routes );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_applies_after_current_retry_submit_proof_and_records_authority_evidence() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Board demo current server content.</p><!-- /wp:paragraph -->',
			21,
			array(
				'hash' => 'server-owned-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC board demo retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Board demo rebased local content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '21',
				'rebased_from_version'       => '18',
				'pending_change_count'       => 3,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $proof_data['result'] );
		$this->assertTrue( $proof_data['retry_submit_accepted'] );
		$this->assertSame( '21', $proof_data['client_base_version'] );
		$this->assertSame( '21', $proof_data['server_version'] );
		$this->assertSame( $proposed_hash, $proof_data['proposed_post_content_hash'] );
		$this->assertTrue( $proof_data['save_path_required'] );
		$this->assertFalse( $proof_data['saves_post'] );
		$this->assertFalse( $proof_data['mutates_post_content'] );
		$this->assertFalse( $proof_data['creates_revision'] );
		$this->assertFalse( $proof_data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );

		$save_request = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_proposed = wp_de_rtc_parse_post_content_sync_meta( $proposed_content );
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertSame( '21', $save_data['client_base_version'] );
		$this->assertSame( '21', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '21', $save_data['previous_server_version'] );
		$this->assertSame( '22', $save_data['server_version'] );
		$this->assertSame( '18', $save_data['rebased_from_version'] );
		$this->assertSame( 3, $save_data['pending_change_count'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( hash( 'sha256', $after_post->post_content ), $save_data['saved_post_content_hash'] );
		$this->assertSame( $after_post->post_content, $save_data['content']['raw'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $save_data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $save_data['revision_ids_after_save'] );
		$this->assertSame(
			array_values( array_diff( $save_data['revision_ids_after_save'], $save_data['revision_ids_before_save'] ) ),
			$save_data['created_revision_ids']
		);
		$this->assertNotEmpty( $save_data['created_revision_ids'] );
		$this->assertIsArray( $parsed_proposed );
		$this->assertNull( $parsed_proposed['sync_meta'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '22', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '21', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'server-owned-base', $parsed_saved['sync_meta']['hash'] );
		$this->assertSame( 'retry_save', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( self::$admin_user_id, $parsed_saved['sync_meta']['last_server_update']['user_id'] );
		$this->assertSame( '21', $parsed_saved['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( '21', $parsed_saved['sync_meta']['last_server_update']['accepted_proof_server_version'] );
		$this->assertSame( '18', $parsed_saved['sync_meta']['last_server_update']['rebased_from_version'] );
		$this->assertSame( 3, $parsed_saved['sync_meta']['last_server_update']['pending_change_count'] );
		$this->assertSame( $proposed_hash, $parsed_saved['sync_meta']['last_server_update']['proposed_post_content_hash'] );
	}

	/**
	 * @dataProvider data_supported_sync_meta_shapes
	 *
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_retry_save_applies_when_current_sync_meta_uses_supported_shape( $shape ) {
		$current_stripped_content = '<!-- wp:paragraph --><p>Wrapped current server content.</p><!-- /wp:paragraph -->';
		$current_content          = $this->add_sync_meta_to_content_with_shape(
			$current_stripped_content,
			21,
			$shape
		);
		$post_id                  = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC ' . $shape . ' sync meta retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content         = '<!-- wp:paragraph --><p>Wrapped current server content with one edit.</p><!-- /wp:paragraph -->';
		$proposed_hash            = hash( 'sha256', $proposed_content );
		$proof_request            = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '21',
				'rebased_from_version'       => '21',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( 'retry_submit_accepted_for_future_save', $proof_data['result'] );
		$this->assertSame( '21', $proof_data['server_version'] );

		$save_request = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$save_data     = $save_response->get_data();
		$after_post    = get_post( $post_id );
		$parsed_saved  = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertSame( '21', $save_data['previous_server_version'] );
		$this->assertSame( '22', $save_data['server_version'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '22', $parsed_saved['sync_meta']['version'] );
		$this->assertStringNotContainsString( '<p><script type="wp/post-sync-meta"', $save_data['content']['raw'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_non_conflicting_serialized_block_changes_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			50,
			array(
				'hash' => 'server-merge-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '50',
				'rebased_from_version'       => '48',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '50', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from another editor.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			51,
			array(
				'previous_version' => '50',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from another editor.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '50', $save_data['client_base_version'] );
		$this->assertSame( '50', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '51', $save_data['previous_server_version'] );
		$this->assertSame( '52', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( $after_post->post_content, $save_data['content']['raw'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( '50', $save_data['server_merge']['base_version'] );
		$this->assertSame( '51', $save_data['server_merge']['server_version'] );
		$this->assertSame( 2, $save_data['server_merge']['block_count'] );
		$this->assertSame( array( 1 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '52', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '51', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $proposed_hash, $parsed_saved['sync_meta']['last_server_update']['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( array( 1 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_block_count'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_retry_save_automerge_server_merges_distinct_inline_formatting_in_one_paragraph_after_accepted_proof() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>This is bold and italicized.</p><!-- /wp:paragraph -->';
		$current_content = $this->add_automerge_sync_meta_to_content(
			$base_content,
			150,
			array(
				'hash' => 'rich-text-format-base',
			)
		);
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC rich text format merge post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>This is bold and <em>italicized</em>.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client' );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '150',
				'rebased_from_version'       => '150',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>This is <strong>bold</strong> and italicized.</p><!-- /wp:paragraph -->';

		$this->assertSame(
			$post_id,
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $advanced_server_content,
					)
				)
			)
		);

		$advanced_post   = get_post( $post_id );
		$parsed_advanced = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );

		$this->assertIsArray( $parsed_advanced );
		$this->assertSame( $advanced_server_content, $parsed_advanced['content'] );
		$this->assertSame( '151', $parsed_advanced['sync_meta']['version'] );

		$save_request = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				'automerge_client_update'             => $client_update,
				'session_key'                         => 'authorship-format-session',
			)
		);

		$save_response  = rest_get_server()->dispatch( $save_request );
		$save_data      = $save_response->get_data();
		$after_post     = get_post( $post_id );
		$parsed_saved   = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content = '<!-- wp:paragraph --><p>This is <strong>bold</strong> and <em>italicized</em>.</p><!-- /wp:paragraph -->';

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['automerge_update_applied'] );
		$this->assertSame( 'native_automerge_blocks_v1', $save_data['server_merge']['merge_strategy'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( $merged_content, wp_de_rtc_parse_post_content_sync_meta( $save_data['content']['raw'] )['content'] );
		$this->assertSame( '152', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '151', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$attribution_key = wp_de_rtc_get_presence_attribution_key_for_session_key( $post_id, 'authorship-format-session' );

		$this->assertSame( $attribution_key, $parsed_saved['sync_meta']['last_server_update']['attribution_key'] );
		$this->assertSame( 'de-rtc-authorship-v1', $parsed_saved['sync_meta']['authorship']['schema'] );
		$this->assertTrue( $parsed_saved['sync_meta']['authorship']['contentFree'] );
		$this->assertFalse( $parsed_saved['sync_meta']['authorship']['rawContentIncluded'] );
		$this->assertFalse( $parsed_saved['sync_meta']['authorship']['rawSessionKeyIncluded'] );
		$this->assertArrayHasKey( $attribution_key, $parsed_saved['sync_meta']['authorship']['sessions'] );
		$this->assertFalse( $parsed_saved['sync_meta']['authorship']['sessions'][ $attribution_key ]['rawSessionKeyIncluded'] );
		$this->assertFalse( $parsed_saved['sync_meta']['authorship']['sessions'][ $attribution_key ]['exposesUserId'] );
		$this->assertCount( 1, $parsed_saved['sync_meta']['authorship']['blocks'] );
		$this->assertSame( array( 0 ), $parsed_saved['sync_meta']['authorship']['blocks'][0]['path'] );
		$this->assertSame( 'core/paragraph', $parsed_saved['sync_meta']['authorship']['blocks'][0]['blockName'] );
		$this->assertNull( $parsed_saved['sync_meta']['authorship']['blocks'][0]['attributionKey'] );
		$this->assertNotEmpty( $parsed_saved['sync_meta']['authorship']['blocks'][0]['richText']['ranges'] );
		$this->assertSame( $attribution_key, $parsed_saved['sync_meta']['authorship']['blocks'][0]['richText']['ranges'][0]['attributionKey'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_duplicate_retry_save_no_write_result
	 */
	public function test_retry_save_duplicate_server_merge_no_writes_after_first_persistence() {
		$base_content     = '<!-- wp:paragraph --><p>Duplicate opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Duplicate details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			56,
			array(
				'hash' => 'duplicate-server-merge-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC duplicate retry save server merge post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Duplicate opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Duplicate details from another editor.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			57,
			array(
				'previous_version' => '56',
			)
		);

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

		$proposed_content = '<!-- wp:paragraph --><p>Duplicate opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Duplicate details base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$merged_content   = '<!-- wp:paragraph --><p>Duplicate opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Duplicate details from another editor.</p><!-- /wp:paragraph -->';
		$merged_hash      = hash( 'sha256', $merged_content );
		$request_params   = array(
			'client_base_version'           => '56',
			'accepted_proof_server_version' => '56',
			'rebased_from_version'          => '54',
			'pending_change_count'          => 1,
			'proposed_post_content'         => $proposed_content,
			'proposed_post_content_hash'    => $proposed_hash,
		);
		$save_request     = $this->create_retry_save_request( 'posts', $post_id, $request_params );

		$save_response = rest_get_server()->dispatch( $save_request );
		$save_data     = $save_response->get_data();
		$after_post    = get_post( $post_id );
		$parsed_saved  = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame( '57', $save_data['previous_server_version'] );
		$this->assertSame( '58', $save_data['server_version'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );

		$duplicate_before_post      = get_post( $post_id );
		$duplicate_before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$duplicate_request          = $this->create_retry_save_request( 'posts', $post_id, $request_params );

		$duplicate_response = rest_get_server()->dispatch( $duplicate_request );
		$duplicate_data     = $duplicate_response->get_data();

		$this->assertSame( 200, $duplicate_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $duplicate_data['result'] );
		$this->assertTrue( $duplicate_data['retry_save_accepted'] );
		$this->assertTrue( $duplicate_data['retry_save_duplicate'] );
		$this->assertTrue( $duplicate_data['idempotent_no_write'] );
		$this->assertTrue( $duplicate_data['already_persisted'] );
		$this->assertTrue( $duplicate_data['server_merge_applied'] );
		$this->assertSame( '56', $duplicate_data['client_base_version'] );
		$this->assertSame( '56', $duplicate_data['accepted_proof_server_version'] );
		$this->assertSame( '57', $duplicate_data['previous_server_version'] );
		$this->assertSame( '58', $duplicate_data['server_version'] );
		$this->assertSame( $proposed_hash, $duplicate_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $duplicate_data['saved_stripped_post_content_hash'] );
		$this->assertSame( $duplicate_before_post->post_content, $duplicate_data['content']['raw'] );
		$this->assertFalse( $duplicate_data['saves_post'] );
		$this->assertFalse( $duplicate_data['mutates_post_content'] );
		$this->assertFalse( $duplicate_data['creates_revision'] );
		$this->assertTrue( $duplicate_data['claims_saved'] );
		$this->assertFalse( $duplicate_data['revision_created'] );
		$this->assertSame( array(), $duplicate_data['created_revision_ids'] );
		$this->assertSame( $duplicate_data['revision_ids_before_save'], $duplicate_data['revision_ids_after_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $duplicate_before_revisions ) ), $duplicate_data['revision_ids_before_save'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $duplicate_data['server_merge']['merge_strategy'] );
		$this->assert_post_unchanged( $post_id, $duplicate_before_post->post_content, $duplicate_before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_multiple_non_conflicting_serialized_block_changes_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Merge alpha base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge beta base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge gamma base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge delta base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			350,
			array(
				'hash' => 'server-merge-multi-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge multi-block retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Merge alpha local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge beta base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge gamma local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge delta base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '350',
				'rebased_from_version'       => '349',
				'pending_change_count'       => 2,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '350', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Merge alpha base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge beta remote.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge gamma base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge delta remote.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			351,
			array(
				'previous_version' => '350',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Merge alpha local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge beta remote.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge gamma local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Merge delta remote.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '350', $save_data['client_base_version'] );
		$this->assertSame( '350', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '351', $save_data['previous_server_version'] );
		$this->assertSame( '352', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( $after_post->post_content, $save_data['content']['raw'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( '350', $save_data['server_merge']['base_version'] );
		$this->assertSame( '351', $save_data['server_merge']['server_version'] );
		$this->assertSame( 4, $save_data['server_merge']['block_count'] );
		$this->assertSame( 4, $save_data['server_merge']['base_block_count'] );
		$this->assertSame( 4, $save_data['server_merge']['server_block_count'] );
		$this->assertSame( 4, $save_data['server_merge']['proposed_block_count'] );
		$this->assertSame( 4, $save_data['server_merge']['merged_block_count'] );
		$this->assertSame( array( 1, 3 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0, 2 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 2, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '352', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '351', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $proposed_hash, $parsed_saved['sync_meta']['last_server_update']['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( array( 1, 3 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0, 2 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
		$this->assertSame( 2, $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_block_count'] );
		$this->assertSame( 2, $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_block_count'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_one_sided_appended_block_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			70,
			array(
				'hash' => 'server-merge-append-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge append retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Local appended note.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '70',
				'rebased_from_version'       => '69',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '70', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening from another editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			71,
			array(
				'previous_version' => '70',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Opening from another editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Local appended note.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '70', $save_data['client_base_version'] );
		$this->assertSame( '70', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '71', $save_data['previous_server_version'] );
		$this->assertSame( '72', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( 3, $save_data['server_merge']['block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['base_block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['server_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['merged_block_count'] );
		$this->assertSame( 'local', $save_data['server_merge']['append_source'] );
		$this->assertSame( 1, $save_data['server_merge']['appended_block_count'] );
		$this->assertSame( array( 0 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 2 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '72', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '71', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( 'local', $parsed_saved['sync_meta']['last_server_update']['server_merge']['append_source'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['appended_block_count'] );
		$this->assertSame( array( 0 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 2 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_one_sided_server_appended_block_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			80,
			array(
				'hash' => 'server-merge-server-append-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge server append retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from local editor.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '80',
				'rebased_from_version'       => '79',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '80', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Server appended note.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			81,
			array(
				'previous_version' => '80',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Server appended note.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '80', $save_data['client_base_version'] );
		$this->assertSame( '80', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '81', $save_data['previous_server_version'] );
		$this->assertSame( '82', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( 3, $save_data['server_merge']['block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['base_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['server_block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['merged_block_count'] );
		$this->assertSame( 'server', $save_data['server_merge']['append_source'] );
		$this->assertSame( 1, $save_data['server_merge']['appended_block_count'] );
		$this->assertSame( array( 2 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 1 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '82', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '81', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( 'server', $parsed_saved['sync_meta']['last_server_update']['server_merge']['append_source'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['appended_block_count'] );
		$this->assertSame( array( 2 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 1 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_edge_insertion
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_one_sided_prepended_block_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			150,
			array(
				'hash' => 'server-merge-prepend-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge prepend retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Local prepended note.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '150',
				'rebased_from_version'       => '149',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '150', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from another editor.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			151,
			array(
				'previous_version' => '150',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Local prepended note.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details from another editor.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '150', $save_data['client_base_version'] );
		$this->assertSame( '150', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '151', $save_data['previous_server_version'] );
		$this->assertSame( '152', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( 3, $save_data['server_merge']['block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['base_block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['server_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['merged_block_count'] );
		$this->assertSame( 'local', $save_data['server_merge']['edge_insert_source'] );
		$this->assertSame( 'prepend', $save_data['server_merge']['edge_insert_position'] );
		$this->assertSame( 1, $save_data['server_merge']['edge_inserted_block_count'] );
		$this->assertNull( $save_data['server_merge']['append_source'] );
		$this->assertSame( 0, $save_data['server_merge']['appended_block_count'] );
		$this->assertSame( 'local', $save_data['server_merge']['prepend_source'] );
		$this->assertSame( 1, $save_data['server_merge']['prepended_block_count'] );
		$this->assertSame( array( 2 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '152', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '151', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( 'local', $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_insert_source'] );
		$this->assertSame( 'prepend', $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_insert_position'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_inserted_block_count'] );
		$this->assertNull( $parsed_saved['sync_meta']['last_server_update']['server_merge']['append_source'] );
		$this->assertSame( 0, $parsed_saved['sync_meta']['last_server_update']['server_merge']['appended_block_count'] );
		$this->assertSame( 'local', $parsed_saved['sync_meta']['last_server_update']['server_merge']['prepend_source'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['prepended_block_count'] );
		$this->assertSame( array( 2 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_edge_insertion
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_one_sided_server_prepended_block_after_accepted_proof() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			160,
			array(
				'hash' => 'server-merge-server-prepend-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge server prepend retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '160',
				'rebased_from_version'       => '159',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '160', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Server prepended note.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			161,
			array(
				'previous_version' => '160',
			)
		);

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

		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response   = rest_get_server()->dispatch( $save_request );
		$save_data       = $save_response->get_data();
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
		$merged_content  = '<!-- wp:paragraph --><p>Server prepended note.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Opening from local editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$merged_hash     = hash( 'sha256', $merged_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $save_data['result'] );
		$this->assertTrue( $save_data['retry_save_accepted'] );
		$this->assertTrue( $save_data['server_merge_applied'] );
		$this->assertSame( '160', $save_data['client_base_version'] );
		$this->assertSame( '160', $save_data['accepted_proof_server_version'] );
		$this->assertSame( '161', $save_data['previous_server_version'] );
		$this->assertSame( '162', $save_data['server_version'] );
		$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'] );
		$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'merged', $save_data['server_merge']['merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'] );
		$this->assertSame( 3, $save_data['server_merge']['block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['base_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['server_block_count'] );
		$this->assertSame( 2, $save_data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $save_data['server_merge']['merged_block_count'] );
		$this->assertSame( 'server', $save_data['server_merge']['edge_insert_source'] );
		$this->assertSame( 'prepend', $save_data['server_merge']['edge_insert_position'] );
		$this->assertSame( 1, $save_data['server_merge']['edge_inserted_block_count'] );
		$this->assertNull( $save_data['server_merge']['append_source'] );
		$this->assertSame( 0, $save_data['server_merge']['appended_block_count'] );
		$this->assertSame( 'server', $save_data['server_merge']['prepend_source'] );
		$this->assertSame( 1, $save_data['server_merge']['prepended_block_count'] );
		$this->assertSame( array( 0 ), $save_data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 1 ), $save_data['server_merge']['local_changed_indexes'] );
		$this->assertSame( 1, $save_data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $save_data['server_merge']['local_changed_block_count'] );
		$this->assertTrue( $save_data['saves_post'] );
		$this->assertTrue( $save_data['mutates_post_content'] );
		$this->assertTrue( $save_data['claims_saved'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$save_data['created_revision_ids']
		);
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $merged_content, $parsed_saved['content'] );
		$this->assertSame( '162', $parsed_saved['sync_meta']['version'] );
		$this->assertSame( '161', $parsed_saved['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'] );
		$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'] );
		$this->assertSame( 'server', $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_insert_source'] );
		$this->assertSame( 'prepend', $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_insert_position'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['edge_inserted_block_count'] );
		$this->assertNull( $parsed_saved['sync_meta']['last_server_update']['server_merge']['append_source'] );
		$this->assertSame( 0, $parsed_saved['sync_meta']['last_server_update']['server_merge']['appended_block_count'] );
		$this->assertSame( 'server', $parsed_saved['sync_meta']['last_server_update']['server_merge']['prepend_source'] );
		$this->assertSame( 1, $parsed_saved['sync_meta']['last_server_update']['server_merge']['prepended_block_count'] );
		$this->assertSame( array( 0 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 1 ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_edge_insertion
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_ambiguous_edge_insertions_without_mutating() {
		$repeated_block = '<!-- wp:paragraph --><p>Repeated base.</p><!-- /wp:paragraph -->';
		$base_content   = $repeated_block . $repeated_block;
		$cases          = array(
			'local_ambiguous_edge_insertion'  => array(
				'base_version'               => 170,
				'server_version'             => 171,
				'rebased_from_version'       => '169',
				'proposed_content'           => $repeated_block . $repeated_block . $repeated_block,
				'advanced_server_content'    => $repeated_block . '<!-- wp:paragraph --><p>Server edited repeated.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 2,
				'proposed_block_count'       => 3,
				'server_block_count_changed' => false,
				'local_block_count_changed'  => true,
				'server_block_count_delta'   => 0,
				'local_block_count_delta'    => 1,
				'edge_insert_source'         => 'local',
			),
			'server_ambiguous_edge_insertion' => array(
				'base_version'               => 180,
				'server_version'             => 181,
				'rebased_from_version'       => '179',
				'proposed_content'           => $repeated_block . '<!-- wp:paragraph --><p>Local edited repeated.</p><!-- /wp:paragraph -->',
				'advanced_server_content'    => $repeated_block . $repeated_block . $repeated_block,
				'server_block_count'         => 3,
				'proposed_block_count'       => 2,
				'server_block_count_changed' => true,
				'local_block_count_changed'  => false,
				'server_block_count_delta'   => 1,
				'local_block_count_delta'    => 0,
				'edge_insert_source'         => 'server',
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-ambiguous-edge-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC ambiguous edge insertion ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409, $label );
			$this->assertSame( 'retry_save_server_merge_ambiguous_edge_insertion', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertSame( 2, $data['base_block_count'], $label );
			$this->assertSame( $case['server_block_count'], $data['server_block_count'], $label );
			$this->assertSame( $case['proposed_block_count'], $data['proposed_block_count'], $label );
			$this->assertSame( $case['server_block_count_changed'], $data['server_block_count_changed'], $label );
			$this->assertSame( $case['local_block_count_changed'], $data['local_block_count_changed'], $label );
			$this->assertSame( $case['server_block_count_delta'], $data['server_block_count_delta'], $label );
			$this->assertSame( $case['local_block_count_delta'], $data['local_block_count_delta'], $label );
			$this->assertSame( $case['edge_insert_source'], $data['edge_insert_source'], $label );
			$this->assertSame( 'ambiguous', $data['edge_insert_position'], $label );
			$this->assertTrue( $data['edge_insert_ambiguous'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_deletion_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_deletion
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_one_sided_deleted_block_after_accepted_proof() {
		$base_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_deletion'  => array(
				'base_version'            => 363,
				'server_version'          => 364,
				'next_server_version'     => '365',
				'rebased_from_version'    => '362',
				'proposed_content'        => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph --><p>Opening changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'merged_content'          => '<!-- wp:paragraph --><p>Opening changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph -->',
				'server_block_count'      => 3,
				'proposed_block_count'    => 2,
				'deletion_source'         => 'local',
				'deleted_block_indexes'   => array( 2 ),
				'server_changed_indexes'  => array( 0 ),
				'local_changed_indexes'   => array(),
				'server_changed_count'    => 1,
				'local_changed_count'     => 1,
			),
			'server_deletion' => array(
				'base_version'            => 373,
				'server_version'          => 374,
				'next_server_version'     => '375',
				'rebased_from_version'    => '372',
				'proposed_content'        => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle changed by local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph -->',
				'merged_content'          => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle changed by local.</p><!-- /wp:paragraph -->',
				'server_block_count'      => 2,
				'proposed_block_count'    => 3,
				'deletion_source'         => 'server',
				'deleted_block_indexes'   => array( 2 ),
				'server_changed_indexes'  => array(),
				'local_changed_indexes'   => array( 1 ),
				'server_changed_count'    => 1,
				'local_changed_count'     => 1,
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-one-sided-deletion-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge one-sided deletion ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response   = rest_get_server()->dispatch( $save_request );
			$save_data       = $save_response->get_data();
			$after_post      = get_post( $post_id );
			$after_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$parsed_saved    = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );
			$merged_hash     = hash( 'sha256', $case['merged_content'] );

			$this->assertSame( 200, $save_response->get_status(), $label );
			$this->assertSame( 'retry_save_server_merged', $save_data['result'], $label );
			$this->assertTrue( $save_data['retry_save_accepted'], $label );
			$this->assertTrue( $save_data['server_merge_applied'], $label );
			$this->assertSame( (string) $case['base_version'], $save_data['client_base_version'], $label );
			$this->assertSame( (string) $case['base_version'], $save_data['accepted_proof_server_version'], $label );
			$this->assertSame( (string) $case['server_version'], $save_data['previous_server_version'], $label );
			$this->assertSame( $case['next_server_version'], $save_data['server_version'], $label );
			$this->assertSame( $proposed_hash, $save_data['proposed_post_content_hash'], $label );
			$this->assertSame( $merged_hash, $save_data['saved_stripped_post_content_hash'], $label );
			$this->assertSame( 'merged', $save_data['server_merge']['merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $save_data['server_merge']['merge_strategy'], $label );
			$this->assertSame( 2, $save_data['server_merge']['block_count'], $label );
			$this->assertSame( 3, $save_data['server_merge']['base_block_count'], $label );
			$this->assertSame( $case['server_block_count'], $save_data['server_merge']['server_block_count'], $label );
			$this->assertSame( $case['proposed_block_count'], $save_data['server_merge']['proposed_block_count'], $label );
			$this->assertSame( 2, $save_data['server_merge']['merged_block_count'], $label );
			$this->assertSame( $case['deletion_source'], $save_data['server_merge']['deletion_source'], $label );
			$this->assertSame( $case['deleted_block_indexes'], $save_data['server_merge']['deleted_block_indexes'], $label );
			$this->assertSame( count( $case['deleted_block_indexes'] ), $save_data['server_merge']['deleted_block_count'], $label );
			$this->assertSame( $case['server_changed_indexes'], $save_data['server_merge']['server_changed_indexes'], $label );
			$this->assertSame( $case['local_changed_indexes'], $save_data['server_merge']['local_changed_indexes'], $label );
			$this->assertSame( $case['server_changed_count'], $save_data['server_merge']['server_changed_block_count'], $label );
			$this->assertSame( $case['local_changed_count'], $save_data['server_merge']['local_changed_block_count'], $label );
			$this->assertTrue( $save_data['saves_post'], $label );
			$this->assertTrue( $save_data['mutates_post_content'], $label );
			$this->assertTrue( $save_data['claims_saved'], $label );
			$this->assertTrue( $save_data['revision_created'], $label );
			$this->assertSame(
				array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
				$save_data['created_revision_ids'],
				$label
			);
			$this->assertIsArray( $parsed_saved, $label );
			$this->assertSame( $case['merged_content'], $parsed_saved['content'], $label );
			$this->assertSame( $case['next_server_version'], $parsed_saved['sync_meta']['version'], $label );
			$this->assertSame( (string) $case['server_version'], $parsed_saved['sync_meta']['previous_version'], $label );
			$this->assertSame( 'retry_save_server_merge', $parsed_saved['sync_meta']['last_server_update']['type'], $label );
			$this->assertSame( $merged_hash, $parsed_saved['sync_meta']['last_server_update']['saved_stripped_post_content_hash'], $label );
			$this->assertSame( $case['deletion_source'], $parsed_saved['sync_meta']['last_server_update']['server_merge']['deletion_source'], $label );
			$this->assertSame( $case['deleted_block_indexes'], $parsed_saved['sync_meta']['last_server_update']['server_merge']['deleted_block_indexes'], $label );
			$this->assertSame( count( $case['deleted_block_indexes'] ), $parsed_saved['sync_meta']['last_server_update']['server_merge']['deleted_block_count'], $label );
			$this->assertSame( $case['server_changed_indexes'], $parsed_saved['sync_meta']['last_server_update']['server_merge']['server_changed_indexes'], $label );
			$this->assertSame( $case['local_changed_indexes'], $parsed_saved['sync_meta']['last_server_update']['server_merge']['local_changed_indexes'], $label );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_deletion_merge_result
	 * @covers ::wp_de_rtc_get_serialized_block_deletion
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_same_block_server_merge_conflict_without_mutating() {
		$base_content     = '<!-- wp:paragraph --><p>Board demo stale proof current content.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			30
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC board demo stale proof post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Board demo stale proof proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '30',
				'rebased_from_version'       => '27',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '30', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Board demo intervening server content.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			31,
			array(
				'previous_version' => '30',
			)
		);

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
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$error         = $save_response->as_error();
		$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
		$this->assertSame( 'retry_save_server_merge_same_serialized_block_changed', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertSame( '30', $data['client_base_version'] );
		$this->assertSame( '30', $data['accepted_proof_server_version'] );
		$this->assertSame( '31', $data['server_version'] );
		$this->assertTrue( $data['server_merge_attempted'] );
		$this->assertSame( 'manual_conflict_required', $data['server_merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'] );
		$this->assertSame( 0, $data['conflicting_block_index'] );
		$this->assertSame( array( 0 ), $data['conflicting_block_indexes'] );
		$this->assertSame( 1, $data['conflicting_block_count'] );
		$this->assertSame( array( 0 ), $data['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['local_changed_indexes'] );
		$this->assertSame( 1, $data['server_changed_block_count'] );
		$this->assertSame( 1, $data['local_changed_block_count'] );
		$this->assertCount( 1, $data['conflicting_block_hashes'] );
		$this->assertSame( 0, $data['conflicting_block_hashes'][0]['block_index'] );
		$this->assertSame( wp_de_rtc_hash_content( $base_content ), $data['conflicting_block_hashes'][0]['base_block_hash'] );
		$this->assertSame( wp_de_rtc_hash_content( $advanced_server_content ), $data['conflicting_block_hashes'][0]['server_block_hash'] );
		$this->assertSame( wp_de_rtc_hash_content( $proposed_content ), $data['conflicting_block_hashes'][0]['proposed_block_hash'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_server_merge_block_count_change_with_content_free_evidence_without_mutating() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			60
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge block count post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Local inserted middle block.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '60',
				'rebased_from_version'       => '58',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '60', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening from another editor.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			61,
			array(
				'previous_version' => '60',
			)
		);

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
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$error         = $save_response->as_error();
		$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
		$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_count_changed', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertTrue( $data['server_merge_attempted'] );
		$this->assertSame( 'manual_conflict_required', $data['server_merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'] );
		$this->assertSame( 2, $data['base_block_count'] );
		$this->assertSame( 2, $data['server_block_count'] );
		$this->assertSame( 3, $data['proposed_block_count'] );
		$this->assertFalse( $data['server_block_count_changed'] );
		$this->assertTrue( $data['local_block_count_changed'] );
		$this->assertSame( 0, $data['server_block_count_delta'] );
		$this->assertSame( 1, $data['local_block_count_delta'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_server_middle_inserted_block_without_mutating() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			140,
			array(
				'hash' => 'server-merge-server-middle-insertion-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge server middle insertion post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details changed by local.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '140',
				'rebased_from_version'       => '139',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '140', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Server inserted middle block.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			141,
			array(
				'previous_version' => '140',
			)
		);

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
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$error         = $save_response->as_error();
		$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
		$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_count_changed', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertTrue( $data['server_merge_attempted'] );
		$this->assertSame( 'manual_conflict_required', $data['server_merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'] );
		$this->assertSame( 2, $data['base_block_count'] );
		$this->assertSame( 3, $data['server_block_count'] );
		$this->assertSame( 2, $data['proposed_block_count'] );
		$this->assertTrue( $data['server_block_count_changed'] );
		$this->assertFalse( $data['local_block_count_changed'] );
		$this->assertSame( 1, $data['server_block_count_delta'] );
		$this->assertSame( 0, $data['local_block_count_delta'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_deleted_block_without_mutating() {
		$base_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_deletion'  => array(
				'base_version'               => 170,
				'server_version'             => 171,
				'rebased_from_version'       => '169',
				'proposed_content'           => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'advanced_server_content'    => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 3,
				'proposed_block_count'       => 2,
				'server_block_count_changed' => false,
				'local_block_count_changed'  => true,
				'server_block_count_delta'   => 0,
				'local_block_count_delta'    => -1,
				'deletion_source'            => 'local',
				'deleted_block_indexes'      => array( 1 ),
				'server_changed_indexes'     => array( 1 ),
				'local_changed_indexes'      => array(),
				'server_changed_count'       => 1,
				'local_changed_count'        => 1,
			),
			'server_deletion' => array(
				'base_version'               => 180,
				'server_version'             => 181,
				'rebased_from_version'       => '179',
				'proposed_content'           => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle changed by local.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'advanced_server_content'    => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 2,
				'proposed_block_count'       => 3,
				'server_block_count_changed' => true,
				'local_block_count_changed'  => false,
				'server_block_count_delta'   => -1,
				'local_block_count_delta'    => 0,
				'deletion_source'            => 'server',
				'deleted_block_indexes'      => array( 1 ),
				'server_changed_indexes'     => array(),
				'local_changed_indexes'      => array( 1 ),
				'server_changed_count'       => 1,
				'local_changed_count'        => 1,
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-deletion-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge deletion ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
			$this->assertSame( 'retry_save_server_merge_deleted_serialized_block_changed', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertSame( 3, $data['base_block_count'], $label );
			$this->assertSame( $case['server_block_count'], $data['server_block_count'], $label );
			$this->assertSame( $case['proposed_block_count'], $data['proposed_block_count'], $label );
			$this->assertSame( $case['server_block_count_changed'], $data['server_block_count_changed'], $label );
			$this->assertSame( $case['local_block_count_changed'], $data['local_block_count_changed'], $label );
			$this->assertSame( $case['server_block_count_delta'], $data['server_block_count_delta'], $label );
			$this->assertSame( $case['local_block_count_delta'], $data['local_block_count_delta'], $label );
			$this->assertSame( $case['deletion_source'], $data['deletion_source'], $label );
			$this->assertSame( $case['deleted_block_indexes'], $data['deleted_block_indexes'], $label );
			$this->assertSame( count( $case['deleted_block_indexes'] ), $data['deleted_block_count'], $label );
			$this->assertSame( $case['server_changed_indexes'], $data['server_changed_indexes'], $label );
			$this->assertSame( $case['local_changed_indexes'], $data['local_changed_indexes'], $label );
			$this->assertSame( $case['server_changed_count'], $data['server_changed_block_count'], $label );
			$this->assertSame( $case['local_changed_count'], $data['local_changed_block_count'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_reordered_serialized_block_indexes
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_reordered_blocks_without_mutating() {
		$base_content = '<!-- wp:paragraph --><p>First base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Third base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_reorder'  => array(
				'base_version'                    => 190,
				'server_version'                  => 191,
				'rebased_from_version'            => '189',
				'proposed_content'                => '<!-- wp:paragraph --><p>Second base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>First base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Third base.</p><!-- /wp:paragraph -->',
				'advanced_server_content'         => '<!-- wp:paragraph --><p>First base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Third changed by server.</p><!-- /wp:paragraph -->',
				'server_block_order_changed'      => false,
				'local_block_order_changed'       => true,
				'server_reordered_block_indexes'  => array(),
				'local_reordered_block_indexes'   => array( 0, 1 ),
			),
			'server_reorder' => array(
				'base_version'                    => 200,
				'server_version'                  => 201,
				'rebased_from_version'            => '199',
				'proposed_content'                => '<!-- wp:paragraph --><p>First base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Third changed by local.</p><!-- /wp:paragraph -->',
				'advanced_server_content'         => '<!-- wp:paragraph --><p>Second base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>First base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Third base.</p><!-- /wp:paragraph -->',
				'server_block_order_changed'      => true,
				'local_block_order_changed'       => false,
				'server_reordered_block_indexes'  => array( 0, 1 ),
				'local_reordered_block_indexes'   => array(),
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-reorder-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge reorder ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
			$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_reordered', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertSame( 3, $data['base_block_count'], $label );
			$this->assertSame( 3, $data['server_block_count'], $label );
			$this->assertSame( 3, $data['proposed_block_count'], $label );
			$this->assertFalse( $data['server_block_count_changed'], $label );
			$this->assertFalse( $data['local_block_count_changed'], $label );
			$this->assertSame( 0, $data['server_block_count_delta'], $label );
			$this->assertSame( 0, $data['local_block_count_delta'], $label );
			$this->assertSame( $case['server_block_order_changed'], $data['server_block_order_changed'], $label );
			$this->assertSame( $case['local_block_order_changed'], $data['local_block_order_changed'], $label );
			$this->assertSame( $case['server_reordered_block_indexes'], $data['server_reordered_block_indexes'], $label );
			$this->assertSame( $case['local_reordered_block_indexes'], $data['local_reordered_block_indexes'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_freeform_html_boundary_without_mutating() {
		$base_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_freeform_html'  => array(
				'base_version'            => 210,
				'server_version'          => 211,
				'rebased_from_version'    => '209',
				'proposed_content'        => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><div>Local raw HTML.</div><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph --><p>Opening changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
			),
			'server_freeform_html' => array(
				'base_version'            => 220,
				'server_version'          => 221,
				'rebased_from_version'    => '219',
				'proposed_content'        => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details changed by local.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><section>Server raw HTML.</section><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-freeform-html-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge freeform HTML ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
			$this->assertSame( 'retry_save_server_merge_freeform_html_boundary', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assertArrayNotHasKey( 'base_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'server_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'proposed_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'server_content', $data, $label );
			$this->assertArrayNotHasKey( 'proposed_content', $data, $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_serialized_block_roundtrip_drift_without_mutating() {
		$base_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_roundtrip_drift'  => array(
				'base_version'            => 230,
				'server_version'          => 231,
				'rebased_from_version'    => '229',
				'proposed_content'        => '<!-- wp:paragraph { "dropCap" : true } --><p class="has-drop-cap">Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph --><p>Opening changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
			),
			'server_roundtrip_drift' => array(
				'base_version'            => 240,
				'server_version'          => 241,
				'rebased_from_version'    => '239',
				'proposed_content'        => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details changed by local.</p><!-- /wp:paragraph -->',
				'advanced_server_content' => '<!-- wp:paragraph { "dropCap" : true } --><p class="has-drop-cap">Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-roundtrip-drift-' . $label,
				)
			);
			$post_id         = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge roundtrip drift ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash   = hash( 'sha256', $case['proposed_content'] );
			$proof_request   = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response  = rest_get_server()->dispatch( $proof_request );
			$proof_data      = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
			$this->assertSame( 'retry_save_server_merge_serialized_block_roundtrip_changed', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assertArrayNotHasKey( 'base_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'server_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'proposed_block_count', $data, $label );
			$this->assertArrayNotHasKey( 'server_content', $data, $label );
			$this->assertArrayNotHasKey( 'proposed_content', $data, $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_appended_block_prefix_drift_without_mutating() {
		$base_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$cases        = array(
			'local_prefix_drift'  => array(
				'base_version'               => 90,
				'server_version'             => 91,
				'rebased_from_version'       => '89',
				'proposed_content'           => '<!-- wp:paragraph --><p>Opening changed by local append.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Local appended note.</p><!-- /wp:paragraph -->',
				'advanced_server_content'    => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details changed by server.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 2,
				'proposed_block_count'       => 3,
				'server_block_count_changed' => false,
				'local_block_count_changed'  => true,
				'server_block_count_delta'   => 0,
				'local_block_count_delta'    => 1,
			),
			'server_prefix_drift' => array(
				'base_version'               => 100,
				'server_version'             => 101,
				'rebased_from_version'       => '99',
				'proposed_content'           => '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details changed by local.</p><!-- /wp:paragraph -->',
				'advanced_server_content'    => '<!-- wp:paragraph --><p>Opening changed by server append.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Server appended note.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 3,
				'proposed_block_count'       => 2,
				'server_block_count_changed' => true,
				'local_block_count_changed'  => false,
				'server_block_count_delta'   => 1,
				'local_block_count_delta'    => 0,
			),
		);

		foreach ( $cases as $label => $case ) {
			$current_content  = $this->add_sync_meta_to_content(
				$base_content,
				$case['base_version'],
				array(
					'hash' => 'server-merge-prefix-drift-' . $label,
				)
			);
			$post_id          = self::factory()->post->create(
				array(
					'post_title'   => 'DE-RTC server merge prefix drift ' . $label,
					'post_content' => $current_content,
				)
			);
			$proposed_hash    = hash( 'sha256', $case['proposed_content'] );
			$proof_request    = $this->create_retry_submit_request(
				'posts',
				$post_id,
				array(
					'client_base_version'        => (string) $case['base_version'],
					'rebased_from_version'       => $case['rebased_from_version'],
					'pending_change_count'       => 1,
					'proposed_post_content_hash' => $proposed_hash,
				)
			);
			$proof_response   = rest_get_server()->dispatch( $proof_request );
			$proof_data       = $proof_response->get_data();

			$this->assertSame( 200, $proof_response->get_status(), $label );
			$this->assertSame( (string) $case['base_version'], $proof_data['server_version'], $label );

			$base_revision_id = wp_save_post_revision( $post_id );
			$this->assertIsInt( $base_revision_id, $label );
			$this->assertGreaterThan( 0, $base_revision_id, $label );

			$advanced_content = $this->add_sync_meta_to_content(
				$case['advanced_server_content'],
				$case['server_version'],
				array(
					'previous_version' => (string) $case['base_version'],
				)
			);

			$this->assertSame(
				$post_id,
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $advanced_content,
						)
					)
				),
				$label
			);

			$before_retry_post      = get_post( $post_id );
			$before_retry_revisions = wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			);
			$save_request           = $this->create_retry_save_request(
				'posts',
				$post_id,
				array(
					'client_base_version'                 => $proof_data['client_base_version'],
					'accepted_proof_server_version'       => $proof_data['server_version'],
					'rebased_from_version'                => $proof_data['rebased_from_version'],
					'pending_change_count'                => $proof_data['pending_change_count'],
					'proposed_post_content'               => $case['proposed_content'],
					'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
					'accepted_proof_saves_post'           => $proof_data['saves_post'],
					'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
					'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
					'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
				)
			);

			$save_response = rest_get_server()->dispatch( $save_request );
			$error         = $save_response->as_error();
			$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

			$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
			$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_count_changed', $data['detail'], $label );
			$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'], $label );
			$this->assertTrue( $data['server_merge_attempted'], $label );
			$this->assertSame( 'manual_conflict_required', $data['server_merge_status'], $label );
			$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'], $label );
			$this->assertSame( 2, $data['base_block_count'], $label );
			$this->assertSame( $case['server_block_count'], $data['server_block_count'], $label );
			$this->assertSame( $case['proposed_block_count'], $data['proposed_block_count'], $label );
			$this->assertSame( $case['server_block_count_changed'], $data['server_block_count_changed'], $label );
			$this->assertSame( $case['local_block_count_changed'], $data['local_block_count_changed'], $label );
			$this->assertSame( $case['server_block_count_delta'], $data['server_block_count_delta'], $label );
			$this->assertSame( $case['local_block_count_delta'], $data['local_block_count_delta'], $label );
			$this->assertFalse( $data['requires_server_state_refetch'], $label );
			$this->assertTrue( $data['requires_manual_conflict_resolution'], $label );
			$this->assertTrue( $data['can_export_local_updates'], $label );
			$this->assertFalse( $data['saves_post'], $label );
			$this->assertFalse( $data['mutates_post_content'], $label );
			$this->assertFalse( $data['creates_revision'], $label );
			$this->assertFalse( $data['claims_saved'], $label );
			$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_serialized_block_server_merge_result
	 * @covers ::wp_de_rtc_get_top_level_serialized_block_records
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_concurrent_appended_blocks_without_mutating() {
		$base_content     = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_sync_meta_to_content(
			$base_content,
			130,
			array(
				'hash' => 'server-merge-concurrent-append-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC server merge concurrent append post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Local appended note.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '130',
				'rebased_from_version'       => '129',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '130', $proof_data['server_version'] );

		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_server_content = '<!-- wp:paragraph --><p>Opening base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Server appended note.</p><!-- /wp:paragraph -->';
		$advanced_content        = $this->add_sync_meta_to_content(
			$advanced_server_content,
			131,
			array(
				'previous_version' => '130',
			)
		);

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
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'rebased_from_version'                => $proof_data['rebased_from_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$error         = $save_response->as_error();
		$data          = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $save_response, 409 );
		$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_count_changed', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertTrue( $data['server_merge_attempted'] );
		$this->assertSame( 'manual_conflict_required', $data['server_merge_status'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'] );
		$this->assertSame( 2, $data['base_block_count'] );
		$this->assertSame( 3, $data['server_block_count'] );
		$this->assertSame( 3, $data['proposed_block_count'] );
		$this->assertTrue( $data['server_block_count_changed'] );
		$this->assertTrue( $data['local_block_count_changed'] );
		$this->assertSame( 1, $data['server_block_count_delta'] );
		$this->assertSame( 1, $data['local_block_count_delta'] );
		$this->assertFalse( $data['requires_server_state_refetch'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_submit_endpoint
	 * @covers ::wp_de_rtc_get_retry_submit_acceptance_result
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_retry_save_requires_setting_enabled_after_accepted_proof_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Board demo setting current content.</p><!-- /wp:paragraph -->',
			40
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC board demo setting gate post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Board demo setting gated proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$proof_request    = $this->create_retry_submit_request(
			'posts',
			$post_id,
			array(
				'client_base_version'        => '40',
				'pending_change_count'       => 1,
				'proposed_post_content_hash' => $proposed_hash,
			)
		);

		$proof_response = rest_get_server()->dispatch( $proof_request );
		$proof_data     = $proof_response->get_data();

		$this->assertSame( 200, $proof_response->get_status() );
		$this->assertSame( '40', $proof_data['server_version'] );

		update_option( 'wp_de_rtc_enabled', false );

		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$save_request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'                 => $proof_data['client_base_version'],
				'accepted_proof_server_version'       => $proof_data['server_version'],
				'pending_change_count'                => $proof_data['pending_change_count'],
				'proposed_post_content'               => $proposed_content,
				'proposed_post_content_hash'          => $proof_data['proposed_post_content_hash'],
				'accepted_proof_saves_post'           => $proof_data['saves_post'],
				'accepted_proof_mutates_post_content' => $proof_data['mutates_post_content'],
				'accepted_proof_creates_revision'     => $proof_data['creates_revision'],
				'accepted_proof_claims_saved'         => $proof_data['claims_saved'],
			)
		);

		$save_response = rest_get_server()->dispatch( $save_request );
		$error         = $save_response->as_error();
		$data          = $error->get_error_data( 'de_rtc_feature_disabled' );

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $save_response, 403 );
		$this->assertSame( 'feature_disabled_for_post', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
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
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_apply_automerge_metadata_to_sync_meta
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_retry_save_applies_automerge_current_base_and_writes_top_pseudo_block_metadata() {
		$this->require_automerge_runtime();

		$base_content     = '<!-- wp:paragraph --><p>Automerge current content.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_automerge_sync_meta_to_content(
			$base_content,
			21,
			array(
				'hash' => 'automerge-current-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Automerge proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client' );
		$this->assertSame( 'block.rich_text_content', $client_update['operations'][0]['type'] );
		$this->assertSame( 'Automerge.Text.splice', $client_update['operations'][0]['automergePrimitive'] );
		$this->assertSame( 'proposed', $client_update['operations'][0]['textSplice']['insertText'] );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '21',
				'accepted_proof_server_version' => '21',
				'rebased_from_version'          => '21',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'automerge_client_update'             => $client_update,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertTrue( $data['automerge_update_applied'] );
		$this->assertSame( 'native-automerge-blocks-v1', $data['automerge_encoding'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $parsed['sync_meta_position'] );
		$this->assertSame( '22', $parsed['sync_meta']['version'] );
		$this->assertSame( '21', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'de-rtc-automerge-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( 'native-automerge-blocks-v1', $parsed['sync_meta']['automerge_encoding'] );
		$this->assertNotEmpty( $parsed['sync_meta']['automerge_update'] );
		$this->assertGreaterThan( 0, $parsed['sync_meta']['automerge_operation_count'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertStringNotContainsString( '<p><script type="wp/post-sync-meta"', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_get_automerge_block_native_current_base_merge_result
	 * @covers ::wp_de_rtc_apply_automerge_metadata_to_sync_meta
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_retry_save_applies_current_base_freeform_body_converted_to_blocks() {
		$this->require_automerge_runtime();

		$base_content    = '<h2>Legacy collaboration note</h2>' . "\n" . '<p>Google Docs and AbiWord both support collaboration.</p>';
		$current_content = $this->add_automerge_sync_meta_to_content(
			$base_content,
			41,
			array(
				'hash' => 'automerge-current-base-freeform-conversion',
			)
		);
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge freeform conversion retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:heading --><h2>Legacy collaboration note</h2><!-- /wp:heading -->'
			. "\n\n"
			. '<!-- wp:paragraph --><p>Google Docs and AbiWord both support collaboration.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client' );

		$this->assertSame( 'document.replace_unsupported', $client_update['operations'][0]['type'] );

		$request = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '41',
				'accepted_proof_server_version' => '41',
				'rebased_from_version'          => '41',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'automerge_client_update'       => $client_update,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertTrue( $data['automerge_update_applied'] );
		$this->assertSame( 'native-automerge-blocks-v1', $data['automerge_encoding'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '42', $parsed['sync_meta']['version'] );
		$this->assertSame( '41', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'native-automerge-blocks-v1', $parsed['sync_meta']['automerge_encoding'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertStringNotContainsString( '<h2>Legacy collaboration note</h2>' . "\n" . '<p>Google Docs', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 */
	public function test_retry_save_allows_author_safe_automerge_current_base_edit() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Automerge author safe current.</p><!-- /wp:paragraph -->';
		$current_content = $this->add_automerge_sync_meta_to_content(
			$base_content,
			25,
			array(
				'post_content_hash' => hash( 'sha256', $base_content ),
			)
		);
		$post_id         = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC Automerge author safe retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Automerge author safe proposed.</p><!-- /wp:paragraph -->';
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author' );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '25',
				'accepted_proof_server_version' => '25',
				'rebased_from_version'          => '25',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertSame( '26', $data['server_version'] );
		$this->assertSame( '25', $data['previous_server_version'] );
		$this->assertFalse( $data['can_export_local_updates'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $parsed['sync_meta_position'] );
		$this->assertSame( '26', $parsed['sync_meta']['version'] );
		$this->assertSame( '25', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( self::$author_user_id, $parsed['sync_meta']['last_server_update']['user_id'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertNotEmpty( $data['created_revision_ids'] );

		$created_revision = get_post( $data['created_revision_ids'][0] );
		$this->assertNotNull( $created_revision );
		$this->assertStringContainsString( '<script ', $created_revision->post_content );

		$parsed_revision = wp_de_rtc_parse_post_content_sync_meta( $created_revision->post_content );
		$this->assertIsArray( $parsed_revision );
		$this->assertSame( $proposed_content, $parsed_revision['content'] );
		$this->assertSame( '26', $parsed_revision['sync_meta']['version'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_modified_kses_block_change_requires_review
	 */
	public function test_retry_save_allows_author_text_edit_when_existing_paragraph_markup_is_kses_normalized() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Original text with <mark style="background-color:rgba(0, 0, 0, 0)" class="has-inline-color has-accent-3-color">expression</mark>.</p><!-- /wp:paragraph -->';
		$current_content = $this->add_automerge_sync_meta_to_content(
			$base_content,
			27,
			array(
				'post_content_hash' => hash( 'sha256', $base_content ),
			)
		);
		$post_id         = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC Automerge author safe KSES-normalized markup retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Updated text with <mark style="background-color:rgba(0, 0, 0, 0)" class="has-inline-color has-accent-3-color">expression</mark>.</p><!-- /wp:paragraph -->';
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author' );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '27',
				'accepted_proof_server_version' => '27',
				'rebased_from_version'          => '27',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertSame( '28', $data['server_version'] );
		$this->assertFalse( $data['can_export_local_updates'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '28', $parsed['sync_meta']['version'] );
		$this->assertStringContainsString( 'Updated text', $parsed['content'] );
		$this->assertStringContainsString( 'background-color:rgba(0, 0, 0, 0)', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_canonicalize_post_content_core_block_names
	 * @covers ::wp_de_rtc_hash_content
	 */
	public function test_retry_save_treats_explicit_core_namespace_as_canonical_equivalent() {
		$this->require_automerge_runtime();

		$base_content           = '<!-- wp:paragraph --><p>Automerge namespace base.</p><!-- /wp:paragraph -->';
		$explicit_base_content  = $this->add_explicit_core_namespace_to_paragraph_blocks( $base_content );
		$current_content        = str_replace(
			$base_content,
			$explicit_base_content,
			$this->add_automerge_sync_meta_to_content(
				$base_content,
				27,
				array(
					'post_content_hash' => hash( 'sha256', $base_content ),
				)
			)
		);
		$current_content        = str_replace( 'wp:sync-meta', 'wp:core/sync-meta', $current_content );
		$post_id                = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge explicit core namespace retry save post',
				'post_content' => $current_content,
			)
		);
		$canonical_proposed     = '<!-- wp:paragraph --><p>Automerge namespace proposed.</p><!-- /wp:paragraph -->';
		$explicit_proposed      = $this->add_explicit_core_namespace_to_paragraph_blocks( $canonical_proposed );
		$client_update          = wp_de_rtc_create_automerge_update_for_content_change( $explicit_base_content, $explicit_proposed, 'test-client' );
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '27',
				'accepted_proof_server_version' => '27',
				'rebased_from_version'          => '27',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $explicit_proposed,
				'proposed_post_content_hash'    => hash( 'sha256', $explicit_proposed ),
				'automerge_client_update'             => $client_update,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertSame( wp_de_rtc_hash_content( $canonical_proposed ), $data['proposed_post_content_hash'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $canonical_proposed, $parsed['content'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertStringNotContainsString( 'wp:core/paragraph', $after_post->post_content );
		$this->assertStringNotContainsString( 'wp:core/sync-meta', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_retry_save_rejects_automerge_update_that_does_not_materialize_proposed_content() {
		$this->require_automerge_runtime();

		$base_content     = '<!-- wp:paragraph --><p>Automerge mismatch current.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_automerge_sync_meta_to_content( $base_content, 31 );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge mismatch retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Automerge mismatch proposed.</p><!-- /wp:paragraph -->';
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '31',
				'accepted_proof_server_version' => '31',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => array(
					'format'      => 'native-automerge-php-v1',
					'operations'  => array(),
					'stateVector' => array(),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'automerge_client_update_materialization_mismatch', $data['detail'] );
		$this->assert_post_unchanged( $post_id, $current_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_preserve_automerge_sync_meta_on_post_update
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_wp_update_post_preserves_automerge_top_pseudo_block_metadata() {
		$this->require_automerge_runtime();

		$base_content     = '<!-- wp:paragraph --><p>Automerge direct update current.</p><!-- /wp:paragraph -->';
		$current_content  = $this->add_automerge_sync_meta_to_content( $base_content, 41 );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge direct update post',
				'post_content' => $current_content,
			)
		);
		$updated_content  = '<!-- wp:paragraph --><p>Automerge direct update saved.</p><!-- /wp:paragraph -->';

		$updated_post_id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				)
			),
			true
		);
		$after_post      = get_post( $post_id );
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( $post_id, $updated_post_id );
		$this->assertIsArray( $parsed );
		$this->assertSame( $updated_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $parsed['sync_meta_position'] );
		$this->assertSame( '42', $parsed['sync_meta']['version'] );
		$this->assertSame( '41', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'de-rtc-automerge-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( 'wp_update_post', $parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_preserve_automerge_sync_meta_on_post_update
	 * @covers ::wp_de_rtc_canonicalize_post_content_core_block_names
	 */
	public function test_wp_update_post_canonicalizes_explicit_core_namespace() {
		$this->require_automerge_runtime();

		$base_content          = '<!-- wp:paragraph --><p>Automerge direct namespace current.</p><!-- /wp:paragraph -->';
		$explicit_base_content = $this->add_explicit_core_namespace_to_paragraph_blocks( $base_content );
		$current_content       = str_replace(
			$base_content,
			$explicit_base_content,
			$this->add_automerge_sync_meta_to_content( $base_content, 43 )
		);
		$current_content       = str_replace( 'wp:sync-meta', 'wp:core/sync-meta', $current_content );
		$post_id               = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge direct explicit core namespace post',
				'post_content' => $current_content,
			)
		);
		$updated_content       = '<!-- wp:paragraph --><p>Automerge direct namespace saved.</p><!-- /wp:paragraph -->';
		$explicit_updated      = $this->add_explicit_core_namespace_to_paragraph_blocks( $updated_content );

		$updated_post_id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $explicit_updated,
				)
			),
			true
		);
		$after_post      = get_post( $post_id );
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( $post_id, $updated_post_id );
		$this->assertIsArray( $parsed );
		$this->assertSame( $updated_content, $parsed['content'] );
		$this->assertSame( '44', $parsed['sync_meta']['version'] );
		$this->assertSame( '43', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'wp_update_post', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertStringNotContainsString( 'wp:core/paragraph', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_repaired_automerge_current_post_snapshot
	 * @covers ::wp_de_rtc_get_automerge_external_repair_update
	 */
	public function test_rest_post_update_repairs_external_body_drift_before_automerge_merge() {
		$this->require_automerge_runtime();

		global $wpdb;

		$base_content   = '<!-- wp:paragraph --><p>Automerge external drift base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge external drift base second.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>Automerge external drift base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge external drift server second.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>Automerge external drift local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge external drift base second.</p><!-- /wp:paragraph -->';
		$merged_content = '<!-- wp:paragraph --><p>Automerge external drift local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge external drift server second.</p><!-- /wp:paragraph -->';
		$base_meta      = array(
			'post_content_hash'   => hash( 'sha256', $base_content ),
			'last_server_update'  => array(
				'type'                        => 'test_seed',
				'saved_stripped_content_hash' => hash( 'sha256', $base_content ),
			),
		);
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge external drift REST post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 101, $base_meta ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		$stale_server_content = $this->add_automerge_sync_meta_to_content( $server_content, 101, $base_meta );
		$updated_rows         = $wpdb->update(
			$wpdb->posts,
			array(
				'post_content' => $stale_server_content,
			),
			array(
				'ID' => $post_id,
			)
		);

		$this->assertSame( 1, $updated_rows );
		clean_post_cache( $post_id );

		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			101,
			array(
				'client_base_version'  => '101',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( '103', $parsed['sync_meta']['version'] );
		$this->assertSame( '102', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'body_hash_drift', $parsed['sync_meta']['last_server_update']['external_repair']['mode'] );
		$this->assertSame( '101', $parsed['sync_meta']['last_server_update']['external_repair']['base_version'] );
		$this->assertSame( '102', $parsed['sync_meta']['last_server_update']['external_repair']['repaired_version'] );
		$this->assertSame( hash( 'sha256', $base_content ), $parsed['sync_meta']['last_server_update']['external_repair']['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $server_content ), $parsed['sync_meta']['last_server_update']['external_repair']['current_content_hash'] );
		$this->assertSame( 'system', $parsed['sync_meta']['last_server_update']['external_repair']['repair_actor']['actor_type'] );
		$this->assertSame( 'system:distributed-editing-recovery', $parsed['sync_meta']['last_server_update']['external_repair']['repair_actor']['attribution_key'] );
		$this->assertSame( 'system', $parsed['sync_meta']['last_sync_meta_recovery']['actor']['actor_type'] );
		$this->assertArrayNotHasKey( 'pending_automerge_update', $parsed['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_preserve_automerge_sync_meta_on_post_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_repaired_automerge_current_post_snapshot
	 */
	public function test_wp_update_post_repairs_external_body_drift_before_automerge_merge() {
		$this->require_automerge_runtime();

		global $wpdb;

		$base_content   = '<!-- wp:paragraph --><p>Automerge direct external base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct external base second.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>Automerge direct external base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct external server second.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>Automerge direct external local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct external base second.</p><!-- /wp:paragraph -->';
		$merged_content = '<!-- wp:paragraph --><p>Automerge direct external local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct external server second.</p><!-- /wp:paragraph -->';
		$base_meta      = array(
			'post_content_hash'  => hash( 'sha256', $base_content ),
			'last_server_update' => array(
				'type'                        => 'test_seed',
				'saved_stripped_content_hash' => hash( 'sha256', $base_content ),
			),
		);
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge external drift direct post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 111, $base_meta ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		$stale_server_content = $this->add_automerge_sync_meta_to_content( $server_content, 111, $base_meta );
		$updated_rows         = $wpdb->update(
			$wpdb->posts,
			array(
				'post_content' => $stale_server_content,
			),
			array(
				'ID' => $post_id,
			)
		);

		$this->assertSame( 1, $updated_rows );
		clean_post_cache( $post_id );

		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			111,
			array(
				'client_base_version'  => '111',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);

		$updated_post_id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $incoming_content,
				)
			),
			true
		);
		$after_post      = get_post( $post_id );
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( $post_id, $updated_post_id );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( '113', $parsed['sync_meta']['version'] );
		$this->assertSame( '112', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'wp_update_post', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'body_hash_drift', $parsed['sync_meta']['last_server_update']['external_repair']['mode'] );
		$this->assertSame( '112', $parsed['sync_meta']['last_server_update']['external_repair']['repaired_version'] );
		$this->assertSame( 'system', $parsed['sync_meta']['last_server_update']['external_repair']['repair_actor']['actor_type'] );
		$this->assertSame( 'system', $parsed['sync_meta']['last_sync_meta_recovery']['actor']['actor_type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_rest_post_update_saves_raw_post_content_with_automerge_sync_meta_block() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Automerge REST current.</p><!-- /wp:paragraph -->';
		$current_content = $this->add_automerge_sync_meta_to_content( $base_content, 51 );
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge REST update post',
				'post_content' => $current_content,
			)
		);
		$updated_content = '<!-- wp:paragraph --><p>Automerge REST saved.</p><!-- /wp:paragraph -->';
		$client_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $updated_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$updated_content,
			51,
			array(
				'client_base_version' => '51',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update' => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request         = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $updated_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $parsed['sync_meta_position'] );
		$this->assertSame( '52', $parsed['sync_meta']['version'] );
		$this->assertSame( '51', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertArrayNotHasKey( 'pending_automerge_update', $parsed['sync_meta'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_canonicalize_post_content_core_block_names
	 */
	public function test_rest_post_update_canonicalizes_explicit_core_namespace() {
		$this->require_automerge_runtime();

		$base_content          = '<!-- wp:paragraph --><p>Automerge REST namespace current.</p><!-- /wp:paragraph -->';
		$explicit_base_content = $this->add_explicit_core_namespace_to_paragraph_blocks( $base_content );
		$current_content       = str_replace(
			$base_content,
			$explicit_base_content,
			$this->add_automerge_sync_meta_to_content( $base_content, 55 )
		);
		$current_content       = str_replace( 'wp:sync-meta', 'wp:core/sync-meta', $current_content );
		$post_id               = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge REST explicit core namespace post',
				'post_content' => $current_content,
			)
		);
		$updated_content       = '<!-- wp:paragraph --><p>Automerge REST namespace saved.</p><!-- /wp:paragraph -->';
		$explicit_updated      = $this->add_explicit_core_namespace_to_paragraph_blocks( $updated_content );
		$client_update         = wp_de_rtc_create_automerge_update_for_content_change( $explicit_base_content, $explicit_updated, 'test-client' );
		$incoming_content      = $this->add_automerge_sync_meta_to_content(
			$explicit_updated,
			55,
			array(
				'client_base_version'  => '55',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$incoming_content      = str_replace( 'wp:sync-meta', 'wp:core/sync-meta', $incoming_content );
		$request               = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $updated_content, $parsed['content'] );
		$this->assertSame( '56', $parsed['sync_meta']['version'] );
		$this->assertSame( '55', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $after_post->post_content );
		$this->assertStringNotContainsString( 'wp:core/paragraph', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_rest_post_update_accepts_current_base_automerge_raw_update_with_legacy_zero_client_base_version() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Automerge REST current zero base.</p><!-- /wp:paragraph -->';
		$current_content = $this->add_automerge_sync_meta_to_content( $base_content, 91 );
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge REST zero base update post',
				'post_content' => $current_content,
			)
		);
		$updated_content = '<!-- wp:paragraph --><p>Automerge REST zero base saved.</p><!-- /wp:paragraph -->';
		$client_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $updated_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$updated_content,
			0,
			array(
				'client_base_version'  => '0',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request         = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $updated_content, $parsed['content'] );
		$this->assertSame( '92', $parsed['sync_meta']['version'] );
		$this->assertSame( '91', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '91', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertArrayNotHasKey( 'pending_automerge_update', $parsed['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_rest_post_update_merges_stale_automerge_raw_post_content_against_base_revision() {
		$this->require_automerge_runtime();

		$base_content   = '<!-- wp:paragraph --><p>Automerge REST stale base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge REST stale base second.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>Automerge REST stale base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge REST stale server second.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>Automerge REST stale local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge REST stale base second.</p><!-- /wp:paragraph -->';
		$merged_content = '<!-- wp:paragraph --><p>Automerge REST stale local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge REST stale server second.</p><!-- /wp:paragraph -->';
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge stale REST update post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 61 ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $server_content,
				)
			),
			true
		);

		$advanced_post    = get_post( $post_id );
		$advanced_parsed  = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			61,
			array(
				'client_base_version'  => '61',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$this->assertSame( '62', $advanced_parsed['sync_meta']['version'] );
		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( '63', $parsed['sync_meta']['version'] );
		$this->assertSame( '62', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '61', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertArrayHasKey( 'base_revision_id', $parsed['sync_meta']['last_server_update'] );
		$this->assertArrayNotHasKey( 'pending_automerge_update', $parsed['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_get_rich_text_serialized_block_merge_candidate
	 */
	public function test_rest_post_update_merges_stale_automerge_paragraph_text_over_remote_formatting() {
		$this->require_automerge_runtime();

		$base_content   = '<!-- wp:paragraph --><p>Some pretext to a post.</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$server_content = '<!-- wp:paragraph --><p>Some <em>pretext</em> to a post.</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$local_content  = '<!-- wp:paragraph --><p>Some pretext to a WordPress post.</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$merged_content = '<!-- wp:paragraph --><p>Some <em>pretext</em> to a WordPress post.</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge stale paragraph text and formatting REST update post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 83 ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $server_content,
				)
			),
			true
		);

		$advanced_post    = get_post( $post_id );
		$advanced_parsed  = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			83,
			array(
				'client_base_version'        => '83',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$this->assertSame( '84', $advanced_parsed['sync_meta']['version'] );
		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( '85', $parsed['sync_meta']['version'] );
		$this->assertSame( '84', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '83', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_get_rich_text_serialized_block_merge_candidate
	 */
	public function test_rest_post_update_merges_stale_automerge_non_overlapping_paragraph_word_edits() {
		$this->require_automerge_runtime();

		$base_content   = '<!-- wp:paragraph --><p>The blue river meets the quiet forest.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>The silver river meets the quiet forest.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>The blue river meets the green forest.</p><!-- /wp:paragraph -->';
		$merged_content = '<!-- wp:paragraph --><p>The silver river meets the green forest.</p><!-- /wp:paragraph -->';
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge stale paragraph word merge REST update post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 91 ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $server_content,
				)
			),
			true
		);

		$advanced_post   = get_post( $post_id );
		$advanced_parsed = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );
		$client_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			91,
			array(
				'client_base_version'        => '91',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request         = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$this->assertSame( '92', $advanced_parsed['sync_meta']['version'] );
		$this->assertSame( 'block.rich_text_content', $client_update['operations'][0]['type'] );
		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( 'automerge', $parsed['sync_meta_format'] );
		$this->assertSame( '93', $parsed['sync_meta']['version'] );
		$this->assertSame( '92', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '91', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( 'rest_post_update', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertArrayNotHasKey( 'pending_automerge_update', $parsed['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_preserve_automerge_sync_meta_on_post_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_wp_update_post_merges_stale_automerge_raw_post_content_against_base_revision() {
		$this->require_automerge_runtime();

		$base_content   = '<!-- wp:paragraph --><p>Automerge direct stale base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct stale base second.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>Automerge direct stale base first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct stale server second.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>Automerge direct stale local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct stale base second.</p><!-- /wp:paragraph -->';
		$merged_content = '<!-- wp:paragraph --><p>Automerge direct stale local first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Automerge direct stale server second.</p><!-- /wp:paragraph -->';
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge stale direct update post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 71 ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $server_content,
				)
			),
			true
		);

		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			71,
			array(
				'client_base_version'  => '71',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);

		$updated_post_id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $incoming_content,
				)
			),
			true
		);
		$after_post      = get_post( $post_id );
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( $post_id, $updated_post_id );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( '73', $parsed['sync_meta']['version'] );
		$this->assertSame( '72', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '71', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( 'wp_update_post', $parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_prepare_automerge_raw_post_content_update
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 */
	public function test_rest_post_update_rejects_overlapping_stale_automerge_raw_post_content_without_write() {
		$this->require_automerge_runtime();

		$base_content   = '<!-- wp:paragraph --><p>Automerge overlap base.</p><!-- /wp:paragraph -->';
		$server_content = '<!-- wp:paragraph --><p>Automerge overlap server.</p><!-- /wp:paragraph -->';
		$local_content  = '<!-- wp:paragraph --><p>Automerge overlap local.</p><!-- /wp:paragraph -->';
		$post_id        = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge overlap REST update post',
				'post_content' => $this->add_automerge_sync_meta_to_content( $base_content, 81 ),
			)
		);

		$this->assertIsInt( wp_save_post_revision( $post_id ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $server_content,
				)
			),
			true
		);

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $local_content, 'test-client' );
		$incoming_content = $this->add_automerge_sync_meta_to_content(
			$local_content,
			81,
			array(
				'client_base_version'  => '81',
				'pending_automerge_encoding' => 'native-automerge-blocks-v1',
				'pending_automerge_update'   => base64_encode( wp_json_encode( $client_update, JSON_UNESCAPED_SLASHES ) ),
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );

		$request->set_body_params(
			array(
				'content' => $incoming_content,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $response, 409 );
		$this->assertSame( 'retry_save_server_merge_same_serialized_block_changed', $data['detail'] );
		$this->assertSame( 'top_level_serialized_block_three_way', $data['server_merge_strategy'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_get_automerge_idempotent_block_insert_merge_result
	 */
	public function test_retry_save_absorbs_automerge_duplicate_insert_and_preserves_second_edit() {
		$this->require_automerge_runtime();

		$alpha_block      = '<!-- wp:paragraph --><p>Demo content alpha.</p><!-- /wp:paragraph -->';
		$beta_block       = '<!-- wp:paragraph --><p>Demo content beta.</p><!-- /wp:paragraph -->';
		$gamma_block      = '<!-- wp:paragraph --><p>Demo content gamma.</p><!-- /wp:paragraph -->';
		$duplicate_block  = '<!-- wp:paragraph --><p>Duplicated content!</p><!-- /wp:paragraph -->';
		$edited_beta      = '<!-- wp:paragraph --><p>Demo content beta, edited by Client B.</p><!-- /wp:paragraph -->';
		$base_content     = $alpha_block . $beta_block . $gamma_block;
		$server_content   = $alpha_block . $duplicate_block . $beta_block . $gamma_block;
		$proposed_content = $alpha_block . $duplicate_block . $edited_beta . $gamma_block;
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC Automerge duplicate insert plus retained edit retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					201,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$server_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $server_content, 'test-client-a' );
		$first_request   = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '201',
				'accepted_proof_server_version' => '201',
				'rebased_from_version'          => '201',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $server_content,
				'proposed_post_content_hash'    => hash( 'sha256', $server_content ),
				'automerge_client_update'             => $server_update,
			)
		);
		$first_response  = rest_get_server()->dispatch( $first_request );
		$advanced_post   = get_post( $post_id );
		$advanced_parsed = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );
		$client_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client-b' );
		$request         = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '201',
				'accepted_proof_server_version' => '201',
				'rebased_from_version'          => '201',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( '202', $advanced_parsed['sync_meta']['version'] );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertTrue( $data['automerge_update_applied'] );
		$this->assertSame( 'native_automerge_blocks_v1', $data['server_merge']['merge_strategy'] );
		$this->assertSame( 1, $data['server_merge']['server_changed_block_count'] );
		$this->assertSame( 1, $data['server_merge']['local_changed_block_count'] );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '203', $parsed['sync_meta']['version'] );
		$this->assertSame( '202', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( '201', $parsed['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $parsed['sync_meta']['post_content_hash'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_automerge_retry_save_result
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_absorbs_automerge_duplicate_insert_but_rejects_unsafe_second_edit_for_review() {
		$this->require_automerge_runtime();

		$alpha_block      = '<!-- wp:paragraph --><p>Demo content alpha.</p><!-- /wp:paragraph -->';
		$beta_block       = '<!-- wp:html --><div>Demo content beta.</div><!-- /wp:html -->';
		$gamma_block      = '<!-- wp:paragraph --><p>Demo content gamma.</p><!-- /wp:paragraph -->';
		$duplicate_block  = '<!-- wp:paragraph --><p>Duplicated content!</p><!-- /wp:paragraph -->';
		$unsafe_beta      = '<!-- wp:html --><script>alert("unsafe")</script><div>Demo content beta.</div><!-- /wp:html -->';
		$base_content     = implode( "\n\n", array( $alpha_block, $beta_block, $gamma_block ) );
		$server_content   = implode( "\n\n", array( $alpha_block, $duplicate_block, $beta_block, $gamma_block ) );
		$proposed_content = implode( "\n\n", array( $alpha_block, $duplicate_block, $unsafe_beta, $gamma_block ) );
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC Automerge duplicate insert plus unsafe retained edit retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					301,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$server_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $server_content, 'test-client-a' );
		$first_request    = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '301',
				'accepted_proof_server_version' => '301',
				'rebased_from_version'          => '301',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $server_content,
				'proposed_post_content_hash'    => hash( 'sha256', $server_content ),
				'automerge_client_update'             => $server_update,
			)
		);
		$first_response   = rest_get_server()->dispatch( $first_request );
		$advanced_post    = get_post( $post_id );
		$advanced_parsed  = wp_de_rtc_parse_post_content_sync_meta( $advanced_post->post_content );
		$advanced_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client-b' );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '301',
				'accepted_proof_server_version' => '301',
				'rebased_from_version'          => '301',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( '302', $advanced_parsed['sync_meta']['version'] );
		$this->assertSame( $server_content, $advanced_parsed['content'] );

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$error      = $response->as_error();
		$data       = $error->get_error_data( 'de_rtc_unfiltered_html_would_change_content' );
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'block_review_required', $data['result'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['reason_code'] );
		$this->assertSame( 'collaborative_unfiltered_html_review_required', $data['detail'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_no_write'] );
		$this->assertFalse( $data['partial_safe_merge_persisted'] );
		$this->assertSame( 'safe_subset_already_current', $data['partial_safe_merge_status'] );
		$this->assertSame( '302', $data['server_version'] );
		$this->assertSame( 1, $data['pending_change_count'] );
		$this->assertSame( 1, $data['review_item_count'] );
		$this->assertSame( 1, $data['pending_review_item_count'] );
		$this->assertTrue( $data['remaining_review_required'] );
		$this->assertFalse( $data['unsafe_raw_content_included'] );
		$this->assertSame( $server_content, wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] )['content'] );
		$this->assertSame( 'modified_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( hash( 'sha256', $unsafe_beta ), $data['review_items'][0]['proposed_content_hash'] );
		$this->assertSame( hash( 'sha256', $beta_block ), $data['review_items'][0]['base_content_hash'] );
		$this->assertSame( $server_content, $parsed['content'] );
		$this->assertSame( '302', $parsed['sync_meta']['version'] );
		$this->assertStringNotContainsString( '<script', $parsed['content'] );
		$this->assert_post_unchanged( $post_id, $advanced_post->post_content, $advanced_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_persists_safe_automerge_subset_while_rejecting_unsafe_block_for_review() {
		$this->require_automerge_runtime();

		$alpha_block      = '<!-- wp:paragraph --><p>Demo content alpha.</p><!-- /wp:paragraph -->';
		$beta_block       = '<!-- wp:html --><div>Demo content beta.</div><!-- /wp:html -->';
		$gamma_block      = '<!-- wp:paragraph --><p>Demo content gamma.</p><!-- /wp:paragraph -->';
		$duplicate_block  = '<!-- wp:paragraph --><p>Duplicated content!</p><!-- /wp:paragraph -->';
		$unsafe_beta      = '<!-- wp:html --><script>alert("unsafe")</script><div>Demo content beta.</div><!-- /wp:html -->';
		$edited_gamma     = '<!-- wp:paragraph --><p>Demo content gamma, safely edited by Client B.</p><!-- /wp:paragraph -->';
		$base_content     = implode( "\n\n", array( $alpha_block, $beta_block, $gamma_block ) );
		$server_content   = implode( "\n\n", array( $alpha_block, $duplicate_block, $beta_block, $gamma_block ) );
		$proposed_content = implode( "\n\n", array( $alpha_block, $duplicate_block, $unsafe_beta, $edited_gamma ) );
		$safe_content     = $alpha_block . $duplicate_block . $beta_block . $edited_gamma;
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC Automerge partial safe subset retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					401,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$server_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $server_content, 'test-client-a' );
		$first_request    = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '401',
				'accepted_proof_server_version' => '401',
				'rebased_from_version'          => '401',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $server_content,
				'proposed_post_content_hash'    => hash( 'sha256', $server_content ),
				'automerge_client_update'             => $server_update,
			)
		);
		$first_response   = rest_get_server()->dispatch( $first_request );
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-client-b' );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '401',
				'accepted_proof_server_version' => '401',
				'rebased_from_version'          => '401',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		$this->assertSame( 200, $first_response->get_status() );

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['reason_code'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_persisted'] );
		$this->assertFalse( $data['partial_safe_merge_no_write'] );
		$this->assertSame( 'safe_subset_persisted', $data['partial_safe_merge_status'] );
		$this->assertSame( '403', $data['server_version'] );
		$this->assertSame( '402', $data['previous_server_version'] );
		$this->assertSame( 1, $data['pending_change_count'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertSame( '403', $parsed['sync_meta']['version'] );
		$this->assertSame( '402', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_partial_safe_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( hash( 'sha256', $safe_content ), $parsed['sync_meta']['post_content_hash'] );
		$this->assertSame( $safe_content, wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] )['content'] );
		$this->assertStringContainsString( 'safely edited by Client B', $after_post->post_content );
		$this->assertStringNotContainsString( '<script', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 */
	public function test_retry_save_persists_safe_author_edit_when_current_content_has_unchanged_unsafe_html() {
		$existing_unsafe_block = '<!-- wp:html --><script>alert("existing")</script>Existing<!-- /wp:html -->';
		$safe_block            = '<!-- wp:paragraph --><p>Original safe paragraph.</p><!-- /wp:paragraph -->';
		$edited_safe_block     = '<!-- wp:paragraph --><p>Original safe paragraph, safely edited.</p><!-- /wp:paragraph -->';
		$base_content          = $existing_unsafe_block . $safe_block;
		$proposed_content      = $existing_unsafe_block . $edited_safe_block;
		$post_id               = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC safe author retry save with existing HTML post',
				'post_content' => $this->add_sync_meta_to_content(
					$base_content,
					451,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$request               = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '451',
				'accepted_proof_server_version' => '451',
				'rebased_from_version'          => '451',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '452', $parsed['sync_meta']['version'] );
		$this->assertStringContainsString( '<script>alert("existing")</script>Existing', $parsed['content'] );
		$this->assertStringContainsString( 'safely edited', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_added_kses_block_change_requires_review
	 */
	public function test_retry_save_persists_safe_author_separator_insert_when_kses_normalizes_void_tag_spacing() {
		$base_content      = '<!-- wp:paragraph --><p>Original safe paragraph.</p><!-- /wp:paragraph -->';
		$separator_block   = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
		$proposed_content  = $base_content . "\n" . $separator_block;
		$post_id           = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC author safe separator retry save post',
				'post_content' => $this->add_sync_meta_to_content(
					$base_content,
					455,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$request           = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '455',
				'accepted_proof_server_version' => '455',
				'rebased_from_version'          => '455',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['retry_save_accepted'] );
		$this->assertArrayNotHasKey( 'reason_code', $data );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '456', $parsed['sync_meta']['version'] );
		$this->assertStringContainsString( '<!-- wp:separator -->', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_partial_safe_merge_preserves_existing_unsafe_html_while_saving_safe_author_edit() {
		$existing_unsafe_block = '<!-- wp:html --><script>alert("existing")</script>Existing<!-- /wp:html -->';
		$paragraph_block       = '<!-- wp:paragraph --><p>Lead paragraph.</p><!-- /wp:paragraph -->';
		$list_block            = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$safe_list_block       = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Cheese</em></li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$new_unsafe_block      = '<!-- wp:html --><script>alert("new")</script>Script<!-- /wp:html -->';
		$base_content          = $existing_unsafe_block . $paragraph_block . $list_block;
		$proposed_content      = $existing_unsafe_block . $paragraph_block . $new_unsafe_block . $safe_list_block;
		$safe_content          = $existing_unsafe_block . $paragraph_block . $safe_list_block;
		$post_id               = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC partial safe retry save with existing HTML post',
				'post_content' => $this->add_sync_meta_to_content(
					$base_content,
					461,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$request               = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '461',
				'accepted_proof_server_version' => '461',
				'rebased_from_version'          => '461',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_persisted'] );
		$this->assertSame( 'safe_subset_persisted', $data['partial_safe_merge_status'] );
		$this->assertSame( 1, $data['review_item_count'] );
		$this->assertSame( 'added_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertSame( '462', $parsed['sync_meta']['version'] );
		$this->assertStringContainsString( '<script>alert("existing")</script>Existing', $parsed['content'] );
		$this->assertStringContainsString( '<em>Cheese</em>', $parsed['content'] );
		$this->assertStringNotContainsString( 'alert("new")', $parsed['content'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_persists_safe_formatting_while_rejecting_added_unsafe_html_block_for_review() {
		$this->require_automerge_runtime();

		$first_block      = '<!-- wp:paragraph --><p>This is a paragraph, safe.</p><!-- /wp:paragraph -->';
		$second_block     = '<!-- wp:paragraph --><p>It contains:</p><!-- /wp:paragraph -->';
		$list_block       = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$safe_list_block  = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Eggs</li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Cheese</em></li><!-- /wp:list-item --><!-- wp:list-item --><li>Mayo</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$unsafe_block     = '<!-- wp:html --><script>alert(1);</script>Script<!-- /wp:html -->';
		$base_content     = implode( "\n\n", array( $first_block, $second_block, $list_block ) );
		$proposed_content = implode( "\n\n", array( $first_block, $unsafe_block, $second_block, $safe_list_block ) );
		$safe_content     = $first_block . $second_block . $safe_list_block;
		$post_id          = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC partial safe added unsafe HTML block retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					501,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$client_update    = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author' );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '501',
				'accepted_proof_server_version' => '501',
				'rebased_from_version'          => '501',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $data['reason_code'] );
		$this->assertSame( 'collaborative_unfiltered_html_review_required', $data['detail'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_persisted'] );
		$this->assertFalse( $data['partial_safe_merge_no_write'] );
		$this->assertSame( 'safe_subset_persisted', $data['partial_safe_merge_status'] );
		$this->assertSame( '502', $data['server_version'] );
		$this->assertSame( '501', $data['previous_server_version'] );
		$this->assertSame( 1, $data['pending_change_count'] );
		$this->assertSame( 1, $data['review_item_count'] );
		$this->assertSame( 1, $data['pending_review_item_count'] );
		$this->assertTrue( $data['remaining_review_required'] );
		$this->assertFalse( $data['unsafe_raw_content_included'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertSame( '502', $parsed['sync_meta']['version'] );
		$this->assertSame( '501', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_partial_safe_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( hash( 'sha256', $safe_content ), $parsed['sync_meta']['post_content_hash'] );
		$this->assertSame( $safe_content, wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] )['content'] );
		$this->assertStringContainsString( '<em>Cheese</em>', $parsed['content'] );
		$this->assertStringNotContainsString( '<script', $parsed['content'] );
		$this->assertSame( 'added_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( 'HTML', $data['review_items'][0]['block_label'] );
		$this->assertSame( hash( 'sha256', '' ), $data['review_items'][0]['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $unsafe_block ), $data['review_items'][0]['proposed_content_hash'] );
		$this->assertSame( 'queued', $data['review_item_queue_status'] );
		$this->assertSame( 1, $data['review_item_pending_count'] );
		$this->assertCount( 1, $data['review_item_descriptors'] );
		$this->assertStringStartsWith( 'de-rtc-review-', $data['review_item_descriptors'][0]['reviewItemId'] );
		$this->assertFalse( $data['review_item_descriptors'][0]['rawContentIncluded'] );
		$this->assertFalse( $data['review_item_descriptors'][0]['canApprove'] );
		$this->assertFalse( $data['review_item_descriptors'][0]['canModifyAdopt'] );
		$this->assertFalse( $data['review_item_descriptors'][0]['canReject'] );
		$this->assertTrue( $data['review_item_descriptors'][0]['canDiscard'] );
		$this->assertArrayNotHasKey( 'proposedSourceDisplay', $data['review_item_descriptors'][0] );

		$review_item_id = $data['review_item_descriptors'][0]['reviewItemId'];
		$author_list_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items' );
		$author_list_response = rest_get_server()->dispatch( $author_list_request );
		$author_list_data     = $author_list_response->get_data();

		$this->assertSame( 200, $author_list_response->get_status() );
		$this->assertStringContainsString( 'no-store', $author_list_response->get_headers()['Cache-Control'] );
		$this->assertSame( 'review_items_loaded', $author_list_data['result'] );
		$this->assertCount( 1, $author_list_data['items'] );
		$this->assertSame( 1, $author_list_data['pendingReviewItemCount'] );
		$this->assertSame( 1, $author_list_data['postPendingReviewItemCount'] );
		$this->assertSame( $review_item_id, $author_list_data['items'][0]['reviewItemId'] );
		$this->assertNotEmpty( $author_list_data['items'][0]['createdAtGmt'] );
		$this->assertStringNotContainsString( '0000', $author_list_data['items'][0]['createdAtGmt'] );
		$this->assertNotEmpty( $author_list_data['items'][0]['updatedAtGmt'] );
		$this->assertNotEmpty( $author_list_data['items'][0]['expiresAtGmt'] );
		$this->assertFalse( $author_list_data['items'][0]['rawContentIncluded'] );
		$this->assertFalse( $author_list_data['items'][0]['canApprove'] );
		$this->assertFalse( $author_list_data['items'][0]['canModifyAdopt'] );
		$this->assertFalse( $author_list_data['items'][0]['canReject'] );
		$this->assertTrue( $author_list_data['items'][0]['canDiscard'] );
		$this->assertArrayNotHasKey( 'proposedSourceDisplay', $author_list_data['items'][0] );

		$author_detail_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $review_item_id );
		$author_detail_response = rest_get_server()->dispatch( $author_detail_request );

		$this->assertSame( 403, $author_detail_response->get_status() );
		$this->assertStringContainsString( 'no-store', $author_detail_response->get_headers()['Cache-Control'] );

		$other_author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_author    = get_userdata( $other_author_id );
		$other_author->add_cap( 'edit_others_posts' );
		$other_author->add_cap( 'edit_published_posts' );
		wp_set_current_user( $other_author_id );

		$other_author_list_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items' );
		$other_author_list_response = rest_get_server()->dispatch( $other_author_list_request );
		$other_author_list_data     = $other_author_list_response->get_data();

		$this->assertSame( 200, $other_author_list_response->get_status() );
		$this->assertSame( 'review_items_loaded', $other_author_list_data['result'] );
		$this->assertCount( 0, $other_author_list_data['items'] );
		$this->assertSame( 0, $other_author_list_data['pendingReviewItemCount'] );
		$this->assertSame( 1, $other_author_list_data['postPendingReviewItemCount'] );

		wp_set_current_user( self::$admin_user_id );

		$admin_detail_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $review_item_id );
		$admin_detail_response = rest_get_server()->dispatch( $admin_detail_request );
		$admin_detail_data     = $admin_detail_response->get_data();

		$this->assertSame( 200, $admin_detail_response->get_status() );
		$this->assertStringContainsString( 'no-store', $admin_detail_response->get_headers()['Cache-Control'] );
		$this->assertSame( 'review_item_loaded', $admin_detail_data['result'] );
		$this->assertSame( 'html_escaped_text', $admin_detail_data['item']['contentTransport'] );
		$this->assertTrue( $admin_detail_data['item']['canApprove'] );
		$this->assertTrue( $admin_detail_data['item']['canModifyAdopt'] );
		$this->assertTrue( $admin_detail_data['item']['canReject'] );
		$this->assertFalse( $admin_detail_data['item']['canDiscard'] );
		$this->assertStringContainsString( '&lt;script&gt;alert(1);&lt;/script&gt;Script', $admin_detail_data['item']['proposedSourceDisplay'] );
		$this->assertStringNotContainsString( '<script>alert(1);</script>', $admin_detail_data['item']['proposedSourceDisplay'] );
		$this->assertFalse( $admin_detail_data['rawContentIncluded'] );

		$reject_request  = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $review_item_id . '/reject' );
		$reject_response = rest_get_server()->dispatch( $reject_request );
		$reject_data     = $reject_response->get_data();
		$repeat_reject_response = rest_get_server()->dispatch( $reject_request );
		$repeat_reject_data     = $repeat_reject_response->get_data();

		$this->assertSame( 200, $reject_response->get_status() );
		$this->assertStringContainsString( 'no-store', $reject_response->get_headers()['Cache-Control'] );
		$this->assertSame( 'review_item_rejected', $reject_data['result'] );
		$this->assertSame( 'rejected', $reject_data['item']['status'] );
		$this->assertFalse( $reject_data['mutatesPostContent'] );
		$this->assertSame( 200, $repeat_reject_response->get_status() );
		$this->assertTrue( $repeat_reject_data['idempotent'] );
		$rejected_detail_response = rest_get_server()->dispatch( $admin_detail_request );

		$this->assertSame( 404, $rejected_detail_response->get_status() );
		$this->assertStringContainsString( 'no-store', $rejected_detail_response->get_headers()['Cache-Control'] );

		wp_set_current_user( self::$author_user_id );

		$repeat_before_post      = get_post( $post_id );
		$repeat_before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$repeat_response         = rest_get_server()->dispatch( $request );
		$repeat_data             = $repeat_response->get_data();

		$this->assertSame( 200, $repeat_response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $repeat_data['result'] );
		$this->assertTrue( $repeat_data['retry_save_duplicate'] );
		$this->assertTrue( $repeat_data['idempotent_no_write'] );
		$this->assertTrue( $repeat_data['remaining_review_required'] );
		$this->assertTrue( $repeat_data['partial_safe_merge_applied'] );
		$this->assertTrue( $repeat_data['partial_safe_merge_no_write'] );
		$this->assertFalse( $repeat_data['saves_post'] );
		$this->assertFalse( $repeat_data['mutates_post_content'] );
		$this->assertFalse( $repeat_data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $repeat_before_post->post_content, $repeat_before_revisions );

		wp_set_current_user( self::$admin_user_id );

		$old_detail_response = rest_get_server()->dispatch( $admin_detail_request );

		$this->assertSame( 404, $old_detail_response->get_status() );

		wp_set_current_user( self::$author_user_id );

			$requeue_result = wp_de_rtc_record_review_required_items(
				$post_id,
				$data['review_items'],
			array(
				'base_post_content'     => $base_content,
				'proposed_post_content' => $proposed_content,
				'server_version'        => '502',
				'client_base_version'   => '501',
				)
			);

				$this->assertSame( 'queued', $requeue_result['review_item_queue_status'] );
				$this->assertSame( 1, $requeue_result['review_item_queued_count'] );
				$this->assertSame( 1, $requeue_result['review_item_pending_count'] );
				$this->assertCount( 1, $requeue_result['review_item_descriptors'] );
				$this->assertNotSame( $review_item_id, $requeue_result['review_item_descriptors'][0]['reviewItemId'] );
				$this->assertSame( 'pending', $requeue_result['review_item_descriptors'][0]['status'] );
				$this->assertFalse( $requeue_result['review_item_descriptors'][0]['canApprove'] );
				$this->assertFalse( $requeue_result['review_item_descriptors'][0]['canModifyAdopt'] );
				$this->assertFalse( $requeue_result['review_item_descriptors'][0]['canReject'] );
				$this->assertTrue( $requeue_result['review_item_descriptors'][0]['canDiscard'] );

			$approve_unsafe_block     = '<!-- wp:html --><script>alert(2);</script>Script<!-- /wp:html -->';
			$approve_proposed_content = implode( "\n\n", array( $first_block, $approve_unsafe_block, $second_block, $safe_list_block ) );
			$approve_classification   = wp_de_rtc_classify_kses_risky_block_review_items(
				$post_id,
				$approve_proposed_content,
				array(
					'base_post_content'          => $safe_content,
					'server_version'             => '502',
					'client_base_version'        => '502',
					'user_can_unfiltered_html'   => false,
					'author_id'                  => self::$author_user_id,
				)
			);

			$this->assertSame( 'block_review_required', $approve_classification['result'] );

			$approve_queue = wp_de_rtc_record_review_required_items(
				$post_id,
				$approve_classification['review_items'],
				array(
					'base_post_content'     => $safe_content,
					'proposed_post_content' => $approve_proposed_content,
					'server_version'        => '502',
					'client_base_version'   => '502',
				)
			);

			$this->assertSame( 'queued', $approve_queue['review_item_queue_status'] );
			$this->assertSame( 1, $approve_queue['review_item_queued_count'] );
			$this->assertSame( 1, $approve_queue['review_item_pending_count'] );

			wp_set_current_user( self::$admin_user_id );

			$approve_review_item_id = $approve_queue['review_item_descriptors'][0]['reviewItemId'];
			$approve_request        = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $approve_review_item_id . '/approve' );
			$approve_response       = rest_get_server()->dispatch( $approve_request );
		$approve_data           = $approve_response->get_data();
		clean_post_cache( $post_id );
		$approved_post          = get_post( $post_id );
		$approved_parsed        = wp_de_rtc_parse_post_content_sync_meta( $approved_post->post_content );

		$this->assertSame( 200, $approve_response->get_status() );
		$this->assertStringContainsString( 'no-store', $approve_response->get_headers()['Cache-Control'] );
		$this->assertSame( 'review_item_approved', $approve_data['result'] );
		$this->assertTrue( $approve_data['savesPost'] );
			$this->assertTrue( $approve_data['mutatesPostContent'] );
			$this->assertSame( 'approved', $approve_data['item']['status'] );
			$this->assertFalse( $approve_data['item']['canApprove'] );
			$this->assertFalse( $approve_data['item']['canModifyAdopt'] );
			$this->assertFalse( $approve_data['item']['canReject'] );
			$this->assertFalse( $approve_data['item']['canDiscard'] );
			$this->assertStringContainsString( '<script>alert(2);</script>Script', $approved_parsed['content'] );
			$this->assertStringContainsString( '<em>Cheese</em>', $approved_parsed['content'] );
			$this->assertSame( 'review_item_approve', $approved_parsed['sync_meta']['last_server_update']['type'] );
		}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_apply_review_item_resolution_to_post
	 */
	public function test_review_approval_inserts_added_unsafe_block_before_safely_modified_neighbor() {
		$this->require_automerge_runtime();

		$paragraph_block = '<!-- wp:paragraph --><p>This is a paragraph, safe.</p><!-- /wp:paragraph -->';
		$list_block      = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Bread</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cheese</li><!-- /wp:list-item --><!-- wp:list-item --><li>Tomato</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$safe_list_block = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Bread</li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Cheese</em></li><!-- /wp:list-item --><!-- wp:list-item --><li>Tomato</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$unsafe_block    = '<!-- wp:html --><script>alert(1);</script>Script<!-- /wp:html -->';
		$reviewed_block  = '<!-- wp:html --><div class="reviewed">Reviewed HTML</div><!-- /wp:html -->';
		$base_content    = $paragraph_block . $list_block;
		$proposed_content = $paragraph_block . $unsafe_block . $safe_list_block;
		$safe_content    = $paragraph_block . $safe_list_block;
		$expected_reviewed_content = $paragraph_block . $reviewed_block . $safe_list_block;
		$post_id         = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC partial safe inserted unsafe HTML before modified list',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					701,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$client_update   = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author' );
		$request         = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '701',
				'accepted_proof_server_version' => '701',
				'rebased_from_version'          => '701',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertSame( 'added_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( array( 1 ), $data['review_items'][0]['block_path'] );
		$this->assertSame( hash( 'sha256', '' ), $data['review_items'][0]['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $unsafe_block ), $data['review_items'][0]['proposed_content_hash'] );

		wp_set_current_user( self::$admin_user_id );

		$modify_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $data['review_item_descriptors'][0]['reviewItemId'] . '/modify-adopt' );
		$modify_request->set_param( 'reviewed_block_source', $reviewed_block );
		$modify_response = rest_get_server()->dispatch( $modify_request );
		$modify_data     = $modify_response->get_data();
		clean_post_cache( $post_id );
		$modified_post   = get_post( $post_id );
		$modified_parsed = wp_de_rtc_parse_post_content_sync_meta( $modified_post->post_content );

		$this->assertSame( 200, $modify_response->get_status() );
		$this->assertSame( 'review_item_modified_adopted', $modify_data['result'] );
		$this->assertSame( $expected_reviewed_content, $modified_parsed['content'] );
		$this->assertStringContainsString( '<em>Cheese</em>', $modified_parsed['content'] );
		$this->assertStringContainsString( '<div class="reviewed">Reviewed HTML</div>', $modified_parsed['content'] );
		$this->assertStringNotContainsString( '<script>alert(1);</script>', $modified_parsed['content'] );
		$this->assertSame( 'review_item_modify_adopt', $modified_parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_get_kses_partial_safe_top_level_candidate
	 * @covers ::wp_de_rtc_remove_block_at_path
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_persists_safe_edits_while_rejecting_nested_unsafe_html_only() {
		$this->require_automerge_runtime();

		$paragraph_block      = '<!-- wp:paragraph --><p>Safe paragraph.</p><!-- /wp:paragraph -->';
		$nested_group_block   = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><p>Nested base.</p></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$list_block           = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --><!-- wp:list-item --><li>Three</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$safe_list_block      = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Two</em></li><!-- /wp:list-item --><!-- wp:list-item --><li>Three</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$unsafe_html_block    = '<!-- wp:html --><script>alert(1);</script>Script<!-- /wp:html -->';
		$unsafe_nested_group  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><p>Nested base.</p>' . $unsafe_html_block . '</blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$base_content         = $paragraph_block . $nested_group_block . $list_block;
		$proposed_content     = $paragraph_block . $unsafe_nested_group . $safe_list_block;
		$safe_content         = $paragraph_block . $nested_group_block . $safe_list_block;
		$post_id              = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC nested partial safe retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					801,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$client_update        = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author-nested' );
		$request              = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '801',
				'accepted_proof_server_version' => '801',
				'rebased_from_version'          => '801',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_persisted'] );
		$this->assertSame( 'safe_subset_persisted', $data['partial_safe_merge_status'] );
		$this->assertSame( 1, $data['review_item_count'] );
		$this->assertSame( 1, $data['pending_review_item_count'] );
		$this->assertSame( 'core/html', $data['review_items'][0]['block_name'] );
		$this->assertSame( array( 1, 0, 0 ), $data['review_items'][0]['block_path'] );
		$this->assertSame( 'added_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( hash( 'sha256', $unsafe_html_block ), $data['review_items'][0]['proposed_content_hash'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertSame( $safe_content, wp_de_rtc_parse_post_content_sync_meta( $data['content']['raw'] )['content'] );
		$this->assertStringContainsString( '<em>Two</em>', $parsed['content'] );
		$this->assertStringNotContainsString( '<script', $parsed['content'] );
		$this->assertSame( '802', $parsed['sync_meta']['version'] );
		$this->assertSame( 'retry_save_partial_safe_merge', $parsed['sync_meta']['last_server_update']['type'] );

		wp_set_current_user( self::$admin_user_id );

		$admin_list_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items' );
		$admin_list_response = rest_get_server()->dispatch( $admin_list_request );
		$admin_list_data     = $admin_list_response->get_data();

		$this->assertSame( 200, $admin_list_response->get_status() );
		$this->assertCount( 1, $admin_list_data['items'] );
		$this->assertSame( 'HTML', $admin_list_data['items'][0]['blockLabel'] );
		$this->assertTrue( $admin_list_data['items'][0]['canApprove'] );
		$this->assertTrue( $admin_list_data['items'][0]['canModifyAdopt'] );
		$this->assertTrue( $admin_list_data['items'][0]['canReject'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_get_kses_partial_safe_retry_save_plan
	 * @covers ::wp_de_rtc_get_kses_partial_safe_top_level_candidate
	 * @covers ::wp_de_rtc_remove_block_at_path
	 * @covers ::wp_de_rtc_apply_partial_safe_retry_save_plan
	 */
	public function test_retry_save_persists_safe_edits_when_nested_unsafe_html_is_inserted_before_retained_sibling() {
		$this->require_automerge_runtime();

		$paragraph_block     = '<!-- wp:paragraph --><p>Safe paragraph.</p><!-- /wp:paragraph -->';
		$nested_group_block  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Nested first.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Nested second.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$list_block          = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$safe_list_block     = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li><em>Two</em></li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$unsafe_html_block   = '<!-- wp:html --><script>alert(1);</script>Script<!-- /wp:html -->';
		$unsafe_nested_group = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Nested first.</p><!-- /wp:paragraph -->' . $unsafe_html_block . '<!-- wp:paragraph --><p>Nested second.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$base_content        = $paragraph_block . $nested_group_block . $list_block;
		$proposed_content    = $paragraph_block . $unsafe_nested_group . $safe_list_block;
		$safe_content        = $paragraph_block . $nested_group_block . $safe_list_block;
		$post_id             = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC nested partial safe before sibling retry save post',
				'post_content' => $this->add_automerge_sync_meta_to_content(
					$base_content,
					811,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$client_update       = wp_de_rtc_create_automerge_update_for_content_change( $base_content, $proposed_content, 'test-author-nested-before-sibling' );
		$request             = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '811',
				'accepted_proof_server_version' => '811',
				'rebased_from_version'          => '811',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'automerge_client_update'             => $client_update,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_partial_safe_merge', $data['result'] );
		$this->assertTrue( $data['partial_safe_merge_applied'] );
		$this->assertTrue( $data['partial_safe_merge_persisted'] );
		$this->assertSame( 1, $data['review_item_count'] );
		$this->assertSame( 'core/html', $data['review_items'][0]['block_name'] );
		$this->assertSame( array( 1, 0, 1 ), $data['review_items'][0]['block_path'] );
		$this->assertSame( 'added_block', $data['review_items'][0]['change_kind'] );
		$this->assertSame( hash( 'sha256', $unsafe_html_block ), $data['review_items'][0]['proposed_content_hash'] );
		$this->assertSame( $safe_content, $parsed['content'] );
		$this->assertStringContainsString( '<em>Two</em>', $parsed['content'] );
		$this->assertStringContainsString( '<p>Nested second.</p>', $parsed['content'] );
		$this->assertStringNotContainsString( '<script', $parsed['content'] );
		$this->assertSame( '812', $parsed['sync_meta']['version'] );
	}

	/**
	 * @covers ::wp_de_rtc_record_review_required_items
	 * @covers ::wp_de_rtc_supersede_active_review_items_for_same_anchor
	 */
	public function test_review_item_modified_retry_supersedes_older_pending_item_for_same_block() {
		$base_content = '<!-- wp:paragraph --><p>Base paragraph.</p><!-- /wp:paragraph -->';
		$post_id      = self::factory()->post->create(
			array(
				'post_author'  => self::$author_user_id,
				'post_title'   => 'DE-RTC modified review retry post',
				'post_content' => $this->add_sync_meta_to_content(
					$base_content,
					601,
					array(
						'post_content_hash' => hash( 'sha256', $base_content ),
					)
				),
			)
		);
		$unsafe_first  = '<!-- wp:html --><script>alert(1);</script>First<!-- /wp:html -->';
		$unsafe_second = '<!-- wp:html --><script>alert(2);</script>Second<!-- /wp:html -->';

		wp_set_current_user( self::$author_user_id );

		$first_review = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$unsafe_first,
			array(
				'client_base_version' => '601',
				'author_id'           => self::$author_user_id,
			)
		);
		$first_queue  = wp_de_rtc_record_review_required_items(
			$post_id,
			$first_review['review_items'],
			array(
				'base_post_content'     => $base_content,
				'proposed_post_content' => $unsafe_first,
				'server_version'        => '601',
				'client_base_version'   => '601',
			)
		);

		$this->assertSame( 'queued', $first_queue['review_item_queue_status'] );
		$this->assertSame( 1, $first_queue['review_item_pending_count'] );

		$second_review = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$unsafe_second,
			array(
				'client_base_version' => '601',
				'author_id'           => self::$author_user_id,
			)
		);
		$second_queue  = wp_de_rtc_record_review_required_items(
			$post_id,
			$second_review['review_items'],
			array(
				'base_post_content'     => $base_content,
				'proposed_post_content' => $unsafe_second,
				'server_version'        => '601',
				'client_base_version'   => '601',
			)
		);

		$this->assertSame( 'queued', $second_queue['review_item_queue_status'] );
		$this->assertSame( 1, $second_queue['review_item_pending_count'] );
		$this->assertSame( 1, $second_queue['review_item_queue_results'][0]['supersededCount'] );

		$list_request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items' );
		$list_response = rest_get_server()->dispatch( $list_request );
		$list_data     = $list_response->get_data();

		$this->assertSame( 200, $list_response->get_status() );
		$this->assertCount( 1, $list_data['items'] );
		$this->assertSame( $second_queue['review_item_descriptors'][0]['reviewItemId'], $list_data['items'][0]['reviewItemId'] );

		global $wpdb;

		$table_sql = wp_de_rtc_get_review_items_table_sql();
		$statuses  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS item_count FROM $table_sql WHERE post_id = %d GROUP BY status",
				$post_id
			),
			ARRAY_A
		);
		$counts    = array();
		foreach ( $statuses as $status_row ) {
			$counts[ $status_row['status'] ] = (int) $status_row['item_count'];
		}

		$this->assertSame( 1, $counts['pending'] );
		$this->assertSame( 1, $counts['superseded'] );

		wp_set_current_user( self::$admin_user_id );

		$modify_request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/review-items/' . $second_queue['review_item_descriptors'][0]['reviewItemId'] . '/modify-adopt' );
		$modify_request->set_param(
			'reviewed_block_source',
			'<!-- wp:html --><div class="reviewed">Reviewed HTML</div><!-- /wp:html -->'
		);
		$modify_response = rest_get_server()->dispatch( $modify_request );
		$modify_data     = $modify_response->get_data();
		clean_post_cache( $post_id );
		$modified_post   = get_post( $post_id );
		$modified_parsed = wp_de_rtc_parse_post_content_sync_meta( $modified_post->post_content );

		$this->assertSame( 200, $modify_response->get_status() );
		$this->assertStringContainsString( 'no-store', $modify_response->get_headers()['Cache-Control'] );
		$this->assertSame( 'review_item_modified_adopted', $modify_data['result'] );
		$this->assertSame( 'modified_adopted', $modify_data['item']['status'] );
		$this->assertStringContainsString( '<div class="reviewed">Reviewed HTML</div>', $modified_parsed['content'] );
		$this->assertStringNotContainsString( '<script>alert(2);</script>', $modified_parsed['content'] );
		$this->assertSame( 'review_item_modify_adopt', $modified_parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_validate_retry_save_block_identity_request_proof
	 * @covers ::wp_de_rtc_apply_block_identity_request_proof_to_sync_meta
	 * @covers ::wp_de_rtc_generate_inserted_block_identity_uid
	 */
	public function test_retry_save_applies_block_identity_request_proof_to_sync_meta() {
		$block_a          = '<!-- wp:paragraph --><p>Identity block A.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity block B.</p><!-- /wp:paragraph -->';
		$inserted_block   = '<!-- wp:paragraph --><p>Identity inserted block.</p><!-- /wp:paragraph -->';
		$current_stripped = $block_a . $block_b;
		$proposed_content = $block_a . $inserted_block . $block_b;
		$current_content  = $this->add_sync_meta_to_content(
			$current_stripped,
			41,
			$this->get_block_identity_sync_meta_for_blocks(
				$current_stripped,
				array(
					'block-a' => $block_a,
					'block-b' => $block_b,
				)
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '41',
				'accepted_proof_server_version' => '41',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '41',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'inserted_block_nonce' => 'inserted-1',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 1 ),
							'serialized_hash'      => hash( 'sha256', $inserted_block ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array( 'inserted-1' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-b' ),
				),
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertTrue( $data['block_identity_request_proof_validated'] );
		$this->assertSame( 'valid', $data['block_identity_request_proof']['status'] );
		$this->assertSame( 3, $data['block_identity_request_proof']['proposed_block_count'] );
		$this->assertSame( 1, $data['block_identity_request_proof']['inserted_block_count'] );
		$this->assertFalse( $data['block_identity_request_proof']['saves_post'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( 'de-rtc-block-identity-v1', $parsed['sync_meta']['schema'] );
		$this->assertSame( $proposed_hash, $parsed['sync_meta']['content_hash'] );
		$this->assertCount( 3, $parsed['sync_meta']['blocks'] );
		$this->assertSame( 'block-a', $parsed['sync_meta']['blocks'][0]['block_uid'] );
		$this->assertStringStartsWith( 'block-', $parsed['sync_meta']['blocks'][1]['block_uid'] );
		$this->assertNotSame( 'inserted-1', $parsed['sync_meta']['blocks'][1]['block_uid'] );
		$this->assertSame( array( 1 ), $parsed['sync_meta']['blocks'][1]['ordinal_path'] );
		$this->assertSame( hash( 'sha256', $inserted_block ), $parsed['sync_meta']['blocks'][1]['serialized_hash'] );
		$this->assertSame( 'block-b', $parsed['sync_meta']['blocks'][2]['block_uid'] );
		$this->assertSame( 'valid', $parsed['sync_meta']['last_server_update']['block_identity_request_proof']['status'] );
		$this->assertSame( 1, $parsed['sync_meta']['last_server_update']['block_identity_request_proof']['inserted_block_count'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_block_identity_sync_meta_stable_map_matches
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof_matches_proposed_content
	 * @covers ::wp_de_rtc_validate_retry_save_block_identity_request_proof
	 * @covers ::wp_de_rtc_apply_block_identity_request_proof_to_sync_meta
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_block_identity_middle_insertion_when_server_body_unchanged() {
		$block_a          = '<!-- wp:paragraph --><p>Identity stale base A.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity stale base B.</p><!-- /wp:paragraph -->';
		$inserted_block   = '<!-- wp:paragraph --><p>Identity stale inserted middle.</p><!-- /wp:paragraph -->';
		$base_content     = $block_a . "\n\n" . $block_b;
		$proposed_content = $block_a . "\n\n" . $inserted_block . "\n\n" . $block_b;
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_content, 41, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity stale retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$base_content,
			42,
			array_merge(
				$base_sync_meta,
				array(
					'previous_version' => '41',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '41',
				'accepted_proof_server_version' => '41',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '41',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'inserted_block_nonce' => 'inserted-middle',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 1 ),
							'serialized_hash'      => hash( 'sha256', $inserted_block ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array( 'inserted-middle' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-b' ),
				),
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
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertTrue( $data['block_identity_request_proof_validated'] );
		$this->assertSame( '41', $data['client_base_version'] );
		$this->assertSame( '41', $data['accepted_proof_server_version'] );
		$this->assertSame( '42', $data['previous_server_version'] );
		$this->assertSame( '43', $data['server_version'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge']['merge_strategy'] );
		$this->assertTrue( $data['server_merge']['block_identity_base_current_match'] );
		$this->assertSame( 2, $data['server_merge']['base_block_count'] );
		$this->assertSame( 2, $data['server_merge']['server_block_count'] );
		$this->assertSame( 3, $data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $data['server_merge']['merged_block_count'] );
		$this->assertSame( array(), $data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 1 ), $data['server_merge']['local_changed_indexes'] );
		$this->assertSame( array( 1 ), $data['server_merge']['block_identity_inserted_indexes'] );
		$this->assertSame( 1, $data['server_merge']['block_identity_inserted_block_count'] );
		$this->assertSame( 1, $data['server_merge']['block_identity_moved_block_count'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$data['created_revision_ids']
		);
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '43', $parsed['sync_meta']['version'] );
		$this->assertSame( '42', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $parsed['sync_meta']['last_server_update']['server_merge']['merge_strategy'] );
		$this->assertTrue( $parsed['sync_meta']['last_server_update']['server_merge']['block_identity_base_current_match'] );
		$this->assertCount( 3, $parsed['sync_meta']['blocks'] );
		$this->assertSame( 'block-a', $parsed['sync_meta']['blocks'][0]['block_uid'] );
		$this->assertStringStartsWith( 'block-', $parsed['sync_meta']['blocks'][1]['block_uid'] );
		$this->assertSame( 'block-b', $parsed['sync_meta']['blocks'][2]['block_uid'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_insertions_only_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_current_sequence_for_merge
	 * @covers ::wp_de_rtc_get_block_identity_proposed_sequence_for_merge
	 * @covers ::wp_de_rtc_get_block_identity_insertions_by_gap
	 * @covers ::wp_de_rtc_apply_block_identity_request_proof_to_sync_meta
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_block_identity_insertions_in_distinct_gaps() {
		$block_a               = '<!-- wp:paragraph --><p>Identity distinct gap A.</p><!-- /wp:paragraph -->';
		$block_b               = '<!-- wp:paragraph --><p>Identity distinct gap B.</p><!-- /wp:paragraph -->';
		$block_c               = '<!-- wp:paragraph --><p>Identity distinct gap C.</p><!-- /wp:paragraph -->';
		$server_inserted_block = '<!-- wp:paragraph --><p>Identity server inserted after A.</p><!-- /wp:paragraph -->';
		$local_inserted_block  = '<!-- wp:paragraph --><p>Identity local inserted after B.</p><!-- /wp:paragraph -->';
		$base_content          = $block_a . $block_b . $block_c;
		$server_content        = $block_a . $server_inserted_block . $block_b . $block_c;
		$proposed_content      = $block_a . $block_b . $local_inserted_block . $block_c;
		$merged_content        = $block_a . $server_inserted_block . $block_b . $local_inserted_block . $block_c;
		$base_sync_meta        = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
				'block-c' => $block_c,
			)
		);
		$current_content       = $this->add_sync_meta_to_content( $base_content, 71, $base_sync_meta );
		$post_id               = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity distinct insertion retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id      = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			72,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-a'        => $block_a,
						'block-server-s' => $server_inserted_block,
						'block-b'        => $block_b,
						'block-c'        => $block_c,
					)
				),
				array(
					'previous_version' => '71',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$merged_hash            = hash( 'sha256', $merged_content );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '71',
				'accepted_proof_server_version' => '71',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '71',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 1 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
						array(
							'inserted_block_nonce' => 'inserted-local-after-b',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 2 ),
							'serialized_hash'      => hash( 'sha256', $local_inserted_block ),
						),
						array(
							'block_uid'       => 'block-c',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 3 ),
							'serialized_hash' => hash( 'sha256', $block_c ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b', 'block-c' ),
					'inserted_block_nonces'      => array( 'inserted-local-after-b' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-c' ),
				),
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
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertTrue( $data['block_identity_request_proof_validated'] );
		$this->assertSame( '71', $data['client_base_version'] );
		$this->assertSame( '71', $data['accepted_proof_server_version'] );
		$this->assertSame( '72', $data['previous_server_version'] );
		$this->assertSame( '73', $data['server_version'] );
		$this->assertSame( $merged_hash, $data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge']['merge_strategy'] );
		$this->assertFalse( $data['server_merge']['block_identity_base_current_match'] );
		$this->assertTrue( $data['server_merge']['block_identity_base_current_insertions_only'] );
		$this->assertSame( 3, $data['server_merge']['base_block_count'] );
		$this->assertSame( 4, $data['server_merge']['server_block_count'] );
		$this->assertSame( 4, $data['server_merge']['proposed_block_count'] );
		$this->assertSame( 5, $data['server_merge']['merged_block_count'] );
		$this->assertSame( array( 1 ), $data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 3 ), $data['server_merge']['local_changed_indexes'] );
		$this->assertSame( array( 1 ), $data['server_merge']['block_identity_server_inserted_indexes'] );
		$this->assertSame( 1, $data['server_merge']['block_identity_server_inserted_block_count'] );
		$this->assertSame( array( 3 ), $data['server_merge']['block_identity_inserted_indexes'] );
		$this->assertSame( 1, $data['server_merge']['block_identity_inserted_block_count'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$data['created_revision_ids']
		);
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( '73', $parsed['sync_meta']['version'] );
		$this->assertSame( '72', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $parsed['sync_meta']['last_server_update']['server_merge']['merge_strategy'] );
		$this->assertTrue( $parsed['sync_meta']['last_server_update']['server_merge']['block_identity_base_current_insertions_only'] );
		$this->assertCount( 5, $parsed['sync_meta']['blocks'] );
		$this->assertSame( 'block-a', $parsed['sync_meta']['blocks'][0]['block_uid'] );
		$this->assertSame( 'block-server-s', $parsed['sync_meta']['blocks'][1]['block_uid'] );
		$this->assertSame( 'block-b', $parsed['sync_meta']['blocks'][2]['block_uid'] );
		$this->assertStringStartsWith( 'block-', $parsed['sync_meta']['blocks'][3]['block_uid'] );
		$this->assertNotSame( 'inserted-local-after-b', $parsed['sync_meta']['blocks'][3]['block_uid'] );
		$this->assertSame( 'block-c', $parsed['sync_meta']['blocks'][4]['block_uid'] );
		$this->assertSame( array( 4 ), $parsed['sync_meta']['blocks'][4]['ordinal_path'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_insertions_only_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_current_sequence_for_merge
	 * @covers ::wp_de_rtc_get_block_identity_proposed_sequence_for_merge
	 * @covers ::wp_de_rtc_get_block_identity_insertions_by_gap
	 * @covers ::wp_de_rtc_get_block_identity_insertions_only_conflict
	 */
	public function test_retry_save_rejects_block_identity_insertions_in_same_gap_without_mutating() {
		$block_a               = '<!-- wp:paragraph --><p>Identity same gap A.</p><!-- /wp:paragraph -->';
		$block_b               = '<!-- wp:paragraph --><p>Identity same gap B.</p><!-- /wp:paragraph -->';
		$server_inserted_block = '<!-- wp:paragraph --><p>Identity same gap server inserted.</p><!-- /wp:paragraph -->';
		$local_inserted_block  = '<!-- wp:paragraph --><p>Identity same gap local inserted.</p><!-- /wp:paragraph -->';
		$base_content          = $block_a . $block_b;
		$server_content        = $block_a . $server_inserted_block . $block_b;
		$proposed_content      = $block_a . $local_inserted_block . $block_b;
		$base_sync_meta        = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
			)
		);
		$current_content       = $this->add_sync_meta_to_content( $base_content, 81, $base_sync_meta );
		$post_id               = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity same-gap insertion retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id      = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			82,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-a'        => $block_a,
						'block-server-s' => $server_inserted_block,
						'block-b'        => $block_b,
					)
				),
				array(
					'previous_version' => '81',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '81',
				'accepted_proof_server_version' => '81',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '81',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'inserted_block_nonce' => 'inserted-local-after-a',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 1 ),
							'serialized_hash'      => hash( 'sha256', $local_inserted_block ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array( 'inserted-local-after-a' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-b' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $response, 409 );
		$this->assertSame( 'retry_save_server_merge_block_identity_inserted_block_gap_conflict', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge_strategy'] );
		$this->assertFalse( $data['block_identity_base_current_match'] );
		$this->assertFalse( $data['block_identity_base_current_insertions_only'] );
		$this->assertSame( 2, $data['base_block_count'] );
		$this->assertSame( 3, $data['server_block_count'] );
		$this->assertSame( 3, $data['proposed_block_count'] );
		$this->assertSame( 1, $data['block_identity_conflicting_gap_index'] );
		$this->assertSame( 1, $data['block_identity_server_inserted_block_count_in_gap'] );
		$this->assertSame( 1, $data['block_identity_local_inserted_block_count_in_gap'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_retained_edits_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_current_sequence_for_merge
	 * @covers ::wp_de_rtc_get_block_identity_proposed_sequence_for_merge
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof_matches_proposed_content
	 * @covers ::wp_de_rtc_apply_block_identity_request_proof_to_sync_meta
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_block_identity_retained_edits_in_distinct_blocks() {
		$block_a          = '<!-- wp:paragraph --><p>Identity retained distinct A.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity retained distinct B.</p><!-- /wp:paragraph -->';
		$block_c          = '<!-- wp:paragraph --><p>Identity retained distinct C.</p><!-- /wp:paragraph -->';
		$server_block_a   = '<!-- wp:paragraph --><p>Identity retained distinct A from server.</p><!-- /wp:paragraph -->';
		$local_block_c    = '<!-- wp:paragraph --><p>Identity retained distinct C from local.</p><!-- /wp:paragraph -->';
		$base_content     = $block_a . $block_b . $block_c;
		$server_content   = $server_block_a . $block_b . $block_c;
		$proposed_content = $block_a . $block_b . $local_block_c;
		$merged_content   = $server_block_a . $block_b . $local_block_c;
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
				'block-c' => $block_c,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_content, 91, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity distinct retained edit retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			92,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-a' => $server_block_a,
						'block-b' => $block_b,
						'block-c' => $block_c,
					)
				),
				array(
					'previous_version' => '91',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$merged_hash            = hash( 'sha256', $merged_content );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '91',
				'accepted_proof_server_version' => '91',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '91',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 1 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
						array(
							'block_uid'       => 'block-c',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $local_block_c ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b', 'block-c' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
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
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertTrue( $data['block_identity_request_proof_validated'] );
		$this->assertSame( '91', $data['client_base_version'] );
		$this->assertSame( '91', $data['accepted_proof_server_version'] );
		$this->assertSame( '92', $data['previous_server_version'] );
		$this->assertSame( '93', $data['server_version'] );
		$this->assertSame( $merged_hash, $data['saved_stripped_post_content_hash'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge']['merge_strategy'] );
		$this->assertFalse( $data['server_merge']['block_identity_base_current_match'] );
		$this->assertFalse( $data['server_merge']['block_identity_base_current_insertions_only'] );
		$this->assertTrue( $data['server_merge']['block_identity_base_current_retained_edits_only'] );
		$this->assertSame( 3, $data['server_merge']['base_block_count'] );
		$this->assertSame( 3, $data['server_merge']['server_block_count'] );
		$this->assertSame( 3, $data['server_merge']['proposed_block_count'] );
		$this->assertSame( 3, $data['server_merge']['merged_block_count'] );
		$this->assertSame( array( 0 ), $data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 2 ), $data['server_merge']['local_changed_indexes'] );
		$this->assertSame( array( 0, 2 ), $data['server_merge']['block_identity_retained_edit_indexes'] );
		$this->assertSame( 2, $data['server_merge']['block_identity_retained_edit_block_count'] );
		$this->assertSame(
			array_values( array_diff( array_map( 'intval', array_keys( $after_revisions ) ), array_map( 'intval', array_keys( $before_retry_revisions ) ) ) ),
			$data['created_revision_ids']
		);
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_content, $parsed['content'] );
		$this->assertSame( '93', $parsed['sync_meta']['version'] );
		$this->assertSame( '92', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save_server_merge', $parsed['sync_meta']['last_server_update']['type'] );
		$this->assertTrue( $parsed['sync_meta']['last_server_update']['server_merge']['block_identity_base_current_retained_edits_only'] );
		$this->assertCount( 3, $parsed['sync_meta']['blocks'] );
		$this->assertSame( 'block-a', $parsed['sync_meta']['blocks'][0]['block_uid'] );
		$this->assertSame( hash( 'sha256', $server_block_a ), $parsed['sync_meta']['blocks'][0]['serialized_hash'] );
		$this->assertSame( 'block-b', $parsed['sync_meta']['blocks'][1]['block_uid'] );
		$this->assertSame( hash( 'sha256', $block_b ), $parsed['sync_meta']['blocks'][1]['serialized_hash'] );
		$this->assertSame( 'block-c', $parsed['sync_meta']['blocks'][2]['block_uid'] );
		$this->assertSame( hash( 'sha256', $local_block_c ), $parsed['sync_meta']['blocks'][2]['serialized_hash'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_retained_edits_server_merge_result
	 * @covers ::wp_de_rtc_get_table_cell_serialized_block_merge_candidate
	 * @covers ::wp_de_rtc_get_table_cell_serialized_block_model
	 * @covers ::wp_de_rtc_get_public_server_merge_evidence
	 */
	public function test_retry_save_server_merges_block_identity_table_cell_edits_in_distinct_cells() {
		$base_table      = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1</td><td>A2</td></tr><tr><td>B1</td><td>B2</td></tr></tbody></table></figure><!-- /wp:table -->';
		$server_table    = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1 from server</td><td>A2</td></tr><tr><td>B1</td><td>B2</td></tr></tbody></table></figure><!-- /wp:table -->';
		$local_table     = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1</td><td>A2</td></tr><tr><td>B1</td><td>B2 from local</td></tr></tbody></table></figure><!-- /wp:table -->';
		$merged_table    = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1 from server</td><td>A2</td></tr><tr><td>B1</td><td>B2 from local</td></tr></tbody></table></figure><!-- /wp:table -->';
		$base_content    = $base_table;
		$server_content  = $server_table;
		$proposed_content = $local_table;
		$base_sync_meta  = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-table' => $base_table,
			)
		);
		$current_content = $this->add_sync_meta_to_content( $base_content, 101, $base_sync_meta );
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity table cell retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			102,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-table' => $server_table,
					)
				),
				array(
					'previous_version' => '101',
				)
			)
		);
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

		$proposed_hash = hash( 'sha256', $proposed_content );
		$merged_hash   = hash( 'sha256', $merged_table );
		$request       = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '101',
				'accepted_proof_server_version' => '101',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '101',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-table',
							'block_name'      => 'core/table',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $local_table ),
						),
					),
					'retained_block_uids'        => array( 'block-table' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertSame( $merged_hash, $data['saved_stripped_post_content_hash'] );
		$this->assertSame( array( 0 ), $data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['server_merge']['local_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['server_merge']['table_cell_merged_indexes'] );
		$this->assertSame( 1, $data['server_merge']['table_cell_merged_block_count'] );
		$this->assertSame(
			array(
				array(
					'block_index'  => 0,
					'cell_index'   => 0,
					'row_index'    => 0,
					'column_index' => 0,
				),
			),
			$data['server_merge']['table_cell_server_changed_cells']
		);
		$this->assertSame(
			array(
				array(
					'block_index'  => 0,
					'cell_index'   => 3,
					'row_index'    => 1,
					'column_index' => 1,
				),
			),
			$data['server_merge']['table_cell_local_changed_cells']
		);
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_table, $parsed['content'] );
		$this->assertSame( '103', $parsed['sync_meta']['version'] );
		$this->assertSame( hash( 'sha256', $merged_table ), $parsed['sync_meta']['blocks'][0]['serialized_hash'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_block_identity_retained_edits_server_merge_result
	 * @covers ::wp_de_rtc_get_rich_text_serialized_block_merge_candidate
	 */
	public function test_retry_save_server_merges_block_identity_paragraph_text_over_remote_formatting() {
		$base_block       = '<!-- wp:paragraph --><p>Some pretext to a post.</p><!-- /wp:paragraph -->';
		$server_block     = '<!-- wp:paragraph --><p>Some <em>pretext</em> to a post.</p><!-- /wp:paragraph -->';
		$local_block      = '<!-- wp:paragraph --><p>Some pretext to a WordPress post.</p><!-- /wp:paragraph -->';
		$merged_block     = '<!-- wp:paragraph --><p>Some <em>pretext</em> to a WordPress post.</p><!-- /wp:paragraph -->';
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_block,
			array(
				'block-paragraph' => $base_block,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_block, 106, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity rich text retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_block,
			107,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_block,
					array(
						'block-paragraph' => $server_block,
					)
				),
				array(
					'previous_version' => '106',
				)
			)
		);
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

		$proposed_hash = hash( 'sha256', $local_block );
		$merged_hash   = hash( 'sha256', $merged_block );
		$request       = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '106',
				'accepted_proof_server_version' => '106',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $local_block,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '106',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-paragraph',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $local_block ),
						),
					),
					'retained_block_uids'        => array( 'block-paragraph' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_server_merged', $data['result'] );
		$this->assertTrue( $data['server_merge_applied'] );
		$this->assertSame( $merged_hash, $data['saved_stripped_post_content_hash'] );
		$this->assertSame( array( 0 ), $data['server_merge']['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['server_merge']['local_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['server_merge']['rich_text_merged_indexes'] );
		$this->assertSame( 1, $data['server_merge']['rich_text_merged_block_count'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $merged_block, $parsed['content'] );
		$this->assertSame( '108', $parsed['sync_meta']['version'] );
		$this->assertSame( hash( 'sha256', $merged_block ), $parsed['sync_meta']['blocks'][0]['serialized_hash'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_block_identity_retained_edits_server_merge_result
	 * @covers ::wp_de_rtc_get_table_cell_serialized_block_merge_candidate
	 */
	public function test_retry_save_rejects_block_identity_table_cell_edits_in_same_cell_without_mutating() {
		$base_table       = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1</td><td>A2</td></tr></tbody></table></figure><!-- /wp:table -->';
		$server_table     = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1 from server</td><td>A2</td></tr></tbody></table></figure><!-- /wp:table -->';
		$local_table      = '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A1 from local</td><td>A2</td></tr></tbody></table></figure><!-- /wp:table -->';
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_table,
			array(
				'block-table' => $base_table,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_table, 104, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity same table cell retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_table,
			105,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_table,
					array(
						'block-table' => $server_table,
					)
				),
				array(
					'previous_version' => '104',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $local_table );
		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '104',
				'accepted_proof_server_version' => '104',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $local_table,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '104',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-table',
							'block_name'      => 'core/table',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $local_table ),
						),
					),
					'retained_block_uids'        => array( 'block-table' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertSame( 409, $response->get_status() );
		$this->assertWPError( $error );
		$this->assertSame( 'retry_save_server_merge_block_identity_retained_block_conflict', $data['detail'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_retained_edits_server_merge_result
	 * @covers ::wp_de_rtc_get_block_identity_insertions_only_conflict
	 */
	public function test_retry_save_rejects_block_identity_retained_edit_same_block_conflict_without_mutating() {
		$block_a          = '<!-- wp:paragraph --><p>Identity retained conflict A.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity retained conflict B.</p><!-- /wp:paragraph -->';
		$server_block_a   = '<!-- wp:paragraph --><p>Identity retained conflict A from server.</p><!-- /wp:paragraph -->';
		$local_block_a    = '<!-- wp:paragraph --><p>Identity retained conflict A from local.</p><!-- /wp:paragraph -->';
		$base_content     = $block_a . $block_b;
		$server_content   = $server_block_a . $block_b;
		$proposed_content = $local_block_a . $block_b;
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_content, 94, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity retained edit conflict retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			95,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-a' => $server_block_a,
						'block-b' => $block_b,
					)
				),
				array(
					'previous_version' => '94',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '94',
				'accepted_proof_server_version' => '94',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '94',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $local_block_a ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 1 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $response, 409 );
		$this->assertSame( 'retry_save_server_merge_block_identity_retained_block_conflict', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge_strategy'] );
		$this->assertFalse( $data['block_identity_base_current_match'] );
		$this->assertFalse( $data['block_identity_base_current_insertions_only'] );
		$this->assertFalse( $data['block_identity_base_current_retained_edits_only'] );
		$this->assertSame( 0, $data['block_index'] );
		$this->assertSame( 0, $data['conflicting_block_index'] );
		$this->assertSame( array( 0 ), $data['server_changed_indexes'] );
		$this->assertSame( array( 0 ), $data['local_changed_indexes'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_block_identity_sync_meta_stable_map_matches
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_block_identity_server_merge_when_server_body_changed_without_mutating() {
		$block_a          = '<!-- wp:paragraph --><p>Identity drift A.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity drift B.</p><!-- /wp:paragraph -->';
		$server_block_a   = '<!-- wp:paragraph --><p>Identity drift A from server.</p><!-- /wp:paragraph -->';
		$inserted_block   = '<!-- wp:paragraph --><p>Identity drift inserted middle.</p><!-- /wp:paragraph -->';
		$base_content     = $block_a . $block_b;
		$server_content   = $server_block_a . $block_b;
		$proposed_content = $block_a . $inserted_block . $block_b;
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_content, 51, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity drift retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$server_content,
			52,
			array_merge(
				$this->get_block_identity_sync_meta_for_blocks(
					$server_content,
					array(
						'block-a' => $server_block_a,
						'block-b' => $block_b,
					)
				),
				array(
					'previous_version' => '51',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '51',
				'accepted_proof_server_version' => '51',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '51',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
						array(
							'inserted_block_nonce' => 'inserted-middle',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 1 ),
							'serialized_hash'      => hash( 'sha256', $inserted_block ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array( 'inserted-middle' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-b' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $response, 409 );
		$this->assertSame( 'retry_save_server_merge_block_identity_base_drift', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge_strategy'] );
		$this->assertFalse( $data['block_identity_base_current_match'] );
		$this->assertSame( 2, $data['base_block_count'] );
		$this->assertSame( 2, $data['server_block_count'] );
		$this->assertSame( 3, $data['proposed_block_count'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_find_revision_with_sync_meta_version
	 * @covers ::wp_de_rtc_get_block_identity_server_merge_result
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof_matches_proposed_content
	 * @covers ::wp_de_rtc_get_server_merge_conflict_error
	 */
	public function test_retry_save_rejects_block_identity_retained_block_hash_mismatch_without_mutating() {
		$block_a          = '<!-- wp:paragraph --><p>Identity retained A.</p><!-- /wp:paragraph -->';
		$changed_block_a  = '<!-- wp:paragraph --><p>Identity retained A changed.</p><!-- /wp:paragraph -->';
		$block_b          = '<!-- wp:paragraph --><p>Identity retained B.</p><!-- /wp:paragraph -->';
		$inserted_block   = '<!-- wp:paragraph --><p>Identity retained inserted middle.</p><!-- /wp:paragraph -->';
		$base_content     = $block_a . $block_b;
		$proposed_content = $changed_block_a . $inserted_block . $block_b;
		$base_sync_meta   = $this->get_block_identity_sync_meta_for_blocks(
			$base_content,
			array(
				'block-a' => $block_a,
				'block-b' => $block_b,
			)
		);
		$current_content  = $this->add_sync_meta_to_content( $base_content, 61, $base_sync_meta );
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity retained mismatch retry save post',
				'post_content' => $current_content,
			)
		);
		$base_revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $base_revision_id );
		$this->assertGreaterThan( 0, $base_revision_id );

		$advanced_content = $this->add_sync_meta_to_content(
			$base_content,
			62,
			array_merge(
				$base_sync_meta,
				array(
					'previous_version' => '61',
				)
			)
		);
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

		$proposed_hash          = hash( 'sha256', $proposed_content );
		$before_retry_post      = get_post( $post_id );
		$before_retry_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request                = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '61',
				'accepted_proof_server_version' => '61',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
				'block_identity_request_proof'  => array(
					'client_base_version'        => '61',
					'proposed_post_content_hash' => $proposed_hash,
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $changed_block_a ),
						),
						array(
							'inserted_block_nonce' => 'inserted-middle',
							'block_name'           => 'core/paragraph',
							'ordinal_path'         => array( 1 ),
							'serialized_hash'      => hash( 'sha256', $inserted_block ),
						),
						array(
							'block_uid'       => 'block-b',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 2 ),
							'serialized_hash' => hash( 'sha256', $block_b ),
						),
					),
					'retained_block_uids'        => array( 'block-a', 'block-b' ),
					'inserted_block_nonces'      => array( 'inserted-middle' ),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array( 'block-b' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_rebase_failed' );

		$this->assertErrorResponse( 'de_rtc_rebase_failed', $response, 409 );
		$this->assertSame( 'retry_save_server_merge_block_identity_retained_block_changed', $data['detail'] );
		$this->assertSame( 'post_retry_save_server_merge', $data['rest_route'] );
		$this->assertSame( 'top_level_serialized_block_identity_map', $data['server_merge_strategy'] );
		$this->assertSame( 'manual_conflict_required', $data['server_merge_status'] );
		$this->assertSame( 0, $data['block_index'] );
		$this->assertTrue( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_retry_post->post_content, $before_retry_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_validate_retry_save_block_identity_request_proof
	 */
	public function test_retry_save_rejects_block_identity_request_proof_hash_mismatch_without_mutating() {
		$block_a          = '<!-- wp:paragraph --><p>Identity mismatch A.</p><!-- /wp:paragraph -->';
		$current_stripped = $block_a;
		$current_content  = $this->add_sync_meta_to_content(
			$current_stripped,
			42,
			$this->get_block_identity_sync_meta_for_blocks(
				$current_stripped,
				array(
					'block-a' => $block_a,
				)
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity mismatch retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Identity mismatch changed.</p><!-- /wp:paragraph -->';
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '42',
				'accepted_proof_server_version' => '42',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
				'block_identity_request_proof'  => array(
					'client_base_version'        => '42',
					'proposed_post_content_hash' => str_repeat( 'a', 64 ),
					'proposed_block_map'         => array(
						array(
							'block_uid'       => 'block-a',
							'block_name'      => 'core/paragraph',
							'ordinal_path'    => array( 0 ),
							'serialized_hash' => hash( 'sha256', $block_a ),
						),
					),
					'retained_block_uids'        => array( 'block-a' ),
					'inserted_block_nonces'      => array(),
					'deleted_block_uids'         => array(),
					'moved_block_uids'           => array(),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'de_rtc_sync_meta_tampered', $data['code'] );
		$this->assertSame( 'retry_save_block_identity_request_proof_hash_mismatch', $data['data']['detail'] );
		$this->assertSame( 'post_retry_save_block_identity', $data['data']['rest_route'] );
		$this->assertFalse( $data['data']['saves_post'] );
		$this->assertFalse( $data['data']['mutates_post_content'] );
		$this->assertFalse( $data['data']['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_retry_save_request_rest_base
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_applies_pages_with_page_permission_contract() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save page current content.</p><!-- /wp:paragraph -->',
			11
		);
		$page_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save page proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$before_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = $this->create_retry_save_request(
			'pages',
			$page_id,
			array(
				'client_base_version'           => '11',
				'accepted_proof_server_version' => '11',
				'rebased_from_version'          => '8',
				'pending_change_count'          => 3,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$after_page      = get_post( $page_id );
		$after_revisions = wp_get_post_revisions(
			$page_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_page->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $page_id, $data['post_id'] );
		$this->assertSame( $page_id, $data['updated_post_id'] );
		$this->assertSame( '11', $data['previous_server_version'] );
		$this->assertSame( '12', $data['server_version'] );
		$this->assertSame( '8', $data['rebased_from_version'] );
		$this->assertSame( 3, $data['pending_change_count'] );
		$this->assertTrue( $data['saves_post'] );
		$this->assertTrue( $data['mutates_post_content'] );
		$this->assertTrue( $data['claims_saved'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $data['revision_ids_before_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $data['revision_ids_after_save'] );
		$this->assertIsArray( $parsed );
		$this->assertSame( $proposed_content, $parsed['content'] );
		$this->assertSame( '12', $parsed['sync_meta']['version'] );
		$this->assertSame( '11', $parsed['sync_meta']['previous_version'] );
		$this->assertSame( 'retry_save', $parsed['sync_meta']['last_server_update']['type'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_get_duplicate_retry_save_no_write_result
	 */
	public function test_retry_save_duplicate_current_base_no_writes_after_first_persistence() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Duplicate current base content.</p><!-- /wp:paragraph -->',
			17,
			array(
				'hash' => 'duplicate-current-base',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC duplicate current-base retry save post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Duplicate current-base proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$request_params   = array(
			'client_base_version'           => '17',
			'accepted_proof_server_version' => '17',
			'rebased_from_version'          => '15',
			'pending_change_count'          => 1,
			'proposed_post_content'         => $proposed_content,
			'proposed_post_content_hash'    => $proposed_hash,
		);
		$save_request     = $this->create_retry_save_request( 'posts', $post_id, $request_params );

		$save_response = rest_get_server()->dispatch( $save_request );
		$save_data     = $save_response->get_data();
		$saved_post    = get_post( $post_id );
		$parsed_saved  = wp_de_rtc_parse_post_content_sync_meta( $saved_post->post_content );

		$this->assertSame( 200, $save_response->get_status() );
		$this->assertSame( 'retry_save_applied', $save_data['result'] );
		$this->assertTrue( $save_data['revision_created'] );
		$this->assertSame( '17', $save_data['previous_server_version'] );
		$this->assertSame( '18', $save_data['server_version'] );
		$this->assertIsArray( $parsed_saved );
		$this->assertSame( $proposed_content, $parsed_saved['content'] );
		$this->assertSame( '17', $parsed_saved['sync_meta']['last_server_update']['client_base_version'] );
		$this->assertSame( '17', $parsed_saved['sync_meta']['last_server_update']['accepted_proof_server_version'] );
		$this->assertSame( $proposed_hash, $parsed_saved['sync_meta']['last_server_update']['proposed_post_content_hash'] );

		$duplicate_before_post      = get_post( $post_id );
		$duplicate_before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$duplicate_request          = $this->create_retry_save_request( 'posts', $post_id, $request_params );

		$duplicate_response = rest_get_server()->dispatch( $duplicate_request );
		$duplicate_data     = $duplicate_response->get_data();

		$this->assertSame( 200, $duplicate_response->get_status() );
		$this->assertSame( 'retry_save_applied', $duplicate_data['result'] );
		$this->assertTrue( $duplicate_data['retry_save_accepted'] );
		$this->assertTrue( $duplicate_data['retry_save_duplicate'] );
		$this->assertTrue( $duplicate_data['idempotent_no_write'] );
		$this->assertTrue( $duplicate_data['already_persisted'] );
		$this->assertFalse( $duplicate_data['server_merge_applied'] );
		$this->assertSame( '17', $duplicate_data['client_base_version'] );
		$this->assertSame( '17', $duplicate_data['accepted_proof_server_version'] );
		$this->assertSame( '17', $duplicate_data['previous_server_version'] );
		$this->assertSame( '18', $duplicate_data['server_version'] );
		$this->assertSame( $proposed_hash, $duplicate_data['proposed_post_content_hash'] );
		$this->assertSame( $proposed_hash, $duplicate_data['saved_stripped_post_content_hash'] );
		$this->assertSame( $duplicate_before_post->post_content, $duplicate_data['content']['raw'] );
		$this->assertFalse( $duplicate_data['saves_post'] );
		$this->assertFalse( $duplicate_data['mutates_post_content'] );
		$this->assertFalse( $duplicate_data['creates_revision'] );
		$this->assertTrue( $duplicate_data['claims_saved'] );
		$this->assertFalse( $duplicate_data['revision_created'] );
		$this->assertSame( array(), $duplicate_data['created_revision_ids'] );
		$this->assertSame( $duplicate_data['revision_ids_before_save'], $duplicate_data['revision_ids_after_save'] );
		$this->assertSame( array_map( 'intval', array_keys( $duplicate_before_revisions ) ), $duplicate_data['revision_ids_before_save'] );
		$this->assert_post_unchanged( $post_id, $duplicate_before_post->post_content, $duplicate_before_revisions );
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
	 * @covers ::wp_de_rtc_get_next_sync_meta_version
	 */
	public function test_retry_save_advances_non_numeric_sync_versions_deterministically() {
		$current_version  = 'server-alpha';
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save non numeric current content.</p><!-- /wp:paragraph -->',
			$current_version
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save non numeric post',
				'post_content' => $current_content,
			)
		);
		$proposed_content = '<!-- wp:paragraph --><p>Retry save non numeric proposed content.</p><!-- /wp:paragraph -->';
		$proposed_hash    = hash( 'sha256', $proposed_content );
		$expected_version = substr( hash( 'sha256', $current_version . '|' . $proposed_hash ), 0, 16 );
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => $current_version,
				'accepted_proof_server_version' => $current_version,
				'rebased_from_version'          => 'offline-alpha',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => $proposed_hash,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$after_post = get_post( $post_id );
		$parsed     = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'retry_save_applied', $data['result'] );
		$this->assertSame( $current_version, $data['previous_server_version'] );
		$this->assertSame( $expected_version, $data['server_version'] );
		$this->assertSame( 'offline-alpha', $data['rebased_from_version'] );
		$this->assertSame( $expected_version, $parsed['sync_meta']['version'] );
		$this->assertSame( $current_version, $parsed['sync_meta']['previous_version'] );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_missing_proposed_content_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save missing proposed current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save missing proposed post',
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
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 2,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertErrorResponse( 'de_rtc_malformed_sync_payload', $response, 400 );
		$this->assertSame( 'missing_retry_save_proposed_content', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_retry_save_returns_current_sync_meta_parser_error_without_mutating() {
		$script           = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array(
				'version' => 7,
			)
		);
		$current_content  = '<!-- wp:paragraph --><p>Retry save malformed current content.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>' . $script . '</p><!-- /wp:paragraph -->';
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save malformed current meta post',
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
		$proposed_content = '<!-- wp:paragraph --><p>Retry save proposed content after malformed current metadata.</p><!-- /wp:paragraph -->';
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 1,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
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

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_proposed_content_hash_mismatch_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save hash current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save hash mismatch post',
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
		$proposed_content = '<!-- wp:paragraph --><p>Retry save hash proposed content.</p><!-- /wp:paragraph -->';
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'pending_change_count'          => 2,
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => str_repeat( '0', 64 ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_proposed_content_hash_mismatch', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $data['proposed_post_content_hash'] );
		$this->assertSame( str_repeat( '0', 64 ), $data['expected_post_content_hash'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
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
	 * @dataProvider data_supported_sync_meta_shapes
	 *
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_client_submitted_sync_meta_without_mutating( $shape ) {
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
		$proposed_content = $this->add_sync_meta_to_content_with_shape(
			'<!-- wp:paragraph --><p>Retry save proposed content with client metadata.</p><!-- /wp:paragraph -->',
			8,
			$shape
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
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 */
	public function test_retry_save_rejects_client_submitted_sync_meta_before_content_hash_mismatch_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save current content before leaked client metadata.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save client meta before hash post',
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
			'<!-- wp:paragraph --><p>Retry save proposed content with leaked client metadata.</p><!-- /wp:paragraph -->',
			8
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => str_repeat( '0', 64 ),
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
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_retry_save_endpoint
	 * @covers ::wp_de_rtc_save_retry_submitted_post
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_count_post_content_sync_meta_scripts
	 */
	public function test_retry_save_rejects_html_wrapped_client_sync_meta_without_mutating() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save current content before HTML metadata.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save HTML client meta post',
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
		$script           = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array(
				'version' => 8,
			)
		);
		$proposed_content = '<!-- wp:html -->' . $script . '<!-- /wp:html -->';
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params(
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => $proposed_content,
				'proposed_post_content_hash'    => hash( 'sha256', $proposed_content ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$data     = $error->get_error_data( 'de_rtc_sync_meta_tampered' );

		$this->assertErrorResponse( 'de_rtc_sync_meta_tampered', $response, 403 );
		$this->assertSame( 'retry_save_client_submitted_sync_meta', $data['detail'] );
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
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
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_retry_save_requires_site_enablement_without_mutating() {
		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save disabled current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled retry save post',
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
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save disabled proposed content.</p><!-- /wp:paragraph -->',
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
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_retry_save_requires_post_filter_enablement_without_mutating() {
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );

		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save filtered current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC filtered retry save post',
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
		$request          = $this->create_retry_save_request(
			'posts',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save filtered proposed content.</p><!-- /wp:paragraph -->',
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
	 * @covers ::wp_de_rtc_rest_retry_save_permissions_check
	 * @covers ::wp_de_rtc_rest_retry_save_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_retry_save_request_rest_base
	 */
	public function test_retry_save_requires_matching_post_type_rest_base() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Retry save route mismatch current content.</p><!-- /wp:paragraph -->',
			7
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC retry save route mismatch post',
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
		$request          = $this->create_retry_save_request(
			'pages',
			$post_id,
			array(
				'client_base_version'           => '7',
				'accepted_proof_server_version' => '7',
				'proposed_post_content'         => '<!-- wp:paragraph --><p>Retry save route mismatch proposed content.</p><!-- /wp:paragraph -->',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * Creates a retry-save REST request.
	 *
	 * @param string $rest_base Post type REST base.
	 * @param int    $post_id   Post ID.
	 * @param array  $params    Body parameters.
	 * @return WP_REST_Request REST request.
	 */
	private function create_retry_save_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/retry-save' );
		$request->set_body_params( $params );

		return $request;
	}

	/**
	 * Creates a retry-submit proof REST request.
	 *
	 * @param string $rest_base Post type REST base.
	 * @param int    $post_id   Post ID.
	 * @param array  $params    Body parameters.
	 * @return WP_REST_Request REST request.
	 */
	private function create_retry_submit_request( $rest_base, $post_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $post_id . '/distributed-editing/retry-submit' );
		$request->set_body_params( $params );

		return $request;
	}

	/**
	 * Asserts that a rejected retry-save request did not mutate content or revisions.
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

	public function data_supported_sync_meta_shapes() {
		return array(
			'raw'               => array( 'raw' ),
			'html-block'        => array( 'html-block' ),
			'paragraph-wrapped' => array( 'paragraph-wrapped' ),
			'freeform-wrapped'  => array( 'freeform-wrapped' ),
		);
	}

	/**
	 * Adds synthetic sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param mixed  $version Sync metadata version.
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

	/**
	 * Adds synthetic paragraph-wrapped sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param mixed  $version Sync metadata version.
	 * @return string Content with paragraph-wrapped sync metadata.
	 */
	private function add_wrapped_sync_meta_to_content( $content, $version ) {
		return $this->add_sync_meta_to_content_with_shape( $content, $version, 'paragraph-wrapped' );
	}

	/**
	 * Adds synthetic sync metadata to content using one of the supported stored shapes.
	 *
	 * @param string $content Post content.
	 * @param mixed  $version Sync metadata version.
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

		if ( 'html-block' === $shape ) {
			return "<!-- wp:html -->\n" . $script . "\n<!-- /wp:html -->" . $content;
		}

		$this->assertSame( 'freeform-wrapped', $shape );

		return $content . "\n\n" . '<!-- wp:freeform --><p>' . $script . '</p><!-- /wp:freeform -->';
	}

	/**
	 * Adds top pseudo-block Automerge metadata to content.
	 *
	 * @param string $content Post content without sync metadata.
	 * @param mixed  $version Sync metadata version.
	 * @param array  $extra   Optional extra sync metadata.
	 * @return string Content with Automerge sync metadata.
	 */
	private function add_automerge_sync_meta_to_content( $content, $version, $extra = array() ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'automerge',
			array_merge(
				array(
					'version'      => $version,
					'schema'       => 'de-rtc-automerge-v1',
					'automerge_encoding' => 'native-automerge-blocks-v1',
				),
				$extra
			),
			'prefix-block'
		);

		$this->assertIsString( $content_with_sync_meta );

		return $content_with_sync_meta;
	}

	/**
	 * Adds the explicit core namespace to paragraph block delimiters.
	 *
	 * @param string $content Serialized post content.
	 * @return string Serialized content with explicit core paragraph delimiters.
	 */
	private function add_explicit_core_namespace_to_paragraph_blocks( $content ) {
		return str_replace(
			array( '<!-- wp:paragraph', '<!-- /wp:paragraph' ),
			array( '<!-- wp:core/paragraph', '<!-- /wp:core/paragraph' ),
			$content
		);
	}

	/**
	 * Skips tests when the native PHP Automerge port cannot load.
	 */
	private function require_automerge_runtime() {
		$status = wp_de_rtc_get_automerge_runtime_status();

		if ( empty( $status['available'] ) ) {
			$reason = isset( $status['reason'] ) ? (string) $status['reason'] : 'unknown';
			$this->markTestSkipped( 'Native PHP Automerge runtime is not available: ' . $reason );
		}
	}

	/**
	 * Builds synthetic block identity sync metadata for top-level serialized blocks.
	 *
	 * @param string $content Serialized post content without sync metadata.
	 * @param array  $blocks  Map of block UID to serialized block.
	 * @return array Sync metadata.
	 */
	private function get_block_identity_sync_meta_for_blocks( $content, $blocks ) {
		$block_records = array();
		$index         = 0;

		foreach ( $blocks as $block_uid => $serialized_block ) {
			$parsed_blocks = wp_de_rtc_remove_empty_freeform_blocks( parse_blocks( $serialized_block ) );
			$block_name    = isset( $parsed_blocks[0]['blockName'] ) && is_string( $parsed_blocks[0]['blockName'] ) ? $parsed_blocks[0]['blockName'] : 'core/paragraph';

			$block_records[] = array(
				'block_uid'       => $block_uid,
				'parent_uid'      => null,
				'block_name'      => $block_name,
				'ordinal_path'    => array( $index ),
				'serialized_hash' => hash( 'sha256', $serialized_block ),
			);
			++$index;
		}

		return array(
			'schema'        => 'de-rtc-block-identity-v1',
			'document_uuid' => 'doc-rest-retry-save-block-identity',
			'content_hash'  => hash( 'sha256', $content ),
			'blocks'        => $block_records,
		);
	}
}
