<?php

/**
 * Defines a basic fixture to run multiple tests.
 *
 * Resets the state of the WordPress installation before and after every test.
 *
 * Includes utility functions and assertions useful for testing WordPress.
 *
 * All WordPress unit tests should inherit from this class.
 */
trait WP_Test_RunsFileUploadTests {

	/**
	 * Uploads given file and creates an attachment post from it.
	 *
	 * @since 6.2.0
	 *
	 * @param array $filename       Absolute path to the file to upload.
	 * @param int   $parent_post_id Optional. Parent post ID.
	 *
	 * @return int|WP_Error The attachment ID on success, WP_Error object on failure.
	 */
	public function _upload_file_and_make_attachment( $filename, $parent_post_id = 0 ) {
		$contents = file_get_contents( $filename );
		$upload   = wp_upload_bits( wp_basename( $filename ), null, $contents );

		return $this->_make_attachment( $upload, $parent_post_id );
	}
}
