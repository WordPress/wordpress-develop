<?php

/**
 * Tests for the `wp_copy_parent_attachment_properties()` function.
 *
 * @group media
 * @covers ::wp_copy_parent_attachment_properties
 */
class Tests_Media_wpCopyParentAttachmentProperties extends WP_UnitTestCase {

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_wp_copy_parent_attachment_properties() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		// Add alternative text.
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Alt text' );
		// Add image description.
		wp_update_post(
			array(
				'ID'           => $attachment,
				'post_excerpt' => 'Image description',
			)
		);
		$cropped = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);

		$cropped = str_replace( wp_basename( $parent_url ), 'cropped-test-image.jpg', $parent_url );
		$object  = wp_copy_parent_attachment_properties( $cropped, $attachment );

		$this->assertSame( $object['post_title'], 'cropped-canola.jpg' );
		$this->assertSame( $object['context'], '' );
		$this->assertSame( $object['post_mime_type'], 'image/jpeg' );
		$this->assertSame( $object['post_content'], $cropped );
		$this->assertSame( $object['guid'], $cropped );
		$this->assertSame( $object['meta_input']['_wp_attachment_image_alt'], 'Alt text' );
		$this->assertSame( $object['post_excerpt'], 'Image description' );

		unlink( $cropped );
	}

}
