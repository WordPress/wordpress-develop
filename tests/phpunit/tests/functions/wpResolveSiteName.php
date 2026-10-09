<?php

/**
 * Tests for wp_resolve_site_name function
 *
 * @group functions
 *
 * @covers ::wp_resolve_site_name
 */
class Tests_Functions_wpResolveSiteName extends WP_UnitTestCase {

	/**
	 * @ticket 65937
	 */
	public function test_wp_resolve_site_name_with_site_name() {
		$site_name = 'My Mock Site Title';
		$expected  = $site_name;

		update_option( 'blogname', $site_name );

		$this->assertSame( $expected, wp_resolve_site_name() );
	}

	/**
	 * @ticket 65937
	 */
	public function test_wp_resolve_site_name_without_site_name() {
		$site_name = '';
		$expected  = wp_parse_url( home_url(), PHP_URL_HOST );
		update_option( 'blogname', $site_name );

		$this->assertSame( $expected, wp_resolve_site_name() );
	}

}
