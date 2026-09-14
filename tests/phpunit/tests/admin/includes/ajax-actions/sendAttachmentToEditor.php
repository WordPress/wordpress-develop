<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_send_attachment_to_editor() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_send_attachment_to_editor
 */
class Tests_wp_ajax_send_attachment_to_editor extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Attachment ID.
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
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id  = $factory->post->create();

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
				'post_content'   => 'Test Description',
				'post_excerpt'   => 'Test Caption',
			)
		);
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_send-attachment-to-editor', 'wp_ajax_send_attachment_to_editor', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		parent::tear_down();
	}

	/**
	 * Returns our custom die handler.
	 *
	 * @return callable
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Custom die handler that throws an exception.
	 *
	 * @param string|WP_Error $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		if ( '-1' === $this->_last_response || ( is_int( $message ) && -1 === $message ) ) {
			throw new WPAjaxDieStopException( $this->_last_response );
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests success for wp_ajax_send_attachment_to_editor() with an image.
	 *
	 * @ticket 65252
	 */
	public function test_send_attachment_to_editor_image_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']      = wp_create_nonce( 'media-send-to-editor' );
		$_POST['post_id']    = self::$post_id;
		$_POST['attachment'] = array(
			'id'           => self::$attachment_id,
			'align'        => 'left',
			'image-size'   => 'medium',
			'image_alt'    => 'Custom Alt',
			'post_excerpt' => 'Custom Caption',
			'url'          => 'http://example.com/test.jpg',
		);

		try {
			$this->_handleAjax( 'send-attachment-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'Custom Alt', $response['data'], 'HTML should contain custom alt text' );
		$this->assertStringContainsString( 'Custom Caption', $response['data'], 'HTML should contain custom caption' );
		$this->assertStringContainsString( 'alignleft', $response['data'], 'HTML should contain correct alignment class' );

		// Verify that the attachment was attached to the post.
		$attachment = get_post( self::$attachment_id );
		$this->assertEquals( self::$post_id, $attachment->post_parent, 'Attachment should be attached to the post' );
	}

	/**
	 * Tests success for wp_ajax_send_attachment_to_editor() with a non-image file.
	 *
	 * @ticket 65252
	 */
	public function test_send_attachment_to_editor_file_success(): void {
		wp_set_current_user( self::$admin_id );

		$file_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.pdf',
				'post_parent'    => 0,
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Test Document',
			)
		);

		$_POST['nonce']      = wp_create_nonce( 'media-send-to-editor' );
		$_POST['post_id']    = self::$post_id;
		$_POST['attachment'] = array(
			'id'         => $file_id,
			'post_title' => 'Custom Link Text',
			'url'        => 'http://example.com/test.pdf',
		);

		try {
			$this->_handleAjax( 'send-attachment-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'Custom Link Text', $response['data'], 'HTML should contain custom link text' );
		$this->assertStringContainsString( 'http://example.com/test.pdf', $response['data'], 'HTML should contain the correct URL' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_send_attachment_to_editor().
	 *
	 * @ticket 65252
	 */
	public function test_send_attachment_to_editor_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce'] = 'invalid-nonce';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'send-attachment-to-editor' );
	}

	/**
	 * Tests failure with missing attachment for wp_ajax_send_attachment_to_editor().
	 *
	 * @ticket 65252
	 */
	public function test_send_attachment_to_editor_missing_attachment(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['nonce']      = wp_create_nonce( 'media-send-to-editor' );
		$_POST['attachment'] = array(
			'id' => 999999, // Non-existent ID.
		);

		try {
			$this->_handleAjax( 'send-attachment-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
