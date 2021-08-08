<?php

/**
 * @group comment
 * @group slashes
 * @ticket 21767
 */
class Tests_Comment_Slashes extends WP_UnitTestCase {
	protected static $author_id;
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// We need an admin user to bypass comment flood protection.
		self::$author_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id   = $factory->post->create();
	}

	function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );

		// It is important to test with both even and odd numbered slashes,
		// as KSES does a strip-then-add slashes in some of its function calls.
		$this->slash_1 = 'String with 1 slash \\';
		$this->slash_2 = 'String with 2 slashes \\\\';
		$this->slash_3 = 'String with 3 slashes \\\\\\';
		$this->slash_4 = 'String with 4 slashes \\\\\\\\';
		$this->slash_5 = 'String with 5 slashes \\\\\\\\\\';
		$this->slash_6 = 'String with 6 slashes \\\\\\\\\\\\';
		$this->slash_7 = 'String with 7 slashes \\\\\\\\\\\\\\';
	}

	/**
	 * Tests the extended model function that expects slashed data.
	 */
	function test_wp_new_comment() {
		$post_id = self::$post_id;

		// Not testing comment_author_email or comment_author_url
		// as slashes are not permitted in that data.
		$data       = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $this->slash_1,
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => $this->slash_7,
		);
		$comment_id = wp_new_comment( $data );

		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_7 ), $comment->comment_content );

		$data       = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $this->slash_2,
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => $this->slash_4,
		);
		$comment_id = wp_new_comment( $data );

		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_4 ), $comment->comment_content );
	}

	/**
	 * Tests the controller function that expects slashed data.
	 */
	function test_edit_comment() {
		$post_id    = self::$post_id;
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		// Not testing comment_author_email or comment_author_url
		// as slashes are not permitted in that data.
		$_POST                            = array();
		$_POST['comment_ID']              = $comment_id;
		$_POST['comment_status']          = '';
		$_POST['newcomment_author']       = $this->slash_1;
		$_POST['newcomment_author_url']   = '';
		$_POST['newcomment_author_email'] = '';
		$_POST['content']                 = $this->slash_7;

		$_POST = add_magic_quotes( $_POST ); // The edit_comment() function will strip slashes.

		edit_comment();
		$comment = get_comment( $comment_id );

		$this->assertSame( $this->slash_1, $comment->comment_author );
		$this->assertSame( $this->slash_7, $comment->comment_content );

		$_POST                            = array();
		$_POST['comment_ID']              = $comment_id;
		$_POST['comment_status']          = '';
		$_POST['newcomment_author']       = $this->slash_2;
		$_POST['newcomment_author_url']   = '';
		$_POST['newcomment_author_email'] = '';
		$_POST['content']                 = $this->slash_4;

		$_POST = add_magic_quotes( $_POST ); // The edit_comment() function will strip slashes.

		edit_comment();
		$comment = get_comment( $comment_id );

		$this->assertSame( $this->slash_2, $comment->comment_author );
		$this->assertSame( $this->slash_4, $comment->comment_content );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_wp_insert_comment() {
		$post_id = self::$post_id;

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => $this->slash_1,
				'comment_content' => $this->slash_7,
			)
		);
		$comment    = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_7 ), $comment->comment_content );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => $this->slash_2,
				'comment_content' => $this->slash_4,
			)
		);
		$comment    = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_4 ), $comment->comment_content );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_wp_update_comment() {
		$post_id    = self::$post_id;
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_author'  => $this->slash_1,
				'comment_content' => $this->slash_7,
			)
		);
		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_7 ), $comment->comment_content );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_author'  => $this->slash_2,
				'comment_content' => $this->slash_4,
			)
		);
		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( $this->slash_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( $this->slash_4 ), $comment->comment_content );
	}

}
