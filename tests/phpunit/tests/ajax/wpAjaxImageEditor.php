<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax media editing.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_image_editor
 *
 * @requires function imagejpeg
 */
class Tests_Ajax_wpAjaxImageEditor extends WP_Ajax_UnitTestCase {

	/**
	 * Tear down the test fixture.
	 */
	public function tear_down() {
		// Cleanup.
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * @ticket 22985
	 * @requires function imagejpeg
	 *
	 * @covers ::wp_insert_attachment
	 * @covers ::wp_save_image
	 */
	public function testCropImageThumbnail() {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id     = $this->_make_attachment( $upload );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'thumbnail';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		$media_meta = wp_get_attachment_metadata( $id );
		$this->assertArrayHasKey( 'sizes', $media_meta, 'attachment should have size data' );
		$this->assertArrayHasKey( 'medium', $media_meta['sizes'], 'attachment should have data for medium size' );
		$ret = wp_save_image( $id );

		$media_meta = wp_get_attachment_metadata( $id );
		$this->assertArrayHasKey( 'sizes', $media_meta, 'cropped attachment should have size data' );
		$this->assertArrayHasKey( 'medium', $media_meta['sizes'], 'cropped attachment should have data for medium size' );
	}

	/**
	 * @ticket 32171
	 * @requires function imagejpeg
	 *
	 * @covers ::wp_insert_attachment
	 * @covers ::wp_save_image
	 */
	public function testImageEditOverwriteConstant() {
		define( 'IMAGE_EDIT_OVERWRITE', true );

		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id     = $this->_make_attachment( $upload );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'all';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		$ret = wp_save_image( $id );

		$media_meta = wp_get_attachment_metadata( $id );
		$sizes1     = $media_meta['sizes'];

		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":189,"h":322}}]';

		$ret = wp_save_image( $id );

		$media_meta = wp_get_attachment_metadata( $id );
		$sizes2     = $media_meta['sizes'];

		$file_path = dirname( get_attached_file( $id ) );

		$files_that_should_not_exist = array();

		foreach ( $sizes1 as $key => $size ) {
			if ( $sizes2[ $key ]['file'] !== $size['file'] ) {
				$files_that_should_not_exist[] = $file_path . '/' . $size['file'];
			}
		}

		if ( ! empty( $files_that_should_not_exist ) ) {
			foreach ( $files_that_should_not_exist as $file ) {
				$this->assertFileDoesNotExist( $file, 'IMAGE_EDIT_OVERWRITE is leaving garbage image files behind.' );
			}
		} else {
			/*
			 * This assertion will always pass due to the "if" condition, but prevents this test
			 * from being marked as "risky" due to the test not performing any assertions.
			 */
			$this->assertSame( array(), $files_that_should_not_exist );
		}
	}
}
