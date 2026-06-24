<?php

/**
 * @ticket 65526
 *
 * @group functions
 * @group upload
 *
 * @covers ::wp_upload_dir
 */
class Tests_Functions_WpUploadDir extends WP_UnitTestCase {

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

	/**
	 * @covers ::wp_upload_dir
	 */
	public function test_upload_dir_default() {
		// wp_upload_dir() with default parameters.
		$info   = wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}
}
