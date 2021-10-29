<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_mw_getPost extends WP_XMLRPC_UnitTestCase {
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_author' => $factory->user->create(
					array(
						'user_login' => 'author',
						'user_pass'  => 'author',
						'role'       => 'author',
					)
				),
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);
	}

	function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->mw_getPost( array( self::$post_id, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->mw_getPost( array( self::$post_id, 'subscriber', 'subscriber' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	/**
	 * @ticket 20336
	 */
	function test_invalid_postid() {
		$result = $this->myxmlrpcserver->mw_getPost( array( 9999, 'author', 'author' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
	}

	function test_valid_post() {
		add_theme_support( 'post-thumbnails' );

		$fields = array( 'post' );
		$result = $this->myxmlrpcserver->mw_getPost( array( self::$post_id, 'author', 'author' ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['userid'] );
		$this->assertIsInt( $result['postid'] );
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
		$this->assertIsBool( $result['sticky'] );

		$post_data = get_post( self::$post_id );

		// Check expected values.
		$this->assertStringMatchesFormat( '%d', $result['userid'] );
		$this->assertSame( $post_data->post_title, $result['title'] );
		$this->assertSame( 'publish', $result['post_status'] );
		$this->assertStringMatchesFormat( '%d', $result['wp_author_id'] );
		$this->assertSame( $post_data->post_excerpt, $result['mt_excerpt'] );
		$this->assertSame( url_to_postid( $result['link'] ), self::$post_id );

		$this->assertSame( 0, $result['wp_post_thumbnail'] );

		remove_theme_support( 'post-thumbnails' );
	}

	/**
	 * @requires function imagejpeg
	 */
	function test_post_thumbnail() {
		add_theme_support( 'post-thumbnails' );

		// Create attachment.
		$filename      = ( DIR_TESTDATA . '/images/a2-small.jpg' );
		$attachment_id = self::factory()->attachment->create_upload_object( $filename );

		set_post_thumbnail( self::$post_id, $attachment_id );

		$fields = array( 'post' );
		$result = $this->myxmlrpcserver->mw_getPost( array( self::$post_id, 'author', 'author' ) );
		$this->assertNotIXRError( $result );

		$this->assertIsInt( $result['wp_post_thumbnail'] );
		$this->assertSame( $attachment_id, $result['wp_post_thumbnail'] );

		remove_theme_support( 'post-thumbnails' );
	}

	function test_date() {
		$fields = array( 'post' );
		$result = $this->myxmlrpcserver->mw_getPost( array( self::$post_id, 'author', 'author' ) );
		$this->assertNotIXRError( $result );

		$this->assertInstanceOf( 'IXR_Date', $result['dateCreated'] );
		$this->assertInstanceOf( 'IXR_Date', $result['date_created_gmt'] );
		$this->assertInstanceOf( 'IXR_Date', $result['date_modified'] );
		$this->assertInstanceOf( 'IXR_Date', $result['date_modified_gmt'] );

		$post_data = get_post( self::$post_id );

		$this->assertSame( strtotime( $post_data->post_date ), $result['dateCreated']->getTimestamp() );
		$this->assertSame( strtotime( $post_data->post_date ), $result['date_modified']->getTimestamp() );

		$post_date_gmt     = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $post_data->post_date, false ), 'Ymd\TH:i:s' ) );
		$post_modified_gmt = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $post_data->post_date, false ), 'Ymd\TH:i:s' ) );

		$this->assertSame( $post_date_gmt, $result['date_created_gmt']->getTimestamp() );
		$this->assertSame( $post_modified_gmt, $result['date_modified_gmt']->getTimestamp() );
	}
}
