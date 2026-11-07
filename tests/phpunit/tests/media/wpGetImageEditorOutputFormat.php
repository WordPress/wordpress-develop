<?php

/**
 * Tests for the `wp_get_image_editor_output_format()` function.
 *
 * @group media
 *
 * @covers ::wp_get_image_editor_output_format
 */
class Tests_Media_WpGetImageEditorOutputFormat extends WP_UnitTestCase {

	/**
	 * Tests default output format mappings for HEIC/HEIF images.
	 *
	 * @ticket 65819
	 */
	public function test_wp_get_image_editor_output_format_defaults() {
		$expected = array(
			'image/heic'          => 'image/jpeg',
			'image/heif'          => 'image/jpeg',
			'image/heic-sequence' => 'image/jpeg',
			'image/heif-sequence' => 'image/jpeg',
		);

		$actual = wp_get_image_editor_output_format( 'sample.heic', 'image/heic' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that the image_editor_output_format filter modifies mappings and passes arguments.
	 *
	 * @ticket 65819
	 */
	public function test_wp_get_image_editor_output_format_filter() {
		$passed_filename  = '';
		$passed_mime_type = '';

		add_filter(
			'image_editor_output_format',
			static function ( $formats, $filename, $mime_type ) use ( &$passed_filename, &$passed_mime_type ) {
				$passed_filename      = $filename;
				$passed_mime_type     = $mime_type;
				$formats['image/png'] = 'image/webp';

				return $formats;
			},
			10,
			3
		);

		$actual = wp_get_image_editor_output_format( '/path/to/image.png', 'image/png' );

		$this->assertSame( '/path/to/image.png', $passed_filename );
		$this->assertSame( 'image/png', $passed_mime_type );
		$this->assertArrayHasKey( 'image/png', $actual );
		$this->assertSame( 'image/webp', $actual['image/png'] );
	}
}
