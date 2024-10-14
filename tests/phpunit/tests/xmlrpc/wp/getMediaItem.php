<?php

/**
 * @group xmlrpc
 * @requires function imagejpeg
 *
 * @covers wp_xmlrpc_server::wp_getMediaItem
 */
class Tests_XMLRPC_wp_getMediaItem extends WP_XMLRPC_UnitTestCase {
	protected static $post_id;

	public $attachment_data;
	public $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		add_theme_support( 'post-thumbnails' );

		$filename = ( DIR_TESTDATA . '/images/waffles.jpg' );
		$contents = file_get_contents( $filename );
		$upload   = wp_upload_bits( wp_basename( $filename ), null, $contents );

		$this->attachment_id   = $this->_make_attachment( $upload, self::$post_id );
		$this->attachment_data = get_post( $this->attachment_id, ARRAY_A );
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Waffle has alt text' );

		set_post_thumbnail( self::$post_id, $this->attachment_id );
	}

	public function tear_down() {
		remove_theme_support( 'post-thumbnails' );

		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getMediaItem( array( 1, 'username', 'password', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_valid_media_item() {
		$this->make_user_by_role( 'author' );

		$fields = array( 'post' );
		$result = $this->myxmlrpcserver->wp_getMediaItem( array( 1, 'author', 'author', $this->attachment_id, $fields ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['attachment_id'] );
		$this->assertIsInt( $result['parent'] );
		$this->assertIsString( $result['title'] );
		$this->assertInstanceOf( 'IXR_Date', $result['date_created_gmt'] );
		$this->assertIsString( $result['caption'] );
		$this->assertIsString( $result['description'] );
		$this->assertIsString( $result['link'] );
		$this->assertIsString( $result['thumbnail'] );
		$this->assertIsArray( $result['metadata'] );
		$this->assertIsString( $result['alt'] );

		// Check expected values.
		$this->assertStringMatchesFormat( '%d', $result['attachment_id'] );
		$this->assertSame( $this->attachment_data['post_title'], $result['title'] );
		$this->assertSame( wp_get_attachment_url( $this->attachment_id ), $result['link'] );
		$this->assertSame( wp_get_attachment_thumb_url( $this->attachment_id ), $result['thumbnail'] );
	}
}
