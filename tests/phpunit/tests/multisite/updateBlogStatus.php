<?php

if ( is_multisite() ) :

	/**
	 * @group ms-site
	 * @group multisite
	 */
	class Tests_Multisite_UpdateBlogStatus extends WP_UnitTestCase {

		/**
		 * Updating a field returns the sme value that was passed.
		 */
		public function test_update_blog_status() {
			$result = update_blog_status( 1, 'spam', 0 );
			$this->assertSame( 0, $result );
		}

		/**
		 * Updating an invalid field returns the same value that was passed.
		 */
		public function test_update_blog_status_invalid_status() {
			$result = update_blog_status( 1, 'doesnotexist', 'invalid' );
			$this->assertSame( 'invalid', $result );
		}

		public function test_update_blog_status_make_ham_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();
			update_blog_details( $blog_id, array( 'spam' => 1 ) );

			add_action( 'make_ham_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'spam', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->spam );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'spam' stays the same.
			update_blog_status( $blog_id, 'spam', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->spam );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_content_from_spam_blog_is_not_available() {
			$spam_blog_id = self::factory()->blog->create();
			switch_to_blog( $spam_blog_id );
			$post_data      = array(
				'post_title'   => 'Hello World!',
				'post_content' => 'Hello world content',
			);
			$post_id        = self::factory()->post->create( $post_data );
			$post           = get_post( $post_id );
			$spam_permalink = site_url() . '/?p=' . $post->ID;
			$spam_embed_url = get_post_embed_url( $post_id );

			restore_current_blog();
			$this->assertNotEmpty( $spam_permalink );
			$this->assertSame( $post_data['post_title'], $post->post_title );

			update_blog_status( $spam_blog_id, 'spam', 1 );

			$post_id = self::factory()->post->create(
				array(
					'post_content' => "\n $spam_permalink \n",
				)
			);
			$post    = get_post( $post_id );
			$content = apply_filters( 'the_content', $post->post_content );

			$this->assertStringNotContainsString( $post_data['post_title'], $content );
			$this->assertStringNotContainsString( "src=\"{$spam_embed_url}#?", $content );
		}

		public function test_update_blog_status_make_spam_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();

			add_action( 'make_spam_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'spam', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->spam );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'spam' stays the same.
			update_blog_status( $blog_id, 'spam', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->spam );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_archive_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();

			add_action( 'archive_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'archived', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->archived );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'archived' stays the same.
			update_blog_status( $blog_id, 'archived', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->archived );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_unarchive_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();
			update_blog_details( $blog_id, array( 'archived' => 1 ) );

			add_action( 'unarchive_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'archived', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->archived );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'archived' stays the same.
			update_blog_status( $blog_id, 'archived', 0 );
			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->archived );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_make_delete_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();

			add_action( 'make_delete_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'deleted', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->deleted );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'deleted' stays the same.
			update_blog_status( $blog_id, 'deleted', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->deleted );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_make_undelete_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();
			update_blog_details( $blog_id, array( 'deleted' => 1 ) );

			add_action( 'make_undelete_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'deleted', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->deleted );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'deleted' stays the same.
			update_blog_status( $blog_id, 'deleted', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->deleted );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_mature_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();

			add_action( 'mature_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'mature', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->mature );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'mature' stays the same.
			update_blog_status( $blog_id, 'mature', 1 );
			$blog = get_site( $blog_id );

			$this->assertSame( '1', $blog->mature );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_unmature_blog_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();
			update_blog_details( $blog_id, array( 'mature' => 1 ) );

			add_action( 'unmature_blog', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'mature', 0 );

			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->mature );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'mature' stays the same.
			update_blog_status( $blog_id, 'mature', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->mature );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}

		public function test_update_blog_status_update_blog_public_action() {
			$test_action_counter = new MockAction();

			$blog_id = self::factory()->blog->create();

			add_action( 'update_blog_public', array( $test_action_counter, 'action' ) );
			update_blog_status( $blog_id, 'public', 0 );

			$blog = get_site( $blog_id );
			$this->assertSame( '0', $blog->public );
			$this->assertSame( 1, $test_action_counter->get_call_count() );

			// The action should not fire if the status of 'mature' stays the same.
			update_blog_status( $blog_id, 'public', 0 );
			$blog = get_site( $blog_id );

			$this->assertSame( '0', $blog->public );
			$this->assertSame( 1, $test_action_counter->get_call_count() );
		}
	}

endif;
