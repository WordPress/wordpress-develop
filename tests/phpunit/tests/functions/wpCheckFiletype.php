<?php

/**
 * Tests for wp_check_filetype()
 *
 * @group functions.php
 * @covers ::wp_check_filetype
 */
class Tests_Functions_wpCheckFiletype extends WP_UnitTestCase {

	public function data_url_filetypes() {
		return array(
			// Invalid or empty data:
			array( null, false ),
			array( '', false ),
			array( ' ', false ),

			// Paths:
			array( 'file.jpg', 'jpg' ),
			array( 'C:\path\to\file.mp3', 'mp3' ),
			array( 'C:\path\to\file.mp3?file.jpg', 'mp3' ),
			array( 'C:\path\to\file.exe?file.jpg', false ),
			array( '/file.jpg', 'jpg' ),
			array( '/path/to/file.jpg', 'jpg' ),
			array( '/path/to/file.jpg', 'jpg' ),
			array( '/file.exe?file.jpg', false ),

			// Absolute URLs:
			array( 'http://example.com', false ),
			array( 'http://example.com/', false ),
			array( 'http://example.com/wibble', false ),
			array( 'http://example.com/wibble/', false ),
			array( 'http://example.com/wibble.wobble', false ),
			array( 'http://example.com/wibble.mp3', 'mp3' ),
			array( 'http://example.com/wibble.mp3#wobble', 'mp3' ),
			array( 'http://example.com/wibble.mp3?wobble=true', 'mp3' ),
			array( 'http://example.com/wibble.mp3?wobble=true#wobble', 'mp3' ),
			array( 'http://example.mp3/', false ),
			array( 'http://example.com/file.mp3#file.jpg', 'mp3' ),
			array( 'http://example.com/file.mp3?file.jpg', 'mp3' ),
			array( 'http://example.com/file.exe#file.jpg', false ),
			array( 'http://example.com/file.exe?file.jpg', false ),
			array( 'http://example.com/file.mp3?foo=bar#?file=file.jpg', 'mp3' ),
			array( 'http://example.com?file.jpg', false ),
		);
	}

	/**
	 * @dataProvider data_url_filetypes
	 *
	 * @param string       $url
	 * @param string|false $expected
	 */
	public function test_url_ext( $url, $expected ) {
		$filetype = wp_check_filetype( $url );
		$this->assertSame( $expected, $filetype['ext'] );
	}
}
