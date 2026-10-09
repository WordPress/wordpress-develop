<?php

/**
 * Tests for wp_resolve_site_name function
 *
 * @group functions
 *
 * @covers ::wp_resolve_site_title
 */
class Tests_Functions_wpResolveSiteTitle extends WP_UnitTestCase {

	/**
	 * @ticket 65937
	 */
	public function test_wp_resolve_site_title_with_site_name() {
		$site_title = 'My Mock Site Title';
		$expected   = $site_title;

		update_option( 'blogname', $site_title );

		$this->assertSame( $expected, wp_resolve_site_title() );
	}

	/**
	 * @ticket 65937
	 */
	public function test_wp_resolve_site_title_without_site_name() {
		$site_title = '';
		$expected   = wp_parse_url( home_url(), PHP_URL_HOST );
		update_option( 'blogname', $site_title );

		$this->assertSame( $expected, wp_resolve_site_title() );
	}

}
