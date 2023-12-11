<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getPage extends WP_XMLRPC_UnitTestCase {
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
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPage( array( 1, self::$post_id, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	/**
	 * @ticket 20336
	 */
	public function test_invalid_pageid() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPage( array( 1, 9999, 'editor', 'editor' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
	}

	public function test_valid_page() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPage( array( 1, self::$post_id, 'editor', 'editor' ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['userid'] );
		$this->assertIsInt( $result['page_id'] );
		$this->assertIsString( $result['page_status'] );
		$this->assertIsString( $result['description'] );
		$this->assertIsString( $result['title'] );
		$this->assertIsString( $result['link'] );
		$this->assertIsString( $result['permaLink'] );
		$this->assertIsArray( $result['categories'] );
		$this->assertIsString( $result['excerpt'] );
		$this->assertIsString( $result['text_more'] );
		$this->assertIsInt( $result['mt_allow_comments'] );
		$this->assertIsInt( $result['mt_allow_pings'] );
		$this->assertIsString( $result['wp_slug'] );
		$this->assertIsString( $result['wp_password'] );
		$this->assertIsString( $result['wp_author'] );
		$this->assertIsInt( $result['wp_page_parent_id'] );
		$this->assertIsString( $result['wp_page_parent_title'] );
		$this->assertIsInt( $result['wp_page_order'] );
		$this->assertIsString( $result['wp_author_id'] );
		$this->assertIsString( $result['wp_author_display_name'] );
		$this->assertIsArray( $result['custom_fields'] );
		$this->assertIsString( $result['wp_page_template'] );

		$post_data = get_post( self::$post_id );

		// Check expected values.
		$this->assertStringMatchesFormat( '%d', $result['userid'] );
		$this->assertSame( 'future', $result['page_status'] );
		$this->assertSame( $post_data->post_title, $result['title'] );
		$this->assertSame( url_to_postid( $result['link'] ), self::$post_id );
		$this->assertSame( $post_data->post_excerpt, $result['excerpt'] );
		$this->assertStringMatchesFormat( '%d', $result['wp_author_id'] );
	}

	public function test_date() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPage( array( 1, self::$post_id, 'editor', 'editor' ) );
		$this->assertNotIXRError( $result );

		$this->assertInstanceOf( 'IXR_Date', $result['dateCreated'] );
		$this->assertInstanceOf( 'IXR_Date', $result['date_created_gmt'] );

		$post_data = get_post( self::$post_id );

		$date_gmt = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $post_data->post_date, false ), 'Ymd\TH:i:s' ) );

		$this->assertSame( strtotime( $post_data->post_date ), $result['dateCreated']->getTimestamp() );
		$this->assertSame( $date_gmt, $result['date_created_gmt']->getTimestamp() );
	}
}
