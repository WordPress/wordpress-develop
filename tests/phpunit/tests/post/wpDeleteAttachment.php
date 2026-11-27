<?php

/**
 * @group post
 * @group media
 */
class Tests_Media_WPDeleteAttachment extends WP_UnitTestCase {
	protected $attachment_id;

	public function set_up(): void {
		parent::set_up();

		// Create a sample attachment for testing
		$filename            = DIR_TESTDATA . '/images/test-image.jpg';
		$this->attachment_id = self::factory()->attachment->create_upload_object( $filename );
	}

	public function tear_down(): void {
		// Clean up the created attachment
		if ( $this->attachment_id ) {
			wp_delete_attachment( $this->attachment_id, true );
		}

		parent::tear_down();
	}

	public function test_should_delete_attachment_successfully() {
		$result = wp_delete_attachment( $this->attachment_id );
		$this->assertInstanceOf( 'WP_Post', $result ); // Check if it returns the post object
		$this->assertEquals( 'attachment', $result->post_type ); // Ensure it's still an attachment post type
		$this->assertNotEquals( 'trash', $result->post_status ); // Ensure it's not in trash
	}

	public function test_should_return_false_when_post_id_does_not_exist() {
		$result = wp_delete_attachment( 99999 ); // ID that doesn't exist
		$this->assertTrue( false === $result || is_null( $result ), 'Expected wp_delete_attachment to return false or null on failure.' );
	}

	public function test_should_return_false_when_post_is_not_attachment() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) ); // Create a regular post
		$result  = wp_delete_attachment( $post_id );
		$this->assertFalse( $result ); // Should return false since it's not an attachment
	}

	public function test_should_force_delete_attachment_when_forced() {

		// Force delete the attachment
		$result = wp_delete_attachment( $this->attachment_id, true );
		$this->assertInstanceOf( 'WP_Post', $result ); // Check if it returns the post object
		$this->assertEquals( 'attachment', $result->post_type ); // Ensure it's still an attachment post type
		$this->assertNotEquals( 'trash', $result->post_status ); // Ensure it's not in trash
	}

	public function test_should_not_delete_if_pre_delete_filter_returns_non_null() {
		add_filter( 'pre_delete_attachment', '__return_false' );

		$result            = wp_delete_attachment( $this->attachment_id );
		$attachment_object = get_post( $this->attachment_id );
		$this->assertFalse( $result );
		$this->assertInstanceOf( 'WP_Post', $attachment_object ); // Should return the post object, not delete it
		$this->assertNotEquals( 'trash', $attachment_object->post_status ); // Should still be published
	}

	public function test_should_handle_non_standard_taxonomies_without_warnings() {
		// Ensure the attachment does not have any default taxonomies
		wp_set_object_terms( $this->attachment_id, array(), 'category' );
		wp_set_object_terms( $this->attachment_id, array(), 'post_tag' );

		// Register a new custom taxonomy.
		register_taxonomy( 'custom_taxonomy', 'attachment' );
		self::factory()->term->create(
			array(
				'taxonomy' => 'custom_taxonomy',
				'name'     => 'custom_term',
			)
		);
		// Ensure the attachment does not have 'category' or 'post_tag' taxonomies
		wp_set_object_terms( $this->attachment_id, array( 'custom_term' ), 'custom_taxonomy' );

		// Attempt to delete the attachment
		$result = wp_delete_attachment( $this->attachment_id );
		$this->assertInstanceOf( 'WP_Post', $result ); // Check if it returns the post object
		$this->assertEquals( 'attachment', $result->post_type ); // Ensure it's still an attachment post type
		$this->assertNotEquals( 'trash', $result->post_status ); // Ensure it's not in trash
	}
}
