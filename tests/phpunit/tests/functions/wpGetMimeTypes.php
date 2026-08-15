<?php

/**
 * @group functions
 *
 * @covers ::wp_get_mime_types
 */
class Tests_Functions_WpGetMimeTypes extends WP_UnitTestCase {
	/**
	 * @ticket 21594
	 */
	public function test_wp_get_mime_types() {
		$mimes = wp_get_mime_types();

		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		add_filter( 'mime_types', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertEmpty( $mimes );

		remove_filter( 'mime_types', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		// 'upload_mimes' should not affect wp_get_mime_types().
		add_filter( 'upload_mimes', '__return_empty_array' );
		$mimes = wp_get_mime_types();
		$this->assertIsArray( $mimes );
		$this->assertNotEmpty( $mimes );

		remove_filter( 'upload_mimes', '__return_empty_array' );
		$mimes2 = wp_get_mime_types();
		$this->assertIsArray( $mimes2 );
		$this->assertNotEmpty( $mimes2 );
		$this->assertSame( $mimes2, $mimes );
	}
}
