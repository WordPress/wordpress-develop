<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_getPosts
 */
class Tests_XMLRPC_wp_getPosts extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPosts( array( 1, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * @ticket 20991
	 */
	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getPosts( array( 1, 'subscriber', 'subscriber' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );

		$filter = array( 'post_type' => 'page' );
		$result = $this->myxmlrpcserver->wp_getPosts( array( 1, 'subscriber', 'subscriber', $filter ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_user() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $result );
	}

	public function test_invalid_post_type() {
		$this->make_user_by_role( 'editor' );

		$filter = array( 'post_type' => 'invalid_post_type_name' );
		$result = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter ) );
		$this->assertIXRError( $result );
	}

	public function test_filters() {
		$this->make_user_by_role( 'editor' );

		$cpt_name = 'test_wp_getposts_cpt';
		register_post_type(
			$cpt_name,
			array(
				'taxonomies' => array( 'post_tag', 'category' ),
				'public'     => true,
			)
		);

		$post_ids  = array();
		$num_posts = 4;
		foreach ( range( 1, $num_posts ) as $i ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_type' => $cpt_name,
					'post_date' => gmdate( 'Y-m-d H:i:s', time() + $i ),
				)
			);
		}
		// Get them all.
		$filter  = array(
			'post_type' => $cpt_name,
			'number'    => $num_posts + 10,
		);
		$results = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( $num_posts, $results );

		// Page through results.
		$posts_found      = array();
		$filter['number'] = 2;
		$filter['offset'] = 0;
		do {
			$presults          = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter ) );
			$posts_found       = array_merge( $posts_found, wp_list_pluck( $presults, 'post_id' ) );
			$filter['offset'] += $filter['number'];
		} while ( count( $presults ) > 0 );
		// Verify that $post_ids matches $posts_found.
		$this->assertCount( 0, array_diff( $post_ids, $posts_found ) );

		// Add comments to some of the posts.
		foreach ( $post_ids as $key => $post_id ) {
			// Larger post IDs will get more comments.
			self::factory()->comment->create_post_comments( $post_id, $key );
		}

		// Get results ordered by comment count.
		$filter2  = array(
			'post_type' => $cpt_name,
			'number'    => $num_posts,
			'orderby'   => 'comment_count',
			'order'     => 'DESC',
		);
		$results2 = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter2 ) );
		$this->assertNotIXRError( $results2 );
		$last_comment_count = 100;
		foreach ( $results2 as $post ) {
			$comment_count = (int) get_comments_number( $post['post_id'] );
			$this->assertLessThanOrEqual( $last_comment_count, $comment_count );
			$last_comment_count = $comment_count;
		}

		// Set one of the posts to draft and get drafts.
		$post              = get_post( $post_ids[0] );
		$post->post_status = 'draft';
		wp_update_post( $post );
		$filter3  = array(
			'post_type'   => $cpt_name,
			'post_status' => 'draft',
		);
		$results3 = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter3 ) );
		$this->assertNotIXRError( $results3 );
		$this->assertCount( 1, $results3 );
		$this->assertEquals( $post->ID, $results3[0]['post_id'] );

		_unregister_post_type( $cpt_name );
	}

	public function test_fields() {
		$this->make_user_by_role( 'editor' );
		self::factory()->post->create();

		// Check default fields.
		$results = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $results );
		$expected_fields = array( 'post_id', 'post_title', 'terms', 'custom_fields', 'link' ); // Subset of expected fields.
		foreach ( $expected_fields as $field ) {
			$this->assertArrayHasKey( $field, $results[0] );
		}

		// Request specific fields and verify that only those are returned.
		$filter   = array();
		$fields   = array( 'post_name', 'post_author', 'enclosure' );
		$results2 = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter, $fields ) );
		$this->assertNotIXRError( $results2 );
		$expected_fields = array_merge( $fields, array( 'post_id' ) );
		foreach ( array_keys( $results2[0] ) as $field ) {
			$this->assertContains( $field, $expected_fields );
		}
	}

	/**
	 * @ticket 21623
	 */
	public function test_search() {
		$this->make_user_by_role( 'editor' );

		$post_ids[] = self::factory()->post->create( array( 'post_title' => 'First: Hello, World!' ) );
		$post_ids[] = self::factory()->post->create( array( 'post_title' => 'Second: Hello, World!' ) );

		// Search for none of them.
		$filter  = array( 's' => 'Third' );
		$results = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( 0, $results );

		// Search for one of them.
		$filter  = array( 's' => 'First:' );
		$results = $this->myxmlrpcserver->wp_getPosts( array( 1, 'editor', 'editor', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( 1, $results );
	}
}
