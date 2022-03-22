<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

/**
 * Class for testing ajax crop image functionality.
 *
 * @group ajax
 */
class Tests_Ajax_CropImage extends WP_Ajax_UnitTestCase {

	/**
	 * @covers wp_ajax_crop_image
	 */
	public function test_it_copies_metadata_from_original_image() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$attachment = $this->create_attachment( true );
		$this->prepare_post( $attachment );

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->validate_response( $response );

		$cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $cropped_attachment );
		$this->assertNotEmpty( $attachment->post_title );
		$this->assertNotEmpty( $cropped_attachment->post_title );
		$this->assertSame( $attachment->post_title, $cropped_attachment->post_title );
		$this->assertSame( $attachment->post_content, $cropped_attachment->post_content );
		$this->assertSame( $attachment->post_excerpt, $cropped_attachment->post_excerpt );
		$this->assertSame( $attachment->_wp_attachment_image_alt, $cropped_attachment->_wp_attachment_image_alt );

		wp_delete_attachment( $attachment->ID, true );
		wp_delete_attachment( $cropped_attachment->ID, true );
	}

	/**
	 * @covers wp_ajax_crop_image
	 */
	public function test_it_doesnt_generate_new_metadata_if_metadata_is_empty() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		$attachment = $this->create_attachment( false );
		$this->prepare_post( $attachment );

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->validate_response( $response );

		$cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $cropped_attachment );
		$this->assertEmpty( $attachment->post_title );
		$this->assertNotEmpty( $cropped_attachment->post_title );
		$this->assertStringStartsWith( 'http', $cropped_attachment->post_content );
		$this->assertEmpty( $cropped_attachment->post_excerpt );
		$this->assertEmpty( $cropped_attachment->_wp_attachment_image_alt );

		wp_delete_attachment( $attachment->ID, true );
		wp_delete_attachment( $cropped_attachment->ID, true );
	}

	/**
	 * Creates an attachment.
	 *
	 * @return WP_Post
	 */
	private function create_attachment( $with_metadata = true ) {
		$uniq_id = uniqid( 'crop-image-ajax-action-test-' );
		$object  = array(
			'post_title'     => $with_metadata ? 'Title ' . $uniq_id : '',
			'post_content'   => $with_metadata ? 'Description ' . $uniq_id : '',
			'post_mime_type' => 'image/jpg',
			'guid'           => 'http://localhost/foo.jpg',
			'context'        => 'custom-logo',
			'post_excerpt'   => $with_metadata ? 'Caption ' . $uniq_id : '',
		);

		$test_file        = DIR_TESTDATA . '/images/test-image.jpg';
		$upload_directory = wp_upload_dir();
		$uploaded_file    = $upload_directory['path'] . '/' . $uniq_id . '.jpg';
		$filesystem       = new WP_Filesystem_Direct( true );
		$filesystem->copy( $test_file, $uploaded_file );
		$attachment_id = wp_insert_attachment( $object, $uploaded_file );

		if ( $with_metadata ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( 'Alt ' . $uniq_id ) );
		}

		return get_post( $attachment_id );
	}

	/**
	 * @param array $response Response to validate.
	 *
	 * @return void
	 */
	private function validate_response( $response ) {
		$this->assertArrayHasKey( 'success', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertNotEmpty( $response['data']['id'] );
	}

	/**
	 * Prepares $_POST for crop-image ajax action.
	 *
	 * @param WP_Post $attachment
	 *
	 * @return void
	 */
	private function prepare_post( WP_Post $attachment ) {
		$_POST = array(
			'wp_customize' => 'on',
			'nonce'        => wp_create_nonce( 'image_editor-' . $attachment->ID ),
			'id'           => $attachment->ID,
			'context'      => 'custom_logo',
			'cropDetails'  =>
				array(
					'x1'         => '0',
					'y1'         => '0',
					'x2'         => '100',
					'y2'         => '100',
					'width'      => '100',
					'height'     => '100',
					'dst_width'  => '100',
					'dst_height' => '100',
				),
			'action'       => 'crop-image',
		);
	}
}
