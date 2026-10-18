<?php

/**
 * Saving network settings without altering starter content ( first page, post, and comment ) shouldn't affect
 * the way it is added to new sites.
 *
 * @group ms-required
 * @group ms-site
 * @group multisite
 */
class Tests_Multisite_wpInstallDefaults extends WP_UnitTestCase {
	/**
	 * @ticket 40036
	 */
	public function test_option_should_not_be_empty_by_default() {
		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		$first_page    = get_page_by_path( '/sample-page' );
		$first_comment = get_comments();

		restore_current_blog();

		wp_delete_site( $blog_id );

		$this->assertNotEmpty( $first_page->post_content );
		$this->assertNotEmpty( $first_comment[0]->comment_content );
	}

	/**
	 * @ticket 40036
	 */
	public function test_empty_option_should_fall_back_to_default() {
		/*
		 * Update first_page / first_comment options,
		 * just like what happens when the network settings page is saved
		 */
		update_site_option( 'first_page', '' );
		update_site_option( 'first_comment', '' );

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		$first_page    = get_page_by_path( '/sample-page' );
		$first_comment = get_comments();

		restore_current_blog();

		wp_delete_site( $blog_id );

		$this->assertNotEmpty( $first_page->post_content );
		$this->assertNotEmpty( $first_comment[0]->comment_content );
	}

	/**
	 * @ticket 40036
	 */
	public function test_non_default_option_values() {
		/*
		 * Update first_page / first_comment options,
		 * just like what happens when the network settings page is saved
		 */
		update_site_option( 'first_page', 'Some page content' );
		update_site_option( 'first_comment', 'Some comment content' );

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		$first_page    = get_page_by_path( '/sample-page' );
		$first_comment = get_comments();

		restore_current_blog();

		wp_delete_site( $blog_id );

		$this->assertSame( 'Some page content', $first_page->post_content );
		$this->assertSame( 'Some comment content', $first_comment[0]->comment_content );
	}

	/**
	 * Creates a site and returns its first post and notes.
	 *
	 * @param string $first_post The first post content network option.
	 * @param int    $user_id    Optional. The site admin. Default is a new administrator.
	 * @return array{0: WP_Post, 1: WP_Comment[]} The first post and the site's notes.
	 */
	private function create_site_and_get_first_post_notes( string $first_post, int $user_id = 0 ) {
		update_site_option( 'first_post', $first_post );

		if ( ! $user_id ) {
			$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		}

		$blog_id = self::factory()->blog->create( array( 'user_id' => $user_id ) );

		switch_to_blog( $blog_id );

		$post  = get_post( 1 );
		$notes = get_comments(
			array(
				'type'   => 'note',
				'status' => 'all',
			)
		);

		restore_current_blog();

		wp_delete_site( $blog_id );

		$this->assertInstanceOf( WP_Post::class, $post, 'The site should have a first post.' );

		return array( $post, $notes );
	}

	/**
	 * @ticket 65890
	 */
	public function test_first_post_should_get_an_open_note_by_the_site_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		list( $post, $notes ) = $this->create_site_and_get_first_post_notes( '', $user_id );

		$this->assertSame( 'hello-world', $post->post_name, 'Post 1 should be the first post.' );
		$this->assertCount( 1, $notes, 'The site should have one note.' );
		$this->assertSame( 'note', $notes[0]->comment_type, 'The note should have the note comment type.' );
		$this->assertSame( '1', $notes[0]->comment_post_ID, 'The note should be on the first post.' );
		$this->assertSame( '0', $notes[0]->comment_parent, 'The note should start a thread.' );
		$this->assertSame( '0', $notes[0]->comment_approved, 'The note should be open.' );
		$this->assertSame( (string) $user_id, $notes[0]->user_id, 'The note should be by the site admin.' );
		$this->assertNotEmpty( $notes[0]->comment_content, 'The note should have content.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_first_post_should_link_its_first_block_to_the_note() {
		list( $post, $notes ) = $this->create_site_and_get_first_post_notes( '' );

		$blocks = parse_blocks( $post->post_content );

		$this->assertCount( 1, $notes, 'The site should have one note.' );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'], 'The first block should be a paragraph.' );
		$this->assertSame( array( 'metadata' => array( 'noteId' => (int) $notes[0]->comment_ID ) ), $blocks[0]['attrs'], 'The first block should be linked to the note.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_default_note_should_not_change_the_comment_counts() {
		update_site_option( 'first_post', '' );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$blog_id = self::factory()->blog->create( array( 'user_id' => $user_id ) );

		switch_to_blog( $blog_id );

		$note_count = get_comments(
			array(
				'type'  => 'note',
				'count' => true,
			)
		);
		$counts     = wp_count_comments( 1 );
		$post       = get_post( 1 );

		restore_current_blog();

		wp_delete_site( $blog_id );

		$this->assertInstanceOf( WP_Post::class, $post, 'The site should have a first post.' );
		$this->assertSame( 1, $note_count, 'The site should have one note.' );
		$this->assertSame( 1, $counts->approved, 'The default comment should be the only approved comment.' );
		$this->assertSame( 0, $counts->moderated, 'The open note should not be counted as awaiting moderation.' );
		$this->assertSame( '1', $post->comment_count, 'The note should not be counted as a comment on the post.' );
	}

	/**
	 * The default network option has no block markup, so there is no block to attach a note to.
	 *
	 * @ticket 65890
	 */
	public function test_first_post_without_blocks_should_not_get_a_note() {
		list( $post, $notes ) = $this->create_site_and_get_first_post_notes( 'Some post content' );

		$this->assertSame( 'Some post content', $post->post_content, 'The first post content should not change.' );
		$this->assertSame( array(), $notes, 'The site should have no notes.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_custom_first_post_should_get_a_note_on_its_first_block() {
		list( $post, $notes ) = $this->create_site_and_get_first_post_notes( "<!-- wp:heading -->\n<h2>Welcome</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"metadata\":{\"name\":\"Intro\"}} -->\n<p>To %s.</p>\n<!-- /wp:paragraph -->" );

		$blocks = parse_blocks( $post->post_content );

		$this->assertCount( 1, $notes, 'The site should have one note.' );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'], 'The first block should be the heading.' );
		$this->assertSame( array( 'metadata' => array( 'noteId' => (int) $notes[0]->comment_ID ) ), $blocks[0]['attrs'], 'The heading should be linked to the note.' );
		$this->assertSame( array( 'metadata' => array( 'name' => 'Intro' ) ), $blocks[2]['attrs'], 'Later blocks should keep their metadata.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_first_post_should_keep_the_first_block_metadata() {
		list( $post, $notes ) = $this->create_site_and_get_first_post_notes( "<!-- wp:paragraph {\"metadata\":{\"name\":\"Intro\"}} -->\n<p>Welcome to %s.</p>\n<!-- /wp:paragraph -->" );

		$blocks = parse_blocks( $post->post_content );

		$this->assertCount( 1, $notes, 'The site should have one note.' );
		$this->assertSame(
			array(
				'metadata' => array(
					'name'   => 'Intro',
					'noteId' => (int) $notes[0]->comment_ID,
				),
			),
			$blocks[0]['attrs'],
			'The note should be added to the existing block metadata.'
		);
	}
}
