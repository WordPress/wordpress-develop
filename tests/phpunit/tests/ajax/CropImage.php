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
 * @covers ::wp_ajax_crop_image
 * @group ajax
 */
class Tests_Ajax_CropImage extends WP_Ajax_UnitTestCase {

	/** @var WP_Post|null */
	private $attachment;

	/** @var WP_Post|null */
	private $cropped_attachment;

	/**
	 * Tests that attachment properties are copied over to the cropped image.
	 *
	 * @ticket 37750
	 */
	public function test_it_copies_metadata_from_original_image() {
		$this->attachment = $this->create_attachment( true );
		$this->prepare_post( $this->attachment );

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->validate_response( $response );

		$this->cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $this->cropped_attachment, 'get_post function must return an instance of WP_Post class' );
		$this->assertNotEmpty( $this->attachment->post_title, 'post_title value must not be empty for testing purposes' );
		$this->assertNotEmpty( $this->cropped_attachment->post_title, 'post_title value must not be empty for testing purposes' );
		$this->assertSame( $this->attachment->post_title, $this->cropped_attachment->post_title, 'post_title value should be copied over to the cropped attachment' );
		$this->assertSame( $this->attachment->post_content, $this->cropped_attachment->post_content, 'post_content value should be copied over to the cropped attachment' );
		$this->assertSame( $this->attachment->post_excerpt, $this->cropped_attachment->post_excerpt, 'post_excerpt value should be copied over to the cropped attachment' );
		$this->assertSame( $this->attachment->_wp_attachment_image_alt, $this->cropped_attachment->_wp_attachment_image_alt, '_wp_attachment_image_alt value should be copied over to the cropped attachment' );
	}

	/**
	 * Tests that attachment properties are not auto-generated if they are not defined for the original image.
	 *
	 * @ticket 37750
	 */
	public function test_it_doesnt_generate_new_metadata_if_metadata_is_empty() {
		$this->attachment = $this->create_attachment( false );
		$this->prepare_post( $this->attachment );

		// Make the request.
		try {
			$this->_handleAjax( 'crop-image' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->validate_response( $response );

		$this->cropped_attachment = get_post( $response['data']['id'] );
		$this->assertInstanceOf( WP_Post::class, $this->cropped_attachment, 'get_post function must return an instance of WP_Post class' );
		$this->assertEmpty( $this->attachment->post_title, 'post_title value must be empty for testing purposes' );
		$this->assertNotEmpty( $this->cropped_attachment->post_title, 'post_title value must be auto-generated if it\'s empty in the original attachment' );
		$this->assertStringStartsWith( 'http', $this->cropped_attachment->post_content, 'post_content value should contain an URL if it\'s empty in the original attachment' );
		$this->assertEmpty( $this->cropped_attachment->post_excerpt, 'post_excerpt value must be empty if it\'s empty in the original attachment' );
		$this->assertEmpty( $this->cropped_attachment->_wp_attachment_image_alt, '_wp_attachment_image_alt value must be empty if it\'s empty in the original attachment' );
	}

	public function set_up() {
		parent::set_up();

		// Become an administrator.
		$this->_setRole( 'administrator' );
	}

	/**
	 * Deletes attachment files and post entities.
	 */
	public function tear_down() {
		if ( $this->attachment instanceof WP_Post ) {
			wp_delete_attachment( $this->attachment->ID, true );
		}

		if ( $this->cropped_attachment instanceof WP_Post ) {
			wp_delete_attachment( $this->cropped_attachment->ID, true );
		}
		$this->attachment         = null;
		$this->cropped_attachment = null;

		parent::tear_down();
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
	 */
	private function validate_response( $response ) {
		$this->assertArrayHasKey( 'success', $response, 'Response array must contain "success" key.' );
		$this->assertArrayHasKey( 'data', $response, 'Response array must contain "data" key.' );
		$this->assertNotEmpty( $response['data']['id'], 'Response array must contain "ID" value of the post entity.' );
	}

	/**
	 * Prepares $_POST for crop-image ajax action.
	 *
	 * @param WP_Post $attachment
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
