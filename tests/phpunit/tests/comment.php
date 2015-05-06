<?php

/**
 * @group comment
 */
class Tests_Comment extends WP_UnitTestCase {
	function test_wp_update_comment() {
		$post = $this->factory->post->create_and_get( array( 'post_title' => 'some-post', 'post_type' => 'post' ) );
		$comments = $this->factory->comment->create_post_comments( $post->ID, 5 );
		$result = wp_update_comment( array( 'comment_ID' => $comments[0], 'comment_parent' => $comments[1] ) );
		$this->assertEquals( 1, $result );
		$comment = get_comment( $comments[0] );
		$this->assertEquals( $comments[1], $comment->comment_parent );
		$result = wp_update_comment( array( 'comment_ID' => $comments[0], 'comment_parent' => $comments[1] ) );
		$this->assertEquals( 0, $result );
	}

	public function test_comment_content_length() {
		// `wp_new_comment()` checks REMOTE_ADDR, so we fake it to avoid PHP notices.
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_addr = $_SERVER['REMOTE_ADDR'];
		} else {
			$_SERVER['REMOTE_ADDR'] = '';
		}

		$u = $this->factory->user->create();
		$post_id = $this->factory->post->create( array( 'post_author' => $u ) );

		$data = array(
			'comment_post_ID' => $post_id,
			'comment_author' => rand_str(),
			'comment_author_url' => '',
			'comment_author_email' => '',
			'comment_type' => '',
			'comment_content' => str_repeat( 'A', 65536 ),
			'comment_date' => '2011-01-01 10:00:00',
			'comment_date_gmt' => '2011-01-01 10:00:00',
		);

		$id = wp_new_comment( $data );

		$this->assertFalse( $id );

		// Cleanup.
		if ( isset( $remote_addr ) ) {
			$_SERVER['REMOTE_ADDR'] = $remote_addr;
		} else {
			unset( $_SERVER['REMOTE_ADDR'] );
		}
	}
}
