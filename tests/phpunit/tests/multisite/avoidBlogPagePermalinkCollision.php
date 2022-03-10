<?php

if ( is_multisite() ) :

	/**
	 * Tests specific to `avoid_blog_page_permalink_collision()` in multisite.
	 *
	 * @group multisite
	 * @group post
	 */
	class Tests_Multisite_AvoidBlogPagePermalinkCollision extends WP_UnitTestCase {
		protected static $site_id;
		protected static $root_page;
		protected static $child_page;
		protected static $post_and_blog_path = 'permalink-collison';

		/**
		 * Create a blog and the pages we need to test the collision.
		 */
		public static function wpSetUpBeforeClass( $factory ) {
			self::$site_id = self::factory()->blog->create(
				array(
					'path' => '/' . self::$post_and_blog_path,
				)
			);

			self::$root_page = self::factory()->post->create_and_get(
				array(
					'post_type'  => 'page',
					'post_title' => 'Bar',
					'post_name'  => self::$post_and_blog_path,
				)
			);

			self::$child_page = self::factory()->post->create_and_get(
				array(
					'post_parent' => self::$root_page->ID,
					'post_type'   => 'page',
					'post_title'  => 'Bar',
					'post_name'   => self::$post_and_blog_path,
				)
			);
		}

		/**
		 * Delete blog and pages we created.
		 */
		public static function wpTearDownAfterClass() {
			wp_delete_site( self::$site_id );

			wp_delete_post( self::$root_page->ID );
			wp_delete_post( self::$child_page->ID );
		}

		public function test_avoid_blog_page_permalink_collision_renames_post_name() {
			$this->assertNotSame( self::$post_and_blog_path, self::$root_page->post_name );
		}

		/**
		 * Ensure `avoid_blog_page_permalink_collision()` doesn't rename child pages' post_name.
		 *
		 * @ticket 51147
		 */
		public function test_avoid_blog_page_permalink_collision_doesnt_rename_child_pages() {
			$this->assertSame( self::$post_and_blog_path, self::$child_page->post_name );
		}
	}

endif;
