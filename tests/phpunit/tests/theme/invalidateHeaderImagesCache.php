<?php
/**
 * @group themes
 *
 * @covers ::invalidate_header_images_cache
 */
class Tests_Theme_invalidateHeaderImagesCache extends WP_UnitTestCase {
	public $custom_image_header;

	public function set_up() {
		parent::set_up();
		$this->custom_image_header = new Custom_Image_Header( '__return_null' );
	}

	/**
	 * @ticket 49446
	 */
	public function test_invalidate_header_images_cache_meta_updated() {
		$id = wp_insert_attachment(
			array(
				'post_status' => 'publish',
				'post_title'  => 'foo.png',
				'post_type'   => 'post',
				'guid'        => 'http://localhost/foo.png',
			)
		);

		// Create initial crop object.
		$cropped_1 = 'foo-cropped-1.png';
		$object    = wp_copy_parent_attachment_properties( $cropped_1, $id, 'custom-header' );

		// Ensure no previous crop exists.
		$previous = $this->custom_image_header->get_previous_crop( $object );
		$this->assertFalse( $previous );

		// Create the initial crop attachment and set it as the header.
		$cropped_1_id = $this->custom_image_header->insert_attachment( $object, $cropped_1 );
		$key          = '_wp_attachment_custom_header_last_used_' . get_stylesheet();
		update_post_meta( $cropped_1_id, $key, time() );
		update_post_meta( $cropped_1_id, '_wp_attachment_is_custom_header', get_stylesheet() );

		$expected = array(
			$cropped_1_id => array(
				'attachment_id'     => $cropped_1_id,
				'url'               => 'http://example.org/wp-content/uploads/foo-cropped-1.png',
				'thumbnail_url'     => 'http://example.org/wp-content/uploads/foo-cropped-1.png',
				'alt_text'          => '',
				'attachment_parent' => $id,
			),
		);

		$num_queries = get_num_queries() + 4;

		$this->assertSame( $expected, get_uploaded_header_images() );

		$this->assertSame( $num_queries, get_num_queries() );
		update_post_meta( $cropped_1_id, '_wp_attachment_is_custom_header', 'updated' );

		$stylesheet    = get_option( 'stylesheet' );
		$transient_key = 'uploaded_header_images' . $stylesheet;
		$this->assertFalse( get_transient( $transient_key ), 'cache cleared' );
	}

	/**
	 * @ticket 49446
	 */
	public function test_invalidate_header_images_cache_meta_deleted() {
		$id = wp_insert_attachment(
			array(
				'post_status' => 'publish',
				'post_title'  => 'foo.png',
				'post_type'   => 'post',
				'guid'        => 'http://localhost/foo.png',
			)
		);

		// Create initial crop object.
		$cropped_1 = 'foo-cropped-1.png';
		$object    = wp_copy_parent_attachment_properties( $cropped_1, $id, 'custom-header' );

		// Ensure no previous crop exists.
		$previous = $this->custom_image_header->get_previous_crop( $object );
		$this->assertFalse( $previous );

		// Create the initial crop attachment and set it as the header.
		$cropped_1_id = $this->custom_image_header->insert_attachment( $object, $cropped_1 );
		$key          = '_wp_attachment_custom_header_last_used_' . get_stylesheet();
		update_post_meta( $cropped_1_id, $key, time() );
		update_post_meta( $cropped_1_id, '_wp_attachment_is_custom_header', get_stylesheet() );

		$expected = array(
			$cropped_1_id => array(
				'attachment_id'     => $cropped_1_id,
				'url'               => 'http://example.org/wp-content/uploads/foo-cropped-1.png',
				'thumbnail_url'     => 'http://example.org/wp-content/uploads/foo-cropped-1.png',
				'alt_text'          => '',
				'attachment_parent' => $id,
			),
		);

		$num_queries = get_num_queries() + 4;

		$this->assertSame( $expected, get_uploaded_header_images() );

		$this->assertSame( $num_queries, get_num_queries() );
		delete_post_meta( $cropped_1_id, '_wp_attachment_is_custom_header' );

		$stylesheet    = get_option( 'stylesheet' );
		$transient_key = 'uploaded_header_images' . $stylesheet;
		$this->assertFalse( get_transient( $transient_key ), 'cache cleared' );
	}
}
