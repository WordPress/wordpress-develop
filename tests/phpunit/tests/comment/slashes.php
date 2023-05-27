<?php

/**
 * @group comment
 * @group slashes
 * @ticket 21767
 */
class Tests_Comment_Slashes extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	protected static $author_id;
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// We need an admin user to bypass comment flood protection.
		self::$author_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id   = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );
	}

	/**
	 * Tests the extended model function that expects slashed data.
	 *
	 * @covers ::wp_new_comment
	 */
	public function test_wp_new_comment() {
		$post_id = self::$post_id;

		// Not testing comment_author_email or comment_author_url
		// as slashes are not permitted in that data.
		$data       = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => self::SLASH_1,
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => self::SLASH_7,
		);
		$comment_id = wp_new_comment( $data );

		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_7 ), $comment->comment_content );

		$data       = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => self::SLASH_2,
			'comment_author_url'   => '',
			'comment_author_email' => '',
			'comment_type'         => '',
			'comment_content'      => self::SLASH_4,
		);
		$comment_id = wp_new_comment( $data );

		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $comment->comment_content );
	}

	/**
	 * Tests the controller function that expects slashed data.
	 *
	 * @covers ::edit_comment
	 */
	public function test_edit_comment() {
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
		$_POST['newcomment_author']       = self::SLASH_1;
		$_POST['newcomment_author_url']   = '';
		$_POST['newcomment_author_email'] = '';
		$_POST['content']                 = self::SLASH_7;

		$_POST = add_magic_quotes( $_POST ); // The edit_comment() function will strip slashes.

		edit_comment();
		$comment = get_comment( $comment_id );

		$this->assertSame( self::SLASH_1, $comment->comment_author );
		$this->assertSame( self::SLASH_7, $comment->comment_content );

		$_POST                            = array();
		$_POST['comment_ID']              = $comment_id;
		$_POST['comment_status']          = '';
		$_POST['newcomment_author']       = self::SLASH_2;
		$_POST['newcomment_author_url']   = '';
		$_POST['newcomment_author_email'] = '';
		$_POST['content']                 = self::SLASH_4;

		$_POST = add_magic_quotes( $_POST ); // The edit_comment() function will strip slashes.

		edit_comment();
		$comment = get_comment( $comment_id );

		$this->assertSame( self::SLASH_2, $comment->comment_author );
		$this->assertSame( self::SLASH_4, $comment->comment_content );
	}

	/**
	 * Tests the model function that expects slashed data.
	 *
	 * @covers ::wp_insert_comment
	 */
	public function test_wp_insert_comment() {
		$post_id = self::$post_id;

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => self::SLASH_1,
				'comment_content' => self::SLASH_7,
			)
		);
		$comment    = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_7 ), $comment->comment_content );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => self::SLASH_2,
				'comment_content' => self::SLASH_4,
			)
		);
		$comment    = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $comment->comment_content );
	}

	/**
	 * Tests the model function that expects slashed data.
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment() {
		$post_id    = self::$post_id;
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_author'  => self::SLASH_1,
				'comment_content' => self::SLASH_7,
			)
		);
		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_7 ), $comment->comment_content );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_author'  => self::SLASH_2,
				'comment_content' => self::SLASH_4,
			)
		);
		$comment = get_comment( $comment_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $comment->comment_author );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $comment->comment_content );
	}

}
