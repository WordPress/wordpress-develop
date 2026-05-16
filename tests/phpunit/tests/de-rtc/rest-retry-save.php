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
				'advanced_server_content'    => '<!-- wp:paragraph --><p>Opening changed by server.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Middle base.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Details base.</p><!-- /wp:paragraph -->',
				'server_block_count'         => 3,
				'proposed_block_count'       => 2,
				'server_block_count_changed' => false,
				'local_block_count_changed'  => true,
				'server_block_count_delta'   => 0,
				'local_block_count_delta'    => -1,
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
			$this->assertSame( 'retry_save_server_merge_top_level_serialized_block_count_changed', $data['detail'], $label );
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
		$this->assertSame( 'post_retry_save', $data['rest_route'] );
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
}
