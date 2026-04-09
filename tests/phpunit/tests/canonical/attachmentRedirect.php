<?php

/**
 * Tests for attachment page redirect when wp_attachment_pages_enabled is disabled.
 *
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_AttachmentRedirect extends WP_Canonical_UnitTestCase {

	/**
	 * Attachment post object.
	 *
	 * @var WP_Post
	 */
	public static $attachment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Create a fake attachment (no real file upload needed).
		$attachment_id = $factory->post->create(
			array(
				'post_type'   => 'attachment',
				'post_title'  => 'Test Image',
				'post_name'   => 'test-image-jpg',
				'post_status' => 'inherit',
				'post_parent' => 0,
			)
		);

		// Set a fake attachment URL via metadata.
		update_post_meta( $attachment_id, '_wp_attached_file', '2025/01/test-image.jpg' );

		self::$attachment = get_post( $attachment_id );
	}

	/**
	 * Pretty permalink slug-based attachment URLs should redirect to the file URL
	 * when wp_attachment_pages_enabled is 0.
	 *
	 * This is a regression test: get_query_var( 'attachment_id' ) is only populated
	 * for ?attachment_id=123 URLs, not slug-based URLs. The fix falls back to
	 * get_queried_object_id().
	 */
	public function test_pretty_permalink_attachment_redirects_when_pages_disabled() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );

		$expected_url = wp_get_attachment_url( self::$attachment->ID );

		$this->assertCanonical( '/test-image-jpg/', $expected_url );
	}

	/**
	 * Query string ?attachment_id=ID should also redirect when pages are disabled.
	 */
	public function test_query_var_attachment_redirects_when_pages_disabled() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );

		$expected_url = wp_get_attachment_url( self::$attachment->ID );

		$this->assertCanonical( '/?attachment_id=' . self::$attachment->ID, $expected_url );
	}
}
