<?php
/**
 * Tests for Distributed Editing sync-meta recovery update plans.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Recovery_Plan extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 */
	public function test_restorable_post_id_returns_apply_plan_with_candidate_content_and_does_not_mutate() {
		$current_content  = '<!-- wp:paragraph --><p>Externally changed current content.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:paragraph --><p>Confirmed base content.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 9,
			'hash'    => 'base-plan',
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC restorable plan post',
				'post_content'  => $current_content,
				'post_modified' => '2026-05-13 13:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'automerge', $base_metadata, 'prefix' );

		$this->assertIsString( $revision_content );

		$revision_id = $this->insert_revision( $post_id, $revision_content, '2026-05-13 13:10:00' );

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$plan = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $plan );
		$this->assertSame( 'sync_meta_recovery_update', $plan['plan'] );
		$this->assertSame( 'recovery_required_restorable', $plan['decision'] );
		$this->assertSame( $post_id, $plan['post_id'] );
		$this->assertTrue( $plan['can_apply'] );
		$this->assertTrue( $plan['recovery_required'] );
		$this->assertFalse( $plan['manual_resolution_required'] );
		$this->assertSame( 'de_rtc_sync_meta_restored_from_revision', $plan['reason_code'] );
		$this->assertSame(
			array(
				'status'             => 409,
				'reason_code'        => 'de_rtc_sync_meta_restored_from_revision',
				'detail'             => 'planned_sync_meta_recovery_update',
				'source_reason_code' => 'de_rtc_missing_sync_meta',
				'base_revision_id'   => $revision_id,
			),
			$plan['reason']
		);
		$this->assertSame( $current_content, $plan['current_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['current_content_hash'] );
		$this->assertSame( 'sha256', $plan['content_hash_algorithm'] );
		$this->assertSame( $current_content, $plan['candidate_stripped_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['candidate_stripped_content_hash'] );
		$this->assertSame( hash( 'sha256', $base_content ), $plan['base_revision_content_hash'] );
		$this->assertSame( wp_de_rtc_hash_content( $plan['candidate_post_content'] ), $plan['candidate_post_content_hash'] );
		$this->assertSame( $base_metadata['version'], $plan['restored_sync_meta']['version'] );
		$this->assertSame( $base_metadata['hash'], $plan['restored_sync_meta']['hash'] );
		$this->assert_system_recovery_attribution( $plan['restored_sync_meta'], 'legacy_sync_meta_restore' );
		$this->assertSame( 'automerge', $plan['restored_sync_meta_format'] );
		$this->assertSame( 'prefix', $plan['restored_sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $plan['restored_raw_sync_meta'] );

		$parsed_candidate = wp_de_rtc_parse_post_content_sync_meta( $plan['candidate_post_content'] );

		$this->assertIsArray( $parsed_candidate );
		$this->assertSame( $current_content, $parsed_candidate['content'] );
		$this->assertSame( $plan['restored_sync_meta'], $parsed_candidate['sync_meta'] );
		$this->assertSame( 'automerge', $parsed_candidate['sync_meta_format'] );
		$this->assertSame( 'prefix', $parsed_candidate['sync_meta_position'] );
		$this->assertSame( $plan['restored_raw_sync_meta'], $parsed_candidate['raw_sync_meta'] );
		$this->assertSame( $revision_id, $plan['external_change']['base_revision_id'] );
		$this->assertSame( $base_content, $plan['external_change']['base_content'] );
		$this->assertSame( $current_content, $plan['external_change']['current_content'] );
		$this->assertSame( hash( 'sha256', $base_content ), $plan['external_change']['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['external_change']['current_content_hash'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['external_change']['candidate_stripped_content_hash'] );
		$this->assertSame( hash( 'sha256', $plan['candidate_post_content'] ), $plan['external_change']['candidate_post_content_hash'] );
		$this->assertSame( 'de_rtc_sync_meta_restored_from_revision', $plan['external_change']['candidate_reason_code'] );

		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( $before_post->post_modified_gmt, $after_post->post_modified_gmt );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 */
	public function test_accepts_recovery_decision_array_for_restorable_plan() {
		$current_content  = '<!-- wp:paragraph --><p>Current content for decision array.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:paragraph --><p>Base content for decision array.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 10,
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC decision-array plan post',
				'post_content' => $current_content,
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'automerge', $base_metadata );

		$this->assertIsString( $revision_content );

		$this->insert_revision( $post_id, $revision_content, '2026-05-13 13:20:00' );

		$decision = wp_de_rtc_get_post_sync_meta_recovery_decision( $post_id );
		$plan     = wp_de_rtc_plan_sync_meta_recovery_update( $decision );

		$this->assertIsArray( $decision );
		$this->assertIsArray( $plan );
		$this->assertTrue( $plan['can_apply'] );
		$this->assertSame( 'recovery_required_restorable', $plan['decision'] );
		$this->assertSame( $current_content, $plan['candidate_stripped_content'] );
		$this->assertSame( 'automerge', $plan['restored_sync_meta_format'] );
		$this->assertSame( $base_metadata['version'], $plan['restored_sync_meta']['version'] );
		$this->assert_system_recovery_attribution( $plan['restored_sync_meta'], 'legacy_sync_meta_restore' );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 */
	public function test_no_recovery_required_returns_noop_plan_with_current_content_unchanged() {
		$stripped_content = '<!-- wp:paragraph --><p>Already has current sync metadata.</p><!-- /wp:paragraph -->';
		$metadata         = array(
			'version' => 11,
			'hash'    => 'current-plan',
		);
		$post_content     = wp_de_rtc_add_sync_meta_to_post_content( $stripped_content, 'diff-match-patch', $metadata );

		$this->assertIsString( $post_content );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC no-op plan post',
				'post_content' => $post_content,
			)
		);

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$plan = wp_de_rtc_plan_sync_meta_recovery_update( get_post( $post_id ) );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $plan );
		$this->assertSame( 'no_recovery_required', $plan['decision'] );
		$this->assertFalse( $plan['can_apply'] );
		$this->assertFalse( $plan['recovery_required'] );
		$this->assertFalse( $plan['manual_resolution_required'] );
		$this->assertNull( $plan['reason'] );
		$this->assertNull( $plan['reason_code'] );
		$this->assertSame( $stripped_content, $plan['current_content'] );
		$this->assertSame( hash( 'sha256', $stripped_content ), $plan['current_content_hash'] );
		$this->assertSame( $stripped_content, $plan['candidate_stripped_content'] );
		$this->assertSame( hash( 'sha256', $stripped_content ), $plan['candidate_stripped_content_hash'] );
		$this->assertSame( $post_content, $plan['candidate_post_content'] );
		$this->assertSame( hash( 'sha256', $post_content ), $plan['candidate_post_content_hash'] );
		$this->assertNull( $plan['base_revision_content_hash'] );
		$this->assertNull( $plan['restored_sync_meta'] );
		$this->assertNull( $plan['external_change'] );

		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 */
	public function test_missing_sync_meta_without_revision_plans_empty_automerge_import() {
		$this->require_automerge_runtime();

		$current_content = '<!-- wp:paragraph --><p>No restorable sync metadata.</p><!-- /wp:paragraph -->';
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC manual plan post',
				'post_content' => $current_content,
			)
		);

		$this->insert_revision(
			$post_id,
			'<!-- wp:paragraph --><p>Revision also has no sync metadata.</p><!-- /wp:paragraph -->',
			'2026-05-13 13:30:00'
		);

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$plan = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $plan );
		$this->assertSame( 'recovery_required_restorable', $plan['decision'] );
		$this->assertTrue( $plan['can_apply'] );
		$this->assertTrue( $plan['recovery_required'] );
		$this->assertFalse( $plan['manual_resolution_required'] );
		$this->assertSame( 'de_rtc_sync_meta_empty_automerge_import', $plan['reason_code'] );
		$this->assertSame( 'planned_empty_automerge_import', $plan['reason']['detail'] );
		$this->assertSame( $current_content, $plan['current_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['current_content_hash'] );
		$this->assertSame( $current_content, $plan['candidate_stripped_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['candidate_stripped_content_hash'] );
		$this->assertIsString( $plan['candidate_post_content'] );
		$this->assertSame( wp_de_rtc_hash_content( $plan['candidate_post_content'] ), $plan['candidate_post_content_hash'] );
		$this->assertSame( 'automerge', $plan['restored_sync_meta_format'] );
		$this->assertSame( 'prefix-block', $plan['restored_sync_meta_position'] );
		$this->assertSame( 'de-rtc-automerge-v1', $plan['restored_sync_meta']['schema'] );
		$this->assertSame( '1', $plan['restored_sync_meta']['version'] );
		$this->assertSame( 'empty_import', $plan['restored_sync_meta']['last_server_update']['external_repair_mode'] );
		$this->assert_system_recovery_attribution( $plan['restored_sync_meta'], 'empty_import' );
		$this->assertSame( 'empty_import', $plan['external_change']['mode'] );

		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 * @covers ::wp_de_rtc_get_automerge_external_repair_update
	 */
	public function test_missing_sync_meta_with_current_automerge_revision_plans_external_html_import() {
		$this->require_automerge_runtime();

		$base_content    = '<!-- wp:paragraph --><p>Automerge revision base paragraph.</p><!-- /wp:paragraph --><!-- wp:list --><ul><!-- wp:list-item --><li>Base list item</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$current_content = '<h2>External HTML update</h2><p>A direct database writer replaced the post body with ordinary HTML and removed the Distributed Editing pseudo-block.</p>';
		$base_metadata   = $this->create_automerge_sync_meta( $base_content, '10' );
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC external HTML recovery plan post',
				'post_content' => $current_content,
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'automerge', $base_metadata, 'prefix-block' );

		$this->assertIsString( $revision_content );

		$revision_id = $this->insert_revision( $post_id, $revision_content, '2026-06-05 12:30:00' );
		$plan        = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

		$this->assertIsArray( $plan );
		$this->assertSame( 'sync_meta_recovery_update', $plan['plan'] );
		$this->assertSame( 'recovery_required_restorable', $plan['decision'] );
		$this->assertTrue( $plan['can_apply'] );
		$this->assertSame( 'de_rtc_sync_meta_repaired_from_body', $plan['reason_code'] );
		$this->assertSame( 'planned_automerge_external_body_repair', $plan['reason']['detail'] );
		$this->assertSame( $revision_id, $plan['reason']['base_revision_id'] );
		$this->assertSame( $current_content, $plan['candidate_stripped_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $plan['candidate_stripped_content_hash'] );
		$this->assertSame( 'automerge', $plan['restored_sync_meta_format'] );
		$this->assertSame( 'prefix-block', $plan['restored_sync_meta_position'] );
		$this->assertSame( 'de-rtc-automerge-v1', $plan['restored_sync_meta']['schema'] );
		$this->assertSame( '11', $plan['restored_sync_meta']['version'] );
		$this->assertSame( '10', $plan['restored_sync_meta']['previous_version'] );
		$this->assertSame( 'native-automerge-php-v1', $plan['restored_sync_meta']['automerge_encoding'] );
		$this->assertGreaterThan( 0, $plan['restored_sync_meta']['automerge_operation_count'] );
		$this->assertSame( wp_de_rtc_hash_content( $current_content ), $plan['restored_sync_meta']['post_content_hash'] );
		$this->assertSame( 'external_repair', $plan['restored_sync_meta']['last_server_update']['type'] );
		$this->assertSame( 'missing_sync_meta_revision', $plan['restored_sync_meta']['last_server_update']['external_repair_mode'] );
		$this->assertSame( 'native_automerge_external_repair_v1', $plan['restored_sync_meta']['last_server_update']['merge_strategy'] );
		$this->assertSame( $revision_id, $plan['restored_sync_meta']['last_server_update']['base_revision_id'] );
		$this->assert_system_recovery_attribution( $plan['restored_sync_meta'], 'missing_sync_meta_revision' );
		$this->assertSame( 'missing_sync_meta_revision', $plan['external_change']['mode'] );
		$this->assertSame( '11', $plan['external_change']['repaired_sync_meta_version'] );

		$parsed_candidate = wp_de_rtc_parse_post_content_sync_meta( $plan['candidate_post_content'] );

		$this->assertIsArray( $parsed_candidate );
		$this->assertSame( $current_content, $parsed_candidate['content'] );
		$this->assertSame( $plan['restored_sync_meta'], $parsed_candidate['sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_plan_sync_meta_recovery_update
	 */
	public function test_malformed_current_sync_meta_propagates_existing_parse_error() {
		$content = '<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">{bad json</script><!-- wp:paragraph --><p>Current.</p><!-- /wp:paragraph -->';
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC malformed current plan post',
				'post_content' => $content,
			)
		);

		$before_post = get_post( $post_id );
		$result      = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );
		$after_post  = get_post( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame(
			array(
				'status'          => 400,
				'reason_code'     => 'de_rtc_malformed_sync_payload',
				'detail'          => 'malformed_json',
				'json_error_code' => JSON_ERROR_SYNTAX,
			),
			$result->get_error_data()
		);
		$this->assertSame( $before_post->post_content, $after_post->post_content );
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
	 * Builds current Automerge sync metadata for recovery fixtures.
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
			'document_uuid'             => 'de-rtc-recovery-plan-test',
		);
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
		$this->assertFalse( $sync_meta['last_sync_meta_recovery']['actor']['focusable'] );
		$this->assertFalse( $sync_meta['last_sync_meta_recovery']['actor']['exposes_user_id'] );

		if ( isset( $sync_meta['last_server_update'] ) && 'external_repair' === $sync_meta['last_server_update']['type'] ) {
			$this->assertArrayNotHasKey( 'user_id', $sync_meta['last_server_update'] );
			$this->assertSame( 'system', $sync_meta['last_server_update']['actor_type'] );
			$this->assertSame( $sync_meta['last_sync_meta_recovery']['actor']['actor_id'], $sync_meta['last_server_update']['repair_actor']['actor_id'] );
		}

		if ( isset( $sync_meta['automerge_update'] ) ) {
			$update = wp_de_rtc_decode_automerge_sync_meta_update( $sync_meta['automerge_update'] );

			$this->assertNotWPError( $update );
			$this->assertIsArray( $update );

			foreach ( $update['operations'] as $index => $operation ) {
				$this->assertSame( $sync_meta['last_sync_meta_recovery']['actor']['actor_id'], $operation['actor'] );
				$this->assertSame( 'system', $operation['actor_type'] );
				$this->assertSame( $sync_meta['last_sync_meta_recovery']['actor']['actor_id'] . ':' . $index, $operation['id'] );
			}

			if ( count( $update['operations'] ) > 0 ) {
				$this->assertSame( count( $update['operations'] ), $update['stateVector'][ $sync_meta['last_sync_meta_recovery']['actor']['actor_id'] ] );
			}
		}
	}
}
