<?php

/**
 * Tests for wp_update_comment().
 *
 * @group comment
 *
 * @covers ::wp_update_comment
 */
class Tests_Comment_wpUpdateComment extends WP_UnitTestCase {

	protected static $post_id;
	protected static $admin_id;
	protected static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id  = $factory->post->create();
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Updating a comment with a user lacking the unfiltered_html capability
	 * should filter the comment content.
	 *
	 * @ticket 57343
	 */
	public function test_wp_update_comment_filters_content_without_unfiltered_html() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$author_id,
			)
		);

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'A comment with a <script>alert( 1 );</script> tag.',
			)
		);

		$this->assertSame( 'A comment with a  tag.', get_comment( $comment_id )->comment_content );
	}

	/**
	 * A user with the unfiltered_html capability editing another user's comment
	 * should be able to save HTML, consistent with the post editor.
	 *
	 * @ticket 57343
	 */
	public function test_wp_update_comment_preserves_html_for_editing_user_with_unfiltered_html() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$author_id,
			)
		);

		wp_set_current_user( self::$admin_id );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'A comment with a <script>alert( 1 );</script> tag.',
			)
		);

		$this->assertSame(
			'A comment with a <script>alert( 1 );</script> tag.',
			get_comment( $comment_id )->comment_content
		);
	}

	/**
	 * Filtering is based on the editing user, not the comment author: content in
	 * a comment authored by a privileged user is still filtered when the update
	 * is performed without the unfiltered_html capability.
	 *
	 * @ticket 57343
	 */
	public function test_wp_update_comment_filters_content_despite_privileged_comment_author() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$admin_id,
			)
		);

		// No logged-in user: a programmatic update keeps filtering by default.
		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'A comment with a <script>alert( 1 );</script> tag.',
			)
		);

		$this->assertSame( 'A comment with a  tag.', get_comment( $comment_id )->comment_content );
	}
}
