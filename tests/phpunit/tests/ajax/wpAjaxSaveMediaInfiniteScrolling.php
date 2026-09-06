<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing the AJAX handler that saves the Media Library infinite scrolling preference.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_media_infinite_scrolling
 */
class Tests_Ajax_wpAjaxSaveMediaInfiniteScrolling extends WP_Ajax_UnitTestCase {

	/**
	 * Tests that the preference is stored in the same personal option the profile screen uses.
	 *
	 * @ticket 65775
	 *
	 * @dataProvider data_save_media_infinite_scrolling
	 *
	 * @param string $sent     The value posted for the `infiniteScrolling` parameter.
	 * @param string $expected The expected value of the `infinite_scrolling` user meta.
	 */
	public function test_save_media_infinite_scrolling( $sent, $expected ) {
		$this->_setRole( 'administrator' );

		$_POST = array(
			'nonce'             => wp_create_nonce( 'save-media-infinite-scrolling' ),
			'infiniteScrolling' => $sent,
		);

		try {
			$this->_handleAjax( 'save-media-infinite-scrolling' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'The request should have succeeded.' );
		$this->assertSame(
			$expected,
			get_user_meta( get_current_user_id(), 'infinite_scrolling', true ),
			'The personal option should match the posted preference.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_save_media_infinite_scrolling() {
		return array(
			'enabled'  => array( 'true', 'true' ),
			'disabled' => array( 'false', 'false' ),
		);
	}

	/**
	 * Tests that the request fails when the nonce is invalid.
	 *
	 * @ticket 65775
	 */
	public function test_save_media_infinite_scrolling_requires_a_valid_nonce() {
		$this->_setRole( 'administrator' );

		$_POST = array(
			'nonce'             => 'invalid-nonce',
			'infiniteScrolling' => 'false',
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-media-infinite-scrolling' );
	}

	/**
	 * Tests that the preference is left alone when no value is posted.
	 *
	 * @ticket 65775
	 */
	public function test_save_media_infinite_scrolling_requires_a_value() {
		$this->_setRole( 'administrator' );

		update_user_meta( get_current_user_id(), 'infinite_scrolling', 'true' );

		$_POST = array(
			'nonce' => wp_create_nonce( 'save-media-infinite-scrolling' ),
		);

		try {
			$this->_handleAjax( 'save-media-infinite-scrolling' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'The request should not have succeeded.' );
		$this->assertSame(
			'true',
			get_user_meta( get_current_user_id(), 'infinite_scrolling', true ),
			'The personal option should not have changed.'
		);
	}
}
