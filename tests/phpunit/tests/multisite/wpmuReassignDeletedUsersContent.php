<?php

if ( is_multisite() ) :

	/**
	 * Tests for _wpmu_reassign_deleted_users_content().
	 *
	 * @group ms-required
	 * @group multisite
	 * @group user
	 *
	 * @covers ::_wpmu_reassign_deleted_users_content
	 */
	class Tests_Multisite_wpmuReassignDeletedUsersContent extends WP_UnitTestCase {

		protected static $admin;
		protected static $author;
		protected static $heir;
		protected static $site_b;

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$admin  = $factory->user->create();
			self::$author = $factory->user->create();
			self::$heir   = $factory->user->create();
			grant_super_admin( self::$admin );

			self::$site_b = $factory->blog->create();

			foreach ( array( 1, self::$site_b ) as $site_id ) {
				add_user_to_blog( $site_id, self::$author, 'author' );
				add_user_to_blog( $site_id, self::$heir, 'author' );
			}
		}

		public static function wpTearDownAfterClass() {
			revoke_super_admin( self::$admin );
			wpmu_delete_user( self::$admin );
			wpmu_delete_user( self::$heir );
			wpmu_delete_user( self::$author );
			wp_delete_site( self::$site_b );
		}

		public function set_up() {
			parent::set_up();
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			wp_set_current_user( self::$admin );
		}

		/**
		 * @ticket 17905
		 */
		public function test_should_delete_posts_on_every_site_set_to_delete_content() {
			$posts = $this->create_posts_on_both_sites();

			$this->reassign_and_delete(
				array(
					1             => 'delete',
					self::$site_b => 'delete',
				)
			);

			$this->assertSame( 'trash', get_post_status( $this->get_post_on_site( 1, $posts[1] ) ) );
			$this->assertSame( 'trash', get_post_status( $this->get_post_on_site( self::$site_b, $posts[ self::$site_b ] ) ) );
		}

		/**
		 * @ticket 17905
		 */
		public function test_should_reassign_posts_on_reassigned_site_and_delete_them_elsewhere() {
			$posts = $this->create_posts_on_both_sites();

			$this->reassign_and_delete(
				array(
					1             => 'reassign',
					self::$site_b => 'delete',
				)
			);

			$reassigned_post = $this->get_post_on_site( 1, $posts[1] );

			$this->assertInstanceOf( WP_Post::class, $reassigned_post );
			$this->assertSame( (string) self::$heir, $reassigned_post->post_author );
			$this->assertSame( 'trash', get_post_status( $this->get_post_on_site( self::$site_b, $posts[ self::$site_b ] ) ) );
		}

		/**
		 * @ticket 17905
		 */
		public function test_should_keep_membership_of_sites_set_to_delete_content() {
			$this->create_posts_on_both_sites();

			_wpmu_reassign_deleted_users_content(
				$this->build_reassign_users(
					array(
						1             => 'delete',
						self::$site_b => 'delete',
					)
				),
				$this->build_content_actions(
					array(
						1             => 'delete',
						self::$site_b => 'delete',
					)
				)
			);

			$this->assertTrue( is_user_member_of_blog( self::$author, 1 ) );
			$this->assertTrue( is_user_member_of_blog( self::$author, self::$site_b ) );
		}

		/**
		 * @ticket 17905
		 */
		public function test_should_not_reassign_content_of_users_the_current_user_cannot_delete() {
			$posts = $this->create_posts_on_both_sites();
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

			_wpmu_reassign_deleted_users_content(
				$this->build_reassign_users( array( 1 => 'reassign' ) ),
				$this->build_content_actions( array( 1 => 'reassign' ) )
			);

			$post = $this->get_post_on_site( 1, $posts[1] );

			$this->assertSame( (string) self::$author, $post->post_author );
			$this->assertTrue( is_user_member_of_blog( self::$author, 1 ) );
		}

		/**
		 * Creates one post by the author on the main site and on the second site.
		 *
		 * @return int[] Post IDs keyed by site ID.
		 */
		private function create_posts_on_both_sites() {
			$posts = array();

			foreach ( array( 1, self::$site_b ) as $site_id ) {
				switch_to_blog( $site_id );
				$posts[ $site_id ] = self::factory()->post->create( array( 'post_author' => self::$author ) );
				restore_current_blog();
			}

			return $posts;
		}

		/**
		 * Runs the same sequence as the network "Delete Users" handler.
		 *
		 * @param string[] $choices 'delete' or 'reassign' keyed by site ID.
		 */
		private function reassign_and_delete( $choices ) {
			_wpmu_reassign_deleted_users_content(
				$this->build_reassign_users( $choices ),
				$this->build_content_actions( $choices )
			);

			wpmu_delete_user( self::$author );
		}

		/**
		 * Builds the `blog` form field: the reassignment target keyed by user, keyed by site.
		 *
		 * @param string[] $choices 'delete' or 'reassign' keyed by site ID.
		 * @return array
		 */
		private function build_reassign_users( $choices ) {
			$targets = array();

			foreach ( $choices as $site_id => $choice ) {
				$targets[ $site_id ] = 'reassign' === $choice ? (string) self::$heir : '-1';
			}

			return array( self::$author => $targets );
		}

		/**
		 * Builds the `delete` form field: the choice keyed by site, keyed by user.
		 *
		 * @param string[] $choices 'delete' or 'reassign' keyed by site ID.
		 * @return array
		 */
		private function build_content_actions( $choices ) {
			$actions = array();

			foreach ( $choices as $site_id => $choice ) {
				$actions[ $site_id ] = array( self::$author => $choice );
			}

			return $actions;
		}

		private function get_post_on_site( $site_id, $post_id ) {
			switch_to_blog( $site_id );
			$post = get_post( $post_id );
			restore_current_blog();

			return $post;
		}
	}

endif;
