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

	/**
	 * Ensure editing an image does not fatal when the attachment metadata has no usable `sizes` data.
	 *
	 * Attachment metadata is not guaranteed to contain a `sizes` array. It can be missing when
	 * sub-size generation never ran or failed (for example `wp_create_image_subsizes()` returns an
	 * empty array when the file cannot be parsed), or when it is removed by a plugin filtering
	 * `wp_get_attachment_metadata`. `wp_save_image()` only validates that the metadata itself is an
	 * array, then passes `$meta['sizes']` straight to `array_merge()`.
	 *
	 * @ticket 65748
	 *
	 * @covers ::wp_save_image
	 *
	 * @dataProvider data_save_image_with_unusable_sizes_metadata
	 *
	 * @param array{ sizes?: mixed } $meta Attachment metadata to store before editing, minus the file-specific keys.
	 */
	public function test_save_image_with_unusable_sizes_metadata( array $meta ) {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );
		$this->assertIsString( $contents );

		$upload = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id     = $this->_make_attachment( $upload );
		$this->assertIsInt( $id );

		$original_meta = wp_get_attachment_metadata( $id );
		$this->assertIsArray( $original_meta );

		// Keep the real file/dimension data, only make `sizes` unusable.
		$meta = array_merge(
			wp_array_slice_assoc( $original_meta, array( 'width', 'height', 'file', 'filesize' ) ),
			$meta
		);

		wp_update_attachment_metadata( $id, $meta );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'all';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		$ret = wp_save_image( $id );

		$this->assertObjectNotHasProperty( 'error', $ret, 'Saving the image should not have returned an error.' );

		$saved_meta = wp_get_attachment_metadata( $id );

		$this->assertIsArray( $saved_meta, 'The saved attachment metadata should be an array.' );
		$this->assertArrayHasKey( 'sizes', $saved_meta );
		$this->assertIsArray( $saved_meta['sizes'], 'The saved attachment metadata should contain a `sizes` array.' );
		$this->assertArrayHasKey( 'thumbnail', $saved_meta['sizes'], 'The edited image should have regenerated the thumbnail size.' );
	}

	/**
	 * Ensure restoring an image does not fatal when the attachment metadata has no usable `sizes` data.
	 *
	 * `wp_restore_image()` writes each backed up size with `$meta['sizes'][ $default_size ] = $data`
	 * without ever checking that `$meta['sizes']` is an array. A scalar value raises
	 * "Cannot use a scalar value as an array", and `false` is deprecated as of PHP 8.1 and
	 * an error as of PHP 9. The same metadata that fatals `wp_save_image()` reaches this code.
	 *
	 * @ticket 65748
	 *
	 * @covers ::wp_restore_image
	 *
	 * @dataProvider data_save_image_with_unusable_sizes_metadata
	 *
	 * @param array{ sizes?: mixed } $meta Replacement `sizes` metadata to store before restoring.
	 */
	public function test_restore_image_with_unusable_sizes_metadata( array $meta ) {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );
		$this->assertIsString( $contents );

		$upload = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$id     = $this->_make_attachment( $upload );
		$this->assertIsInt( $id );

		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = 'all';
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = '[{"c":{"x":5,"y":8,"w":289,"h":322}}]';

		// Edit the image first so that `_wp_attachment_backup_sizes` holds the original sizes.
		wp_save_image( $id );

		$this->assertNotEmpty(
			get_post_meta( $id, '_wp_attachment_backup_sizes', true ),
			'The image edit should have stored backup sizes to restore from.'
		);

		// Keep the metadata written by the edit, only make `sizes` unusable.
		$edited_meta = wp_get_attachment_metadata( $id );
		$this->assertIsArray( $edited_meta );
		unset( $edited_meta['sizes'] );

		wp_update_attachment_metadata( $id, array_merge( $edited_meta, $meta ) );

		wp_restore_image( $id );

		$restored_meta = wp_get_attachment_metadata( $id );
		$this->assertIsArray( $restored_meta );

		$this->assertArrayHasKey( 'sizes', $restored_meta );
		$this->assertIsArray( $restored_meta['sizes'], 'The restored attachment metadata should contain a `sizes` array.' );
		$this->assertArrayHasKey( 'thumbnail', $restored_meta['sizes'], 'The restored image should have the thumbnail size restored from the backup sizes.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-empty-string, array{ 0: array{ sizes?: mixed } }>
	 */
	public function data_save_image_with_unusable_sizes_metadata(): array {
		return array(
			'no sizes key'  => array( array() ),
			'null sizes'    => array( array( 'sizes' => null ) ),
			'empty string'  => array( array( 'sizes' => '' ) ),
			'string sizes'  => array( array( 'sizes' => 'not-an-array' ) ),
			'boolean sizes' => array( array( 'sizes' => false ) ),
		);
	}
}
