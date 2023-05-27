<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax attachment handling.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_send_attachment_to_editor
 */
class Tests_Ajax_wpAjaxSendAttachmentToEditor extends WP_Ajax_UnitTestCase {

	/**
	 * @ticket 36578
	 *
	 * @covers ::get_image_send_to_editor
	 */
	public function test_wp_ajax_send_attachment_to_editor_should_return_an_image() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		$filename = DIR_TESTDATA . '/images/canola.jpg';
		$contents = file_get_contents( $filename );

		$upload     = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$attachment = $this->_make_attachment( $upload );

		// Set up a default request.
		$_POST['nonce']      = wp_create_nonce( 'media-send-to-editor' );
		$_POST['html']       = 'Bar Baz';
		$_POST['post_id']    = 0;
		$_POST['attachment'] = array(
			'id'         => $attachment,
			'align'      => 'left',
			'image-size' => 'large',
			'image_alt'  => 'Foo bar',
			'url'        => 'http://example.com/',
		);

		// Make the request.
		try {
			$this->_handleAjax( 'send-attachment-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = get_image_send_to_editor( $attachment, '', '', 'left', 'http://example.com/', false, 'large', 'Foo bar' );

		// Ensure everything is correct.
		$this->assertTrue( $response['success'] );
		$this->assertSame( $expected, $response['data'] );
	}

	/**
	 * @ticket 36578
	 * @group ms-excluded
	 */
	public function test_wp_ajax_send_attachment_to_editor_should_return_a_link() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		$filename = DIR_TESTDATA . '/formatting/entities.txt';
		$contents = file_get_contents( $filename );

		$upload     = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$attachment = $this->_make_attachment( $upload );

		// Set up a default request.
		$_POST['nonce']      = wp_create_nonce( 'media-send-to-editor' );
		$_POST['html']       = 'Bar Baz';
		$_POST['post_id']    = 0;
		$_POST['attachment'] = array(
			'id'         => $attachment,
			'post_title' => 'Foo bar',
			'url'        => get_attachment_link( $attachment ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'send-attachment-to-editor' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$expected = sprintf(
			'<a href="%s" rel="attachment wp-att-%d">Foo bar</a>',
			get_attachment_link( $attachment ),
			$attachment
		);

		// Ensure everything is correct.
		$this->assertTrue( $response['success'] );
		$this->assertSame( $expected, $response['data'] );
	}

	public function test_wp_ajax_set_attachment_thumbnail_success() {
		// Become an administrator.
		$post    = $_POST;
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'user_36578_administrator',
				'user_email' => 'user_36578_administrator@example.com',
			)
		);
		wp_set_current_user( $user_id );
		$_POST = array_merge( $_POST, $post );

		// Upload the attachment itself.
		$filename = DIR_TESTDATA . '/uploads/small-audio.mp3';
		$contents = file_get_contents( $filename );

		$upload     = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$attachment = $this->_make_attachment( $upload );

		// Upload the thumbnail.
		$filename = DIR_TESTDATA . '/images/waffles.jpg';
		$contents = file_get_contents( $filename );

		$upload    = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$thumbnail = $this->_make_attachment( $upload );

		// Set up a default request.
		$_POST['_ajax_nonce']  = wp_create_nonce( 'set-attachment-thumbnail' );
		$_POST['thumbnail_id'] = $thumbnail;
		$_POST['urls']         = array( wp_get_attachment_url( $attachment ) );

		// Make the request.
		try {
			$this->_handleAjax( 'set-attachment-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		// Ensure everything is correct.
		$this->assertTrue( $response['success'] );
	}

	public function test_wp_ajax_set_attachment_thumbnail_missing_nonce() {
		// Become an administrator.
		$post    = $_POST;
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'user_36578_administrator',
				'user_email' => 'user_36578_administrator@example.com',
			)
		);
		wp_set_current_user( $user_id );
		$_POST = array_merge( $_POST, $post );

		// Upload the attachment itself.
		$filename = DIR_TESTDATA . '/uploads/small-audio.mp3';
		$contents = file_get_contents( $filename );

		$upload     = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$attachment = $this->_make_attachment( $upload );

		// Upload the thumbnail.
		$filename = DIR_TESTDATA . '/images/waffles.jpg';
		$contents = file_get_contents( $filename );

		$upload    = wp_upload_bits( wp_basename( $filename ), null, $contents );
		$thumbnail = $this->_make_attachment( $upload );

		// Set up a default request.
		$_POST['thumbnail_id'] = $thumbnail;
		$_POST['urls']         = array( wp_get_attachment_url( $attachment ) );

		// Make the request.
		try {
			$this->_handleAjax( 'set-attachment-thumbnail' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		// Check that success is false without sending nonce.
		$this->assertFalse( $response['success'] );
	}
}
