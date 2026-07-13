<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests saving the Media Library infinite scrolling preference.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_media_library_infinite_scrolling
 */
class Tests_Ajax_wpAjaxSaveMediaLibraryInfiniteScrolling extends WP_Ajax_UnitTestCase {

	/**
	 * Tests disabling infinite scrolling.
	 *
	 * @ticket 65564
	 */
	public function test_disable_infinite_scrolling() {
		$this->_setRole( 'administrator' );

		$response = $this->make_ajax_request( 'false' );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['infiniteScrolling'] );
		$this->assertFalse( $response['data']['overridden'] );
		$this->assertSame( 'false', get_user_meta( get_current_user_id(), 'infinite_scrolling', true ) );
	}

	/**
	 * Tests enabling infinite scrolling.
	 *
	 * @ticket 65564
	 */
	public function test_enable_infinite_scrolling() {
		$this->_setRole( 'administrator' );
		update_user_meta( get_current_user_id(), 'infinite_scrolling', 'false' );

		$response = $this->make_ajax_request( 'true' );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['infiniteScrolling'] );
		$this->assertFalse( $response['data']['overridden'] );
		$this->assertSame( 'true', get_user_meta( get_current_user_id(), 'infinite_scrolling', true ) );
	}

	/**
	 * Tests that the filter takes precedence over the saved preference.
	 *
	 * @ticket 65564
	 */
	public function test_filter_overrides_saved_preference() {
		$this->_setRole( 'administrator' );
		add_filter( 'media_library_infinite_scrolling', '__return_true' );

		$response = $this->make_ajax_request( 'false' );

		remove_filter( 'media_library_infinite_scrolling', '__return_true' );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['infiniteScrolling'] );
		$this->assertTrue( $response['data']['overridden'] );
		$this->assertSame( 'false', get_user_meta( get_current_user_id(), 'infinite_scrolling', true ) );
	}

	/**
	 * Tests that users who cannot upload files cannot update the preference.
	 *
	 * @ticket 65564
	 */
	public function test_user_without_upload_files_capability_cannot_update_preference() {
		$this->_setRole( 'subscriber' );
		$previous_setting = get_user_meta( get_current_user_id(), 'infinite_scrolling', true );

		$response = $this->make_ajax_request( 'false' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( $previous_setting, get_user_meta( get_current_user_id(), 'infinite_scrolling', true ) );
	}

	/**
	 * Tests that invalid values do not update the preference.
	 *
	 * @ticket 65564
	 */
	public function test_invalid_value_cannot_update_preference() {
		$this->_setRole( 'administrator' );
		$previous_setting = get_user_meta( get_current_user_id(), 'infinite_scrolling', true );

		$response = $this->make_ajax_request( array( 'false' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( $previous_setting, get_user_meta( get_current_user_id(), 'infinite_scrolling', true ) );
	}

	/**
	 * Makes the Ajax request and returns the decoded response.
	 *
	 * @param string|array $infinite_scrolling The requested preference value.
	 * @return array The decoded Ajax response.
	 */
	private function make_ajax_request( $infinite_scrolling ) {
		$_POST['nonce']              = wp_create_nonce( 'save-media-library-infinite-scrolling' );
		$_POST['infinite_scrolling'] = $infinite_scrolling;

		try {
			$this->_handleAjax( 'save-media-library-infinite-scrolling' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}
}
