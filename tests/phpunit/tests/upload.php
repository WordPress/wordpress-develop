<?php
/**
 * @group upload
 * @group media
 */
class Tests_Upload extends WP_UnitTestCase {

	var $siteurl;

	function setUp() {
		$this->_reset_options();
		parent::setUp();
	}

	function _reset_options() {
		// System defaults.
		update_option( 'upload_path', 'wp-content/uploads' );
		update_option( 'upload_url_path', '' );
		update_option( 'uploads_use_yearmonth_folders', 1 );
	}

	function test_upload_dir_default() {
		// wp_upload_dir() with default parameters.
		$info   = wp_upload_dir();
		$subdir = gmstrftime( '/%Y/%m' );

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	function test_upload_dir_relative() {
		// wp_upload_dir() with a relative upload path that is not 'wp-content/uploads'.
		update_option( 'upload_path', 'foo/bar' );
		$info   = _wp_upload_dir();
		$subdir = gmstrftime( '/%Y/%m' );

		$this->assertSame( get_option( 'siteurl' ) . '/foo/bar' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'foo/bar' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	/**
	 * @ticket 5953
	 */
	function test_upload_dir_absolute() {
		$path = get_temp_dir() . 'wp-unit-test';

		// wp_upload_dir() with an absolute upload path.
		update_option( 'upload_path', $path );

		// Doesn't make sense to use an absolute file path without setting the url path.
		update_option( 'upload_url_path', '/baz' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = gmstrftime( '/%Y/%m' );

		$this->assertSame( '/baz' . $subdir, $info['url'] );
		$this->assertSame( $path . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	function test_upload_dir_no_yearnum() {
		update_option( 'uploads_use_yearmonth_folders', 0 );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		$info = _wp_upload_dir();

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads', $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads', $info['path'] );
		$this->assertSame( '', $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	function test_upload_path_absolute() {
		update_option( 'upload_url_path', 'http://' . WP_TESTS_DOMAIN . '/asdf' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = gmstrftime( '/%Y/%m' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/asdf' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	function test_upload_dir_empty() {
		// Upload path setting is empty - it should default to 'wp-content/uploads'.
		update_option( 'upload_path', '' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = gmstrftime( '/%Y/%m' );

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

}
