<?php
/**
 * Tests for Distributed Editing sync-meta recovery decisions.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Recovery_Decision extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_sync_meta_recovery_decision
	 */
	public function test_current_sync_meta_needs_no_recovery() {
		$stripped_content = '<!-- wp:paragraph --><p>Current content.</p><!-- /wp:paragraph -->';
		$metadata         = array(
			'version' => 7,
			'hash'    => 'current',
		);
		$post_content     = wp_de_rtc_add_sync_meta_to_post_content( $stripped_content, 'diff-match-patch', $metadata );

		$this->assertIsString( $post_content );

		$post_id  = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC current sync-meta post',
				'post_content' => $post_content,
			)
		);
		$decision = wp_de_rtc_get_post_sync_meta_recovery_decision( $post_id );

		$this->assertIsArray( $decision );
		$this->assertSame( 'no_recovery_required', $decision['decision'] );
		$this->assertFalse( $decision['recovery_required'] );
		$this->assertFalse( $decision['restorable'] );
		$this->assertFalse( $decision['manual_resolution_required'] );
		$this->assertNull( $decision['reason'] );
		$this->assertSame( $stripped_content, $decision['current_content'] );
		$this->assertSame( hash( 'sha256', $stripped_content ), $decision['current_content_hash'] );
		$this->assertSame( 'sha256', $decision['content_hash_algorithm'] );
		$this->assertSame( $metadata, $decision['current_sync_meta'] );
		$this->assertSame( 'diff-match-patch', $decision['current_sync_meta_format'] );
		$this->assertSame( 'trailer', $decision['current_sync_meta_position'] );
		$this->assertNull( $decision['base_revision'] );
		$this->assertNull( $decision['external_change'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_sync_meta_recovery_decision
	 */
	public function test_missing_current_sync_meta_with_revision_is_restorable_and_does_not_mutate() {
		$current_content  = '<!-- wp:paragraph --><p>Externally changed current content.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:paragraph --><p>Confirmed base content.</p><!-- /wp:paragraph -->';
		$base_metadata    = array(
			'version' => 3,
			'hash'    => 'base',
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC restorable post',
				'post_content'  => $current_content,
				'post_modified' => '2026-05-13 12:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content( $base_content, 'automerge', $base_metadata );

		$this->assertIsString( $revision_content );

		$revision_id = $this->insert_revision( $post_id, $revision_content, '2026-05-13 12:10:00' );

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$decision = wp_de_rtc_get_post_sync_meta_recovery_decision( $post_id );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertIsArray( $decision );
		$this->assertSame( 'recovery_required_restorable', $decision['decision'] );
		$this->assertTrue( $decision['recovery_required'] );
		$this->assertTrue( $decision['restorable'] );
		$this->assertFalse( $decision['manual_resolution_required'] );
		$this->assertSame(
			array(
				'status'               => 409,
				'reason_code'          => 'de_rtc_missing_sync_meta',
				'detail'               => 'restorable_revision_found',
				'recovery_reason_code' => 'de_rtc_sync_meta_restored_from_revision',
			),
			$decision['reason']
		);
		$this->assertSame( $current_content, $decision['current_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $decision['current_content_hash'] );
		$this->assertSame( $revision_id, $decision['base_revision']['revision_id'] );
		$this->assertSame( '2026-05-13 12:10:00', $decision['base_revision']['revision_date_gmt'] );
		$this->assertSame( $base_content, $decision['base_revision']['content'] );
		$this->assertSame( hash( 'sha256', $base_content ), $decision['base_revision']['content_hash'] );
		$this->assertSame( hash( 'sha256', $base_content ), $decision['base_revision_content_hash'] );
		$this->assertSame( $base_metadata, $decision['base_revision']['sync_meta'] );
		$this->assertSame( 'automerge', $decision['base_revision']['sync_meta_format'] );
		$this->assertSame( $revision_id, $decision['external_change']['base_revision_id'] );
		$this->assertSame( $base_content, $decision['external_change']['base_content'] );
		$this->assertSame( $current_content, $decision['external_change']['current_content'] );
		$this->assertSame( hash( 'sha256', $base_content ), $decision['external_change']['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $current_content ), $decision['external_change']['current_content_hash'] );
		$this->assertSame( $base_metadata, $decision['external_change']['restored_sync_meta'] );

		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $current_content, $after_post->post_content );
		$this->assertSame( $before_post->post_modified_gmt, $after_post->post_modified_gmt );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_sync_meta_recovery_decision
	 */
	public function test_missing_current_sync_meta_without_revision_is_empty_automerge_import() {
		$current_content = '<!-- wp:paragraph --><p>No sync metadata anywhere.</p><!-- /wp:paragraph -->';
		$post_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC unrecoverable post',
				'post_content' => $current_content,
			)
		);

		$this->insert_revision(
			$post_id,
			'<!-- wp:paragraph --><p>Revision without metadata.</p><!-- /wp:paragraph -->',
			'2026-05-13 12:20:00'
		);

		$decision = wp_de_rtc_get_post_sync_meta_recovery_decision( $post_id );

		$this->assertIsArray( $decision );
		$this->assertSame( 'recovery_required_restorable', $decision['decision'] );
		$this->assertTrue( $decision['recovery_required'] );
		$this->assertTrue( $decision['restorable'] );
		$this->assertFalse( $decision['manual_resolution_required'] );
		$this->assertSame(
			array(
				'status'                 => 409,
				'reason_code'            => 'de_rtc_missing_sync_meta',
				'detail'                 => 'empty_automerge_import_required',
				'recovery_reason_code'   => 'de_rtc_sync_meta_empty_automerge_import',
				'scanned_revisions'      => 1,
				'malformed_revision_ids' => array(),
			),
			$decision['reason']
		);
		$this->assertSame( $current_content, $decision['current_content'] );
		$this->assertSame( hash( 'sha256', $current_content ), $decision['current_content_hash'] );
		$this->assertSame( 0, $decision['base_revision']['revision_id'] );
		$this->assertSame( '', $decision['base_revision']['content'] );
		$this->assertSame( 'automerge', $decision['base_revision']['sync_meta_format'] );
		$this->assertSame( 'de-rtc-automerge-v1', $decision['base_revision']['sync_meta']['schema'] );
		$this->assertSame( hash( 'sha256', '' ), $decision['base_revision_content_hash'] );
		$this->assertFalse( $decision['revision_scan']['found'] );
		$this->assertSame( 'empty_import', $decision['external_change']['mode'] );
		$this->assertSame( $current_content, $decision['external_change']['current_content'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_post_sync_meta_recovery_decision
	 */
	public function test_malformed_current_sync_meta_returns_existing_parse_error() {
		$content = '<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">{bad json</script><!-- wp:paragraph --><p>Current.</p><!-- /wp:paragraph -->';
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC malformed current sync-meta post',
				'post_content' => $content,
			)
		);

		$result = wp_de_rtc_get_post_sync_meta_recovery_decision( $post_id );

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
