<?php
/**
 * @group link
 * @group comment
 * @covers ::get_edit_comment_link
 */
class Tests_Link_GetEditCommentLink extends WP_UnitTestCase {

	public static $comment_id;
	public static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$comment_id = $factory->comment->create( array( 'comment_content' => 'Test comment' ) );

		self::$user_ids = array(
			'admin'      => $factory->user->create( array( 'role' => 'administrator' ) ),
			'subscriber' => $factory->user->create( array( 'role' => 'subscriber' ) ),
		);
	}

	public static function wpTearDownAfterClass() {
		// Delete the test comment.
		wp_delete_comment( self::$comment_id, true );

		// Delete the test users.
		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$user_ids['admin'] );
	}

	/**
	 * Tests that get_edit_comment_link() returns the correct URL by default.
	 */
	public function test_get_edit_comment_link_default() {
		$comment_id   = self::$comment_id;
		$expected_url = admin_url( 'comment.php?action=editcomment&amp;c=' . $comment_id );
		$actual_url   = get_edit_comment_link( $comment_id );

		$this->assertSame( $expected_url, $actual_url );
	}

	/**
	 * Tests that get_edit_comment_link() returns the correct URL with a context of 'display'.
	 *
	 * The expected result should include HTML entities.
	 *
	 * @ticket 61727
	 */
	public function test_get_edit_comment_link_display_context() {
		$comment_id   = self::$comment_id;
		$expected_url = admin_url( 'comment.php?action=editcomment&amp;c=' . $comment_id );
		$actual_url   = get_edit_comment_link( $comment_id, 'display' );

		$this->assertSame( $expected_url, $actual_url );
	}

	/**
	 * Tests that get_edit_comment_link() returns the correct URL with a context of 'url'.
	 *
	 * The expected result should not include HTML entities.
	 *
	 * @ticket 61727
	 */
	public function test_get_edit_comment_link_url_context() {
		$comment_id   = self::$comment_id;
		$expected_url = admin_url( 'comment.php?action=editcomment&c=' . $comment_id );
		$actual_url   = get_edit_comment_link( $comment_id, 'url' );

		$this->assertSame( $expected_url, $actual_url );
	}

	/**
	 * Tests that get_edit_comment_link() returns nothing if the comment ID is invalid.
	 *
	 * @ticket 61727
	 */
	public function test_get_edit_comment_link_invalid_comment() {
		$comment_id         = 12345;
		$actual_url_display = get_edit_comment_link( $comment_id, 'display' );
		$actual_url         = get_edit_comment_link( $comment_id, 'url' );

		$this->assertNull( $actual_url_display );
		$this->assertNull( $actual_url );
	}

	/**
	 * Tests that get_edit_comment_link() returns nothing if the current user cannot edit it.
	 */
	public function test_get_edit_comment_link_user_cannot_edit() {
		wp_set_current_user( self::$user_ids['subscriber'] );
		$comment_id         = self::$comment_id;
		$actual_url_display = get_edit_comment_link( $comment_id, 'display' );
		$actual_url         = get_edit_comment_link( $comment_id, 'url' );

		$this->assertNull( $actual_url_display );
		$this->assertNull( $actual_url );
	}

	/**
	 * Tests that the 'get_edit_comment_link' filter works as expected, including the additional parameters.
	 *
	 * @ticket 61727
	 */
	public function test_get_edit_comment_link_filter() {
		$comment_id           = self::$comment_id;
		$expected_url_display = admin_url( 'comment-test.php?context=display' );
		$expected_url         = admin_url( 'comment-test.php?context=url' );

		add_filter(
			'get_edit_comment_link',
			function ( $location, $comment_id, $context ) {
				return admin_url( 'comment-test.php?context=' . $context );
			},
			10,
			3
		);

		$actual_url_display = get_edit_comment_link( $comment_id, 'display' );
		$actual_url         = get_edit_comment_link( $comment_id, 'url' );

		// Assert the final URLs are as expected
		$this->assertSame( $expected_url_display, $actual_url_display );
		$this->assertSame( $expected_url, $actual_url );
	}

	/**
	 * Tests that the 'get_edit_comment_link' filter receives the comment ID, even when a comment object is passed.
	 *
	 * @ticket 61727
	 */
	public function test_get_edit_comment_link_filter_uses_id() {
		// Add a filter just to catch the $comment_id filter parameter value.
		$comment_id_filter_param = null;
		add_filter(
			'get_edit_comment_link',
			function ( $location, $comment_id ) use ( &$comment_id_filter_param ) {
				$comment_id_filter_param = $comment_id;
				return $location;
			},
			10,
			2
		);

		// Pass a comment object to get_edit_comment_link().
		get_edit_comment_link( get_comment( self::$comment_id ) );

		// The filter should still always receive the comment ID, not the object.
		$this->assertSame( self::$comment_id, $comment_id_filter_param );
	}
}
