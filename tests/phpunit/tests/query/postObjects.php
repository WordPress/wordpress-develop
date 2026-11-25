<?php

/**
 * Tests for how WP_Query converts query results to post objects.
 *
 * @group query
 *
 * @covers WP_Query::get_posts
 */
class Tests_Query_PostObjects extends WP_UnitTestCase {

	/**
	 * Tests that values from the 'the_posts' filter which do not resolve to a post are removed.
	 *
	 * @ticket 60745
	 */
	public function test_the_posts_filter_values_which_are_not_posts_are_removed(): void {
		$post_ids = self::factory()->post->create_many( 2 );

		add_filter(
			'the_posts',
			static function () use ( $post_ids ): array {
				return array(
					'first'  => get_post( $post_ids[0] ),
					'second' => null,
					'third'  => array( 'admin' ),
					'fourth' => (string) $post_ids[1],
					'fifth'  => PHP_INT_MAX,
				);
			}
		);

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertIsArray( $query->posts );
		$this->assertInstanceOf( WP_Post::class, $query->post );
		$this->assertSame( array( $post_ids[0], $post_ids[1] ), wp_list_pluck( $query->posts, 'ID' ), 'Expected only the values which resolve to posts, reindexed.' );
		$this->assertSame( array( 0, 1 ), array_keys( $query->posts ), 'Expected the posts to be reindexed.' );
		$this->assertContainsOnlyInstancesOf( WP_Post::class, $query->posts );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( $post_ids[0], $query->post->ID );
	}

	/**
	 * Tests that post IDs from the 'posts_results' filter are converted to post objects before the 'the_posts' filter.
	 *
	 * @ticket 60745
	 */
	public function test_posts_results_filter_values_are_converted_to_post_objects(): void {
		$post_ids = self::factory()->post->create_many( 2 );

		add_filter(
			'posts_results',
			static function () use ( $post_ids ): array {
				return array( null, $post_ids[1], (string) $post_ids[0] );
			}
		);

		$posts_seen_by_the_posts = null;
		add_filter(
			'the_posts',
			static function ( $posts ) use ( &$posts_seen_by_the_posts ) {
				$posts_seen_by_the_posts = $posts;
				return $posts;
			}
		);

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertIsArray( $posts_seen_by_the_posts );
		$this->assertContainsOnlyInstancesOf( WP_Post::class, $posts_seen_by_the_posts );
		$this->assertSame( array( $post_ids[1], $post_ids[0] ), wp_list_pluck( $posts_seen_by_the_posts, 'ID' ) );
		$this->assertIsArray( $query->posts );
		$this->assertSame( array( $post_ids[1], $post_ids[0] ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Tests that the post is removed when the 'the_preview' filter does not return a post.
	 *
	 * @ticket 60745
	 */
	public function test_the_preview_filter_value_which_is_not_a_post_removes_the_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'the_preview', '__return_null' );

		$query = new WP_Query(
			array(
				'p'       => $post_id,
				'preview' => 'true',
			)
		);

		$this->assertTrue( $query->is_preview );
		$this->assertSame( array(), $query->posts );
		$this->assertSame( 0, $query->post_count );
	}

	/**
	 * Tests that when no value from the 'the_posts' filter resolves to a post, the query has no posts.
	 *
	 * @ticket 60745
	 */
	public function test_the_posts_filter_with_no_posts_results_in_empty_query(): void {
		self::factory()->post->create();

		add_filter(
			'the_posts',
			static function (): array {
				return array( null, PHP_INT_MAX );
			}
		);

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertSame( array(), $query->posts );
		$this->assertSame( 0, $query->post_count );
		$this->assertNull( $query->post );
	}
}
