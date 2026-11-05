<?php
/**
 * @group upload
 * @group media
 */
class Tests_Upload extends WP_UnitTestCase {

	public $siteurl;

	public function set_up() {
		parent::set_up();
		$this->reset_options();
	}

	private function reset_options() {
		// System defaults.
		update_option( 'upload_path', 'wp-content/uploads' );
		update_option( 'upload_url_path', '' );
		update_option( 'uploads_use_yearmonth_folders', 1 );
	}

	public function test_upload_dir_default() {
		// wp_upload_dir() with default parameters.
		$info   = wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( _upload_get_url( $subdir ), $info['url'] );
		$this->assertSame( ABSPATH . UPLOADS . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_dir_no_yearnum() {
		update_option( 'uploads_use_yearmonth_folders', 0 );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		$info = _wp_upload_dir();

		$this->assertSame( get_option( 'siteurl' ) . '/' . UPLOADS, $info['url'] );
		$this->assertSame( ABSPATH . UPLOADS, $info['path'] );
		$this->assertSame( '', $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_dir_empty() {
		// Upload path setting is empty - it should default to UPLOADS.
		update_option( 'upload_path', '' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( _upload_get_url( $subdir ), $info['url'] );
		$this->assertSame( ABSPATH . UPLOADS . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}
}
