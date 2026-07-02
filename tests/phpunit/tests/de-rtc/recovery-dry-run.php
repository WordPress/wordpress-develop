<?php
/**
 * Tests for Distributed Editing sync-meta recovery dry runs.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Recovery_Dry_Run extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * @covers ::wp_de_rtc_dry_run_sync_meta_recovery_update
	 */
	public function test_restorable_plan_dry_run_validates_candidate_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>Externally changed current content for dry run.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:paragraph --><p>Confirmed dry-run base content.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 12,
			'hash'    => 'base-dry-run',
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC dry-run restorable post',
				'post_content'  => $current_content,
				'post_modified' => '2026-05-13 14:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'diff-match-patch', $base_metadata );

		$this->assertIsString( $revision_content );

		$this->insert_revision( $post_id, $revision_content, '2026-05-13 14:10:00' );

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$dry_run = wp_de_rtc_dry_run_sync_meta_recovery_update( $post_id );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $dry_run );
		$this->assertSame( 'dry_run', $dry_run['mode'] );
		$this->assertSame( 'candidate_update_valid', $dry_run['result'] );
		$this->assertSame( 'valid_candidate', $dry_run['validation_status'] );
		$this->assertSame( 'recovery_required_restorable', $dry_run['decision'] );
		$this->assertTrue( $dry_run['valid'] );
		$this->assertTrue( $dry_run['can_apply'] );
		$this->assertFalse( $dry_run['would_apply'] );
		$this->assertTrue( $dry_run['recovery_required'] );
		$this->assertFalse( $dry_run['manual_resolution_required'] );
		$this->assertSame( 'de_rtc_sync_meta_restored_from_revision', $dry_run['reason_code'] );
		$this->assertSame(
			array(
				'candidate_post_content_present'          => true,
				'candidate_post_content_hash_matches'     => true,
				'candidate_stripped_content_hash_matches' => true,
				'candidate_parseable'                     => true,
				'candidate_stripped_content_matches'      => true,
				'candidate_sync_meta_matches'             => true,
				'candidate_sync_meta_format_matches'      => true,
				'candidate_recovery_attribution_valid'    => true,
			),
			$dry_run['checks']
		);
		$this->assertSame( $base_metadata['version'], $dry_run['plan']['restored_sync_meta']['version'] );
		$this->assertSame( $base_metadata['hash'], $dry_run['plan']['restored_sync_meta']['hash'] );
		$this->assertSame( 'system', $dry_run['plan']['restored_sync_meta']['last_sync_meta_recovery']['actor']['actor_type'] );
		$this->assertSame( $current_content, $dry_run['plan']['candidate_stripped_content'] );

		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( $before_post->post_modified_gmt, $after_post->post_modified_gmt );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_dry_run_sync_meta_recovery_update
	 */
	public function test_dry_run_accepts_plan_array_and_rejects_tampered_candidate() {
		$current_content  = '<!-- wp:paragraph --><p>Current content for invalid dry run.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:paragraph --><p>Base content for invalid dry run.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 13,
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC invalid dry-run post',
				'post_content' => $current_content,
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'automerge', $base_metadata );

		$this->assertIsString( $revision_content );

		$this->insert_revision( $post_id, $revision_content, '2026-05-13 14:20:00' );

		$plan = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

		$this->assertIsArray( $plan );

		$plan['candidate_post_content_hash'] = hash( 'sha256', 'tampered' );
		$dry_run                             = wp_de_rtc_dry_run_sync_meta_recovery_update( $plan );

		$this->assertIsArray( $dry_run );
		$this->assertSame( 'candidate_update_invalid', $dry_run['result'] );
		$this->assertSame( 'invalid_candidate', $dry_run['validation_status'] );
		$this->assertFalse( $dry_run['valid'] );
		$this->assertFalse( $dry_run['can_apply'] );
		$this->assertFalse( $dry_run['would_apply'] );
		$this->assertFalse( $dry_run['checks']['candidate_post_content_hash_matches'] );
		$this->assertTrue( $dry_run['checks']['candidate_parseable'] );
		$this->assertTrue( $dry_run['checks']['candidate_sync_meta_matches'] );
	}

	/**
	 * @covers ::wp_de_rtc_dry_run_sync_meta_recovery_update
	 */
	public function test_no_recovery_required_returns_noop_dry_run() {
		$stripped_content = '<!-- wp:paragraph --><p>Already synced dry-run content.</p><!-- /wp:paragraph -->';
		$metadata         = array(
			'version' => 14,
		);
		$post_content     = wp_de_rtc_add_sync_meta_to_post_content( $stripped_content, 'diff-match-patch', $metadata );

		$this->assertIsString( $post_content );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC no-op dry-run post',
				'post_content' => $post_content,
			)
		);

		$dry_run = wp_de_rtc_dry_run_sync_meta_recovery_update( $post_id );

		$this->assertIsArray( $dry_run );
		$this->assertSame( 'no_update_required', $dry_run['result'] );
		$this->assertSame( 'noop', $dry_run['validation_status'] );
		$this->assertSame( 'no_recovery_required', $dry_run['decision'] );
		$this->assertTrue( $dry_run['valid'] );
		$this->assertFalse( $dry_run['can_apply'] );
		$this->assertFalse( $dry_run['would_apply'] );
		$this->assertFalse( $dry_run['recovery_required'] );
		$this->assertFalse( $dry_run['manual_resolution_required'] );
		$this->assertNull( $dry_run['reason'] );
		$this->assertNull( $dry_run['reason_code'] );
		$this->assertSame(
			array(
				'candidate_required'     => false,
				'candidate_content_safe' => true,
			),
			$dry_run['checks']
		);
	}

	/**
	 * @covers ::wp_de_rtc_dry_run_sync_meta_recovery_update
	 */
	public function test_missing_sync_meta_without_revision_empty_import_dry_run_is_valid() {
		$this->require_automerge_runtime();

		$current_content = '<!-- wp:paragraph --><p>Manual dry-run content.</p><!-- /wp:paragraph -->';
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC manual dry-run post',
				'post_content' => $current_content,
			)
		);

		$this->insert_revision(
			$post_id,
			'<!-- wp:paragraph --><p>Revision without dry-run sync metadata.</p><!-- /wp:paragraph -->',
			'2026-05-13 14:30:00'
		);

		$dry_run = wp_de_rtc_dry_run_sync_meta_recovery_update( $post_id );

		$this->assertIsArray( $dry_run );
		$this->assertSame( 'candidate_update_valid', $dry_run['result'] );
		$this->assertSame( 'valid_candidate', $dry_run['validation_status'] );
		$this->assertSame( 'recovery_required_restorable', $dry_run['decision'] );
		$this->assertTrue( $dry_run['valid'] );
		$this->assertTrue( $dry_run['can_apply'] );
		$this->assertFalse( $dry_run['would_apply'] );
		$this->assertTrue( $dry_run['recovery_required'] );
		$this->assertFalse( $dry_run['manual_resolution_required'] );
		$this->assertSame( 'de_rtc_sync_meta_empty_automerge_import', $dry_run['reason_code'] );
		$this->assertSame( 'automerge', $dry_run['plan']['restored_sync_meta_format'] );
		$this->assertSame( '1', $dry_run['plan']['restored_sync_meta']['version'] );
		$this->assertSame( $current_content, $dry_run['plan']['candidate_stripped_content'] );
	}

	/**
	 * @covers ::wp_de_rtc_dry_run_sync_meta_recovery_update
	 */
	public function test_malformed_current_sync_meta_propagates_existing_parse_error() {
		$content = '<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">{bad json</script><!-- wp:paragraph --><p>Current.</p><!-- /wp:paragraph -->';
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC malformed dry-run post',
				'post_content' => $content,
			)
		);

		$result = wp_de_rtc_dry_run_sync_meta_recovery_update( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
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
}
