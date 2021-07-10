<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_mw_getRecentPosts extends WP_XMLRPC_UnitTestCase {
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => $factory->user->create(
					array(
						'user_login' => 'author',
						'user_pass'  => 'author',
						'role'       => 'author',
					)
				),
				'post_date'   => strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 day' ) ),
			)
		);
	}

	function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->mw_getRecentPosts( array( 1, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * @ticket 22320
	 */
	function test_no_editing_privileges() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->mw_getRecentPosts( array( 1, 'subscriber', 'subscriber' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	function test_no_editable_posts() {
		wp_delete_post( self::$post_id, true );

		$result = $this->myxmlrpcserver->mw_getRecentPosts( array( 1, 'author', 'author' ) );
		$this->assertNotIXRError( $result );
		$this->assertCount( 0, $result );
	}

	function test_valid_post() {
		add_theme_support( 'post-thumbnails' );

		$fields  = array( 'post' );
		$results = $this->myxmlrpcserver->mw_getRecentPosts( array( 1, 'author', 'author' ) );
		$this->assertNotIXRError( $results );

		foreach ( $results as $result ) {
			$post = get_post( $result['postid'] );

			// Check data types.
			$this->assertIsString( $result['userid'] );
			$this->assertIsString( $result['postid'] );
			$this->assertIsString( $result['description'] );
			$this->assertIsString( $result['title'] );
			$this->assertIsString( $result['link'] );
			$this->assertIsString( $result['permaLink'] );
			$this->assertIsArray( $result['categories'] );
			$this->assertIsString( $result['mt_excerpt'] );
			$this->assertIsString( $result['mt_text_more'] );
			$this->assertIsString( $result['wp_more_text'] );
			$this->assertIsInt( $result['mt_allow_comments'] );
			$this->assertIsInt( $result['mt_allow_pings'] );
			$this->assertIsString( $result['mt_keywords'] );
			$this->assertIsString( $result['wp_slug'] );
			$this->assertIsString( $result['wp_password'] );
			$this->assertIsString( $result['wp_author_id'] );
			$this->assertIsString( $result['wp_author_display_name'] );
			$this->assertIsString( $result['post_status'] );
			$this->assertIsArray( $result['custom_fields'] );
			$this->assertIsString( $result['wp_post_format'] );

			// Check expected values.
			$this->assertStringMatchesFormat( '%d', $result['userid'] );
			$this->assertStringMatchesFormat( '%d', $result['postid'] );
			$this->assertSame( $post->post_title, $result['title'] );
			$this->assertSame( 'draft', $result['post_status'] );
			$this->assertStringMatchesFormat( '%d', $result['wp_author_id'] );
			$this->assertSame( $post->post_excerpt, $result['mt_excerpt'] );
			$this->assertSame( url_to_postid( $result['link'] ), $post->ID );

			$this->assertSame( '', $result['wp_post_thumbnail'] );
		}

		remove_theme_support( 'post-thumbnails' );
	}

	function test_post_thumbnail() {
		add_theme_support( 'post-thumbnails' );

		// Create attachment.
		$filename      = ( DIR_TESTDATA . '/images/a2-small.jpg' );
		$attachment_id = self::factory()->attachment->create_upload_object( $filename, self::$post_id );
		set_post_thumbnail( self::$post_id, $attachment_id );

		$results = $this->myxmlrpcserver->mw_getRecentPosts( array( self::$post_id, 'author', 'author' ) );
		$this->assertNotIXRError( $results );

		foreach ( $results as $result ) {
			$this->assertIsString( $result['wp_post_thumbnail'] );
			$this->assertStringMatchesFormat( '%d', $result['wp_post_thumbnail'] );

			if ( ! empty( $result['wp_post_thumbnail'] ) || $result['postid'] === self::$post_id ) {
				$attachment_id = get_post_meta( $result['postid'], '_thumbnail_id', true );

				$this->assertSame( $attachment_id, $result['wp_post_thumbnail'] );
			}
		}

		remove_theme_support( 'post-thumbnails' );
	}

	function test_date() {
		$this->make_user_by_role( 'editor' );

		$results = $this->myxmlrpcserver->mw_getRecentPosts( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $results );

		foreach ( $results as $result ) {
			$post              = get_post( $result['postid'] );
			$date_gmt          = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $post->post_date, false ), 'Ymd\TH:i:s' ) );
			$date_modified_gmt = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $post->post_modified, false ), 'Ymd\TH:i:s' ) );

			$this->assertInstanceOf( 'IXR_Date', $result['dateCreated'] );
			$this->assertInstanceOf( 'IXR_Date', $result['date_created_gmt'] );
			$this->assertInstanceOf( 'IXR_Date', $result['date_modified'] );
			$this->assertInstanceOf( 'IXR_Date', $result['date_modified_gmt'] );

			$this->assertSame( strtotime( $post->post_date ), $result['dateCreated']->getTimestamp() );
			$this->assertSame( $date_gmt, $result['date_created_gmt']->getTimestamp() );
			$this->assertSame( strtotime( $post->post_date ), $result['date_modified']->getTimestamp() );
			$this->assertSame( $date_modified_gmt, $result['date_modified_gmt']->getTimestamp() );
		}
	}
}
