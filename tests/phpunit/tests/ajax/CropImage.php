<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Add Meta AJAX functionality.
 *
 * @group ajax
 */
class Tests_Ajax_CropImage extends WP_Ajax_UnitTestCase {

	/**
	 * @covers wp_ajax_crop_image
	 * @covers wp_insert_attachment
	 */
	public function test_it_copies_metadata_from_original_image() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$attachement = $this->create_attachment();

		$_POST = array(
			'wp_customize' => 'on',
			'nonce'        => wp_create_nonce( 'image_editor-' . $attachement->ID ),
			'id'           => $attachement->ID,
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

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertArrayHasKey( 'success', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertNotEmpty( $response['data']['id'] );
		$cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $cropped_attachment );

		$this->assertNotEmpty( $attachement->post_title );
		$this->assertNotEmpty( $cropped_attachment->post_title );
		$this->assertNotSame( $attachement->post_title, $cropped_attachment->post_title );
		$this->assertSame( $attachement->post_content, $cropped_attachment->post_content );
		$this->assertSame( $attachement->post_excerpt, $cropped_attachment->post_excerpt );
		$this->assertSame( $attachement->_wp_attachment_image_alt, $cropped_attachment->_wp_attachment_image_alt );

		wp_delete_post( $attachement->ID );
		wp_delete_attachment( $cropped_attachment->ID, true );
	}

	public function test_it_doesnt_add_metadata_if_it_is_empty() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$attachement = $this->create_attachment( false );

		$_POST = array(
			'wp_customize' => 'on',
			'nonce'        => wp_create_nonce( 'image_editor-' . $attachement->ID ),
			'id'           => $attachement->ID,
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

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertArrayHasKey( 'success', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertNotEmpty( $response['data']['id'] );
		$cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $cropped_attachment );

		$this->assertNotEmpty( $attachement->post_title );
		$this->assertNotEmpty( $cropped_attachment->post_title );
		$this->assertNotSame( $attachement->post_title, $cropped_attachment->post_title );
		$this->assertStringStartsWith( 'http', $cropped_attachment->post_content );
		$this->assertEmpty( $cropped_attachment->post_excerpt );
		$this->assertEmpty( $cropped_attachment->_wp_attachment_image_alt );

		wp_delete_post( $attachement->ID );
		wp_delete_attachment( $cropped_attachment->ID, true );
	}

	/**
	 * Creates attachment.
	 *
	 * @return WP_Post
	 */
	private function create_attachment( $with_metadata = true ) {
		$uniq_id       = uniqid();
		$object        = array(
			'post_title'     => 'Title ' . $uniq_id,
			'post_content'   => $with_metadata ? 'Description ' . $uniq_id : '',
			'post_mime_type' => 'image/jpg',
			'guid'           => 'http://localhost/foo.jpg',
			'context'        => 'custom-logo',
			'post_excerpt'   => $with_metadata ? 'Caption ' . $uniq_id : '',
		);
		$filename      = DIR_TESTDATA . '/images/test-image.jpg';
		$attachment_id = wp_insert_attachment( $object, $filename );

		if ( $with_metadata ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( 'Alt ' . $uniq_id ) );
		}

		return get_post( $attachment_id );
	}
}
