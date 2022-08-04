<?php

/**
 * @group  comment
 * @covers ::comments_open
 */
class Tests_Comment_CommentsOpen extends WP_UnitTestCase {

	/**
	 * @ticket 54159
	 */
	public function test_post_does_not_exist() {
		$this->assertFalse( comments_open( 99999 ) );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_exist_status_open() {
		$post = $this->factory->post->create_and_get();
		$this->assertTrue( comments_open( $post ) );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_exist_status_closed() {
		$post                 = $this->factory->post->create_and_get();
		$post->comment_status = 'closed';

		$this->assertFalse( comments_open( $post ) );
	}
}
