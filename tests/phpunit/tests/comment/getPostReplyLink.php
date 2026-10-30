<?php

/**
 * Tests for the `get_post_reply_link()` and `post_reply_link()` functions.
 *
 * @group comment
 * @group link
 */
class Tests_Comment_GetPostReplyLink extends WP_UnitTestCase {

	/**
	 * Post ID with comments open.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Second post ID with comments open.
	 *
	 * @var int
	 */
	private static $other_post_id;

	/**
	 * Post ID with comments closed.
	 *
	 * @var int
	 */
	private static $closed_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id        = $factory->post->create( array( 'comment_status' => 'open' ) );
		self::$other_post_id  = $factory->post->create( array( 'comment_status' => 'open' ) );
		self::$closed_post_id = $factory->post->create( array( 'comment_status' => 'closed' ) );
	}

	/**
	 * Returns the expected reply link for a post.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $text       Link text.
	 * @param string $add_below  The add_below argument.
	 * @param string $respond_id The respond_id argument.
	 * @return string Expected link markup.
	 */
	private function expected_link( $post_id, $text = 'Leave a Comment', $add_below = 'post', $respond_id = 'respond' ) {
		return "<a rel='nofollow' class='comment-reply-link' href='http://" . WP_TESTS_DOMAIN . '/?p=' . $post_id . '#' . $respond_id . "'"
			. " onclick='return addComment.moveForm( \"" . $add_below . '-' . $post_id . '", "0", "' . $respond_id . '", "' . $post_id . "\" )'>"
			. $text . '</a>';
	}

	/**
	 * Tests that custom arguments change the reply link.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_get_post_reply_link_custom_arguments() {
		$args = array(
			'add_below'  => 'article',
			'respond_id' => 'reply-form',
			'reply_text' => 'Join the discussion',
			'before'     => '<p>',
			'after'      => '</p>',
		);

		$this->assertSame(
			'<p>' . $this->expected_link( self::$post_id, 'Join the discussion', 'article', 'reply-form' ) . '</p>',
			get_post_reply_link( $args, self::$post_id )
		);
	}

	/**
	 * Tests that the global post is used by default, and an explicit post takes precedence.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_get_post_reply_link_uses_global_post_by_default() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame( $this->expected_link( self::$post_id ), get_post_reply_link(), 'The global post should be used by default.' );
		$this->assertSame( $this->expected_link( self::$other_post_id ), get_post_reply_link( array(), self::$other_post_id ), 'An explicit post should be used instead of the global post.' );
	}

	/**
	 * Tests that false is returned when comments are closed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_get_post_reply_link_returns_false_when_comments_closed() {
		$this->assertFalse( get_post_reply_link( array(), self::$closed_post_id ) );
	}

	/**
	 * Tests the login link when registration is required and the user is logged out.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_get_post_reply_link_login_link_when_registration_required() {
		update_option( 'comment_registration', 1 );

		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame(
			'<p><a rel="nofollow" class="comment-reply-login" href="http://' . WP_TESTS_DOMAIN . '/wp-login.php?redirect_to=' . urlencode( get_permalink( self::$post_id ) ) . '">Log in to comment</a></p>',
			get_post_reply_link(
				array(
					'login_text' => 'Log in to comment',
					'before'     => '<p>',
					'after'      => '</p>',
				)
			)
		);
	}

	/**
	 * Tests that logged-in users get the reply link when registration is required.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_get_post_reply_link_for_logged_in_user_when_registration_required() {
		update_option( 'comment_registration', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( $this->expected_link( self::$post_id ), get_post_reply_link( array(), self::$post_id ) );
	}

	/**
	 * Tests the arguments and return value of the 'post_comments_link' filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_reply_link
	 */
	public function test_post_comments_link_filter() {
		$filter = new MockAction();
		add_filter( 'post_comments_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'post_comments_link',
			static function ( $link, $post ) {
				return '<div data-post="' . $post->ID . '">' . $link . '</div>';
			},
			20,
			2
		);

		$link = get_post_reply_link( array( 'before' => '<p>' ), self::$post_id );
		$args = $filter->get_args()[0];

		$this->assertSame( '<p>' . $this->expected_link( self::$post_id ), $args[0], 'The filter should receive the link markup.' );
		$this->assertSame( self::$post_id, $args[1]->ID, 'The filter should receive the post.' );
		$this->assertSame( '<div data-post="' . self::$post_id . '"><p>' . $this->expected_link( self::$post_id ) . '</div>', $link, 'The filtered link should be returned.' );
	}

	/**
	 * Tests that post_reply_link() displays the reply link.
	 *
	 * @ticket 65819
	 *
	 * @covers ::post_reply_link
	 */
	public function test_post_reply_link_displays_link() {
		$this->expectOutputString( '<p>' . $this->expected_link( self::$post_id, 'Reply' ) . '</p>' );

		post_reply_link(
			array(
				'reply_text' => 'Reply',
				'before'     => '<p>',
				'after'      => '</p>',
			),
			self::$post_id
		);
	}
}
