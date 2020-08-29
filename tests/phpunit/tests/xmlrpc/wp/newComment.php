<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_newComment extends WP_XMLRPC_UnitTestCase {

	function test_valid_comment() {
		$this->make_user_by_role( 'administrator' );
		$post = self::factory()->post->create_and_get();

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

		$this->assertNotIXRError( $result );
	}

	function test_empty_comment() {
		$this->make_user_by_role( 'administrator' );
		$post = self::factory()->post->create_and_get();

		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'administrator',
				'administrator',
				$post->ID,
				array(
					'content' => '',
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertEquals( 403, $result->code );
	}

	function test_new_comment_post_closed() {
		$this->make_user_by_role( 'administrator' );
		$post = self::factory()->post->create_and_get(
			array(
				'comment_status' => 'closed',
			)
		);

		$this->assertEquals( 'closed', $post->comment_status );

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
		$this->assertEquals( 403, $result->code );
	}

	function test_new_comment_duplicated() {
		$this->make_user_by_role( 'administrator' );
		$post = self::factory()->post->create_and_get();

		$comment_args = array(
			1,
			'administrator',
			'administrator',
			$post->ID,
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
		$this->assertEquals( 403, $result->code );
	}

	/*
	 * @ticket 38622
	 */
	function test_new_comment_with_incorrect_comment_field_lengths() {
		$fixtures = array(
			array(
				'name'   => 'author',
				'value'  => str_repeat( 'A', 250 ),
				'expect' => '<strong>Error</strong>: Your name is too long.',
			),
			array(
				'name'   => 'author_url',
				'value'  => 'http://' . str_repeat( 'A', 250 ) . '.net',
				'expect' => '<strong>Error</strong>: Your URL is too long.',
			),
			array(
				'name'   => 'author_email',
				'value'  => str_repeat( 'A', 250 ) . '@somewhere.net',
				'expect' => '<strong>Error</strong>: Your email address is too long.',
			),
			array(
				'name'   => 'content',
				'value'  => str_repeat( 'A', 70000 ),
				'expect' => '<strong>Error</strong>: Your comment is too long.',
			),
		);

		add_filter(
			'xmlrpc_allow_anonymous_comments',
			function() {
				return true;
			}
		);

		//$this->make_user_by_role( 'administrator' );
		$post = self::factory()->post->create_and_get();

		foreach ( $fixtures as $fixture ) {

			$data = array(
				'author'       => 'author',
				'author_url'   => '',
				'author_email' => 'author@somewhere.net',
				'content'      => 'required',
			);

			$data[ $fixture['name'] ] = $fixture['value'];

			$result = $this->myxmlrpcserver->wp_newComment(
				array(
					1,
					'',
					'',
					$post->ID,
					$data,
				)
			);

			$this->assertIXRError( $result );
		}
	}

	function test_anon_comment() {
		add_filter(
			'xmlrpc_allow_anonymous_comments',
			function() {
				return true;
			}
		);
		$post = self::factory()->post->create_and_get();

		$result = $this->myxmlrpcserver->wp_newComment(
			array(
				1,
				'',
				'',
				$post->ID,
				array(
					'author'       => 'author',
					'author_url'   => '',
					'author_email' => 'author@somewhere.net',
					'content'      => rand_str( 100 ),
				),
			)
		);

		$this->assertNotIXRError( $result );
	}
}
