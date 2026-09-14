<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_fullscreen_save_post AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_wp_fullscreen_save_post
 */
class Tests_wp_ajax_wp_fullscreen_save_post extends WP_Ajax_UnitTestCase {

	/**
	 * @covers ::wp_ajax_wp_fullscreen_save_post
	 * @ticket 65252
	 */
	public function test_wp_ajax_wp_fullscreen_save_post_success() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Original Title',
				'post_content' => 'Original Content',
			)
		);

		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_ID'      => $post_id,
			'post_title'   => 'Updated Title',
			'post_content' => 'Updated Content',
			'_wpnonce'     => wp_create_nonce( 'update-post_' . $post_id ),
		);

		try {
			$this->_handleAjax( 'wp-fullscreen-save-post' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'last_edited', $response['data'] );

		$post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $post->post_title );
		$this->assertSame( 'Updated Content', $post->post_content );
	}

	/**
	 * @covers ::wp_ajax_wp_fullscreen_save_post
	 * @ticket 65252
	 */
	public function test_wp_ajax_wp_fullscreen_save_post_invalid_nonce() {
		$post_id = self::factory()->post->create();

		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_ID'  => $post_id,
			'_wpnonce' => 'invalid-nonce',
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'wp-fullscreen-save-post' );
	}
}
