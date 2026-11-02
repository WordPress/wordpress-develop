<?php
/**
 * Test wp_admin_css_uri().
 *
 * @group general
 * @group template
 *
 * @covers ::wp_admin_css_uri
 */
class Tests_General_wpAdminCssUri extends WP_UnitTestCase {

	/**
	 * Tests wp_admin_css_uri() with default argument.
	 *
	 * @ticket 65819
	 */
	public function test_wp_admin_css_uri_default() {
		$expected = add_query_arg( 'version', get_bloginfo( 'version' ), admin_url( 'wp-admin.css' ) );

		$this->assertSame( $expected, wp_admin_css_uri() );
	}

	/**
	 * Tests wp_admin_css_uri() with custom file.
	 *
	 * @ticket 65819
	 */
	public function test_wp_admin_css_uri_custom_file() {
		$file     = 'colors/modern/colors';
		$expected = add_query_arg( 'version', get_bloginfo( 'version' ), admin_url( "$file.css" ) );

		$this->assertSame( $expected, wp_admin_css_uri( $file ) );
	}

	/**
	 * Tests that 'wp_admin_css_uri' filter can modify the returned URI and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_wp_admin_css_uri_filter() {
		$received_file = null;

		$callback = function ( $uri, $file ) use ( &$received_file ) {
			$received_file = $file;
			return 'https://example.org/filtered-admin.css';
		};

		add_filter( 'wp_admin_css_uri', $callback, 10, 2 );
		$actual = wp_admin_css_uri( 'custom-admin' );

		$this->assertSame( 'custom-admin', $received_file );
		$this->assertSame( 'https://example.org/filtered-admin.css', $actual );
	}
}
