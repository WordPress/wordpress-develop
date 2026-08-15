<?php

/**
 * @group functions
 *
 * @covers ::wp_get_default_extension_for_mime_type
 */
class Tests_Functions_WpGetDefaultExtensionForMimeType extends WP_UnitTestCase {
	/**
	 * @ticket 53668
	 */
	public function test_wp_get_default_extension_for_mime_type() {
		$this->assertSame( 'jpg', wp_get_default_extension_for_mime_type( 'image/jpeg' ), 'jpg not returned as default extension for "image/jpeg"' );
		$this->assertNotEquals( 'jpeg', wp_get_default_extension_for_mime_type( 'image/jpeg' ), 'jpeg should not be returned as default extension for "image/jpeg"' );
		$this->assertSame( 'png', wp_get_default_extension_for_mime_type( 'image/png' ), 'png not returned as default extension for "image/png"' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( 'wibble/wobble' ), 'false not returned for unrecognized mime type' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( '' ), 'false not returned when empty string as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( '   ' ), 'false not returned when empty string as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( 123 ), 'false not returned when int as mime type supplied' );
		$this->assertFalse( wp_get_default_extension_for_mime_type( null ), 'false not returned when null as mime type supplied' );
	}
}
