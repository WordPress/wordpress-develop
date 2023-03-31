<?php

/**
 * @group comment
 */
class Tests_Comment extends WP_UnitTestCase {
	protected static $user_id;
	protected static $post_id;
	protected static $notify_message = '';

	protected $preprocess_comment_data = array();

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'test_wp_user_get',
				'user_pass'  => 'password',
				'user_email' => 'test@test.com',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);
	}

	/**
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment() {
		$post  = self::factory()->post->create_and_get(
			array(
				'post_title' => 'some-post',
				'post_type'  => 'post',
			)
		);
		$post2 = self::factory()->post->create_and_get(
			array(
				'post_title' => 'some-post-2',
				'post_type'  => 'post',
			)
		);

		$comments = self::factory()->comment->create_post_comments( $post->ID, 5 );

		$result = wp_update_comment(
			array(
				'comment_ID'     => $comments[0],
				'comment_parent' => $comments[1],
			)
		);
		$this->assertSame( 1, $result );

		$comment = get_comment( $comments[0] );
		$this->assertEquals( $comments[1], $comment->comment_parent );

		$result = wp_update_comment(
			array(
				'comment_ID'     => $comments[0],
				'comment_parent' => $comments[1],
			)
		);
		$this->assertSame( 0, $result );

		$result = wp_update_comment(
			array(
				'comment_ID'      => $comments[0],
				'comment_post_ID' => $post2->ID,
			)
		);

		$comment = get_comment( $comments[0] );
		$this->assertEquals( $post2->ID, $comment->comment_post_ID );
	}

	public function test_update_comment_from_privileged_user_by_privileged_user() {
		$admin_id_1 = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id_1 );

		$comment_id = wp_new_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'Author',
				'comment_author_url'   => 'http://example.localhost/',
				'comment_author_email' => 'test@test.com',
				'user_id'              => $admin_id_1,
				'comment_content'      => 'This is a comment',
			)
		);

		wp_set_current_user( 0 );

		$admin_id_2 = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_wp_admin_get',
				'user_pass'  => 'password',
				'user_email' => 'testadmin@test.com',
			)
		);

		wp_set_current_user( $admin_id_2 );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'new comment <img onerror=demo src=x>',
			)
		);

		$comment          = get_comment( $comment_id );
		$expected_content = is_multisite()
			? 'new comment '
			: 'new comment <img onerror=demo src=x>';

		$this->assertSame( $expected_content, $comment->comment_content );

		wp_set_current_user( 0 );
	}

	public function test_update_comment_from_unprivileged_user_by_privileged_user() {
		wp_set_current_user( self::$user_id );

		$comment_id = wp_new_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'Author',
				'comment_author_url'   => 'http://example.localhost/',
				'comment_author_email' => 'test@test.com',
				'user_id'              => self::$user_id,
				'comment_content'      => '<a href="http://example.localhost/something.html">click</a>',
			)
		);

		wp_set_current_user( 0 );

		$admin_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_wp_admin_get',
				'user_pass'  => 'password',
				'user_email' => 'testadmin@test.com',
			)
		);

		wp_set_current_user( $admin_id );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => '<a href="http://example.localhost/something.html" disallowed=attribute>click</a>',
			)
		);

		$comment = get_comment( $comment_id );
		$this->assertSame( '<a href="http://example.localhost/something.html" rel="nofollow ugc">click</a>', $comment->comment_content, 'Comment: ' . $comment->comment_content );
		wp_set_current_user( 0 );
	}

	/**
	 * @ticket 30627
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_updates_comment_type() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		wp_update_comment(
			array(
				'comment_ID'   => $comment_id,
				'comment_type' => 'pingback',
			)
		);

		$comment = get_comment( $comment_id );
		$this->assertSame( 'pingback', $comment->comment_type );
	}

	/**
	 * @ticket 36784
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_updates_comment_meta() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		wp_update_comment(
			array(
				'comment_ID'   => $comment_id,
				'comment_meta' => array(
					'food'  => 'taco',
					'sauce' => 'fire',
				),
			)
		);

		$this->assertSame( 'fire', get_comment_meta( $comment_id, 'sauce', true ) );
	}

	/**
	 * @ticket 30307
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_updates_user_id() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		wp_update_comment(
			array(
				'comment_ID' => $comment_id,
				'user_id'    => 1,
			)
		);

		$comment = get_comment( $comment_id );
		$this->assertEquals( 1, $comment->user_id );
	}

	/**
	 * @ticket 34954
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_with_no_post_id() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => 0 ) );

		$updated_comment_text = 'I should be able to update a comment with a Post ID of zero';

		$update = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => $updated_comment_text,
				'comment_post_ID' => 0,
			)
		);
		$this->assertSame( 1, $update );

		$comment = get_comment( $comment_id );
		$this->assertSame( $updated_comment_text, $comment->comment_content );
	}

	/**
	 * @ticket 39732
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_returns_false_for_invalid_comment_or_post_id() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$update = wp_update_comment(
			array(
				'comment_ID'      => -1,
				'comment_post_ID' => self::$post_id,
			)
		);
		$this->assertFalse( $update );

		$update = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_post_ID' => -1,
			)
		);
		$this->assertFalse( $update );
	}

	/**
	 * @ticket 39732
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_is_wp_error() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		add_filter( 'wp_update_comment_data', array( $this, 'wp_update_comment_data_filter' ), 10, 3 );

		$result = wp_update_comment(
			array(
				'comment_ID'   => $comment_id,
				'comment_type' => 'pingback',
			),
			true
		);

		remove_filter( 'wp_update_comment_data', array( $this, 'wp_update_comment_data_filter' ), 10, 3 );

		$this->assertWPError( $result );
	}

	/**
	 * Blocks comments from being updated by returning WP_Error.
	 */
	public function wp_update_comment_data_filter( $data, $comment, $commentarr ) {
		return new WP_Error( 'comment_wrong', 'wp_update_comment_data filter fails for this comment.', 500 );
	}

	/**
	 * @covers ::get_approved_comments
	 */
	public function test_get_approved_comments() {
		$ca1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$ca2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$ca3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$c2  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$found = get_approved_comments( self::$post_id );

		// All comment types will be returned.
		$this->assertEquals( array( $ca1, $ca2, $c2, $c3, $c4, $c5 ), wp_list_pluck( $found, 'comment_ID' ) );
	}

	/**
	 * @ticket 30412
	 *
	 * @covers ::get_approved_comments
	 */
	public function test_get_approved_comments_with_post_id_0_should_return_empty_array() {
		$ca1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$found = get_approved_comments( 0 );

		$this->assertSame( array(), $found );
	}

	/**
	 * Tests that get_cancel_comment_reply_link() returns the expected value.
	 *
	 * @ticket 53962
	 *
	 * @dataProvider data_get_cancel_comment_reply_link
	 *
	 * @covers ::get_cancel_comment_reply_link
	 *
	 * @param string        $text       Text to display for cancel reply link.
	 *                                  If empty, defaults to 'Click here to cancel reply'.
	 * @param string|int    $post       The post the comment thread is being displayed for.
	 *                                  Accepts 'POST_ID', 'POST', or an integer post ID.
	 * @param int|bool|null $replytocom A comment ID (int), whether to generate an approved (true) or unapproved (false) comment,
	 *                                  or null not to create a comment.
	 * @param string        $expected   The expected reply link.
	 */
	public function test_get_cancel_comment_reply_link( $text, $post, $replytocom, $expected ) {
		if ( 'POST_ID' === $post ) {
			$post = self::$post_id;
		} elseif ( 'POST' === $post ) {
			$post = self::factory()->post->get_object_by_id( self::$post_id );
		}

		if ( null === $replytocom ) {
			unset( $_GET['replytocom'] );
		} else {
			$_GET['replytocom'] = $this->create_comment_with_approval_status( $replytocom );
		}

		$this->assertSame( $expected, get_cancel_comment_reply_link( $text, $post ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_cancel_comment_reply_link() {
		return array(
			'text as empty string, a valid post ID and an approved comment'    => array(
				'text'       => '',
				'post'       => 'POST_ID',
				'replytocom' => true,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond">Click here to cancel reply.</a>',
			),
			'text as a custom string, a valid post ID and an approved comment' => array(
				'text'       => 'Leave a reply!',
				'post'       => 'POST_ID',
				'replytocom' => true,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond">Leave a reply!</a>',
			),
			'text as empty string, a valid WP_Post object and an approved comment' => array(
				'text'       => '',
				'post'       => 'POST',
				'replytocom' => true,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond">Click here to cancel reply.</a>',
			),
			'text as a custom string, a valid WP_Post object and an approved comment' => array(
				'text'       => 'Leave a reply!',
				'post'       => 'POST',
				'replytocom' => true,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond">Leave a reply!</a>',
			),
			'text as empty string, an invalid post and an approved comment'    => array(
				'text'       => '',
				'post'       => -99999,
				'replytocom' => true,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">Click here to cancel reply.</a>',
			),
			'text as a custom string, a valid post, but no replytocom' => array(
				'text'       => 'Leave a reply!',
				'post'       => 'POST',
				'replytocom' => null,
				'expected'   => '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">Leave a reply!</a>',
			),
		);
	}

	/**
	 * Tests that comment_form_title() outputs the author of an approved comment.
	 *
	 * @ticket 53962
	 *
	 * @covers ::comment_form_title
	 */
	public function test_should_output_the_author_of_an_approved_comment() {
		// Must be set for `comment_form_title()`.
		$_GET['replytocom'] = $this->create_comment_with_approval_status( true );

		$comment = get_comment( $_GET['replytocom'] );
		comment_form_title( false, false, false, self::$post_id );

		$this->assertInstanceOf(
			'WP_Comment',
			$comment,
			'The comment is not an instance of WP_Comment.'
		);

		$this->assertObjectHasAttribute(
			'comment_author',
			$comment,
			'The comment object does not have a "comment_author" property.'
		);

		$this->assertIsString(
			$comment->comment_author,
			'The "comment_author" is not a string.'
		);

		$this->expectOutputString(
			'Leave a Reply to ' . $comment->comment_author,
			'The expected string was not output.'
		);
	}

	/**
	 * Tests that get_comment_id_fields() allows replying to an approved comment.
	 *
	 * @ticket 53962
	 *
	 * @dataProvider data_should_allow_reply_to_an_approved_comment
	 *
	 * @covers ::get_comment_id_fields
	 *
	 * @param string $comment_post The post of the comment.
	 *                             Accepts 'POST', 'NEW_POST', 'POST_ID' and 'NEW_POST_ID'.
	 */
	public function test_should_allow_reply_to_an_approved_comment( $comment_post ) {
		// Must be set for `get_comment_id_fields()`.
		$_GET['replytocom'] = $this->create_comment_with_approval_status( true );

		if ( 'POST_ID' === $comment_post ) {
			$comment_post = self::$post_id;
		} elseif ( 'POST' === $comment_post ) {
			$comment_post = self::factory()->post->get_object_by_id( self::$post_id );
		}

		$expected  = "<input type='hidden' name='comment_post_ID' value='" . self::$post_id . "' id='comment_post_ID' />\n";
		$expected .= "<input type='hidden' name='comment_parent' id='comment_parent' value='" . $_GET['replytocom'] . "' />\n";
		$actual    = get_comment_id_fields( $comment_post );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_allow_reply_to_an_approved_comment() {
		return array(
			'a post ID'        => array( 'comment_post' => 'POST_ID' ),
			'a WP_Post object' => array( 'comment_post' => 'POST' ),
		);
	}

	/**
	 * Tests that get_comment_id_fields() returns an empty string
	 * when the post cannot be retrieved.
	 *
	 * @ticket 53962
	 *
	 * @dataProvider data_non_existent_posts
	 *
	 * @covers ::get_comment_id_fields
	 *
	 * @param bool  $replytocom   Whether to create an approved (true) or unapproved (false) comment.
	 * @param int   $comment_post The post of the comment.
	 *
	 */
	public function test_should_return_empty_string( $replytocom, $comment_post ) {
		if ( is_bool( $replytocom ) ) {
			$replytocom = $this->create_comment_with_approval_status( $replytocom );
		}

		// Must be set for `get_comment_id_fields()`.
		$_GET['replytocom'] = $replytocom;

		$actual = get_comment_id_fields( $comment_post );

		$this->assertSame( '', $actual );
	}

	/**
	 * Tests that comment_form_title() does not output the author.
	 *
	 * @ticket 53962
	 *
	 * @covers ::comment_form_title
	 *
	 * @dataProvider data_parent_comments
	 * @dataProvider data_non_existent_posts
	 *
	 * @param bool   $replytocom   Whether to create an approved (true) or unapproved (false) comment.
	 * @param string $comment_post The post of the comment.
	 *                             Accepts 'POST', 'NEW_POST', 'POST_ID' and 'NEW_POST_ID'.
	 */
	public function test_should_not_output_the_author( $replytocom, $comment_post ) {
		if ( is_bool( $replytocom ) ) {
			$replytocom = $this->create_comment_with_approval_status( $replytocom );
		}

		// Must be set for `comment_form_title()`.
		$_GET['replytocom'] = $replytocom;

		if ( 'NEW_POST_ID' === $comment_post ) {
			$comment_post = self::factory()->post->create();
		} elseif ( 'NEW_POST' === $comment_post ) {
			$comment_post = self::factory()->post->create_and_get();
		} elseif ( 'POST_ID' === $comment_post ) {
			$comment_post = self::$post_id;
		} elseif ( 'POST' === $comment_post ) {
			$comment_post = self::factory()->post->get_object_by_id( self::$post_id );
		}

		$comment_post_id = $comment_post instanceof WP_Post ? $comment_post->ID : $comment_post;

		get_comment( $_GET['replytocom'] );

		comment_form_title( false, false, false, $comment_post_id );

		$this->expectOutputString( 'Leave a Reply' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_existent_posts() {
		return array(
			'an unapproved comment and a non-existent post ID' => array(
				'replytocom'   => false,
				'comment_post' => -99999,
			),
			'an approved comment and a non-existent post ID' => array(
				'replytocom'   => true,
				'comment_post' => -99999,
			),
		);
	}

	/**
	 * Tests that get_comment_id_fields() does not allow replies when
	 * the comment does not have a parent post.
	 *
	 * @ticket 53962
	 *
	 * @covers ::get_comment_id_fields
	 *
	 * @dataProvider data_parent_comments
	 *
	 * @param mixed  $replytocom   Whether to create an approved (true) or unapproved (false) comment,
	 *                             or an invalid comment ID.
	 * @param string $comment_post The post of the comment.
	 *                             Accepts 'POST', 'NEW_POST', 'POST_ID' and 'NEW_POST_ID'.
	 */
	public function test_should_not_allow_reply( $replytocom, $comment_post ) {
		if ( is_bool( $replytocom ) ) {
			$replytocom = $this->create_comment_with_approval_status( $replytocom );
		}

		// Must be set for `get_comment_id_fields()`.
		$_GET['replytocom'] = $replytocom;

		if ( 'NEW_POST_ID' === $comment_post ) {
			$comment_post = self::factory()->post->create();
		} elseif ( 'NEW_POST' === $comment_post ) {
			$comment_post = self::factory()->post->create_and_get();
		} elseif ( 'POST_ID' === $comment_post ) {
			$comment_post = self::$post_id;
		} elseif ( 'POST' === $comment_post ) {
			$comment_post = self::factory()->post->get_object_by_id( self::$post_id );
		}

		$comment_post_id = $comment_post instanceof WP_Post ? $comment_post->ID : $comment_post;

		$expected  = "<input type='hidden' name='comment_post_ID' value='" . $comment_post_id . "' id='comment_post_ID' />\n";
		$expected .= "<input type='hidden' name='comment_parent' id='comment_parent' value='0' />\n";
		$actual    = get_comment_id_fields( $comment_post );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_parent_comments() {
		return array(
			'an unapproved parent comment (ID)'      => array(
				'replytocom'   => false,
				'comment_post' => 'POST_ID',
			),
			'an approved parent comment on another post (ID)' => array(
				'replytocom'   => true,
				'comment_post' => 'NEW_POST_ID',
			),
			'an unapproved parent comment on another post (ID)' => array(
				'replytocom'   => false,
				'comment_post' => 'NEW_POST_ID',
			),
			'a parent comment ID that cannot be cast to an integer' => array(
				'replytocom'   => array( 'I cannot be cast to an integer.' ),
				'comment_post' => 'POST_ID',
			),
			'an unapproved parent comment (WP_Post)' => array(
				'replytocom'   => false,
				'comment_post' => 'POST',
			),
			'an approved parent comment on another post (WP_Post)' => array(
				'replytocom'   => true,
				'comment_post' => 'NEW_POST',
			),
			'an unapproved parent comment on another post (WP_Post)' => array(
				'replytocom'   => false,
				'comment_post' => 'NEW_POST',
			),
			'a parent comment WP_Post that cannot be cast to an integer' => array(
				'replytocom'   => array( 'I cannot be cast to an integer.' ),
				'comment_post' => 'POST',
			),
		);
	}

	/**
	 * Helper function to create a comment with an approval status.
	 *
	 * @since 6.2.0
	 *
	 * @param bool $approved Whether or not the comment is approved.
	 * @return int The comment ID.
	 */
	public function create_comment_with_approval_status( $approved ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => ( $approved ) ? '1' : '0',
			)
		);
	}

	/**
	 * Tests that _get_comment_reply_id() returns the expected value.
	 *
	 * @ticket 53962
	 *
	 * @dataProvider data_get_comment_reply_id
	 *
	 * @covers ::_get_comment_reply_id
	 *
	 * @param int|bool|null $replytocom A comment ID (int), whether to generate an approved (true) or unapproved (false) comment,
	 *                                  or null not to create a comment.
	 * @param string|int    $post       The post the comment thread is being displayed for.
	 *                                  Accepts 'POST_ID', 'POST', or an integer post ID.
	 * @param int           $expected   The expected result.
	 */
	public function test_get_comment_reply_id( $replytocom, $post, $expected ) {
		if ( false === $replytocom ) {
			unset( $_GET['replytocom'] );
		} else {
			$_GET['replytocom'] = $this->create_comment_with_approval_status( (bool) $replytocom );
		}

		if ( 'POST_ID' === $post ) {
			$post = self::$post_id;
		} elseif ( 'POST' === $post ) {
			$post = self::factory()->post->get_object_by_id( self::$post_id );
		}

		if ( 'replytocom' === $expected ) {
			$expected = $_GET['replytocom'];
		}

		$this->assertSame( $expected, _get_comment_reply_id( $post ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_comment_reply_id() {
		return array(
			'no comment ID set ($_GET["replytocom"])'     => array(
				'replytocom' => false,
				'post'       => 0,
				'expected'   => 0,
			),
			'a non-numeric comment ID'                    => array(
				'replytocom' => 'three',
				'post'       => 0,
				'expected'   => 0,
			),
			'a non-existent comment ID'                   => array(
				'replytocom' => -999999,
				'post'       => 0,
				'expected'   => 0,
			),
			'an unapproved comment'                       => array(
				'replytocom' => false,
				'post'       => 0,
				'expected'   => 0,
			),
			'a post that does not match the parent'       => array(
				'replytocom' => false,
				'post'       => -999999,
				'expected'   => 0,
			),
			'an approved comment and the correct post ID' => array(
				'replytocom' => true,
				'post'       => 'POST_ID',
				'expected'   => 'replytocom',
			),
			'an approved comment and the correct WP_Post object' => array(
				'replytocom' => true,
				'post'       => 'POST',
				'expected'   => 'replytocom',
			),
		);
	}

	/**
	 * @ticket 14279
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_dates() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
			'comment_date'         => '2011-01-01 10:00:00',
			'comment_date_gmt'     => '2011-01-01 10:00:00',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( $data['comment_date'], $comment->comment_date );
		$this->assertSame( $data['comment_date_gmt'], $comment->comment_date_gmt );
	}

	/**
	 * @ticket 14601
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_author_ip() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '192.168.1.1',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( $data['comment_author_IP'], $comment->comment_author_IP );
	}

	/**
	 * @ticket 14601
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_author_ip_empty_string() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( $data['comment_author_IP'], $comment->comment_author_IP );
	}

	/**
	 * @ticket 14601
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_comment_agent() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_agent'        => 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( $data['comment_agent'], $comment->comment_agent );
	}

	/**
	 * @ticket 14601
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_should_trim_provided_comment_agent_to_254_chars() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_agent'        => 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53 Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16 Mozilla/5.0 (Macintosh; U; PPC Mac OS X Mach-O; en; rv:1.8.1.4pre) Gecko/20070511 Camino/1.6pre',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53 Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16 Mozilla/5.0 (Macintosh; U; PPC Mac OS ', $comment->comment_agent );
	}

	/**
	 * @ticket 14601
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_comment_agent_empty_string() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_agent'        => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( $data['comment_agent'], $comment->comment_agent );
	}

	/**
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_respects_comment_field_lengths() {
		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => str_repeat( 'A', 65536 ),
			'comment_date'         => '2011-01-01 10:00:00',
			'comment_date_gmt'     => '2011-01-01 10:00:00',
		);

		$id = wp_new_comment( $data );

		$comment = get_comment( $id );

		$this->assertSame( 65535, strlen( $comment->comment_content ) );
	}

	/**
	 * @ticket 56244
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment_sends_all_expected_parameters_to_preprocess_comment_filter() {
		$user = get_userdata( self::$user_id );
		wp_set_current_user( $user->ID );

		$data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => $user->user_url,
			'comment_content'      => 'Comment',
			'comment_type'         => '',
			'comment_parent'       => 0,
			'user_id'              => $user->ID,
		);

		add_filter( 'preprocess_comment', array( $this, 'filter_preprocess_comment' ) );

		$comment = wp_new_comment( $data );

		$this->assertNotWPError( $comment );
		$this->assertSameSetsWithIndex(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => $user->user_url,
				'comment_content'      => $data['comment_content'],
				'comment_type'         => '',
				'comment_parent'       => 0,
				'user_ID'              => $user->ID,
				'user_id'              => $user->ID,
				'comment_author_IP'    => '127.0.0.1',
				'comment_agent'        => '',
			),
			$this->preprocess_comment_data
		);

	}

	public function filter_preprocess_comment( $commentdata ) {
		$this->preprocess_comment_data = $commentdata;
		return $commentdata;
	}

	/**
	 * @ticket 32566
	 *
	 * @covers ::wp_notify_moderator
	 */
	public function test_wp_notify_moderator_should_not_throw_notice_when_post_author_is_0() {
		$p = self::factory()->post->create(
			array(
				'post_author' => 0,
			)
		);

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID' => $p,
			)
		);

		$this->assertTrue( wp_notify_moderator( $c ) );
	}

	/**
	 * @covers ::wp_new_comment_notify_postauthor
	 */
	public function test_wp_new_comment_notify_postauthor_should_send_email_when_comment_is_approved() {
		$c = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		$sent = wp_new_comment_notify_postauthor( $c );
		$this->assertTrue( $sent );
	}

	/**
	 * @covers ::wp_new_comment_notify_postauthor
	 */
	public function test_wp_new_comment_notify_postauthor_should_not_send_email_when_comment_is_unapproved() {
		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$sent = wp_new_comment_notify_postauthor( $c );
		$this->assertFalse( $sent );
	}

	/**
	 * @ticket 33587
	 *
	 * @covers ::wp_new_comment_notify_postauthor
	 */
	public function test_wp_new_comment_notify_postauthor_should_not_send_email_when_comment_has_been_marked_as_spam() {
		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
			)
		);

		$sent = wp_new_comment_notify_postauthor( $c );
		$this->assertFalse( $sent );
	}

	/**
	 * @ticket 35006
	 *
	 * @covers ::wp_new_comment_notify_postauthor
	 */
	public function test_wp_new_comment_notify_postauthor_should_not_send_email_when_comment_has_been_trashed() {
		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'trash',
			)
		);

		$sent = wp_new_comment_notify_postauthor( $c );
		$this->assertFalse( $sent );
	}

	/**
	 * @ticket 43805
	 *
	 * @covers ::wp_new_comment_notify_postauthor
	 */
	public function test_wp_new_comment_notify_postauthor_content_should_include_link_to_parent() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_parent'  => $c1,
			)
		);

		add_filter( 'comment_notification_text', array( $this, 'save_comment_notification_text' ) );
		wp_new_comment_notify_postauthor( $c2 );
		remove_filter( 'comment_notification_text', array( $this, 'save_comment_notification_text' ) );

		$this->assertStringContainsString( admin_url( "comment.php?action=editcomment&c={$c1}" ), self::$notify_message );
	}

	/**
	 * @ticket 43805
	 *
	 * @covers ::wp_new_comment_notify_moderator
	 */
	public function test_wp_new_comment_notify_moderator_content_should_include_link_to_parent() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_parent'   => $c1,
				'comment_approved' => '0',
			)
		);

		add_filter( 'comment_moderation_text', array( $this, 'save_comment_notification_text' ) );
		wp_new_comment_notify_moderator( $c2 );
		remove_filter( 'comment_moderation_text', array( $this, 'save_comment_notification_text' ) );

		$this->assertStringContainsString( admin_url( "comment.php?action=editcomment&c={$c1}" ), self::$notify_message );
	}

	/**
	 * Callback for the `comment_notification_text` & `comment_moderation_text` filters.
	 *
	 * @param string $notify_message The comment notification or moderation email text.
	 * @return string
	 */
	public function save_comment_notification_text( $notify_message = '' ) {
		self::$notify_message = $notify_message;
		return $notify_message;
	}

	/**
	 * @ticket 12431
	 *
	 * @covers ::get_comment_meta
	 */
	public function test_wp_new_comment_with_meta() {
		$c = self::factory()->comment->create(
			array(
				'comment_approved' => '1',
				'comment_meta'     => array(
					'food'  => 'taco',
					'sauce' => 'fire',
				),
			)
		);

		$this->assertSame( 'fire', get_comment_meta( $c, 'sauce', true ) );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment::get_children
	 */
	public function test_wp_comment_get_children_should_fill_children() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c2,
			)
		);

		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c5,
			)
		);

		$comment  = get_comment( $c1 );
		$children = $comment->get_children();

		// Direct descendants of $c1.
		$this->assertEqualSets( array( $c2, $c4 ), array_values( wp_list_pluck( $children, 'comment_ID' ) ) );

		// Direct descendants of $c2.
		$this->assertEqualSets( array( $c3 ), array_values( wp_list_pluck( $children[ $c2 ]->get_children(), 'comment_ID' ) ) );
	}

	/**
	 * @ticket 27571
	 *
	 * @covers ::get_comment
	 */
	public function test_post_properties_should_be_lazyloaded() {
		$c = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$post    = get_post( self::$post_id );
		$comment = get_comment( $c );

		$post_fields = array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count' );

		foreach ( $post_fields as $pf ) {
			$this->assertTrue( isset( $comment->$pf ), $pf );
			$this->assertSame( $post->$pf, $comment->$pf, $pf );
		}
	}


	/**
	 * Helper function to set up comment for 761 tests.
	 *
	 * @since 4.4.0
	 * @access public
	 */
	public function setup_notify_comment() {
		/**
		 * Prevent flood alert from firing.
		 */
		add_filter( 'comment_flood_filter', '__return_false' );

		/**
		 * Set up a comment for testing.
		 */
		$post = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post,
			)
		);

		return array(
			'post'    => $post,
			'comment' => $comment,
		);
	}

	/**
	 * @ticket 761
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_notify_moderator_filter_moderation_notify_option_true_filter_false() {
		$comment_data = $this->setup_notify_comment();

		/**
		 * Test with moderator notification setting on, filter set to off.
		 * Should not send a notification.
		 */
		update_option( 'moderation_notify', 1 );
		add_filter( 'notify_moderator', '__return_false' );

		$notification_sent = $this->try_sending_moderator_notification( $comment_data['comment'], $comment_data['post'] );

		$this->assertFalse( $notification_sent, 'Moderator notification setting on, filter set to off' );

		remove_filter( 'notify_moderator', '__return_false' );
		remove_filter( 'comment_flood_filter', '__return_false' );
	}

	/**
	 * @ticket 761
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_notify_moderator_filter_moderation_notify_option_false_filter_true() {
		$comment_data = $this->setup_notify_comment();

		/**
		 * Test with moderator notification setting off, filter set to on.
		 * Should send a notification.
		 */
		update_option( 'moderation_notify', 0 );
		add_filter( 'notify_moderator', '__return_true' );

		$notification_sent = $this->try_sending_moderator_notification( $comment_data['comment'], $comment_data['post'] );

		$this->assertTrue( $notification_sent, 'Moderator notification setting off, filter set to on' );

		remove_filter( 'notify_moderator', '__return_true' );
		remove_filter( 'comment_flood_filter', '__return_false' );
	}

	/**
	 * @ticket 761
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_notify_post_author_filter_comments_notify_option_true_filter_false() {

		$comment_data = $this->setup_notify_comment();

		/**
		 * Test with author notification setting on, filter set to off.
		 * Should not send a notification.
		 */
		update_option( 'comments_notify', 1 );
		add_filter( 'notify_post_author', '__return_false' );

		$notification_sent = $this->try_sending_author_notification( $comment_data['comment'], $comment_data['post'] );

		$this->assertFalse( $notification_sent, 'Test with author notification setting on, filter set to off' );

		remove_filter( 'notify_post_author', '__return_false' );
		remove_filter( 'comment_flood_filter', '__return_false' );
	}

	/**
	 * @ticket 761
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_notify_post_author_filter_comments_notify_option_false_filter_true() {
		$comment_data = $this->setup_notify_comment();

		/**
		 * Test with author notification setting off, filter set to on.
		 * Should send a notification.
		 */
		update_option( 'comments_notify', 0 );
		add_filter( 'notify_post_author', '__return_true' );

		$notification_sent = $this->try_sending_author_notification( $comment_data['comment'], $comment_data['post'] );

		$this->assertTrue( $notification_sent, 'Test with author notification setting off, filter set to on' );

		remove_filter( 'notify_post_author', '__return_true' );
		remove_filter( 'comment_flood_filter', '__return_false' );
	}

	/**
	 * Helper function to test moderator notifications.
	 *
	 * @since 4.4.0
	 * @access public
	 */
	public function try_sending_moderator_notification( $comment, $post ) {

		// Don't approve comments, triggering notifications.
		add_filter( 'pre_comment_approved', '__return_false' );

		// Moderators are notified when a new comment is added.
		$data = array(
			'comment_post_ID'      => $post,
			'comment_author'       => 'Comment Author',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);
		wp_new_comment( $data );

		// Check to see if a notification email was sent to the moderator `admin@example.org`.
		if ( isset( $GLOBALS['phpmailer']->mock_sent )
			&& ! empty( $GLOBALS['phpmailer']->mock_sent )
			&& WP_TESTS_EMAIL === $GLOBALS['phpmailer']->mock_sent[0]['to'][0][0]
		) {
			$email_sent_when_comment_added = true;
			reset_phpmailer_instance();
		} else {
			$email_sent_when_comment_added = false;
		}

		return $email_sent_when_comment_added;
	}

	/**
	 * Helper function to test sending author notifications.
	 *
	 * @since 4.4.0
	 * @access public
	 */
	public function try_sending_author_notification( $comment, $post ) {

		// Approve comments, triggering notifications.
		add_filter( 'pre_comment_approved', '__return_true' );

		// Post authors possibly notified when a comment is approved on their post.
		wp_set_comment_status( $comment, 'approve' );

		// Check to see if a notification email was sent to the post author `test@test.com`.
		if ( isset( $GLOBALS['phpmailer']->mock_sent )
			&& ! empty( $GLOBALS['phpmailer']->mock_sent )
			&& 'test@test.com' === $GLOBALS['phpmailer']->mock_sent[0]['to'][0][0]
		) {
			$email_sent_when_comment_approved = true;
		} else {
			$email_sent_when_comment_approved = false;
		}
		reset_phpmailer_instance();

		// Post authors are notified when a new comment is added to their post.
		$data = array(
			'comment_post_ID'      => $post,
			'comment_author'       => 'Comment Author',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);
		wp_new_comment( $data );

		// Check to see if a notification email was sent to the post author `test@test.com`.
		if ( isset( $GLOBALS['phpmailer']->mock_sent ) &&
			! empty( $GLOBALS['phpmailer']->mock_sent ) &&
			'test@test.com' === $GLOBALS['phpmailer']->mock_sent[0]['to'][0][0] ) {
				$email_sent_when_comment_added = true;
				reset_phpmailer_instance();
		} else {
			$email_sent_when_comment_added = false;
		}

		return $email_sent_when_comment_approved || $email_sent_when_comment_added;
	}

	/**
	 * @covers ::_close_comments_for_old_post
	 */
	public function test_close_comments_for_old_post() {
		update_option( 'close_comments_for_old_posts', true );
		// Close comments more than one day old.
		update_option( 'close_comments_days_old', 1 );

		$old_date    = date_create( '-25 hours' );
		$old_post_id = self::factory()->post->create( array( 'post_date' => date_format( $old_date, 'Y-m-d H:i:s' ) ) );

		$old_post_comment_status = _close_comments_for_old_post( true, $old_post_id );
		$this->assertFalse( $old_post_comment_status );

		$new_post_comment_status = _close_comments_for_old_post( true, self::$post_id );
		$this->assertTrue( $new_post_comment_status );
	}

	/**
	 * @covers ::_close_comments_for_old_post
	 */
	public function test_close_comments_for_old_post_undated_draft() {
		$draft_id             = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_type'   => 'post',
			)
		);
		$draft_comment_status = _close_comments_for_old_post( true, $draft_id );

		$this->assertTrue( $draft_comment_status );
	}

	/**
	 * @ticket 35276
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_author_id_and_agent() {

		$default_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_IP'    => '192.168.0.1',
			'comment_agent'        => 'WRONG_AGENT',
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => 'Comment',
		);

		$comment_id = wp_new_comment( $default_data );

		// Confirm that the IP and Agent are correct on initial save.
		$save = get_comment( $comment_id );
		$this->assertSame( $default_data['comment_author_IP'], $save->comment_author_IP );
		$this->assertSame( $default_data['comment_agent'], $save->comment_agent );

		// Update the comment.
		wp_update_comment(
			array(
				'comment_ID'        => $comment_id,
				'comment_author_IP' => '111.111.1.1',
				'comment_agent'     => 'SHIELD_AGENT',
			)
		);

		// Retrieve and check the new values.
		$updated = get_comment( $comment_id );
		$this->assertSame( '111.111.1.1', $updated->comment_author_IP );
		$this->assertSame( 'SHIELD_AGENT', $updated->comment_agent );
	}

	/**
	 * @covers ::wp_get_comment_fields_max_lengths
	 */
	public function test_wp_get_comment_fields_max_lengths() {
		$expected = array(
			'comment_author'       => 245,
			'comment_author_email' => 100,
			'comment_author_url'   => 200,
			'comment_content'      => 65525,
		);

		$lengths = wp_get_comment_fields_max_lengths();

		foreach ( $lengths as $field => $length ) {
			$this->assertSame( $expected[ $field ], $length );
		}
	}

	/**
	 * @covers ::wp_update_comment
	 */
	public function test_update_should_invalidate_comment_cache() {
		global $wpdb;

		$c = self::factory()->comment->create( array( 'comment_author' => 'Foo' ) );

		$comment = get_comment( $c );
		$this->assertSame( 'Foo', $comment->comment_author );

		wp_update_comment(
			array(
				'comment_ID'     => $c,
				'comment_author' => 'Bar',
			)
		);

		$comment = get_comment( $c );

		$this->assertSame( 'Bar', $comment->comment_author );
	}

	/**
	 * @covers ::wp_trash_comment
	 */
	public function test_trash_should_invalidate_comment_cache() {
		global $wpdb;

		$c = self::factory()->comment->create();

		$comment = get_comment( $c );

		wp_trash_comment( $c );

		$comment = get_comment( $c );

		$this->assertSame( 'trash', $comment->comment_approved );
	}

	/**
	 * @covers ::wp_untrash_comment
	 */
	public function test_untrash_should_invalidate_comment_cache() {
		global $wpdb;

		$c = self::factory()->comment->create();
		wp_trash_comment( $c );

		$comment = get_comment( $c );
		$this->assertSame( 'trash', $comment->comment_approved );

		wp_untrash_comment( $c );

		$comment = get_comment( $c );

		$this->assertSame( '1', $comment->comment_approved );
	}

	/**
	 * @covers ::wp_spam_comment
	 */
	public function test_spam_should_invalidate_comment_cache() {
		global $wpdb;

		$c = self::factory()->comment->create();

		$comment = get_comment( $c );

		wp_spam_comment( $c );

		$comment = get_comment( $c );

		$this->assertSame( 'spam', $comment->comment_approved );
	}

	/**
	 * @covers ::wp_unspam_comment
	 */
	public function test_unspam_should_invalidate_comment_cache() {
		global $wpdb;

		$c = self::factory()->comment->create();
		wp_spam_comment( $c );

		$comment = get_comment( $c );
		$this->assertSame( 'spam', $comment->comment_approved );

		wp_unspam_comment( $c );

		$comment = get_comment( $c );

		$this->assertSame( '1', $comment->comment_approved );
	}
}
