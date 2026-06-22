<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_attachment() and wp_ajax_save_attachment_compat() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_attachment
 * @covers ::wp_ajax_save_attachment_compat
 */
class Tests_wp_ajax_save_attachment extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

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

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Original Title',
				'post_content'   => 'Original Description',
				'post_excerpt'   => 'Original Caption',
			)
		);
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_save-attachment', 'wp_ajax_save_attachment', 1 );
		add_action( 'wp_ajax_save-attachment-compat', 'wp_ajax_save_attachment_compat', 1 );

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

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests success for wp_ajax_save_attachment().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['id']      = self::$attachment_id;
		$_POST['nonce']   = wp_create_nonce( 'update-post_' . self::$attachment_id );
		$_POST['changes'] = array(
			'title'   => 'Updated Title',
			'caption' => 'Updated Caption',
			'alt'     => 'Updated Alt Text',
		);

		try {
			$this->_handleAjax( 'save-attachment' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );

		$post = get_post( self::$attachment_id );
		$this->assertSame( 'Updated Title', $post->post_title, 'Title should be updated' );
		$this->assertSame( 'Updated Caption', $post->post_excerpt, 'Caption should be updated' );
		$this->assertSame( 'Updated Alt Text', get_post_meta( self::$attachment_id, '_wp_attachment_image_alt', true ), 'Alt text should be updated' );
	}

	/**
	 * Tests success for wp_ajax_save_attachment_compat().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_compat_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['id']          = self::$attachment_id;
		$_POST['nonce']       = wp_create_nonce( 'update-post_' . self::$attachment_id );
		$_POST['attachments'] = array(
			self::$attachment_id => array(
				'post_title'   => 'Compat Updated Title',
				'post_excerpt' => 'Compat Updated Caption',
				'post_content' => 'Compat Updated Description',
			),
		);

		// wp_ajax_save_attachment_compat() relies on filters for legacy compatibility.
		add_filter(
			'attachment_fields_to_save',
			function ( $post, $attachment_data ) {
				if ( isset( $attachment_data['post_title'] ) ) {
					$post['post_title'] = $attachment_data['post_title'];
				}
				if ( isset( $attachment_data['post_excerpt'] ) ) {
					$post['post_excerpt'] = $attachment_data['post_excerpt'];
				}
				if ( isset( $attachment_data['post_content'] ) ) {
					$post['post_content'] = $attachment_data['post_content'];
				}
				return $post;
			},
			10,
			2
		);

		try {
			$this->_handleAjax( 'save-attachment-compat' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );

		$post = get_post( self::$attachment_id );
		$this->assertSame( 'Compat Updated Title', $post->post_title, 'Title should be updated' );
		$this->assertSame( 'Compat Updated Caption', $post->post_excerpt, 'Caption should be updated' );
		$this->assertSame( 'Compat Updated Description', $post->post_content, 'Description should be updated' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_save_attachment().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['id']      = self::$attachment_id;
		$_POST['nonce']   = 'invalid-nonce';
		$_POST['changes'] = array( 'title' => 'Should fail' );

		try {
			$this->_handleAjax( 'save-attachment' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$this->assertSame( '-1', $this->_last_response );
	}

	/**
	 * Tests failure with missing ID for wp_ajax_save_attachment().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_missing_id(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['changes'] = array( 'title' => 'Should fail' );

		try {
			$this->_handleAjax( 'save-attachment' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_save_attachment().
	 *
	 * @ticket 65252
	 */
	public function test_save_attachment_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['id']      = self::$attachment_id;
		$_POST['nonce']   = wp_create_nonce( 'update-post_' . self::$attachment_id );
		$_POST['changes'] = array( 'title' => 'Should fail' );

		try {
			$this->_handleAjax( 'save-attachment' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'], 'AJAX response should be a failure' );
	}
}
