<?php

/**
 * @group post
 */
class Tests_Post_GetLastPostDate extends WP_UnitTestCase {

	/**
	 * @ticket 47777
	 */
	public function test_get_lastpostdate() {
		$post_post_date = '2020-01-30 16:09:28';
		$book_post_date = '2019-02-28 18:11:30';

		// Register book post type.
		register_post_type( 'book', array( 'has_archive' => true ) );

		// Create a simple post.
		$simple_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Simple Post',
				'post_type'  => 'post',
				'post_date'  => $post_post_date,
			)
		);

		// Create custom type post.
		$book_cpt_id = self::factory()->post->create(
			array(
				'post_title' => 'Book CPT',
				'post_type'  => 'book',
				'post_date'  => $book_post_date,
			)
		);

		$this->assertEquals( $post_post_date, get_lastpostdate( 'blog', 'post' ) );
		$this->assertEquals( $book_post_date, get_lastpostdate( 'blog', 'book' ) );
	}
}
