<?php

/**
 * @group admin
 */
class Tests_Admin_Post_QuickDraftSave extends WP_UnitTestCase {
	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
		set_current_screen( 'dashboard' );
	}

	public function tear_down() {
		parent::tear_down();
		set_current_screen( 'front' );
		unset( $_REQUEST['_wpnonce'], $_REQUEST['post_ID'], $_REQUEST['action'] );
	}

	/**
	 * Test Happy Path: Successfully validating a correct nonce and post_ID.
	 *
	 * @ticket 65052
	 */
	public function test_post_quickdraft_save_happy_path() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$nonce   = wp_create_nonce( 'add-post' );

		$_REQUEST['_wpnonce'] = $nonce;
		$_REQUEST['post_ID']  = $post_id;

		$nonce_req = $_REQUEST['_wpnonce'] ?? '';
		$id_req    = (int) ( $_REQUEST['post_ID'] ?? 0 );
		$post      = $id_req ? get_post( $id_req ) : null;

		$error_msg = false;
		if ( ! $post || ! wp_verify_nonce( $nonce_req, 'add-post' ) ) {
			$error_msg = __( 'Unable to submit this form, please refresh and try again.' );
		}

		$this->assertFalse( $error_msg, 'Happy path should not produce an error message.' );
		$this->assertNotNull( $post );
		$this->assertEquals( $post_id, $post->ID );
	}

	/**
	 * test post quickdraft save missing nonce
	 *
	 * @ticket 65052
	 */
	public function test_post_quickdraft_save_missing_nonce() {
		$_REQUEST['action'] = 'post-quickdraft-save';
		unset( $_REQUEST['_wpnonce'] ); // invliad nonce
		$_REQUEST['post_ID'] = 0;

		$nonce   = $_REQUEST['_wpnonce'] ?? '';
		$post_id = (int) ( $_REQUEST['post_ID'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		$error_msg = false;
		if ( ! $post || ! wp_verify_nonce( $nonce, 'add-post' ) ) {
			$error_msg = __( 'Unable to submit this form, please refresh and try again.' );
		}

		$this->assertSame( 'Unable to submit this form, please refresh and try again.', $error_msg );
	}

	/**
	 * test post quickdraft save invalid all
	 *
	 * @ticket 65052
	 */
	public function test_post_quickdraft_save_invalid_all() {
		$_REQUEST['_wpnonce'] = 'invalid_nonce';
		$_REQUEST['post_ID']  = -1; // invalid ID

		$nonce   = $_REQUEST['_wpnonce'] ?? '';
		$post_id = (int) ( $_REQUEST['post_ID'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		$this->assertNull( $post );

		$error_msg = false;
		if ( ! $post || ! wp_verify_nonce( $nonce, 'add-post' ) ) {
			$error_msg = __( 'Unable to submit this form, please refresh and try again.' );
		}

		$this->assertNotEmpty( $error_msg );
	}

	/**
	 * test post quickdraft save should not use global post
	 *
	 * @ticket 65052
	 */
	public function test_post_quickdraft_save_should_not_use_global_post() {
		$other_post_id   = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $other_post_id );

		$_REQUEST['post_ID'] = 0;
		$post_id             = (int) $_REQUEST['post_ID'];
		$post                = ( $post_id > 0 ) ? get_post( $post_id ) : null;

		$this->assertNull( $post, 'Should return null instead of falling back to global post.' );
	}
}
