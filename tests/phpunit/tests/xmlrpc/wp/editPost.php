<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_editPost
 */
class Tests_XMLRPC_wp_editPost extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'username', 'password', 0, array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_edit_own_post() {
		$contributor_id = $this->make_user_by_role( 'contributor' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$new_title = 'Post test (updated)';
		$post2     = array( 'post_title' => $new_title );
		$result    = $this->myxmlrpcserver->wp_editPost( array( 1, 'contributor', 'contributor', $post_id, $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $new_title, $out->post_title );
	}

	public function test_capable_edit_others_post() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$this->make_user_by_role( 'editor' );

		$post    = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id = wp_insert_post( $post );

		$new_title = 'Post test (updated)';
		$post2     = array( 'post_title' => $new_title );
		$result    = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $post2 ) );
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
		$post2     = array( 'post_title' => $new_title );
		$result    = $this->myxmlrpcserver->wp_editPost( array( 1, 'contributor', 'contributor', $post_id, $post2 ) );
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

		$post2  = array( 'post_author' => $author_id );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $post2 ) );
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

		$post2  = array( 'post_author' => $author_id );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'contributor', 'contributor', $post_id, $post2 ) );
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

		$post2  = array( 'post_author' => $editor_id );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $post2 ) );
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
		$post2  = array( 'post_thumbnail' => $attachment_id );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'author', 'author', $post_id, $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Fetch the post to verify that it appears.
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'author', 'author', $post_id ) );
		$this->assertNotIXRError( $result );
		$this->assertArrayHasKey( 'post_thumbnail', $result );
		$this->assertIsArray( $result['post_thumbnail'] );
		$this->assertEquals( $attachment_id, $result['post_thumbnail']['attachment_id'] );

		// Edit the post without supplying a post_thumbnail and check that it didn't change.
		$post3  = array( 'post_content' => 'Updated post' );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'author', 'author', $post_id, $post3 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Create another attachment.
		$attachment2_id = self::factory()->attachment->create_upload_object( $filename, $post_id );

		// Change the post's post_thumbnail.
		$post4  = array( 'post_thumbnail' => $attachment2_id );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'author', 'author', $post_id, $post4 ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment2_id, get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Unset the post's post_thumbnail.
		$post5  = array( 'post_thumbnail' => '' );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'author', 'author', $post_id, $post5 ) );
		$this->assertNotIXRError( $result );
		$this->assertSame( '', get_post_meta( $post_id, '_thumbnail_id', true ) );

		// Use invalid ID.
		$post6  = array( 'post_thumbnail' => 398420983409 );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'author', 'author', $post_id, $post6 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );

		remove_theme_support( 'post-thumbnails' );
	}

	public function test_edit_custom_fields() {
		$contributor_id = $this->make_user_by_role( 'contributor' );

		$post       = array(
			'post_title'  => 'Post test',
			'post_author' => $contributor_id,
		);
		$post_id    = wp_insert_post( $post );
		$mid_edit   = add_post_meta( $post_id, 'custom_field_key', '12345678' );
		$mid_delete = add_post_meta( $post_id, 'custom_field_to_delete', '12345678' );

		$new_title = 'Post test (updated)';
		$post2     = array(
			'post_title'    => $new_title,
			'custom_fields' =>
				array(
					array( 'id' => $mid_delete ),
					array(
						'id'    => $mid_edit,
						'key'   => 'custom_field_key',
						'value' => '87654321',
					),
					array(
						'key'   => 'custom_field_to_create',
						'value' => '12345678',
					),
				),
		);

		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'contributor', 'contributor', $post_id, $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$out = get_post( $post_id );
		$this->assertSame( $new_title, $out->post_title );

		$edited_object = get_metadata_by_mid( 'post', $mid_edit );
		$this->assertSame( '87654321', $edited_object->meta_value );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid_delete ) );

		$created_object = get_post_meta( $post_id, 'custom_field_to_create', true );
		$this->assertSame( $created_object, '12345678' );
	}

	public function test_capable_unsticky() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create( array( 'post_author' => $editor_id ) );
		stick_post( $post_id );

		$post2  = array( 'sticky' => false );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertFalse( is_sticky( $post_id ) );
	}

	public function test_password_transition_unsticky() {
		// When transitioning to private status or adding a post password, post should be un-stuck.
		$editor_id = $this->make_user_by_role( 'editor' );
		$post_id   = self::factory()->post->create( array( 'post_author' => $editor_id ) );
		stick_post( $post_id );

		$post2  = array(
			'post_password' => 'foobar',
			'sticky'        => false,
		);
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $post2 ) );
		$this->assertNotIXRError( $result );
		$this->assertFalse( is_sticky( $post_id ) );
	}

	public function test_if_not_modified_since() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$yesterday = strtotime( '-1 day' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post Revision Test',
				'post_content' => 'Not edited',
				'post_author'  => $editor_id,
				'post_status'  => 'publish',
				'post_date'    => gmdate( 'Y-m-d H:i:s', $yesterday ),
			)
		);

		// Modify the day old post. In this case, we think it was last modified yesterday.
		$struct = array(
			'post_content'          => 'First edit',
			'if_not_modified_since' => new IXR_Date( $yesterday ),
		);
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $struct ) );
		$this->assertNotIXRError( $result );

		// Make sure the edit went through.
		$this->assertSame( 'First edit', get_post( $post_id )->post_content );

		// Modify it again. We think it was last modified yesterday, but we actually just modified it above.
		$struct = array(
			'post_content'          => 'Second edit',
			'if_not_modified_since' => new IXR_Date( $yesterday ),
		);
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $struct ) );
		$this->assertIXRError( $result );
		$this->assertSame( 409, $result->code );

		// Make sure the edit did not go through.
		$this->assertSame( 'First edit', get_post( $post_id )->post_content );
	}

	public function test_edit_attachment() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post Revision Test',
				'post_content' => 'Not edited',
				'post_status'  => 'inherit',
				'post_type'    => 'attachment',
				'post_author'  => $editor_id,
			)
		);

		$struct = array( 'post_content' => 'First edit' );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $struct ) );
		$this->assertNotIXRError( $result );

		// Make sure that the post status is still inherit.
		$this->assertSame( 'inherit', get_post( $post_id )->post_status );
	}

	public function test_use_invalid_post_status() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post Revision Test',
				'post_content' => 'Not edited',
				'post_author'  => $editor_id,
			)
		);

		$struct = array( 'post_status' => 'doesnt_exists' );
		$result = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $struct ) );
		$this->assertNotIXRError( $result );

		// Make sure that the post status is still inherit.
		$this->assertSame( 'draft', get_post( $post_id )->post_status );
	}

	/**
	 * @ticket 22220
	 */
	public function test_loss_of_categories_on_edit() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create( array( 'post_author' => $editor_id ) );
		$term_id = self::factory()->category->create();
		self::factory()->term->add_post_terms( $post_id, $term_id, 'category', true );
		$term_ids = wp_list_pluck( get_the_category( $post_id ), 'term_id' );
		$this->assertContains( $term_id, $term_ids );

		$result = $this->myxmlrpcserver->wp_editPost(
			array(
				1,
				'editor',
				'editor',
				$post_id,
				array(
					'ID'         => $post_id,
					'post_title' => 'Updated',
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertSame( 'Updated', get_post( $post_id )->post_title );

		$term_ids = wp_list_pluck( get_the_category( $post_id ), 'term_id' );
		$this->assertContains( $term_id, $term_ids );
	}

	/**
	 * @ticket 26686
	 */
	public function test_clear_categories_on_edit() {
		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create( array( 'post_author' => $editor_id ) );
		$term_id = self::factory()->category->create();
		self::factory()->term->add_post_terms( $post_id, $term_id, 'category', true );
		$term_ids = wp_list_pluck( get_the_category( $post_id ), 'term_id' );
		$this->assertContains( $term_id, $term_ids );

		$new_post_content = array(
			'ID'         => $post_id,
			'post_title' => 'Updated',
			'terms'      => array(
				'category' => array(),
			),
		);
		$result           = $this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $new_post_content ) );
		$this->assertNotIXRError( $result );
		$this->assertSame( 'Updated', get_post( $post_id )->post_title );

		$term_ids = wp_list_pluck( get_the_category( $post_id ), 'term_id' );
		$this->assertNotContains( $term_id, $term_ids );
	}

	/**
	 * @ticket 23219
	 */
	public function test_add_enclosure_if_new() {
		// Sample enclosure data.
		$enclosure = array(
			'url'    => 'http://example.com/sound.mp3',
			'length' => 12345,
			'type'   => 'audio/mpeg',
		);

		// Second sample enclosure data array.
		$new_enclosure = array(
			'url'    => 'http://example.com/sound2.mp3',
			'length' => 12345,
			'type'   => 'audio/mpeg',
		);

		// Create a test user.
		$editor_id = $this->make_user_by_role( 'editor' );

		// Add a dummy post.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post Enclosure Test',
				'post_content' => 'Fake content',
				'post_author'  => $editor_id,
				'post_status'  => 'publish',
			)
		);

		// Add the enclosure as it is added in "do_enclose()".
		$enclosure_string = "{$enclosure['url']}\n{$enclosure['length']}\n{$enclosure['type']}\n";
		add_post_meta( $post_id, 'enclosure', $enclosure_string );

		// Verify that the correct data is there.
		$this->assertSame( $enclosure_string, get_post_meta( $post_id, 'enclosure', true ) );

		// Attempt to add the enclosure a second time.
		$this->myxmlrpcserver->add_enclosure_if_new( $post_id, $enclosure );

		// Verify that there is only a single value in the array and that a duplicate is not present.
		$this->assertCount( 1, get_post_meta( $post_id, 'enclosure' ) );

		// For good measure, check that the expected value is in the array.
		$this->assertContains( $enclosure_string, get_post_meta( $post_id, 'enclosure' ) );

		// Attempt to add a brand new enclosure via XML-RPC.
		$this->myxmlrpcserver->add_enclosure_if_new( $post_id, $new_enclosure );

		// Having added the new enclosure, 2 values are expected in the array.
		$this->assertCount( 2, get_post_meta( $post_id, 'enclosure' ) );

		// Check that the new enclosure is in the enclosure meta.
		$new_enclosure_string = "{$new_enclosure['url']}\n{$new_enclosure['length']}\n{$new_enclosure['type']}\n";
		$this->assertContains( $new_enclosure_string, get_post_meta( $post_id, 'enclosure' ) );

		// Check that the old enclosure is in the enclosure meta.
		$this->assertContains( $enclosure_string, get_post_meta( $post_id, 'enclosure' ) );
	}

	/**
	 * @ticket 35874
	 */
	public function test_draft_not_prematurely_published() {
		$editor_id = $this->make_user_by_role( 'editor' );

		/**
		 * We have to use wp_newPost method, rather than the factory
		 * post->create method to create the database conditions that exhibit
		 * the bug.
		 */
		$post    = array(
			'post_title'  => 'Test',
			'post_status' => 'draft',
		);
		$post_id = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );

		// Change the post's status to publish and date to future.
		$future_time      = strtotime( '+1 day' );
		$future_date      = new IXR_Date( $future_time );
		$new_post_content = array(
			'ID'          => $post_id,
			'post_title'  => 'Updated',
			'post_status' => 'publish',
			'post_date'   => $future_date,
		);

		$this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $new_post_content ) );

		$after = get_post( $post_id );
		$this->assertSame( 'future', $after->post_status );

		$future_date_string = date_format( date_create( "@{$future_time}" ), 'Y-m-d H:i:s' );
		$this->assertSame( $future_date_string, $after->post_date );
	}

	/**
	 * @ticket 45322
	 */
	public function test_draft_not_assigned_published_date() {
		$editor_id = $this->make_user_by_role( 'editor' );

		// Start with a draft post, confirming its post_date_gmt is "zero".
		$post    = array(
			'post_title'  => 'Test',
			'post_status' => 'draft',
		);
		$post_id = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );

		$before = get_post( $post_id );
		$this->assertSame( '0000-00-00 00:00:00', $before->post_date_gmt );

		// Edit the post without specifying any dates.
		$new_post_content = array(
			'ID'         => $post_id,
			'post_title' => 'Updated',
		);

		$this->myxmlrpcserver->wp_editPost( array( 1, 'editor', 'editor', $post_id, $new_post_content ) );

		// The published date should still be zero.
		$after = get_post( $post_id );
		$this->assertSame( '0000-00-00 00:00:00', $after->post_date_gmt );
	}
}
