<?php

/**
 * @group functions
 *
 * @covers ::get_allowed_mime_types
 */
class Tests_Functions_GetAllowedMimeTypes extends WP_UnitTestCase {
	/**
	 * @ticket 21594
	 */
	public function test_get_allowed_mime_types() {
		$mimes = get_allowed_mime_types();

		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		add_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = get_allowed_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertEmpty( $mimes );

		remove_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = get_allowed_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );
	}
}
