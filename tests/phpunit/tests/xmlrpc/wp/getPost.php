<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getPost extends WP_XMLRPC_UnitTestCase {
	public $post_data;
	public $post_id;
	public $post_date_ts;
	public $post_custom_field;

	function set_up() {
		parent::set_up();

		$this->post_date_ts            = strtotime( '+1 day' );
		$this->post_data               = array(
			'post_title'   => rand_str(),
			'post_content' => rand_str( 2000 ),
			'post_excerpt' => rand_str( 100 ),
			'post_author'  => $this->make_user_by_role( 'author' ),
			'post_date'    => date_format( date_create( "@{$this->post_date_ts}" ), 'Y-m-d H:i:s' ),
		);
		$this->post_id                 = wp_insert_post( $this->post_data );
		$this->post_custom_field       = array(
			'key'   => 'test_custom_field',
			'value' => 12345678,
		);
		$this->post_custom_field['id'] = add_post_meta( $this->post_id, $this->post_custom_field['key'], $this->post_custom_field['value'] );
	}

	function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'username', 'password', 1 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	function test_valid_post() {
		add_theme_support( 'post-thumbnails' );

		$fields = array( 'post', 'custom_fields' );
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'author', 'author', $this->post_id, $fields ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['post_id'] );
		$this->assertIsString( $result['post_title'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_date'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_date_gmt'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_modified'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_modified_gmt'] );
		$this->assertIsString( $result['post_status'] );
		$this->assertIsString( $result['post_type'] );
		$this->assertIsString( $result['post_name'] );
		$this->assertIsString( $result['post_author'] );
		$this->assertIsString( $result['post_password'] );
		$this->assertIsString( $result['post_excerpt'] );
		$this->assertIsString( $result['post_content'] );
		$this->assertIsString( $result['link'] );
		$this->assertIsString( $result['comment_status'] );
		$this->assertIsString( $result['ping_status'] );
		$this->assertIsBool( $result['sticky'] );
		$this->assertIsString( $result['post_format'] );
		$this->assertIsArray( $result['post_thumbnail'] );
		$this->assertIsArray( $result['custom_fields'] );

		// Check expected values.
		$this->assertStringMatchesFormat( '%d', $result['post_id'] );
		$this->assertSame( $this->post_data['post_title'], $result['post_title'] );
		$this->assertSame( 'draft', $result['post_status'] );
		$this->assertSame( 'post', $result['post_type'] );
		$this->assertStringMatchesFormat( '%d', $result['post_author'] );
		$this->assertSame( $this->post_data['post_excerpt'], $result['post_excerpt'] );
		$this->assertSame( $this->post_data['post_content'], $result['post_content'] );
		$this->assertSame( url_to_postid( $result['link'] ), $this->post_id );
		$this->assertEquals( $this->post_custom_field['id'], $result['custom_fields'][0]['id'] );
		$this->assertSame( $this->post_custom_field['key'], $result['custom_fields'][0]['key'] );
		$this->assertEquals( $this->post_custom_field['value'], $result['custom_fields'][0]['value'] );

		remove_theme_support( 'post-thumbnails' );
	}

	function test_no_fields() {
		$fields = array();
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'author', 'author', $this->post_id, $fields ) );
		$this->assertNotIXRError( $result );

		// When no fields are requested, only the IDs should be returned.
		$this->assertCount( 1, $result );
		$this->assertSame( array( 'post_id' ), array_keys( $result ) );
	}

	function test_default_fields() {
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'author', 'author', $this->post_id ) );
		$this->assertNotIXRError( $result );

		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertArrayHasKey( 'link', $result ); // Random field from 'posts' group.
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertArrayHasKey( 'custom_fields', $result );
	}

	function test_date() {
		$fields = array( 'post' );
		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'author', 'author', $this->post_id, $fields ) );
		$this->assertNotIXRError( $result );

		$this->assertInstanceOf( 'IXR_Date', $result['post_date'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_date_gmt'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_modified'] );
		$this->assertInstanceOf( 'IXR_Date', $result['post_modified_gmt'] );

		$this->assertSame( $this->post_date_ts, $result['post_date']->getTimestamp() );
		$this->assertSame( $this->post_date_ts, $result['post_modified']->getTimestamp() );

		$post_date_gmt     = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $this->post_data['post_date'], false ), 'Ymd\TH:i:s' ) );
		$post_modified_gmt = strtotime( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $this->post_data['post_date'], false ), 'Ymd\TH:i:s' ) );

		$this->assertSame( $post_date_gmt, $result['post_date_gmt']->getTimestamp() );
		$this->assertSame( $post_modified_gmt, $result['post_modified_gmt']->getTimestamp() );
	}

	/**
	 * @ticket 21308
	 */
	function test_valid_page() {
		$this->make_user_by_role( 'editor' );

		$parent_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$child_page_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_page_id,
				'menu_order'  => 2,
			)
		);

		$result = $this->myxmlrpcserver->wp_getPost( array( 1, 'editor', 'editor', $child_page_id ) );
		$this->assertNotIXRError( $result );

		$this->assertIsString( $result['post_id'] );
		$this->assertIsString( $result['post_parent'] );
		$this->assertIsInt( $result['menu_order'] );
		$this->assertIsString( $result['guid'] );
		$this->assertIsString( $result['post_mime_type'] );

		$this->assertSame( 'page', $result['post_type'] );
		$this->assertEquals( $parent_page_id, $result['post_parent'] );
		$this->assertSame( 2, $result['menu_order'] );
	}
}
