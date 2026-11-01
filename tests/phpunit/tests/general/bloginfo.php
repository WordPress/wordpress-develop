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
		$this->expectOutputString( 'Test Blog' );
		bloginfo();
	}

	/**
	 * Tests that bloginfo( 'name' ) displays the site name.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_name() {
		$this->expectOutputString( 'Test Blog' );
		bloginfo( 'name' );
	}

	/**
	 * Tests that bloginfo( 'url' ) displays the home URL.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_url() {
		$this->expectOutputString( 'http://example.org' );
		bloginfo( 'url' );
	}

	/**
	 * Tests that bloginfo( 'description' ) displays the site description.
	 *
	 * @ticket 65819
	 */
	public function test_bloginfo_description() {
		update_option( 'blogdescription', 'Just another WordPress site' );

		$this->expectOutputString( 'Just another WordPress site' );
		bloginfo( 'description' );
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
