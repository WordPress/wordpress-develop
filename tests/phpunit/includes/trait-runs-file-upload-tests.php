<?php

/**
 * Defines helper functions for dealing with file uploads and attachment creation.
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
