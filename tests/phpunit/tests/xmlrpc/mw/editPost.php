<?php

/**
 * @group xmlrpc
 *
 * @covers  wp_xmlrpc_server::mw_editPost
 */
class Tests_XMLRPC_mw_editPost extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$post   = array();
		$result = $this->myxmlrpcserver->mw_editPost( array( 1, 'username', 'password', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_edit_own_post() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$post           = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id        = wp_insert_post( $post );

		$new_title = 'Post test (updated)';
		$post2     = array( 'title' => $new_title );
		$result    = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $new_title, $out->post_title );
	}

	public function test_capable_edit_others_post() {
		$this->make_user_by_role( 'editor' );
		$contributor_id = $this->make_user_by_role( 'contributor' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$new_title = 'Post test (updated)';
		$post2     = array( 'title' => $new_title );
		$result    = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'editor', 'editor', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $new_title, $out->post_title );
	}

	public function test_incapable_edit_others_post() {
		$this->make_user_by_role( 'contributor' );
		$author_id = $this->make_user_by_role( 'author' );

		$original_title = 'Post test';
		$post           = array(
			'post_title'  => $original_title,
			'post_author' => $author_id,
		);
		$post_id        = wp_insert_post( $post );

		$new_title = 'Post test (updated)';
		$post2     = array( 'title' => $new_title );
		$result    = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post2 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );

		$out = get_post( $post_id );
		$this->assertSame( $original_title, $out->post_title );
	}

	public function test_capable_reassign_author() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$author_id      = $this->make_user_by_role( 'author' );
		$this->make_user_by_role( 'editor' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$post2  = array( 'wp_author_id' => $author_id );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'editor', 'editor', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertEquals( $author_id, $out->post_author );
	}

	public function test_incapable_reassign_author() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$author_id      = $this->make_user_by_role( 'author' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$post2  = array( 'wp_author_id' => $author_id );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post2 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );

		$out = get_post( $post_id );
		$this->assertEquals( $contributor_id, $out->post_author );
	}

	/**
	 * @ticket 24916
	 */
	public function test_capable_reassign_author_to_self() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$editor_id      = $this->make_user_by_role( 'editor' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$post2  = array( 'wp_author_id' => $editor_id );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'editor', 'editor', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertEquals( $editor_id, $out->post_author );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_post_thumbnail() {
		add_theme_support( 'post-thumbnails' );

		$author_id = $this->make_user_by_role( 'author' );

		$post    = array(
			'post_title'  => 'Post Thumbnail Test',
			'post_author' => $author_id,
		);
		$post_id = wp_insert_post( $post );

		$this->assertSame( '', get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Create attachment.
		$filename      = ( DIR_TESTDATA . '/images/a2-small.jpg' );
		$attachment_id = self::factory()->attachment->create_upload_object( $filename, $post_id );

		// Add post thumbnail to post that does not have one.
		$post2  = array( 'wp_post_thumbnail' => $attachment_id );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'author', 'author', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Edit the post without supplying a post_thumbnail and check that it didn't change.
		$post3  = array( 'post_content' => 'Updated post' );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'author', 'author', $post3 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Create another attachment.
		$attachment2_id = self::factory()->attachment->create_upload_object( $filename, $post_id );

		// Change the post's post_thumbnail.
		$post4  = array( 'wp_post_thumbnail' => $attachment2_id );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'author', 'author', $post4 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment2_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Unset the post's post_thumbnail.
		$post5  = array( 'wp_post_thumbnail' => '' );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'author', 'author', $post5 ) );
		$this->assertNotIXRError( $result );
		$this->assertSame( '', get_post_meta( $post_id, '_thumbnail_id', true ) );

		remove_theme_support( 'post-thumbnails' );
	}

	public function test_edit_basic_post_info() {
		$contributor_id = $this->make_user_by_role( 'contributor' );

		$post    = array(
			'post_title'   => 'Title',
			'post_content' => 'Content',
			'post_excerpt' => 'Excerpt',
			'post_author'  => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$post2  = array(
			'title'       => 'New Title',
			'post_author' => $contributor_id,
		);
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $post2['title'], $out->post_title );

		$post3  = array(
			'description' => 'New Content',
			'post_author' => $contributor_id,
		);
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post3 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $post2['title'], $out->post_title );
		$this->assertSame( $post3['description'], $out->post_content );

		$post4  = array(
			'mt_excerpt'  => 'New Excerpt',
			'post_author' => $contributor_id,
		);
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post4 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $post2['title'], $out->post_title );
		$this->assertSame( $post3['description'], $out->post_content );
		$this->assertSame( $post4['mt_excerpt'], $out->post_excerpt );
	}

	/**
	 * @ticket 20662
	 */
	public function test_make_post_sticky() {
		$author_id = $this->make_user_by_role( 'editor' );

		$post    = array(
			'post_title'   => 'Title',
			'post_content' => 'Content',
			'post_author'  => $author_id,
			'post_status'  => 'publish',
		);
		$post_id = wp_insert_post( $post );

		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'editor', 'editor', array( 'sticky' => '1' ) ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );
	}

	// Not allowed since [19914].
	public function test_change_post_type() {
		$contributor_id = $this->make_user_by_role( 'contributor' );

		$post    = array(
			'post_title'  => 'Title',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$post2  = array( 'post_type' => 'page' );
		$result = $this->myxmlrpcserver->mw_editPost( array( $post_id, 'contributor', 'contributor', $post2 ) );
		$this->assertIXRError( $result );
		$this->assertSame( $result->code, 401 );
	}

	/**
	 * @ticket 16980
	 */
	public function test_empty_not_null() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Title',
				'post_author' => $editor_id,
				'tags_input'  => 'taco',
			)
		);

		$tags1 = get_the_tags( $post_id );
		$this->assertNotEmpty( $tags1 );

		$this->myxmlrpcserver->mw_editPost(
			array(
				$post_id,
				'editor',
				'editor',
				array(
					'mt_keywords' => '',
				),
			)
		);

		$tags2 = get_the_tags( $post_id );
		$this->assertEmpty( $tags2 );
	}

	/**
	 * @ticket 35874
	 */
	public function test_draft_not_prematurely_published() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post = array(
			'title' => 'Title',
		);

		/**
		 * We have to use wp_newPost method, rather than the factory
		 * post->create method to create the database conditions that exhibit
		 * the bug.
		 */
		$post_id = $this->myxmlrpcserver->mw_newPost( array( 1, 'editor', 'editor', $post ) );

		// Change the post's status to publish and date to future.
		$future_time = strtotime( '+1 day' );
		$future_date = new IXR_Date( $future_time );
		$this->myxmlrpcserver->mw_editPost(
			array(
				$post_id,
				'editor',
				'editor',
				array(
					'dateCreated' => $future_date,
					'post_status' => 'publish',
				),
			)
		);

		$after = get_post( $post_id );
		$this->assertSame( 'future', $after->post_status );

		$future_date_string = date_format( date_create( "@{$future_time}" ), 'Y-m-d H:i:s' );
		$this->assertSame( $future_date_string, $after->post_date );
	}
}
