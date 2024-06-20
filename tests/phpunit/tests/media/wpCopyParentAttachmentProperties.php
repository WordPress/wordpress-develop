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
		$parent_url = get_post( $attachment )->guid;
		// Add alternative text.
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Alt text' );
		// Add image description.
		wp_update_post(
			array(
				'ID'           => $attachment,
				'post_excerpt' => 'Image description',
			)
		);
		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);

		$object  = wp_copy_parent_attachment_properties( $file, $attachment );
		$cropped = str_replace( wp_basename( $parent_url ), 'cropped-canola.jpg', $parent_url );

		$this->assertSame( $object['post_title'], 'cropped-canola.jpg', 'Attachment title is not identical' );
		$this->assertSame( $object['context'], '', 'Attachment context is not identical' );
		$this->assertSame( $object['post_mime_type'], 'image/jpeg', 'Attachment mime type is not identical' );
		$this->assertSame( $object['post_content'], $cropped, 'Attachment content is not identical' );
		$this->assertSame( $object['guid'], $cropped, 'Attachment GUID is not identical' );
		$this->assertSame( $object['meta_input']['_wp_attachment_image_alt'], 'Alt text', 'Attachment alt text is not identical' );
		$this->assertSame( $object['post_excerpt'], 'Image description', 'Attachment description is not identical' );

		unlink( $file );
	}
}
