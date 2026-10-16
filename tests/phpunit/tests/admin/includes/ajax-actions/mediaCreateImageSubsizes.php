<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_media_create_image_subsizes() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.3.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_media_create_image_subsizes
 */
class Tests_wp_ajax_media_create_image_subsizes extends WP_Ajax_UnitTestCase {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	protected static $attachment_id;

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
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$attachment_id = $factory->attachment->create_object(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_media-create-image-subsizes', 'wp_ajax_media_create_image_subsizes', 1 );
	}

	/**
	 * Tests failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_media_create_image_subsizes_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'media-create-image-subsizes',
			'_ajax_nonce' => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
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
	public function test_media_create_image_subsizes_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST = array(
			'action'      => 'media-create-image-subsizes',
			'_ajax_nonce' => wp_create_nonce( 'media-form' ),
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you are not allowed to upload files.', $response['data']['message'] );
	}

	/**
	 * Tests failure due to missing attachment_id.
	 *
	 * @ticket 65252
	 */
	public function test_media_create_image_subsizes_missing_attachment_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'media-create-image-subsizes',
			'_ajax_nonce' => wp_create_nonce( 'media-form' ),
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Upload failed. Please reload and try again.', $response['data']['message'] );
	}

	/**
	 * Tests successful sub-sizes creation with legacy support.
	 *
	 * @ticket 65252
	 */
	public function test_media_create_image_subsizes_legacy_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'          => 'media-create-image-subsizes',
			'attachment_id'   => self::$attachment_id,
			'_legacy_support' => 1,
			'_ajax_nonce'     => wp_create_nonce( 'media-form' ),
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertSame( self::$attachment_id, $response['data']['id'] );
	}

	/**
	 * Tests successful sub-sizes creation with full response.
	 *
	 * @ticket 65252
	 */
	public function test_media_create_image_subsizes_full_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'        => 'media-create-image-subsizes',
			'attachment_id' => self::$attachment_id,
			'_ajax_nonce'   => wp_create_nonce( 'media-form' ),
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertSame( self::$attachment_id, $response['data']['id'] );
		$this->assertArrayHasKey( 'sizes', $response['data'] );
	}

	/**
	 * Tests failed upload cleanup.
	 *
	 * @ticket 65252
	 */
	public function test_media_create_image_subsizes_cleanup_success(): void {
		wp_set_current_user( self::$admin_id );

		// Create a fresh attachment for cleanup.
		$attachment_id = self::factory()->attachment->create_object(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$_POST = array(
			'action'                    => 'media-create-image-subsizes',
			'attachment_id'             => $attachment_id,
			'_wp_upload_failed_cleanup' => 1,
			'_ajax_nonce'               => wp_create_nonce( 'media-form' ),
		);

		try {
			$this->_handleAjax( 'media-create-image-subsizes' );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertNull( get_post( $attachment_id ), 'Attachment should be deleted' );
	}
}
