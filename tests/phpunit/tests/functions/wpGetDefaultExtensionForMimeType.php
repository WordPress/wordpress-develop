<?php

/**
 * Tests for wp_get_default_extension_for_mime_type().
 *
 * @group functions
 *
 * @covers ::wp_get_default_extension_for_mime_type
 */
class Tests_Functions_WpGetDefaultExtensionForMimeType extends WP_UnitTestCase {

	/**
	 * Tests that known mime types return the first mapped extension.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_known_mime_types
	 *
	 * @param string $mime_type Mime type.
	 * @param string $expected  Expected first extension.
	 */
	public function test_returns_first_extension_for_known_mime_type( $mime_type, $expected ) {
		$this->assertSame( $expected, wp_get_default_extension_for_mime_type( $mime_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_known_mime_types() {
		return array(
			'jpeg' => array( 'image/jpeg', 'jpg' ),
			'png'  => array( 'image/png', 'png' ),
			'pdf'  => array( 'application/pdf', 'pdf' ),
			'mp3'  => array( 'audio/mpeg', 'mp3' ),
		);
	}

	/**
	 * Tests that an unknown mime type returns false.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_unknown_mime_type() {
		$this->assertFalse( wp_get_default_extension_for_mime_type( 'application/x-not-a-real-type' ) );
	}
}
