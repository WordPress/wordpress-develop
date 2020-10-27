<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_newComment extends WP_XMLRPC_UnitTestCase {

	/**
	 * Post object for shared fixture.
	 *
	 * @var WP_Post
	 */
	public static $post;

	public static function wpSetUpBeforeClass( $factory ) {
		self::make_user_by_role( 'administrator' );
		self::$post = $factory->post->create_and_get();
	}

	function test_valid_comment() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$post->ID,
				array(
					'content' => rand_str( 100 ),
				),
			)
		);

		$this->assertNotIXRError( $result );
	}

	function test_empty_comment() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$post->ID,
				array(
					'content' => '',
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * @ticket 43177
	 */
	public function test_empty_content_multiple_spaces() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$post->ID,
				array(
					'content' => '   ',
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * @ticket 43177
	 */
	public function test_valid_comment_0_content() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$post->ID,
				array(
					'content' => '0',
				),
			)
		);

		$this->assertNotIXRError( $result );
	}

	/**
	 * @ticket 43177
	 */
	public function test_valid_comment_allow_empty_content() {
		add_filter( 'allow_empty_comment', '__return_true' );
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$post->ID,
				array(
					'content' => '   ',
				),
			)
		);

		$this->assertNotIXRError( $result );
	}

	function test_new_comment_post_closed() {
		$post = self::factory()->post->create_and_get(
			array(
				'comment_status' => 'closed',
			)
		);

		$this->assertSame( 'closed', $post->comment_status );

		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				$post->ID,
				array(
					'content' => rand_str( 100 ),
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	function test_new_comment_duplicated() {
		$comment_args = array(
			1,
			'administrator',
			'administrator',
			self::$post->ID,
			array(
				'content' => rand_str( 100 ),
			),
		);

		// First time it's a valid comment.
		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );
		$this->assertNotIXRError( $result );

		// Run second time for duplication error.
		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * Ensure anonymous comments can be made via XML-RPC.
	 *
	 * @ticket 51595
	 */
	function test_allowed_anon_comments() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'',
			'',
			self::$post->ID,
			array(
				'author'       => 'WordPress',
				'author_email' => 'noreply@wordpress.org',
				'content'      => 'Test Anon Comments',
			),
		);

		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );
		$this->assertNotIXRError( $result );
		$this->assertInternalType( 'int', $result );
	}

	/**
	 * Ensure anonymous XML-RPC comments require a valid email.
	 *
	 * @ticket 51595
	 */
	function test_anon_comments_require_email() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'',
			'',
			self::$post->ID,
			array(
				'author'       => 'WordPress',
				'author_email' => 'noreply at wordpress.org',
				'content'      => 'Test Anon Comments',
			),
		);

		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * Ensure valid users don't use the anon flow.
	 *
	 * @ticket 51595
	 */
	function test_username_avoids_anon_flow() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'administrator',
			'administrator',
			self::$post->ID,
			array(
				'author'       => 'WordPress',
				'author_email' => 'noreply at wordpress.org',
				'content'      => 'Test Anon Comments',
			),
		);

		$result  = $this->myxmlrpcserver->wp_newComment( $comment_args );
		$comment = get_comment( $result );
		$user_id = get_user_by( 'login', 'administrator' )->ID;

		$this->assertSame( $user_id, (int) $comment->user_id );
	}
}
