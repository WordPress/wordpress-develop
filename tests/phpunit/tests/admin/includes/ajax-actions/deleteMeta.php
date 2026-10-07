<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_meta() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_meta
 */
class Tests_wp_ajax_delete_meta extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Post ID to attach meta to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$post_id       = $factory->post->create();
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'admin_init', 'wp_ajax_delete_meta', 1 );
	}

	/**
	 * Tests successful meta deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_meta_success(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key', 'test_value' );

		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => wp_create_nonce( "delete-meta_$meta_id" ),
		);

		try {
			$this->_handleAjax( 'delete_meta' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertFalse( get_metadata_by_mid( 'post', $meta_id ), 'Meta should be deleted.' );
	}

	/**
	 * Tests meta deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_meta_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key_nonce', 'test_value' );

		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete_meta' );
	}

	/**
	 * Tests meta deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_meta_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$meta_id = add_post_meta( self::$post_id, 'test_meta_key_perms', 'test_value' );

		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => wp_create_nonce( "delete-meta_$meta_id" ),
		);

		try {
			$this->_handleAjax( 'delete_meta' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ), 'Meta should NOT be deleted.' );
	}

	/**
	 * Tests meta deletion failure for protected meta.
	 *
	 * @ticket 65252
	 */
	public function test_delete_meta_protected(): void {
		wp_set_current_user( self::$admin_id );

		// Meta keys starting with underscore are protected.
		$meta_id = add_post_meta( self::$post_id, '_protected_meta_key', 'test_value' );

		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => wp_create_nonce( "delete-meta_$meta_id" ),
		);

		try {
			$this->_handleAjax( 'delete_meta' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for protected meta.' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for protected meta.' );
		}

		$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ), 'Protected meta should NOT be deleted.' );
	}

	/**
	 * Tests meta deletion with non-existent ID.
	 *
	 * @ticket 65252
	 */
	public function test_delete_meta_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$meta_id = 99999;

		$_POST = array(
			'id'          => $meta_id,
			'_ajax_nonce' => wp_create_nonce( "delete-meta_$meta_id" ),
		);

		try {
			$this->_handleAjax( 'delete_meta' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 if meta doesn\'t exist.' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 if meta doesn\'t exist.' );
		}
	}
}
