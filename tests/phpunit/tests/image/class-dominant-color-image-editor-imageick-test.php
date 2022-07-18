<?php
/**
 * Tests for dominant-color module.
 *
 * @since 6.1
 *
 * @group image
 * @group media
 * @group wp-image-editor-gd
 * @group dominant-color
 */

class Dominant_Color_Image_Editor_Imageick_Test extends DominantColorTestCase {

	public $editor_engine = 'WP_Image_Editor_Imagick';

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers       WP_Image_Editor_Imagick::get_dominant_color
	 */
	public function test_get_dominant_color( $image_path, $expected_color, $expected_transparency ) {

		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = _dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertContains( $dominant_color_data['dominant_color'], $expected_color );
		$this->assertSame( $dominant_color_data['has_transparency'], $expected_transparency );
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color_invalid_images
	 *
	 * @group ms-excluded
	 *
	 * @covers       WP_Image_Editor_Imagick::get_dominant_color
	 */
	public function test_get_dominant_color_invalid( $image_path, $expected_color, $expected_transparency ) {

		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );

		$dominant_color_data = _dominant_color_get_dominant_color_data( $attachment_id );
		if ( is_wp_error( $dominant_color_data ) ) {
			$this->assertWPError( $dominant_color_data );
		} else {
			$this->assertContains( $dominant_color_data['dominant_color'], $expected_color );
		}
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color_none_images
	 *
	 * @covers       WP_Image_Editor_Imagick::get_dominant_color
	 */
	public function test_get_dominant_color_none_images( $image_path ) {

		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = _dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertContains( $dominant_color_data->get_error_code(), array( 'no_image_found', 'invalid_image' ) );
	}
}
