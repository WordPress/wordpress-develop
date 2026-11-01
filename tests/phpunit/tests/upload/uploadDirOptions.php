<?php
/**
 * Tests for the upload_path and upload_url_path options in _wp_upload_dir().
 *
 * The UPLOADS constant, defined in bootstrap.php, overrides these options, so the
 * tests run in a separate process where bootstrap.php skips defining it.
 *
 * @group upload
 * @group media
 *
 * @covers ::_wp_upload_dir
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Tests_Upload_UploadDirOptions extends WP_UnitTestCase {

	/**
	 * These path-option tests do not create upload files to track.
	 *
	 * Skipping the scan also prevents wp_upload_dir() from creating the real
	 * wp-content/uploads/YYYY/MM directory, since UPLOADS is not defined here.
	 *
	 * @return array Empty upload snapshot.
	 */
	public function scan_user_uploads() {
		return array();
	}

	public static function set_up_before_class() {
		putenv( 'WP_TESTS_SKIP_UPLOADS_CONSTANT=1' );

		parent::set_up_before_class();
	}

	public static function tear_down_after_class() {
		putenv( 'WP_TESTS_SKIP_UPLOADS_CONSTANT' );

		parent::tear_down_after_class();
	}

	public function test_upload_dir_relative() {
		// wp_upload_dir() with a relative upload path that is not 'wp-content/uploads'.
		update_option( 'upload_path', 'foo/bar' );
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( get_option( 'siteurl' ) . '/foo/bar' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'foo/bar' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	/**
	 * @ticket 5953
	 */
	public function test_upload_dir_absolute() {
		$path = get_temp_dir() . 'wp-unit-test';

		// wp_upload_dir() with an absolute upload path.
		update_option( 'upload_path', $path );

		// Doesn't make sense to use an absolute file path without setting the url path.
		update_option( 'upload_url_path', '/baz' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( '/baz' . $subdir, $info['url'] );
		$this->assertSame( $path . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_path_absolute() {
		update_option( 'upload_url_path', 'http://' . WP_TESTS_DOMAIN . '/asdf' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/asdf' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}
}
