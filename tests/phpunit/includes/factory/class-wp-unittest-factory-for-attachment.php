<?php

/**
 * Unit test factory for attachments.
 */
class WP_UnitTest_Factory_For_Attachment extends WP_UnitTest_Factory_For_Post {

	/**
	 * Create an attachment fixture.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array $args {
	 *     Array of arguments. Accepts all arguments that can be passed to
	 *     wp_insert_attachment(), in addition to the following:
	 *     @type int    $post_parent ID of the post to which the attachment belongs.
	 *     @type string $file        Path of the attached file.
	 * }
	 * @param int          $legacy_parent Deprecated.
	 * @param array        $legacy_args   Deprecated.
	 * @return positive-int The attachment ID.
	 * @throws WP_UnitTest_Factory_Exception When the attachment could not be created.
	 */
	public function create_object( $args, $legacy_parent = 0, $legacy_args = array() ) {
		// Backward compatibility for legacy argument format.
		if ( is_string( $args ) ) {
			$file                = $args;
			$args                = $legacy_args;
			$args['post_parent'] = $legacy_parent;
			$args['file']        = $file;
		}

		$r = array_merge(
			array(
				'file'        => '',
				'post_parent' => 0,
			),
			$args
		);

		$attachment_id = wp_insert_attachment( $r, $r['file'], $r['post_parent'], true );

		$this->assert_valid_object_id( $attachment_id, 'Unable to create the attachment' );

		return $attachment_id;
	}

	/**
	 * Saves a file as an attachment.
	 *
	 * @since 4.4.0
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param string $file           Full path to the file to create an attachment object for.
	 *                               The name of the file will be used as the attachment name.
	 * @param int    $parent_post_id ID of the post to attach the file to.
	 * @return int|WP_Error The attachment ID on success, WP_Error object on failure.
	 */
	public function create_upload_object( $file, $parent_post_id = 0 ) {
		$contents = file_get_contents( $file );
		$upload   = wp_upload_bits( wp_basename( $file ), null, $contents );

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

		// Save the data.
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
}
