<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_trackback_urls
 */
class Tests_Formatting_SanitizeTrackbackUrls extends WP_UnitTestCase {
	/**
	 * @ticket 21624
	 * @dataProvider breaks
	 */
	public function test_sanitize_trackback_urls_with_multiple_urls( $break ) {
		$this->assertSame( "http://example.com\nhttp://example.org", sanitize_trackback_urls( "http://example.com{$break}http://example.org" ) );
	}

	public function breaks() {
		return array(
			array( "\r\n\t " ),
			array( "\r" ),
			array( "\n" ),
			array( "\t" ),
			array( ' ' ),
			array( '  ' ),
			array( "\n  " ),
			array( "\r\n" ),
		);
	}
}
