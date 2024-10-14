<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_newPost
 */
class Tests_XMLRPC_wp_newPost extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'username', 'password', array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'subscriber', 'subscriber', array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_no_content() {
		$this->make_user_by_role( 'author' );

		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
		$this->assertSame( 'Content, title, and excerpt are empty.', $result->message );
	}

	public function test_basic_content() {
		$this->make_user_by_role( 'author' );

		$post   = array( 'post_title' => 'Test' );
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );
	}

	public function test_ignore_id() {
		$this->make_user_by_role( 'author' );

		$post   = array(
			'post_title' => 'Test',
			'ID'         => 103948,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertNotEquals( '103948', $result );
	}

	public function test_capable_publish() {
		$this->make_user_by_role( 'author' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'publish',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
	}

	public function test_incapable_publish() {
		$this->make_user_by_role( 'contributor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'publish',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'contributor', 'contributor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_private() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'private',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
	}

	public function test_incapable_private() {
		$this->make_user_by_role( 'contributor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'private',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'contributor', 'contributor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_other_author() {
		$other_author_id = $this->make_user_by_role( 'author' );
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_author' => $other_author_id,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
	}

	public function test_incapable_other_author() {
		$other_author_id = $this->make_user_by_role( 'author' );
		$this->make_user_by_role( 'contributor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_author' => $other_author_id,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'contributor', 'contributor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_invalid_author() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_author' => 99999999,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
	}

	public function test_empty_author() {
		$my_author_id = $this->make_user_by_role( 'author' );

		$post   = array( 'post_title' => 'Test' );
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );

		$out = get_post( $result );
		$this->assertEquals( $my_author_id, $out->post_author );
		$this->assertSame( 'Test', $out->post_title );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_post_thumbnail() {
		add_theme_support( 'post-thumbnails' );

		$this->make_user_by_role( 'author' );

		// Create attachment.
		$filename      = ( DIR_TESTDATA . '/images/a2-small.jpg' );
		$attachment_id = self::factory()->attachment->create_upload_object( $filename );

		$post   = array(
			'post_title'     => 'Post Thumbnail Test',
			'post_thumbnail' => $attachment_id,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertEquals( $attachment_id, get_post_meta( $result, '_thumbnail_id', true ) );

		remove_theme_support( 'post-thumbnails' );
	}

	public function test_invalid_post_status() {
		$this->make_user_by_role( 'author' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'foobar_status',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertSame( 'draft', get_post_status( $result ) );
	}

	public function test_incapable_sticky() {
		$this->make_user_by_role( 'contributor' );

		$post   = array(
			'post_title' => 'Test',
			'sticky'     => true,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'contributor', 'contributor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_sticky() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title' => 'Test',
			'sticky'     => true,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( is_sticky( $result ) );
	}

	public function test_private_sticky() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_status' => 'private',
			'sticky'      => true,
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_post_format() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_format' => 'quote',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertSame( 'quote', get_post_format( $result ) );
	}

	public function test_invalid_post_format() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title'  => 'Test',
			'post_format' => 'tumblr',
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
		$this->assertFalse( get_post_format( $result ) );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title' => 'Test',
			'terms'      => array(
				'foobar_nonexistent' => array( 1 ),
			),
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );

		$post2   = array(
			'post_title'  => 'Test',
			'terms_names' => array(
				'foobar_nonexistent' => array( 1 ),
			),
		);
		$result2 = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post2 ) );
		$this->assertIXRError( $result2 );
		$this->assertSame( 401, $result2->code );
	}

	public function test_invalid_term_id() {
		$this->make_user_by_role( 'editor' );

		$post   = array(
			'post_title' => 'Test',
			'terms'      => array(
				'post_tag' => array( 1390490823409 ),
			),
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_terms() {
		$this->make_user_by_role( 'editor' );

		$tag1 = wp_create_tag( 'tag1' );
		$this->assertIsArray( $tag1 );
		$tag2 = wp_create_tag( 'tag2' );
		$this->assertIsArray( $tag2 );
		$tag3 = wp_create_tag( 'tag3' );
		$this->assertIsArray( $tag3 );

		$post   = array(
			'post_title' => 'Test',
			'terms'      => array(
				'post_tag' => array( $tag2['term_id'], $tag3['term_id'] ),
			),
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );

		$post_tags = wp_get_object_terms( $result, 'post_tag', array( 'fields' => 'ids' ) );
		$this->assertNotContains( $tag1['term_id'], $post_tags );
		$this->assertContains( $tag2['term_id'], $post_tags );
		$this->assertContains( $tag3['term_id'], $post_tags );
	}

	public function test_terms_names() {
		$this->make_user_by_role( 'editor' );

		$ambiguous_name = 'foo';
		$parent_cat     = wp_create_category( $ambiguous_name );
		$child_cat      = wp_create_category( $ambiguous_name, $parent_cat );

		$cat1_name = 'cat1';
		$cat1      = wp_create_category( $cat1_name, $parent_cat );
		$cat2_name = 'cat2';

		// First a post with valid categories; one that already exists and one to be created.
		$post   = array(
			'post_title'  => 'Test',
			'terms_names' => array(
				'category' => array( $cat1_name, $cat2_name ),
			),
		);
		$result = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post ) );
		$this->assertNotIXRError( $result );
		// Verify that cat2 was created.
		$cat2 = get_term_by( 'name', $cat2_name, 'category' );
		$this->assertNotEmpty( $cat2 );
		// Check that both categories were set on the post.
		$post_cats = wp_get_object_terms( $result, 'category', array( 'fields' => 'ids' ) );
		$this->assertContains( $cat1, $post_cats );
		$this->assertContains( $cat2->term_id, $post_cats );

		// Create a second post attempting to use the ambiguous name.
		$post2   = array(
			'post_title'  => 'Test',
			'terms_names' => array(
				'category' => array( $cat1_name, $ambiguous_name ),
			),
		);
		$result2 = $this->myxmlrpcserver->wp_newPost( array( 1, 'editor', 'editor', $post2 ) );
		$this->assertIXRError( $result2 );
		$this->assertSame( 401, $result2->code );
	}

	/**
	 * @ticket 28601
	 */
	public function test_invalid_post_date_does_not_fatal() {
		$this->make_user_by_role( 'author' );
		$date_string  = 'invalid_date';
		$post         = array(
			'post_title'   => 'test',
			'post_content' => 'test',
			'post_date'    => $date_string,
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( current_time( 'Y-m-d' ), substr( $fetched_post->post_date, 0, 10 ) );
	}

	/**
	 * @ticket 28601
	 */
	public function test_invalid_post_date_gmt_does_not_fatal() {
		$this->make_user_by_role( 'author' );
		$date_string  = 'invalid_date';
		$post         = array(
			'post_title'    => 'test',
			'post_content'  => 'test',
			'post_date_gmt' => $date_string,
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( '0000-00-00', substr( $fetched_post->post_date_gmt, 0, 10 ) );
	}

	/**
	 * @ticket 28601
	 */
	public function test_valid_string_post_date() {
		$this->make_user_by_role( 'author' );
		$date_string  = '1984-01-11 05:00:00';
		$post         = array(
			'post_title'   => 'test',
			'post_content' => 'test',
			'post_date'    => $date_string,
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( $date_string, $fetched_post->post_date );
	}

	/**
	 * @ticket 28601
	 */
	public function test_valid_string_post_date_gmt() {
		$this->make_user_by_role( 'author' );
		$date_string  = '1984-01-11 05:00:00';
		$post         = array(
			'post_title'    => 'test',
			'post_content'  => 'test',
			'post_date_gmt' => $date_string,
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( $date_string, $fetched_post->post_date_gmt );
	}

	/**
	 * @ticket 28601
	 */
	public function test_valid_IXR_post_date() {
		$this->make_user_by_role( 'author' );
		$date_string  = '1984-01-11 05:00:00';
		$post         = array(
			'post_title'   => 'test',
			'post_content' => 'test',
			'post_date'    => new IXR_Date( mysql2date( 'Ymd\TH:i:s', $date_string, false ) ),
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( $date_string, $fetched_post->post_date );
	}

	/**
	 * @ticket 28601
	 */
	public function test_valid_IXR_post_date_gmt() {
		$this->make_user_by_role( 'author' );
		$date_string  = '1984-01-11 05:00:00';
		$post         = array(
			'post_title'    => 'test',
			'post_content'  => 'test',
			'post_date_gmt' => new IXR_Date( mysql2date( 'Ymd\TH:i:s', $date_string, false ) ),
		);
		$result       = $this->myxmlrpcserver->wp_newPost( array( 1, 'author', 'author', $post ) );
		$fetched_post = get_post( $result );
		$this->assertStringMatchesFormat( '%d', $result );
		$this->assertSame( $date_string, $fetched_post->post_date_gmt );
	}
}
