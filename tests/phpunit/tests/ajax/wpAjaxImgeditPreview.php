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
 *
 * @covers ::wp_ajax_crop_image
 */
class Tests_Ajax_WpAjaxImgeditPreview extends WP_Ajax_UnitTestCase {

	/**
	 * @var WP_Post|null
	 */
	private $attachment;

	/**
	 * @var WP_Post|null
	 */
	private $cropped_attachment;

	protected static $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		self::$attachment_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_attachment( self::$attachment_id, true );
	}

	public function set_up() {
		parent::set_up();

		// Become an administrator.
		$this->_setRole( 'administrator' );
	}

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
	 * Tests that attachment properties are copied over to the cropped image.
	 *
	 * @group ajax
	 *
	 * @ticket 37750
	 */
	public function test_stream_preview_image() {
		$this->attachment = $this->make_attachment( true );
		$this->prepare_post( $this->attachment );
		$this->_setRole( 'editor' );

		$_GET = wp_parse_args(
			array(
				'action'      => 'imgedit-preview',
				'_ajax_nonce' => wp_create_nonce( 'image_editor-' . $this->attachment->ID ),
				'postid'      => $this->attachment->ID,
				'rand'        => random_int( 1, 1000 ),
			)
		);

		try {
			$this->_handleAjax( 'imgedit-preview' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$this->assertStringStartsWith( "\xFF\xD8", $this->_last_response );
	}

	/**
	 * Test stream_preview_image function with non-image attachment.
	 */
	public function test_stream_preview_image_non_image() {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/functions/dummy.txt' );

		$result = stream_preview_image( $attachment_id );

		$this->assertFalse( $result );

		wp_delete_attachment( $attachment_id, true );
	}


	/**
	 * Test stream_preview_image function with invalid post ID.
	 */
	public function test_stream_preview_image_invalid_id() {
		$result = stream_preview_image( 0 );

		$this->assertFalse( $result );
	}

	/**
	 * Creates an attachment.
	 *
	 * @return WP_Post
	 */
	private function make_attachment( $with_metadata = true ) {
		$uniq_id = uniqid( 'crop-image-ajax-action-test-' );

		$test_file        = DIR_TESTDATA . '/images/test-image.jpg';
		$upload_directory = wp_upload_dir();
		$uploaded_file    = $upload_directory['path'] . '/' . $uniq_id . '.jpg';
		$filesystem       = new WP_Filesystem_Direct( true );
		$filesystem->copy( $test_file, $uploaded_file );

		$attachment_data = array(
			'file' => $uploaded_file,
			'type' => 'image/jpg',
			'url'  => 'http://localhost/foo.jpg',
		);

		$attachment_id = $this->_make_attachment( $attachment_data );
		$post_data     = array(
			'ID'           => $attachment_id,
			'post_title'   => $with_metadata ? 'Title ' . $uniq_id : '',
			'post_content' => $with_metadata ? 'Description ' . $uniq_id : '',
			'context'      => 'custom-logo',
			'post_excerpt' => $with_metadata ? 'Caption ' . $uniq_id : '',
		);

		// Update the post because _make_attachment method doesn't support these arguments.
		wp_update_post( $post_data );

		if ( $with_metadata ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( 'Alt ' . $uniq_id ) );
		}

		return get_post( $attachment_id );
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
