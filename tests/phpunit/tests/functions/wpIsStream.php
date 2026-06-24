<?php

/**
 * @group functions
 *
 * @covers ::wp_is_stream
 */
class Tests_Functions_WpIsStream extends WP_UnitTestCase {
	/**
	 * Test stream URL validation.
	 *
	 * @dataProvider data_wp_is_stream
	 *
	 * @param string $path     The resource path or URL.
	 * @param bool   $expected Expected result.
	 */
	public function test_wp_is_stream( $path, $expected ) {
		if ( ! extension_loaded( 'openssl' ) && false !== strpos( $path, 'https://' ) ) {
			$this->markTestSkipped( 'The openssl PHP extension is not loaded.' );
		}

		$this->assertSame( $expected, wp_is_stream( $path ) );
	}

	/**
	 * Data provider for stream URL validation.
	 *
	 * @return array {
	 *     @type array ...$0 {
	 *         @type string $0 The resource path or URL.
	 *         @type bool   $1 Expected result.
	 *     }
	 * }
	 */
	public function data_wp_is_stream() {
		return array(
			// Legitimate stream examples.
			array( 'http://example.com', true ),
			array( 'https://example.com', true ),
			array( 'ftp://example.com', true ),
			array( 'file:///path/to/some/file', true ),
			array( 'php://some/php/file.php', true ),

			// Non-stream examples.
			array( 'fakestream://foo/bar/baz', false ),
			array( '../../some/relative/path', false ),
			array( 'some/other/relative/path', false ),
			array( '/leading/relative/path', false ),
		);
	}
}
