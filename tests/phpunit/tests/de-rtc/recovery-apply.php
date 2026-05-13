<?php
/**
 * Tests for Distributed Editing sync-meta recovery apply guards.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Recovery_Apply extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * @covers ::wp_de_rtc_apply_sync_meta_recovery_update
	 */
	public function test_apply_requires_explicit_mode_without_mutating() {
		$current_content  = '<!-- wp:paragraph --><p>Guarded apply current content.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>Guarded apply base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 21,
			),
			'diff-match-patch'
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$apply_result    = wp_de_rtc_apply_sync_meta_recovery_update( $post_id );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $apply_result );
		$this->assertSame( 'apply', $apply_result['mode'] );
		$this->assertSame( 'apply_not_requested', $apply_result['result'] );
		$this->assertSame( 'dry_run', $apply_result['requested_mode'] );
		$this->assertSame( 'valid_candidate', $apply_result['validation_status'] );
		$this->assertTrue( $apply_result['valid'] );
		$this->assertTrue( $apply_result['can_apply'] );
		$this->assertFalse( $apply_result['would_apply'] );
		$this->assertFalse( $apply_result['applied'] );
		$this->assertSame( 'candidate_update_valid', $apply_result['dry_run']['result'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( $before_post->post_modified_gmt, $after_post->post_modified_gmt );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_apply_sync_meta_recovery_update
	 */
	public function test_apply_rejects_invalid_dry_run_candidate_without_mutating() {
		$current_content = '<!-- wp:paragraph --><p>Invalid apply current content.</p><!-- /wp:paragraph -->';
		$post_id         = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>Invalid apply base content.</p><!-- /wp:paragraph -->',
			array(
				'version' => 22,
			),
			'yjs'
		);
		$plan            = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

		$this->assertIsArray( $plan );

		$before_post                         = get_post( $post_id );
		$before_revisions                    = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$plan['candidate_post_content_hash'] = hash( 'sha256', 'invalid apply candidate' );
		$apply_result                        = wp_de_rtc_apply_sync_meta_recovery_update(
			$plan,
			array(
				'mode' => 'apply',
			)
		);
		$after_post                          = get_post( $post_id );
		$after_revisions                     = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertWPError( $apply_result );
		$this->assertSame( 'de_rtc_sync_meta_tampered', $apply_result->get_error_code() );
		$this->assertSame( 'invalid_recovery_update_candidate', $apply_result->get_error_data()['detail'] );
		$this->assertSame( 'candidate_update_invalid', $apply_result->get_error_data()['dry_run_result'] );
		$this->assertFalse( $apply_result->get_error_data()['dry_run_valid'] );
		$this->assertFalse( $apply_result->get_error_data()['dry_run_can_apply'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_apply_sync_meta_recovery_update
	 */
	public function test_apply_with_explicit_mode_persists_valid_candidate() {
		$current_content  = '<!-- wp:paragraph --><p>Apply current content.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 23,
			'hash'    => 'apply-base',
		);
		$post_id          = $this->create_restorable_post(
			$current_content,
			'<!-- wp:paragraph --><p>Apply base content.</p><!-- /wp:paragraph -->',
			$base_metadata,
			'diff-match-patch'
		);
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$apply_result    = wp_de_rtc_apply_sync_meta_recovery_update(
			$post_id,
			array(
				'mode' => 'apply',
			)
		);
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$parsed          = wp_de_rtc_parse_post_content_sync_meta( $after_post->post_content );

		$this->assertIsArray( $apply_result );
		$this->assertSame( 'apply', $apply_result['mode'] );
		$this->assertSame( 'candidate_update_applied', $apply_result['result'] );
		$this->assertSame( 'valid_candidate', $apply_result['validation_status'] );
		$this->assertTrue( $apply_result['valid'] );
		$this->assertTrue( $apply_result['can_apply'] );
		$this->assertTrue( $apply_result['would_apply'] );
		$this->assertTrue( $apply_result['applied'] );
		$this->assertSame( $post_id, $apply_result['updated_post_id'] );
		$this->assertTrue( $apply_result['revision_created'] );
		$this->assertSame( array_map( 'intval', array_keys( $before_revisions ) ), $apply_result['revision_ids_before_apply'] );
		$this->assertSame( array_map( 'intval', array_keys( $after_revisions ) ), $apply_result['revision_ids_after_apply'] );
		$this->assertCount( 1, $apply_result['created_revision_ids'] );
		$this->assertContains( $apply_result['created_revision_ids'][0], array_map( 'intval', array_keys( $after_revisions ) ) );
		$this->assertSame(
			$apply_result['candidate_post_content_hash'],
			hash( 'sha256', $after_post->post_content )
		);
		$this->assertGreaterThan( count( $before_revisions ), count( $after_revisions ) );
		$this->assertIsArray( $parsed );
		$this->assertSame( $current_content, $parsed['content'] );
		$this->assertSame( $base_metadata, $parsed['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $parsed['sync_meta_format'] );
	}

	/**
	 * Creates a post whose current content is missing sync metadata but whose
	 * latest revision contains recoverable sync metadata.
	 *
	 * @param string $current_content Current parent post content.
	 * @param string $base_content    Base revision content.
	 * @param array  $base_metadata   Base revision sync metadata.
	 * @param string $format          Sync-meta format.
	 * @return int Post ID.
	 */
	private function create_restorable_post( $current_content, $base_content, $base_metadata, $format ) {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC guarded apply post',
				'post_content'  => $current_content,
				'post_modified' => '2026-05-13 15:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, $format, $base_metadata );

		$this->assertIsString( $revision_content );
		$this->insert_revision( $post_id, $revision_content, '2026-05-13 15:10:00' );

		return $post_id;
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
}
