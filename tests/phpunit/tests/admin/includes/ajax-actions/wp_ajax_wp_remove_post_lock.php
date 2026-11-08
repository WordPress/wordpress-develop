<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_remove_post_lock AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_wp_remove_post_lock
 */
class Tests_wp_ajax_wp_remove_post_lock extends WP_Ajax_UnitTestCase {

	/**
	 * @covers ::wp_ajax_wp_remove_post_lock
	 * @ticket 65252
	 */
	public function test_wp_ajax_wp_remove_post_lock_success() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create();

		$lock_time   = time() - 100;
		$active_lock = $lock_time . ':' . $user_id;
		update_post_meta( $post_id, '_edit_lock', $active_lock );

		$_POST = array(
			'post_ID'          => $post_id,
			'active_post_lock' => $active_lock,
			'_ajax_nonce'      => wp_create_nonce( 'update-post_' . $post_id ),
		);

		try {
			$this->_handleAjax( 'wp-remove-post-lock' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$new_lock = get_post_meta( $post_id, '_edit_lock', true );
		$this->assertNotSame( $active_lock, $new_lock );
		$parts = explode( ':', $new_lock );
		$this->assertSame( (string) $user_id, $parts[1] );
		$this->assertLessThanOrEqual( time() - 145, (int) $parts[0] );
	}

	/**
	 * @covers ::wp_ajax_wp_remove_post_lock
	 * @ticket 65252
	 */
	public function test_wp_ajax_wp_remove_post_lock_wrong_user() {
		$user_id       = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$other_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create();

		$lock_time   = time() - 100;
		$active_lock = $lock_time . ':' . $other_user_id;
		update_post_meta( $post_id, '_edit_lock', $active_lock );

		$_POST = array(
			'post_ID'          => $post_id,
			'active_post_lock' => $active_lock,
			'_ajax_nonce'      => wp_create_nonce( 'update-post_' . $post_id ),
		);

		try {
			$this->_handleAjax( 'wp-remove-post-lock' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		}

		$this->assertSame( $active_lock, get_post_meta( $post_id, '_edit_lock', true ) );
	}
}
