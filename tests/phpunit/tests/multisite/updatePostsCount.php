<?php

if ( is_multisite() ) :
	/**
	 * Test that update_posts_count() gets called via default filters on multisite.
	 *
	 * @group ms-site
	 * @group multisite
	 *
	 * @covers ::update_posts_count
	 */
	class Tests_Multisite_UpdatePostsCount extends WP_UnitTestCase {

		/**
		 * Tests that posts count is updated correctly when posts are added or deleted.
		 *
		 * @ticket 27952
		 * @ticket 53443
		 *
		 * @covers ::_update_posts_count_on_transition_post_status
		 * @covers ::_update_posts_count_on_delete
		 */
		public function test_update_posts_count() {
			$blog_id = self::factory()->blog->create();
			switch_to_blog( $blog_id );

			$original_post_count = (int) get_site()->post_count;

			$post_id = self::factory()->post->create();

			$post_count_after_creating = get_site()->post_count;

			wp_delete_post( $post_id );

			$post_count_after_deleting = get_site()->post_count;

			restore_current_blog();

			/*
			 * Check that posts count is updated when a post is created:
			 * add_action( 'transition_post_status', '_update_posts_count_on_transition_post_status', 10, 3 );
			 *
			 * Check that _update_posts_count_on_transition_post_status() is called on that filter,
			 * which then calls update_posts_count() to update the count.
			 */
			$this->assertSame( $original_post_count + 1, $post_count_after_creating, 'Post count should be incremented by 1.' );

			/*
			 * Check that posts count is updated when a post is deleted:
			 * add_action( 'deleted_post', '_update_posts_count_on_delete' );
			 *
			 * Check that _update_posts_count_on_delete() is called on that filter,
			 * which then calls update_posts_count() to update the count.
			 */
			$this->assertSame( $original_post_count, $post_count_after_deleting, 'Post count should match the original count.' );
		}
	}

endif;
