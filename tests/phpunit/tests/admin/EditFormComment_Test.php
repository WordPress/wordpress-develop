<?php

/**
 * Tests for the parent comment selector on the Edit Comment screen.
 *
 * @group admin
 * @group comment
 */
class Admin_EditFormComment_Test extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 */
	public static int $admin_id;

	/**
	 * Post ID to add comments to.
	 */
	public static int $post_id;

	/**
	 * Create the user and post for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id  = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Renders the Edit Comment form for a comment and returns the output.
	 *
	 * @param int $comment_id Comment ID.
	 * @return string The rendered form.
	 */
	private function render_edit_form( $comment_id ) {
		global $comment;

		$comment = get_comment_to_edit( $comment_id );
		$action  = 'editcomment';

		set_current_screen( 'comment' );

		ob_start();
		require ABSPATH . 'wp-admin/edit-form-comment.php';
		$output = ob_get_clean();

		unset( $GLOBALS['comment'] );

		return $output;
	}

	/**
	 * Returns the parent comment dropdown markup from the rendered form.
	 *
	 * @param int $comment_id Comment ID.
	 * @return string The dropdown markup, or an empty string if not rendered.
	 */
	private function get_parent_dropdown( $comment_id ) {
		$output = $this->render_edit_form( $comment_id );

		if ( ! preg_match( '|<select name="comment_parent"[^>]*>.*?</select>|s', $output, $matches ) ) {
			return '';
		}

		return $matches[0];
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_list_valid_parents_and_exclude_invalid_ones() {
		$top_id     = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$reply_id   = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $top_id,
			)
		);
		$spam_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
			)
		);
		$trash_id   = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'trash',
			)
		);
		$pending_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$child_id   = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $comment_id,
			)
		);

		$dropdown = $this->get_parent_dropdown( $comment_id );

		$this->assertStringContainsString( 'value="0"', $dropdown, 'The "None" option should be listed.' );
		$this->assertStringContainsString( "value='{$top_id}'", $dropdown, 'A top-level comment should be listed.' );
		$this->assertStringContainsString( "value='{$reply_id}'", $dropdown, 'A nested comment should be listed.' );
		$this->assertStringNotContainsString( "value='{$comment_id}'", $dropdown, 'The comment being edited should not be listed.' );
		$this->assertStringNotContainsString( "value='{$child_id}'", $dropdown, 'A reply to the comment being edited should not be listed.' );
		$this->assertStringNotContainsString( "value='{$spam_id}'", $dropdown, 'A spam comment should not be listed.' );
		$this->assertStringNotContainsString( "value='{$trash_id}'", $dropdown, 'A trashed comment should not be listed.' );
		$this->assertStringNotContainsString( "value='{$pending_id}'", $dropdown, 'A pending comment should not be listed for an approved comment.' );
	}

	/**
	 * @ticket 65688
	 */
	public function test_should_list_pending_parent_for_pending_comment() {
		$parent_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$dropdown = $this->get_parent_dropdown( $comment_id );

		$this->assertStringContainsString( "value='{$parent_id}'", $dropdown, 'A pending comment should be listed for another pending comment.' );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_not_render_parent_selector_when_comment_threading_is_disabled() {
		update_option( 'thread_comments', 0 );

		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$output = $this->render_edit_form( $comment_id );

		$this->assertStringContainsString( 'id="comment-parent-display"', $output, 'The parent should still be displayed.' );
		$this->assertStringNotContainsString( 'name="comment_parent"', $output, 'The parent selector should not be rendered.' );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_account_for_replies_when_excluding_deep_parents() {
		update_option( 'thread_comments_depth', 3 );

		$top_id     = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$mid_id     = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $top_id,
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $comment_id,
			)
		);

		$dropdown = $this->get_parent_dropdown( $comment_id );

		$this->assertStringContainsString( "value='{$top_id}'", $dropdown, 'A parent leaving room for the reply should be listed.' );
		$this->assertStringNotContainsString( "value='{$mid_id}'", $dropdown, 'A parent whose depth plus the replies would exceed the maximum should not be listed.' );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_list_current_parent_even_when_it_is_not_a_valid_option() {
		$spam_parent_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
			)
		);
		$comment_id     = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $spam_parent_id,
			)
		);

		$dropdown = $this->get_parent_dropdown( $comment_id );

		$this->assertStringContainsString( "value='{$spam_parent_id}'", $dropdown, 'The current parent should be listed.' );
		$this->assertStringContainsString( ' selected>', $dropdown, 'The current parent should be selected.' );
	}
}
