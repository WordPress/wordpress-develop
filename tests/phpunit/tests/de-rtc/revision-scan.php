<?php
/**
 * Tests for Distributed Editing revision scanning.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group revision
 */

class Tests_DE_RTC_Revision_Scan extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * @covers ::wp_de_rtc_find_latest_revision_with_sync_meta
	 */
	public function test_finds_most_recent_revision_with_parseable_sync_meta() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC revision scan post',
				'post_content' => '<!-- wp:paragraph --><p>Current content.</p><!-- /wp:paragraph -->',
			)
		);

		$older_content            = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Older revision.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => 1,
				'hash'    => 'older',
			)
		);
		$latest_parseable_content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Latest parseable revision.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => 2,
				'hash'    => 'latest-parseable',
			)
		);

		$this->assertIsString( $older_content );
		$this->assertIsString( $latest_parseable_content );

		$this->insert_revision( $post_id, $older_content, '2026-05-13 10:00:00' );
		$latest_parseable_revision_id = $this->insert_revision( $post_id, $latest_parseable_content, '2026-05-13 10:10:00' );
		$malformed_revision_id        = $this->insert_revision(
			$post_id,
			'<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">{bad json</script><!-- wp:paragraph --><p>Malformed.</p><!-- /wp:paragraph -->',
			'2026-05-13 10:20:00'
		);
		$this->insert_revision(
			$post_id,
			'<!-- wp:paragraph --><p>No sync metadata.</p><!-- /wp:paragraph -->',
			'2026-05-13 10:30:00'
		);

		$result = wp_de_rtc_find_latest_revision_with_sync_meta( $post_id );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['found'] );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertSame( $latest_parseable_revision_id, $result['revision_id'] );
		$this->assertSame( '2026-05-13 10:10:00', $result['revision_date_gmt'] );
		$this->assertSame(
			array(
				'version' => 2,
				'hash'    => 'latest-parseable',
			),
			$result['sync_meta']
		);
		$this->assertSame( 'diff-match-patch', $result['sync_meta_format'] );
		$this->assertSame( 'trailer', $result['sync_meta_position'] );
		$this->assertSame( '<!-- wp:paragraph --><p>Latest parseable revision.</p><!-- /wp:paragraph -->', $result['content'] );
		$this->assertSame( 3, $result['scanned_revisions'] );
		$this->assertSame( array( $malformed_revision_id ), $result['malformed_revision_ids'] );
	}

	/**
	 * @covers ::wp_de_rtc_find_latest_revision_with_sync_meta
	 */
	public function test_revision_scan_does_not_restore_or_update_the_parent_post() {
		$original_content = '<!-- wp:paragraph --><p>Current parent content.</p><!-- /wp:paragraph -->';
		$post_id          = self::factory()->post->create(
			array(
				'post_title'    => 'DE-RTC non-mutating scan post',
				'post_content'  => $original_content,
				'post_modified' => '2026-05-13 11:00:00',
			)
		);
		$revision_content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Revision content with metadata.</p><!-- /wp:paragraph -->',
			'automerge',
			array(
				'version' => 3,
			)
		);

		$this->assertIsString( $revision_content );

		$this->insert_revision( $post_id, $revision_content, '2026-05-13 11:10:00' );

		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$result = wp_de_rtc_find_latest_revision_with_sync_meta( $post_id );

		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertTrue( $result['found'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( $original_content, $after_post->post_content );
		$this->assertSame( $before_post->post_modified_gmt, $after_post->post_modified_gmt );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_find_latest_revision_with_sync_meta
	 */
	public function test_revision_scan_skips_autosave_revisions() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC autosave skip post',
				'post_content' => '<!-- wp:paragraph --><p>Current content.</p><!-- /wp:paragraph -->',
			)
		);

		$confirmed_content = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Confirmed revision.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => 4,
			)
		);
		$autosave_content  = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>Autosave revision.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => 5,
			)
		);

		$this->assertIsString( $confirmed_content );
		$this->assertIsString( $autosave_content );

		$confirmed_revision_id = $this->insert_revision( $post_id, $confirmed_content, '2026-05-13 11:20:00' );
		$autosave_revision_id  = $this->insert_revision( $post_id, $autosave_content, '2026-05-13 11:30:00', true );

		$this->assertSame( $post_id, wp_is_post_autosave( $autosave_revision_id ) );

		$result = wp_de_rtc_find_latest_revision_with_sync_meta( $post_id );

		$this->assertTrue( $result['found'] );
		$this->assertSame( $confirmed_revision_id, $result['revision_id'] );
		$this->assertSame(
			array(
				'version' => 4,
			),
			$result['sync_meta']
		);
		$this->assertSame( 2, $result['scanned_revisions'] );
	}

	/**
	 * @covers ::wp_de_rtc_find_latest_revision_with_sync_meta
	 */
	public function test_returns_not_found_result_when_no_revision_has_sync_meta() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC no metadata post',
				'post_content' => '<!-- wp:paragraph --><p>Current content.</p><!-- /wp:paragraph -->',
			)
		);

		$this->insert_revision(
			$post_id,
			'<!-- wp:paragraph --><p>No sync metadata here.</p><!-- /wp:paragraph -->',
			'2026-05-13 12:00:00'
		);

		$result = wp_de_rtc_find_latest_revision_with_sync_meta( $post_id );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['found'] );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertSame( 0, $result['revision_id'] );
		$this->assertNull( $result['sync_meta'] );
		$this->assertSame( 1, $result['scanned_revisions'] );
		$this->assertSame( array(), $result['malformed_revision_ids'] );
	}

	/**
	 * @covers ::wp_de_rtc_find_latest_revision_with_sync_meta
	 */
	public function test_invalid_post_returns_reason_error() {
		$result = wp_de_rtc_find_latest_revision_with_sync_meta( PHP_INT_MAX );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_sync_meta_unrecoverable', $result->get_error_code() );
		$this->assertSame(
			array(
				'status'      => 409,
				'reason_code' => 'de_rtc_sync_meta_unrecoverable',
				'detail'      => 'invalid_post',
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
	private function insert_revision( $post_id, $post_content, $date_gmt, $autosave = false ) {
		$post                 = (array) get_post( $post_id );
		$post_revision_fields = _wp_post_revision_data( $post, $autosave );

		$post_revision_fields['post_content']  = $post_content;
		$post_revision_fields['post_date']     = $date_gmt;
		$post_revision_fields['post_date_gmt'] = $date_gmt;

		$revision_id = wp_insert_post( wp_slash( $post_revision_fields ), true );

		$this->assertNotWPError( $revision_id );
		$this->assertIsInt( $revision_id );

		return $revision_id;
	}
}
