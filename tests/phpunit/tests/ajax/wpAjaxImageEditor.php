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
	 * @ticket 26381
	 * @requires function imagejpeg
	 *
	 * @covers ::wp_save_image
	 */
	public function testCropImageIntoLargerOne() {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id     = $this->_make_attachment( $upload );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['postid']  = $id;
		$_REQUEST['do']      = 'scale';
		$_REQUEST['fwidth']  = 700;
		$_REQUEST['fheight'] = 500;

		$ret = wp_save_image( $id );

		$this->assertObjectHasProperty( 'error', $ret );
		$this->assertSame( 'Images cannot be scaled to a size larger than the original.', $ret->error );
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

	/**
	 * Ensure the filesize is updated after editing an image.
	 *
	 * Tests that the image meta data file size is updated after editing an image,
	 * this includes both the full size image and all the generated sizes.
	 *
	 * @ticket 59684
	 */
	public function test_filesize_updated_after_editing_an_image() {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload              = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id                  = $this->_make_attachment( $upload );
		$original_image_meta = wp_get_attachment_metadata( $id );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'all';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		wp_save_image( $id );

		$post_edit_meta = wp_get_attachment_metadata( $id );

		$pre_file_sizes         = array_combine( array_keys( $original_image_meta['sizes'] ), array_column( $original_image_meta['sizes'], 'filesize' ) );
		$pre_file_sizes['full'] = $original_image_meta['filesize'];

		$post_file_sizes         = array_combine( array_keys( $post_edit_meta['sizes'] ), array_column( $post_edit_meta['sizes'], 'filesize' ) );
		$post_file_sizes['full'] = $post_edit_meta['filesize'];

		foreach ( $pre_file_sizes as $size => $size_filesize ) {
			// These are asserted individually as each image size needs to be checked separately.
			$this->assertNotSame( $size_filesize, $post_file_sizes[ $size ], "Filesize for $size should have changed after editing an image." );
		}
	}

	/**
	 * Ensure the filesize is restored after restoring the original image.
	 *
	 * Tests that the image meta data file size is restored after restoring the original image,
	 * this includes both the full size image and all the generated sizes.
	 *
	 * @ticket 59684
	 */
	public function test_filesize_restored_after_restoring_original_image() {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload              = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id                  = $this->_make_attachment( $upload );
		$original_image_meta = wp_get_attachment_metadata( $id );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'all';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		wp_save_image( $id );
		wp_restore_image( $id );

		$post_restore_meta = wp_get_attachment_metadata( $id );

		$pre_file_sizes         = array_combine( array_keys( $original_image_meta['sizes'] ), array_column( $original_image_meta['sizes'], 'filesize' ) );
		$pre_file_sizes['full'] = $original_image_meta['filesize'];

		$post_restore_file_sizes         = array_combine( array_keys( $post_restore_meta['sizes'] ), array_column( $post_restore_meta['sizes'], 'filesize' ) );
		$post_restore_file_sizes['full'] = $post_restore_meta['filesize'];

		$this->assertSameSetsWithIndex( $pre_file_sizes, $post_restore_file_sizes, 'Filesize should have restored after restoring the original image.' );
	}
}
