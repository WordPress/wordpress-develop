<?php

/**
 * Tests for `_wp_get_revision_restore_redirect_url()`.
 *
 * @group post
 * @group revisions
 *
 * @covers ::_wp_get_revision_restore_redirect_url
 */
class Tests_Post_WpGetRevisionRestoreRedirectUrl extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Revision ID.
	 *
	 * @var int
	 */
	protected static $revision_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/revision.php';

		self::$editor_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$editor_id,
				'post_content' => 'Initial content',
			)
		);

		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => 'Updated content',
			)
		);

		$revisions         = wp_get_post_revisions( self::$post_id );
		$first_revision    = reset( $revisions );
		self::$revision_id = $first_revision->ID;
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$editor_id );
	}

	/**
	 * Tests that the `revision` query arg is omitted when the block editor is enabled.
	 *
	 * @ticket 66708
	 */
	public function test_omits_revision_query_arg_when_block_editor_is_enabled() {
		add_filter( 'use_block_editor_for_post', '__return_true' );

		$post     = get_post( self::$post_id );
		$redirect = _wp_get_revision_restore_redirect_url( $post, self::$revision_id );
		$query    = array();
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );

		$this->assertArrayHasKey( 'message', $query, 'Redirect URL should include the message query arg.' );
		$this->assertSame( '5', $query['message'], 'Redirect URL message query arg should be 5.' );
		$this->assertArrayNotHasKey( 'revision', $query, 'Redirect URL should omit the revision query arg for the block editor.' );
	}

	/**
	 * Tests that the `revision` query arg is preserved when the block editor is disabled.
	 *
	 * @ticket 66708
	 */
	public function test_includes_revision_query_arg_when_block_editor_is_disabled() {
		add_filter( 'use_block_editor_for_post', '__return_false' );

		$post     = get_post( self::$post_id );
		$redirect = _wp_get_revision_restore_redirect_url( $post, self::$revision_id );
		$query    = array();
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );

		$this->assertArrayHasKey( 'message', $query, 'Redirect URL should include the message query arg.' );
		$this->assertSame( '5', $query['message'], 'Redirect URL message query arg should be 5.' );
		$this->assertArrayHasKey( 'revision', $query, 'Redirect URL should include the revision query arg for the classic editor.' );
		$this->assertSame( (string) self::$revision_id, $query['revision'], 'Redirect URL revision query arg should match the restored revision ID.' );
	}
}
