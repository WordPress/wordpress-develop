<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_wp_getUsersBlogs extends WP_XMLRPC_UnitTestCase {

	public function test_empty_site_icon() {
		$this->make_user_by_role( 'subscriber' );

		// wp_getUsersBlogs
		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( 'subscriber', 'subscriber' ) );
		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 0, $result );
		$this->assertArrayHasKey( 'siteIcon', $result[0] );
		$this->assertSame( '', $result[0]['siteIcon'] );

		// blogger_getUsersBlogs
		$result_blogger = $this->myxmlrpcserver->blogger_getUsersBlogs( array( '', 'subscriber', 'subscriber' ) );
		$this->assertNotIXRError( $result_blogger );
		$this->assertIsArray( $result_blogger );
		$this->assertArrayHasKey( 0, $result_blogger );
		$this->assertArrayHasKey( 'siteIcon', $result_blogger[0] );
		$this->assertSame( '', $result_blogger[0]['siteIcon'] );
	}

	public function test_with_site_icon() {
		$this->make_user_by_role( 'subscriber' );

		// Create an attachment and set it as site_icon
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/images/test-image.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		update_option( 'site_icon', $attachment_id );

		$expected_url = get_site_icon_url( 512 );

		// wp_getUsersBlogs
		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( 'subscriber', 'subscriber' ) );
		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 0, $result );
		$this->assertArrayHasKey( 'siteIcon', $result[0] );
		$this->assertSame( $expected_url, $result[0]['siteIcon'] );

		// blogger_getUsersBlogs
		$result_blogger = $this->myxmlrpcserver->blogger_getUsersBlogs( array( '', 'subscriber', 'subscriber' ) );
		$this->assertNotIXRError( $result_blogger );
		$this->assertIsArray( $result_blogger );
		$this->assertArrayHasKey( 0, $result_blogger );
		$this->assertArrayHasKey( 'siteIcon', $result_blogger[0] );
		$this->assertSame( $expected_url, $result_blogger[0]['siteIcon'] );
	}
}
