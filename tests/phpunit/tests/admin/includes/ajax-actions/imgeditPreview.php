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
 *
 * @group ajax
 *
 * @covers ::wp_ajax_imgedit_preview
 */
class Tests_Admin_Includes_AjaxActions_imgeditPreview extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Image attachment ID, owned by the administrator.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/images/canola.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$admin_id,
			)
		);
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) ) ) {
			$this->markTestSkipped( 'No image editor supports JPEG.' );
		}
	}

	/**
	 * Builds a preview request for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function set_up_request( int $attachment_id ): void {
		$_GET = array(
			'postid'      => $attachment_id,
			'_ajax_nonce' => wp_create_nonce( "image_editor-{$attachment_id}" ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_streams_scaled_jpeg(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_up_request( self::$attachment_id );

		$image = getimagesizefromstring( $this->get_ajax_response( 'imgedit-preview' ) );

		// canola.jpg is 640x480 and previews are scaled to fit within 600x600.
		$this->assertIsArray( $image, 'The response is not an image.' );
		$this->assertSame( 'image/jpeg', $image['mime'] );
		$this->assertSame( array( 600, 450 ), array( $image[0], $image[1] ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_applies_history(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_up_request( self::$attachment_id );
		$_GET['history'] = wp_json_encode( array( array( 'r' => 90 ) ) );

		$image = getimagesizefromstring( $this->get_ajax_response( 'imgedit-preview' ) );

		$this->assertIsArray( $image, 'The response is not an image.' );
		$this->assertSame( array( 450, 600 ), array( $image[0], $image[1] ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_rejects_user_who_cannot_edit_attachment(): void {
		// Authors can only edit their own attachments.
		wp_set_current_user( self::$author_id );
		$this->set_up_request( self::$attachment_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'imgedit-preview' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_allows_author_on_own_attachment(): void {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/images/canola.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$author_id,
			)
		);

		wp_set_current_user( self::$author_id );
		$this->set_up_request( $attachment_id );

		$image = getimagesizefromstring( $this->get_ajax_response( 'imgedit-preview' ) );

		$this->assertIsArray( $image, 'The response is not an image.' );
		$this->assertSame( 'image/jpeg', $image['mime'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_rejects_missing_post_id(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_up_request( 0 );

		$this->assertSame( '-1', $this->get_ajax_response( 'imgedit-preview' ) );
	}

	/**
	 * A post that doesn't exist fails the capability check, so this covers that branch.
	 *
	 * @ticket 65252
	 */
	public function test_imgedit_preview_rejects_nonexistent_post_through_capability_check(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_up_request( PHP_INT_MAX );

		$this->assertSame( '-1', $this->get_ajax_response( 'imgedit-preview' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_up_request( self::$attachment_id );
		$_GET['_ajax_nonce'] = 'invalid-nonce';

		$this->assertSame( '-1', $this->get_ajax_response( 'imgedit-preview' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_imgedit_preview_rejects_attachment_that_is_not_an_image(): void {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/uploads/test.csv',
				'post_mime_type' => 'text/csv',
			)
		);

		wp_set_current_user( self::$admin_id );
		$this->set_up_request( $attachment_id );

		$this->assertSame( '-1', $this->get_ajax_response( 'imgedit-preview' ) );
	}
}
