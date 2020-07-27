<?php

/**
 * @group post
 */
class Tests_Post_GetLastPostModified extends WP_UnitTestCase {

	/**
	 * @ticket 47777
	 */
	public function test_get_lastpostmodified() {
		global $wpdb;

		$post_post_date     = '2020-01-30 16:09:28';
		$post_post_modified = '2020-02-28 17:10:29';

		$book_post_date     = '2019-03-30 20:09:28';
		$book_post_modified = '2019-04-30 21:10:29';

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

		// Update `post_modified` and `post_modified_gmt`.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $post_post_modified,
				'post_modified_gmt' => $post_post_modified,
			),
			array(
				'ID' => $simple_post_id,
			)
		);

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $book_post_modified,
				'post_modified_gmt' => $book_post_modified,
			),
			array(
				'ID' => $book_cpt_id,
			)
		);

		$this->assertEquals( $post_post_modified, get_lastpostmodified( 'blog', 'post' ) );
		$this->assertEquals( $book_post_modified, get_lastpostmodified( 'blog', 'book' ) );
	}
}
