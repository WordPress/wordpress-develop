<?php

/**
 * Defines helper functions for dealing with file uploads and attachment creation.
 */
trait WP_Test_RunsFileUploadTests {

	/**
	 * Creates an attachment post from an uploaded file.
	 *
	 * @since 4.4.0
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param array $upload         Array of information about the uploaded file, provided by wp_upload_bits().
	 * @param int   $parent_post_id Optional. Parent post ID.
	 * @return int|WP_Error The attachment ID on success, WP_Error object on failure.
	 */
	public function _make_attachment( $upload, $parent_post_id = 0 ) {
		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( $mime ) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => '',
			'post_type'      => 'attachment',
			'post_parent'    => $parent_post_id,
			'post_mime_type' => $type,
			'guid'           => $upload['url'],
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id, true );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);

		return $attachment_id;
	}

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
