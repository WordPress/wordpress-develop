<?php
/**
 * Tests for wp_cookie_constants().
 *
 * @group load
 * @covers ::wp_cookie_constants
 */

class Tests_Load_wpCookieConstants extends WP_UnitTestCase {

	/**
	 * Ensure that COOKIEPATH, SITECOOKIEPATH and PLUGINS_COOKIE_PATH are trimmed
	 * when option values contain leading/trailing spaces.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_trim_cookie_paths_from_options_with_spaces() {
		update_option( 'home', ' http://example.org/ ' );
		update_option( 'siteurl', ' http://example.org/ ' );

		wp_plugin_directory_constants();
		wp_cookie_constants();

		$this->assertSame( '/', COOKIEPATH );
		$this->assertSame( '/', SITECOOKIEPATH );
		$this->assertSame( '/wp-content/plugins', PLUGINS_COOKIE_PATH );

		$this->assertSame( trim( COOKIEPATH ), COOKIEPATH );
		$this->assertSame( trim( SITECOOKIEPATH ), SITECOOKIEPATH );
		$this->assertSame( trim( PLUGINS_COOKIE_PATH ), PLUGINS_COOKIE_PATH );

		$this->assertStringNotContainsString( ' ', COOKIEPATH );
		$this->assertStringNotContainsString( ' ', SITECOOKIEPATH );
		$this->assertStringNotContainsString( ' ', PLUGINS_COOKIE_PATH );
	}

	/**
	 * When options are normal (no spaces), constants should be their expected defaults.
	 */
	public function test_cookie_paths_are_unchanged_when_no_spaces() {
		update_option( 'home', 'http://example.com' );
		update_option( 'siteurl', 'http://example.com' );

		wp_plugin_directory_constants();
		wp_cookie_constants();

		$this->assertSame( '/', COOKIEPATH );
		$this->assertSame( '/', SITECOOKIEPATH );
		$this->assertSame( '/wp-content/plugins', PLUGINS_COOKIE_PATH );
	}
}
