<?php

/**
 * @group admin
 * @group comment
 *
 * @covers ::edit_comment
 */
class Admin_Includes_Comment_EditComment_Test extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 */
	public static int $admin_id;

	/**
	 * Post ID to add comments to.
	 */
	public static int $post_id;

	/**
	 * Another post ID, to test cross-post re-parenting.
	 */
	public static int $other_post_id;

	/**
	 * Create the user and posts for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id       = $factory->post->create();
		self::$other_post_id = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Calls edit_comment() with a comment ID and a new parent, as submitted from the Edit Comment screen.
	 *
	 * @param int         $comment_id     Comment ID.
	 * @param int         $comment_parent New parent comment ID.
	 * @param string|null $comment_status Optional. New comment status.
	 * @return int|WP_Error The edit_comment() return value.
	 */
	private function update_comment_parent( $comment_id, $comment_parent, $comment_status = null ) {
		$_POST = array(
			'comment_ID'     => $comment_id,
			'comment_parent' => $comment_parent,
		);

		if ( null !== $comment_status ) {
			$_POST['comment_status'] = $comment_status;
		}

		return edit_comment();
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_update_comment_parent() {
		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertSame( 1, $result );
		$this->assertSame( (string) $parent_id, get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65688
	 */
	public function test_should_reject_unapproved_parent_for_approved_comment() {
		$parent_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65688
	 */
	public function test_should_reject_unapproved_parent_when_comment_is_approved_in_same_update() {
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

		$result = $this->update_comment_parent( $comment_id, $parent_id, '1' );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_approved );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65688
	 */
	public function test_should_allow_unapproved_parent_when_comment_is_unapproved_in_same_update() {
		$parent_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id, '0' );

		$this->assertSame( 1, $result );
		$this->assertSame( '0', get_comment( $comment_id )->comment_approved );
		$this->assertSame( (string) $parent_id, get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_allow_clearing_comment_parent() {
		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $parent_id,
			)
		);

		$result = $this->update_comment_parent( $comment_id, 0 );

		$this->assertSame( 1, $result );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_parent_on_a_different_post() {
		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$other_post_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_nonexistent_parent() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $comment_id + 1000 );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_comment_as_its_own_parent() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $comment_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_child_as_parent() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$child_id   = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $comment_id,
			)
		);

		$result = $this->update_comment_parent( $comment_id, $child_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_descendant_as_parent() {
		$comment_id    = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$child_id      = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $comment_id,
			)
		);
		$grandchild_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $child_id,
			)
		);

		$result = $this->update_comment_parent( $comment_id, $grandchild_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_allow_resubmitting_unchanged_parent() {
		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $parent_id,
			)
		);

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertNotWPError( $result );
		$this->assertSame( (string) $parent_id, get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_new_parent_when_comment_threading_is_disabled() {
		update_option( 'thread_comments', 0 );

		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_allow_clearing_parent_when_comment_threading_is_disabled() {
		update_option( 'thread_comments', 0 );

		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $parent_id,
			)
		);

		$result = $this->update_comment_parent( $comment_id, 0 );

		$this->assertSame( 1, $result );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_parent_at_maximum_threading_depth() {
		update_option( 'thread_comments_depth', 2 );

		$top_id     = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$child_id   = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $top_id,
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $child_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_parent_when_replies_would_exceed_maximum_threading_depth() {
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

		// The comment itself would be at depth 3, but its reply would end up at depth 4.
		$result = $this->update_comment_parent( $comment_id, $mid_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_allow_moving_comment_with_replies_within_maximum_threading_depth() {
		update_option( 'thread_comments_depth', 3 );

		$top_id     = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $comment_id,
			)
		);

		// The comment ends up at depth 2 and its reply at depth 3, the maximum.
		$result = $this->update_comment_parent( $comment_id, $top_id );

		$this->assertSame( 1, $result );
		$this->assertSame( (string) $top_id, get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 */
	public function test_should_reject_parent_of_a_different_comment_type() {
		$parent_id  = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'pingback',
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * @ticket 65570
	 *
	 * @dataProvider data_should_reject_spam_or_trashed_parent
	 *
	 * @param string $comment_approved The parent's comment_approved value.
	 */
	public function test_should_reject_spam_or_trashed_parent( $comment_approved ) {
		$parent_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => $comment_approved,
			)
		);
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$result = $this->update_comment_parent( $comment_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_parent_invalid', $result->get_error_code() );
		$this->assertSame( '0', get_comment( $comment_id )->comment_parent );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_reject_spam_or_trashed_parent() {
		return array(
			'a spam parent'    => array( 'spam' ),
			'a trashed parent' => array( 'trash' ),
		);
	}
}
