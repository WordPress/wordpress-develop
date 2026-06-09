<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing dismiss_wp_pointer AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_dismiss_wp_pointer
 */
class Tests_wp_ajax_dismiss_wp_pointer extends WP_Ajax_UnitTestCase {

	/**
	 * @covers ::wp_ajax_dismiss_wp_pointer
	 * @ticket 65252
	 */
	public function test_wp_ajax_dismiss_wp_pointer_success() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$pointer = 'test-pointer';

		$_POST = array(
			'pointer' => $pointer,
		);

		try {
			$this->_handleAjax( 'dismiss-wp-pointer' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$dismissed = explode( ',', (string) get_user_meta( $user_id, 'dismissed_wp_pointers', true ) );
		$this->assertContains( $pointer, $dismissed );
	}

	/**
	 * @covers ::wp_ajax_dismiss_wp_pointer
	 * @ticket 65252
	 */
	public function test_wp_ajax_dismiss_wp_pointer_invalid_pointer() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST = array(
			'pointer' => 'invalid pointer with spaces',
		);

		try {
			$this->_handleAjax( 'dismiss-wp-pointer' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		}

		$dismissed = get_user_meta( $user_id, 'dismissed_wp_pointers', true );
		$this->assertEmpty( $dismissed );
	}

	/**
	 * @covers ::wp_ajax_dismiss_wp_pointer
	 * @ticket 65252
	 */
	public function test_wp_ajax_dismiss_wp_pointer_already_dismissed() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$pointer = 'test-pointer';
		update_user_meta( $user_id, 'dismissed_wp_pointers', $pointer );

		$_POST = array(
			'pointer' => $pointer,
		);

		try {
			$this->_handleAjax( 'dismiss-wp-pointer' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		}

		$dismissed = get_user_meta( $user_id, 'dismissed_wp_pointers', true );
		$this->assertSame( $pointer, $dismissed );
	}
}
