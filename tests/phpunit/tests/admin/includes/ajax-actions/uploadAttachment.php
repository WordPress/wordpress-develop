<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_upload_attachment() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.3.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_upload_attachment
 */
class Tests_wp_ajax_upload_attachment extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_upload-attachment', 'wp_ajax_upload_attachment', 1 );

		// Force the action to be something other than wp_handle_upload to bypass the is_uploaded_file check.
		add_filter(
			'wp_handle_upload_overrides',
			function ( $overrides ) {
				$overrides['action'] = 'wp_handle_sideload';
				return $overrides;
			}
		);
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_upload_attachment_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'upload-attachment',
			'_ajax_nonce' => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'upload-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $this->_last_response );
		}
	}

	/**
	 * Tests failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_upload_attachment_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'      => 'upload-attachment',
			'_ajax_nonce' => wp_create_nonce( 'media-form' ),
		);

		$_FILES = array(
			'async-upload' => array(
				'name'     => 'canola.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => DIR_TESTDATA . '/images/canola.jpg',
				'error'    => 0,
				'size'     => filesize( DIR_TESTDATA . '/images/canola.jpg' ),
			),
		);

		// wp_ajax_upload_attachment() uses echo and wp_die() instead of wp_send_json_error().
		try {
			$this->_handleAjax( 'upload-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you are not allowed to upload files.', $response['data']['message'] );
	}
	/**
	 * Tests successful upload.
	 *
	 * @ticket 65252
	 */
	public function test_upload_attachment_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'upload-attachment',
			'_ajax_nonce' => wp_create_nonce( 'media-form' ),
		);

		$_FILES = array(
			'async-upload' => array(
				'name'     => 'canola.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => DIR_TESTDATA . '/images/canola.jpg',
				'error'    => 0,
				'size'     => filesize( DIR_TESTDATA . '/images/canola.jpg' ),
			),
		);

		// Short-circuit media_handle_upload() by filtering 'wp_handle_upload_prefilter'.
		// Wait, media_handle_upload() is a function. It calls wp_handle_upload().
		// We already tried many filters.

		// Let's use a very late filter in wp_insert_attachment or something? No.

		// The issue is that _wp_handle_upload returns an error array because is_uploaded_file fails.
		// If we use the 'wp_handle_upload_prefilter' to set 'error' to something,
		// it will go to the error handler.

		add_filter(
			'wp_handle_upload_prefilter',
			function ( $file ) {
				$file['error'] = 'mock_success';
				return $file;
			}
		);

		// We MUST ensure the error handler returns what we want.
		// The default error handler is wp_handle_upload_error which returns array('error' => $message).
		// media_handle_upload checks for isset($file['error']).

		// What if we filter 'wp_handle_upload_overrides' to change the error handler?
		add_filter(
			'wp_handle_upload_overrides',
			function ( $overrides ) {
				$overrides['upload_error_handler'] = function ( $file, $message ) {
					if ( 'mock_success' === $message ) {
						$upload_dir = wp_upload_dir();
						$new_file   = $upload_dir['path'] . '/canola.jpg';
						@copy( DIR_TESTDATA . '/images/canola.jpg', $new_file );
						return array(
							'file' => $new_file,
							'url'  => $upload_dir['url'] . '/canola.jpg',
							'type' => 'image/jpeg',
						);
					}
					return array( 'error' => $message );
				};
				return $overrides;
			},
			10
		);

		try {
			$this->_handleAjax( 'upload-attachment' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			$this->fail( 'AJAX response was not successful. Response: ' . $this->_last_response );
		}
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'canola.jpg', $response['data']['filename'] );
	}
}
