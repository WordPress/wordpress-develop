<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_newComment extends WP_XMLRPC_UnitTestCase {

	/**
	 * Array of posts.
	 *
	 * @var WP_Post[]
	 */
	public static $posts;

	/**
	 * User IDs.
	 *
	 * Array of user IDs keyed by role.
	 *
	 * @var int[]
	 */
	public static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids                     = array(
			'administrator' => self::make_user_by_role( 'administrator' ),
			'contributor'   => self::make_user_by_role( 'contributor' ),
		);
		self::$posts['publish']             = $factory->post->create_and_get();
		self::$posts['password']            = $factory->post->create_and_get(
			array(
				'post_password' => 'xmlrpc',
				'post_author'   => self::$user_ids['administrator'],
			)
		);
		self::$posts['private']             = $factory->post->create_and_get(
			array(
				'post_status' => 'private',
				'post_author' => self::$user_ids['administrator'],
			)
		);
		self::$posts['private_contributor'] = $factory->post->create_and_get(
			array(
				'post_status' => 'private',
				'post_author' => self::$user_ids['contributor'],
			)
		);
	}

	public function test_valid_comment() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$posts['publish']->ID,
				array(
					'content' => 'Content',
				),
			)
		);

		$this->assertNotIXRError( $result );
	}

	public function test_empty_comment() {
		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				self::$posts['publish']->ID,
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
				self::$posts['publish']->ID,
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
				self::$posts['publish']->ID,
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
				self::$posts['publish']->ID,
				array(
					'content' => '   ',
				),
			)
		);

		$this->assertNotIXRError( $result );
	}

	public function test_new_comment_post_closed() {
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
					'content' => 'Content',
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_new_comment_duplicated() {
		$comment_args = array(
			1,
			'administrator',
			'administrator',
			self::$posts['publish']->ID,
			array(
				'content' => 'Content',
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
	public function test_allowed_anon_comments() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'',
			'',
			self::$posts['publish']->ID,
			array(
				'author'       => 'WordPress',
				'author_email' => 'noreply@wordpress.org',
				'content'      => 'Test Anon Comments',
			),
		);

		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );
		$this->assertNotIXRError( $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Ensure anonymous XML-RPC comments require a valid email.
	 *
	 * @ticket 51595
	 */
	public function test_anon_comments_require_email() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'',
			'',
			self::$posts['publish']->ID,
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
	public function test_username_avoids_anon_flow() {
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );

		$comment_args = array(
			1,
			'administrator',
			'administrator',
			self::$posts['publish']->ID,
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

	/**
	 * Ensure users can only comment on posts they're permitted to access.
	 *
	 * @dataProvider data_comments_observe_post_permissions
	 *
	 * @param string $post_key      Post identifier from the self::$posts array.
	 * @param string $username      Username leaving comment.
	 * @param bool   $expected      Expected result. True: successfull comment. False: Refused comment.
	 * @param string $anon_callback Optional. Allow anonymous comment callback. Default __return_false.
	 */
	public function test_comments_observe_post_permissions( $post_key, $username, $expected, $anon_callback = '__return_false' ) {
		add_filter( 'xmlrpc_allow_anonymous_comments', $anon_callback );

		$comment_args = array(
			1,
			$username,
			$username,
			self::$posts[ $post_key ]->ID,
			array(
				'author'       => 'WordPress',
				'author_email' => 'noreply@wordpress.org',
				'content'      => 'Test Comment',
			),
		);

		$result = $this->myxmlrpcserver->wp_newComment( $comment_args );
		if ( $expected ) {
			$this->assertIsInt( $result );
			return;
		}

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * Data provider for test_comments_observe_post_permissions.
	 *
	 * @return array[] {
	 *     @type string Post identifier from the self::$posts array.
	 *     @type string Username leaving comment.
	 *     @type bool   Expected result. True: successfull comment. False: Refused comment.
	 *     @type string Optional. Allow anonymous comment callback. Default __return_false.
	 * }
	 */
	public function data_comments_observe_post_permissions() {
		return array(
			// 0: Post author, password protected public post.
			array(
				'password',
				'administrator',
				true,
			),
			// 1: Low privileged non-author, password protected public post.
			array(
				'password',
				'contributor',
				false,
			),
			// 2: Anonymous user, password protected public post.
			array(
				'password',
				'', // Anonymous user.
				false,
			),
			// 3: Anonymous user, anon comments allowed, password protected public post.
			array(
				'password',
				'', // Anonymous user.
				false,
				'__return_true',
			),

			// 4: Post author, private post.
			array(
				'private',
				'administrator',
				true,
			),
			// 5: Low privileged non-author, private post.
			array(
				'private',
				'contributor',
				false,
			),
			// 6: Anonymous user, private post.
			array(
				'private',
				'', // Anonymous user.
				false,
			),
			// 7: Anonymous user, anon comments allowed, private post.
			array(
				'private',
				'', // Anonymous user.
				false,
				'__return_true',
			),

			// 8: High privileged non-author, private post.
			array(
				'private_contributor',
				'administrator',
				true,
			),
			// 9: Low privileged author, private post.
			array(
				'private_contributor',
				'contributor',
				true,
			),
			// 10: Anonymous user, private post.
			array(
				'private_contributor',
				'', // Anonymous user.
				false,
			),
			// 11: Anonymous user, anon comments allowed, private post.
			array(
				'private_contributor',
				'', // Anonymous user.
				false,
				'__return_true',
			),
		);
	}
}
