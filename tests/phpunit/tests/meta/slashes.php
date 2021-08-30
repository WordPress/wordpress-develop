<?php

/**
 * @group meta
 * @group slashes
 * @ticket 21767
 */
class Tests_Meta_Slashes extends WP_UnitTestCase {
	protected static $editor_id;
	protected static $post_id;
	protected static $comment_id;
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id  = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id    = $factory->post->create();
		self::$comment_id = $factory->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		self::$user_id    = $factory->user->create();
	}

	function set_up() {
		parent::set_up();

		wp_set_current_user( self::$editor_id );

		$this->slash_1 = 'String with 1 slash \\';
		$this->slash_2 = 'String with 2 slashes \\\\';
		$this->slash_3 = 'String with 3 slashes \\\\\\';
		$this->slash_4 = 'String with 4 slashes \\\\\\\\';
		$this->slash_5 = 'String with 5 slashes \\\\\\\\\\';
		$this->slash_6 = 'String with 6 slashes \\\\\\\\\\\\';
		$this->slash_7 = 'String with 7 slashes \\\\\\\\\\\\\\';
	}

	/**
	 * Tests the controller function that expects slashed data.
	 */
	function test_edit_post() {
		$post_id = self::$post_id;

		if ( function_exists( 'wp_add_post_meta' ) ) {
			$meta_1 = wp_add_post_meta( $post_id, 'slash_test_1', 'foo' );
			$meta_2 = wp_add_post_meta( $post_id, 'slash_test_2', 'foo' );
			$meta_3 = wp_add_post_meta( $post_id, 'slash_test_3', 'foo' );
		} else {
			// Expects slashed data.
			$meta_1 = add_post_meta( $post_id, 'slash_test_1', addslashes( 'foo' ) );
			$meta_2 = add_post_meta( $post_id, 'slash_test_2', addslashes( 'foo' ) );
			$meta_3 = add_post_meta( $post_id, 'slash_test_3', addslashes( 'foo' ) );
		}

		$_POST                  = array();
		$_POST['post_ID']       = $post_id;
		$_POST['metakeyselect'] = '#NONE#';
		$_POST['metakeyinput']  = 'slash_test_0';
		$_POST['metavalue']     = $this->slash_6;
		$_POST['meta']          = array(
			$meta_1 => array(
				'key'   => 'slash_test_1',
				'value' => $this->slash_1,
			),
			$meta_2 => array(
				'key'   => 'slash_test_2',
				'value' => $this->slash_3,
			),
			$meta_3 => array(
				'key'   => 'slash_test_3',
				'value' => $this->slash_4,
			),
		);

		$_POST = add_magic_quotes( $_POST ); // The edit_post() function will strip slashes.

		edit_post();
		$post = get_post( $post_id );

		$this->assertSame( $this->slash_6, get_post_meta( $post_id, 'slash_test_0', true ) );
		$this->assertSame( $this->slash_1, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( $this->slash_3, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( $this->slash_4, get_post_meta( $post_id, 'slash_test_3', true ) );

		$_POST                  = array();
		$_POST['post_ID']       = $post_id;
		$_POST['metakeyselect'] = '#NONE#';
		$_POST['metakeyinput']  = 'slash_test_0';
		$_POST['metavalue']     = $this->slash_7;
		$_POST['meta']          = array(
			$meta_1 => array(
				'key'   => 'slash_test_1',
				'value' => $this->slash_2,
			),
			$meta_2 => array(
				'key'   => 'slash_test_2',
				'value' => $this->slash_4,
			),
			$meta_3 => array(
				'key'   => 'slash_test_3',
				'value' => $this->slash_5,
			),
		);

		$_POST = add_magic_quotes( $_POST ); // The edit_post() function will strip slashes.

		edit_post();
		$post = get_post( $post_id );

		$this->assertSame( $this->slash_2, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( $this->slash_4, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( $this->slash_5, get_post_meta( $post_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the legacy model function that expects slashed data.
	 */
	function test_add_post_meta() {
		$post_id = self::$post_id;

		add_post_meta( $post_id, 'slash_test_1', addslashes( $this->slash_1 ) );
		add_post_meta( $post_id, 'slash_test_2', addslashes( $this->slash_3 ) );
		add_post_meta( $post_id, 'slash_test_3', addslashes( $this->slash_4 ) );

		$this->assertSame( $this->slash_1, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( $this->slash_3, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( $this->slash_4, get_post_meta( $post_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the legacy model function that expects slashed data.
	 */
	function test_update_post_meta() {
		$post_id = self::$post_id;

		update_post_meta( $post_id, 'slash_test_1', addslashes( $this->slash_1 ) );
		update_post_meta( $post_id, 'slash_test_2', addslashes( $this->slash_3 ) );
		update_post_meta( $post_id, 'slash_test_3', addslashes( $this->slash_4 ) );

		$this->assertSame( $this->slash_1, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( $this->slash_3, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( $this->slash_4, get_post_meta( $post_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_add_comment_meta() {
		$comment_id = self::$comment_id;

		add_comment_meta( $comment_id, 'slash_test_1', $this->slash_1 );
		add_comment_meta( $comment_id, 'slash_test_2', $this->slash_3 );
		add_comment_meta( $comment_id, 'slash_test_3', $this->slash_5 );

		$this->assertSame( wp_unslash( $this->slash_1 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_3 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_5 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );

		add_comment_meta( $comment_id, 'slash_test_4', $this->slash_2 );
		add_comment_meta( $comment_id, 'slash_test_5', $this->slash_4 );
		add_comment_meta( $comment_id, 'slash_test_6', $this->slash_6 );

		$this->assertSame( wp_unslash( $this->slash_2 ), get_comment_meta( $comment_id, 'slash_test_4', true ) );
		$this->assertSame( wp_unslash( $this->slash_4 ), get_comment_meta( $comment_id, 'slash_test_5', true ) );
		$this->assertSame( wp_unslash( $this->slash_6 ), get_comment_meta( $comment_id, 'slash_test_6', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_update_comment_meta() {
		$comment_id = self::$comment_id;

		add_comment_meta( $comment_id, 'slash_test_1', 'foo' );
		add_comment_meta( $comment_id, 'slash_test_2', 'foo' );
		add_comment_meta( $comment_id, 'slash_test_3', 'foo' );

		update_comment_meta( $comment_id, 'slash_test_1', $this->slash_1 );
		update_comment_meta( $comment_id, 'slash_test_2', $this->slash_3 );
		update_comment_meta( $comment_id, 'slash_test_3', $this->slash_5 );

		$this->assertSame( wp_unslash( $this->slash_1 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_3 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_5 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );

		update_comment_meta( $comment_id, 'slash_test_1', $this->slash_2 );
		update_comment_meta( $comment_id, 'slash_test_2', $this->slash_4 );
		update_comment_meta( $comment_id, 'slash_test_3', $this->slash_6 );

		$this->assertSame( wp_unslash( $this->slash_2 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_4 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_6 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_add_user_meta() {
		$user_id = self::$user_id;

		add_user_meta( $user_id, 'slash_test_1', $this->slash_1 );
		add_user_meta( $user_id, 'slash_test_2', $this->slash_3 );
		add_user_meta( $user_id, 'slash_test_3', $this->slash_5 );

		$this->assertSame( wp_unslash( $this->slash_1 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_3 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_5 ), get_user_meta( $user_id, 'slash_test_3', true ) );

		add_user_meta( $user_id, 'slash_test_4', $this->slash_2 );
		add_user_meta( $user_id, 'slash_test_5', $this->slash_4 );
		add_user_meta( $user_id, 'slash_test_6', $this->slash_6 );

		$this->assertSame( wp_unslash( $this->slash_2 ), get_user_meta( $user_id, 'slash_test_4', true ) );
		$this->assertSame( wp_unslash( $this->slash_4 ), get_user_meta( $user_id, 'slash_test_5', true ) );
		$this->assertSame( wp_unslash( $this->slash_6 ), get_user_meta( $user_id, 'slash_test_6', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_update_user_meta() {
		$user_id = self::$user_id;

		add_user_meta( $user_id, 'slash_test_1', 'foo' );
		add_user_meta( $user_id, 'slash_test_2', 'foo' );
		add_user_meta( $user_id, 'slash_test_3', 'foo' );

		update_user_meta( $user_id, 'slash_test_1', $this->slash_1 );
		update_user_meta( $user_id, 'slash_test_2', $this->slash_3 );
		update_user_meta( $user_id, 'slash_test_3', $this->slash_5 );

		$this->assertSame( wp_unslash( $this->slash_1 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_3 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_5 ), get_user_meta( $user_id, 'slash_test_3', true ) );

		update_user_meta( $user_id, 'slash_test_1', $this->slash_2 );
		update_user_meta( $user_id, 'slash_test_2', $this->slash_4 );
		update_user_meta( $user_id, 'slash_test_3', $this->slash_6 );

		$this->assertSame( wp_unslash( $this->slash_2 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( $this->slash_4 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( $this->slash_6 ), get_user_meta( $user_id, 'slash_test_3', true ) );
	}
}
