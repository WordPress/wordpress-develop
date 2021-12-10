<?php

/**
 * Test wp_get_mime_types().
 *
 * @group functions.php
 * @covers ::wp_get_mime_types
 */
class Tests_Functions_wpGetMimeTypes extends WP_UnitTestCase {

	/**
	 * @ticket 47701
	 */
	public function test_all_mime_match() {
		$mime_types_start = wp_get_mime_types();

		$this->assertIsArray( $mime_types_start );
		$this->assertNotEmpty( $mime_types_start );

		add_filter( 'mime_types', '__return_empty_array' );
		$mime_types_empty = wp_get_mime_types();
		$this->assertSame( array(), $mime_types_empty );

		remove_filter( 'mime_types', '__return_empty_array' );
		$mime_types = wp_get_mime_types();
		$this->assertIsArray( $mime_types );
		$this->assertNotEmpty( $mime_types );
		// Did it revert to the original after filter remove?
		$this->assertSame( $mime_types_start, $mime_types );
	}
}
