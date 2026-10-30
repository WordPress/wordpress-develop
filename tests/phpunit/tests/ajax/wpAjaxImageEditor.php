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
		remove_image_size( 'test-600w' );
		parent::tear_down();
	}

	/**
	 * @ticket 26381
	 * @requires function imagejpeg
	 *
	 * @covers ::wp_save_image
	 */
	public function test_crop_image_into_larger_one() {
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
	public function test_image_edit_overwrite_constant() {
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
	public static function data_save_image_with_unusable_sizes_metadata(): array {
		return array(
			'no sizes key'  => array( array() ),
			'null sizes'    => array( array( 'sizes' => null ) ),
			'empty string'  => array( array( 'sizes' => '' ) ),
			'string sizes'  => array( array( 'sizes' => 'not-an-array' ) ),
			'boolean sizes' => array( array( 'sizes' => false ) ),
		);
	}

	/**
	 * Uploads canola.jpg (640x480) and returns the attachment ID.
	 *
	 * @return int Attachment ID.
	 */
	private function make_canola_attachment() {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );
		$upload   = wp_upload_bits( wp_basename( $filename ), null, $contents );

		return $this->_make_attachment( $upload );
	}

	/**
	 * Sets up the request for saving an image edit.
	 *
	 * @param int    $id      Attachment ID.
	 * @param string $target  Which sizes to apply the edit to.
	 * @param string $history JSON-encoded edit history.
	 */
	private function set_save_request( $id, $target, $history ) {
		$_REQUEST['action']  = 'image-editor';
		$_REQUEST['context'] = 'edit-attachment';
		$_REQUEST['postid']  = $id;
		$_REQUEST['target']  = $target;
		$_REQUEST['do']      = 'save';
		$_REQUEST['history'] = $history;
	}

	/**
	 * A size that cannot be created for the edited image must not keep
	 * pointing at the unedited file.
	 *
	 * @ticket 55290
	 *
	 * @covers ::wp_save_image
	 */
	public function test_size_too_large_for_rotated_image_is_removed_from_metadata() {
		// Fits the 640px wide original, but not the 480px wide rotated image.
		add_image_size( 'test-600w', 600, 0 );

		$id            = $this->make_canola_attachment();
		$original_meta = wp_get_attachment_metadata( $id );

		$this->assertArrayHasKey( 'test-600w', $original_meta['sizes'], 'The custom size should exist before editing.' );

		$this->set_save_request( $id, 'all', '[{"r":-90}]' );
		$ret = wp_save_image( $id );

		$this->assertObjectNotHasProperty( 'error', $ret, 'Saving the edit should not fail.' );

		$edited_meta = wp_get_attachment_metadata( $id );

		$this->assertSame( 480, $edited_meta['width'], 'The full size image should be rotated.' );
		$this->assertArrayNotHasKey( 'test-600w', $edited_meta['sizes'], 'The stale, unrotated size should be removed.' );

		$backup_sizes = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
		$this->assertSame(
			$original_meta['sizes']['test-600w'],
			$backup_sizes['test-600w-orig'],
			'The original size should be kept in the backup sizes so it can be restored.'
		);
	}

	/**
	 * All sizes that can be created for the edited image are regenerated.
	 *
	 * @ticket 55290
	 *
	 * @covers ::wp_save_image
	 */
	public function test_all_possible_sizes_are_regenerated_after_rotation() {
		$id            = $this->make_canola_attachment();
		$original_meta = wp_get_attachment_metadata( $id );

		$this->set_save_request( $id, 'all', '[{"r":-90}]' );
		wp_save_image( $id );

		$edited_meta = wp_get_attachment_metadata( $id );

		$this->assertSameSets(
			array_keys( $original_meta['sizes'] ),
			array_keys( $edited_meta['sizes'] ),
			'The same sizes should exist after rotating the image.'
		);

		foreach ( $edited_meta['sizes'] as $size => $size_meta ) {
			$this->assertNotSame( $original_meta['sizes'][ $size ]['file'], $size_meta['file'], "The $size size should be regenerated." );
			$this->assertGreaterThanOrEqual( $size_meta['width'], $size_meta['height'], "The $size size should be rotated." );
		}
	}

	/**
	 * Sizes that are not part of the edit target are kept in the metadata.
	 *
	 * @ticket 55290
	 *
	 * @covers ::wp_save_image
	 *
	 * @dataProvider data_sizes_outside_the_edit_target_are_kept
	 *
	 * @param string   $target         The edit target.
	 * @param string[] $untouched_sizes Sizes that should be unchanged after the edit.
	 */
	public function test_sizes_outside_the_edit_target_are_kept( $target, $untouched_sizes ) {
		add_filter( 'image_edit_thumbnails_separately', '__return_true' );

		$id            = $this->make_canola_attachment();
		$original_meta = wp_get_attachment_metadata( $id );

		$this->set_save_request( $id, $target, '[{"r":-90}]' );
		wp_save_image( $id );

		$edited_meta = wp_get_attachment_metadata( $id );

		foreach ( $untouched_sizes as $size ) {
			$this->assertArrayHasKey( $size, $edited_meta['sizes'], "The $size size should be kept." );
			$this->assertSame( $original_meta['sizes'][ $size ], $edited_meta['sizes'][ $size ], "The $size size should be unchanged." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_sizes_outside_the_edit_target_are_kept() {
		return array(
			'all sizes except thumbnail' => array(
				'target'          => 'nothumb',
				'untouched_sizes' => array( 'thumbnail' ),
			),
			'thumbnail only'             => array(
				'target'          => 'thumbnail',
				'untouched_sizes' => array( 'medium' ),
			),
		);
	}
}
