<?php

/**
 * @group media
 */
class Tests_Media_WpGetAttachmentImage extends WP_UnitTestCase {

	/**
	 * @ticket 43070
	 */
	public function test_wp_get_attachment_image_should_not_include_sizes_when_srcset_is_disabled() {
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );

		$html = wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'srcset' => false ) );

		wp_delete_attachment( $attachment_id, true );

		$this->assertStringNotContainsString( 'srcset=', $html, 'The srcset attribute should not be rendered.' );
		$this->assertStringNotContainsString( 'sizes=', $html, 'The sizes attribute should not be rendered when srcset is absent.' );
	}
}
