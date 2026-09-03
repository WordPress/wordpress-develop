<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_imgedit_preview() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_imgedit_preview
 */
class Tests_wp_ajax_imgedit_preview extends WP_Ajax_UnitTestCase {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/images/canola.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * Tests image editor preview via AJAX.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview(): void {
		// Mock the user to allow the request.
		$this->_setRole( 'administrator' );

		$_GET['postid']      = self::$attachment_id;
		$_GET['_ajax_nonce'] = wp_create_nonce( 'image_editor-' . self::$attachment_id );

		// Make the request.
		try {
			$this->_handleAjax( 'imgedit_preview' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			// Expected exception.
			$this->_last_response = (string) $e->getMessage();
		}

		// Since stream_preview_image() calls wp_stream_image() which eventually dies,
		// and WP_Ajax_UnitTestCase catches these, we check the response.
		// However, wp_stream_image for JPEG would output binary data.
		// In test environment, it might be captured or we just ensure it didn't die with -1.
		$this->assertNotEquals( '-1', $this->_last_response, 'The AJAX request failed with -1' );
	}

	/**
	 * Tests imgedit_preview with missing post ID.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview_missing_postid(): void {
		$this->_setRole( 'administrator' );

		unset( $_GET['postid'] );
		$_GET['_ajax_nonce'] = wp_create_nonce( 'image_editor-' );

		$this->_handleAjax( 'imgedit_preview' );
		$this->assertEquals( '', $this->_last_response );
	}

	/**
	 * Tests imgedit_preview with invalid post ID.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview_invalid_postid(): void {
		$this->_setRole( 'administrator' );

		$_GET['postid']      = 99999;
		$_GET['_ajax_nonce'] = wp_create_nonce( 'image_editor-99999' );

		$this->_handleAjax( 'imgedit_preview' );
		$this->assertEquals( '', $this->_last_response );
	}

	/**
	 * Tests imgedit_preview as an unprivileged user.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview_unprivileged_user(): void {
		$this->_setRole( 'subscriber' );

		$_GET['postid']      = self::$attachment_id;
		$_GET['_ajax_nonce'] = wp_create_nonce( 'image_editor-' . self::$attachment_id );

		$this->_handleAjax( 'imgedit_preview' );
		$this->assertEquals( '', $this->_last_response );
	}

	/**
	 * Tests imgedit_preview with invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview_invalid_nonce(): void {
		$this->_setRole( 'administrator' );

		$_GET['postid']      = self::$attachment_id;
		$_GET['_ajax_nonce'] = 'invalid-nonce';

		$this->_handleAjax( 'imgedit_preview' );
		$this->assertEquals( '', $this->_last_response );
	}
}
