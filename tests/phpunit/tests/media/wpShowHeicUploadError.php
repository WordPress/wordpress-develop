<?php

/**
 * Tests for the `wp_show_heic_upload_error()` function.
 *
 * @group media
 * @covers ::wp_show_heic_upload_error
 */
class Tests_Media_wpShowHeicUploadError extends WP_UnitTestCase {

	/**
	 * @ticket 65802
	 */
	public function test_adds_error_flag_to_the_given_settings_when_heic_is_not_editable() {
		add_filter( 'wp_image_editors', '__return_empty_array' );

		$this->assertSame(
			array(
				'existing'          => 'value',
				'heic_upload_error' => true,
			),
			wp_show_heic_upload_error( array( 'existing' => 'value' ) )
		);
	}
}
