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
