<?php

/**
 * Tests for the `bloginfo()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::bloginfo
 */
class Tests_General_Bloginfo extends WP_UnitTestCase {

	/**
	 * Tests that bloginfo() displays the site name by default.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_default_displays_name() {
		update_option( 'blogname', 'Test Blog' );

		$this->expectOutputString( 'Test Blog' );
		bloginfo();
	}

	/**
	 * Tests that bloginfo( 'url' ) displays the home URL.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_url() {
		update_option( 'home', 'http://example.org' );

		$this->expectOutputString( 'http://example.org' );
		bloginfo( 'url' );
	}

	/**
	 * Tests that bloginfo() displays filtered value when the bloginfo filter is used.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_filter() {
		add_filter(
			'bloginfo',
			static function ( $output, $show ) {
				if ( 'name' === $show ) {
					return 'Filtered Site Title';
				}
				return $output;
			},
			10,
			2
		);

		$this->expectOutputString( 'Filtered Site Title' );
		bloginfo( 'name' );
	}
}
