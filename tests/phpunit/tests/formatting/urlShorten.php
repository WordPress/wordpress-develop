<?php

/**
 * @group formatting
 *
 * @covers ::url_shorten
 */
class Tests_Formatting_UrlShorten extends WP_UnitTestCase {
	public function test_url_shorten() {
		$tests = array(
			'wordpress\.org/about/philosophy'            => 'wordpress\.org/about/philosophy', // No longer strips slashes.
			'wordpress.org/about/philosophy'             => 'wordpress.org/about/philosophy',
			'http://wordpress.org/about/philosophy/'     => 'wordpress.org/about/philosophy',  // Remove http, trailing slash.
			'http://www.wordpress.org/about/philosophy/' => 'wordpress.org/about/philosophy',  // Remove http, www.
			'http://wordpress.org/about/philosophy/#box' => 'wordpress.org/about/philosophy/#box',            // Don't shorten 35 characters.
			'http://wordpress.org/about/philosophy/#decisions' => 'wordpress.org/about/philosophy/#&hellip;', // Shorten to 32 if > 35 after cleaning.
		);
		foreach ( $tests as $k => $v ) {
			$this->assertSame( $v, url_shorten( $k ) );
		}

		// Shorten to 31 if > 34 after cleaning.
		$this->assertSame( 'wordpress.org/about/philosophy/#&hellip;', url_shorten( 'http://wordpress.org/about/philosophy/#decisions' ), 31 );
	}
}
