<?php
/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing the Ajax handler for saving the Media Library settings.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.1.0
 *
 * @ticket 65775
 *
 * @group ajax
 *
 * @covers ::wp_ajax_set_media_library_settings
 */
class Tests_Ajax_wpAjaxSetMediaLibrarySettings extends WP_Ajax_UnitTestCase {

	/**
	 * The request must be rejected when the nonce is missing.
	 *
	 * @ticket 65775
	 */
	public function test_missing_nonce() {
		$this->_setRole( 'administrator' );

		$_POST['infinite_scrolling'] = 'false';

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'set-media-library-settings' );
	}

	/**
	 * The request must be rejected when the nonce is invalid.
	 *
	 * @ticket 65775
	 */
	public function test_invalid_nonce() {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_nonce']        = 'invalid-nonce';
		$_POST['infinite_scrolling'] = 'false';

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'set-media-library-settings' );
	}

	/**
	 * A user without the `upload_files` capability must not be able to save the setting.
	 *
	 * @ticket 65775
	 */
	public function test_missing_capability() {
		// The nonce is tied to the current user, so the role has to be set first.
		$this->_setRole( 'subscriber' );

		$_POST['_ajax_nonce']        = wp_create_nonce( 'media-library-settings' );
		$_POST['infinite_scrolling'] = 'false';

		// Make the request.
		try {
			$this->_handleAjax( 'set-media-library-settings' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'The request should not have succeeded.' );
		$this->assertSame(
			'Sorry, you are not allowed to edit this setting.',
			$response['data']['message'],
			'The response should mention the missing capability.'
		);
		$this->assertSame(
			'true',
			get_user_meta( get_current_user_id(), 'infinite_scrolling', true ),
			'The preference should have been left at the default set by wp_insert_user().'
		);
	}

	/**
	 * The setting must not be saved when the value is missing from the request.
	 *
	 * @ticket 65775
	 */
	public function test_missing_infinite_scrolling_value() {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'media-library-settings' );

		// Make the request.
		try {
			$this->_handleAjax( 'set-media-library-settings' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'The request should not have succeeded.' );
		$this->assertSame(
			'The setting could not be saved.',
			$response['data']['message'],
			'The response should mention that the setting was not saved.'
		);
		$this->assertSame(
			'true',
			get_user_meta( get_current_user_id(), 'infinite_scrolling', true ),
			'The preference should have been left at the default set by wp_insert_user().'
		);
	}

	/**
	 * The preference must be stored as a string and reported back as a boolean.
	 *
	 * @ticket 65775
	 *
	 * @dataProvider data_infinite_scrolling_values
	 *
	 * @param string $value    The submitted value.
	 * @param string $expected The value expected in user meta.
	 */
	public function test_saves_the_preference( $value, $expected ) {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_nonce']        = wp_create_nonce( 'media-library-settings' );
		$_POST['infinite_scrolling'] = $value;

		// Make the request.
		try {
			$this->_handleAjax( 'set-media-library-settings' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'The request should have succeeded.' );
		$this->assertSame(
			'true' === $expected,
			$response['data']['infiniteScrolling'],
			'The response should report the stored value as a boolean.'
		);
		$this->assertSame(
			$expected,
			get_user_meta( get_current_user_id(), 'infinite_scrolling', true ),
			'The preference should have been stored as a string.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[] {
	 *     @type string $value    The submitted value.
	 *     @type string $expected The value expected in user meta.
	 * }
	 */
	public function data_infinite_scrolling_values() {
		return array(
			'enabled'          => array( 'true', 'true' ),
			'disabled'         => array( 'false', 'false' ),
			'an unknown value' => array( 'nope', 'false' ),
			'an empty string'  => array( '', 'false' ),
			'the string 1'     => array( '1', 'false' ),
		);
	}

	/**
	 * An existing preference must be overwritten rather than duplicated.
	 *
	 * @ticket 65775
	 */
	public function test_overwrites_an_existing_preference() {
		$this->_setRole( 'administrator' );

		$user_id = get_current_user_id();

		update_user_meta( $user_id, 'infinite_scrolling', 'false' );

		$_POST['_ajax_nonce']        = wp_create_nonce( 'media-library-settings' );
		$_POST['infinite_scrolling'] = 'true';

		// Make the request.
		try {
			$this->_handleAjax( 'set-media-library-settings' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'The request should have succeeded.' );
		$this->assertSame(
			array( 'true' ),
			get_user_meta( $user_id, 'infinite_scrolling' ),
			'The preference should have been overwritten.'
		);
	}

	/**
	 * The preference must be stored as plain user meta, not as a site prefixed option.
	 *
	 * `wp_enqueue_media()` reads it with `get_user_option()`, which falls back to the
	 * unprefixed key, so the profile screen and the dialog share a single value.
	 *
	 * @ticket 65775
	 */
	public function test_stores_the_preference_without_a_site_prefix() {
		global $wpdb;

		$this->_setRole( 'administrator' );

		$user_id = get_current_user_id();

		$_POST['_ajax_nonce']        = wp_create_nonce( 'media-library-settings' );
		$_POST['infinite_scrolling'] = 'false';

		// Make the request.
		try {
			$this->_handleAjax( 'set-media-library-settings' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame(
			'',
			get_user_meta( $user_id, $wpdb->get_blog_prefix() . 'infinite_scrolling', true ),
			'The preference should not have been stored under a prefixed key.'
		);
		$this->assertSame(
			'false',
			get_user_option( 'infinite_scrolling', $user_id ),
			'The preference should be readable with get_user_option().'
		);
	}
}
