<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_post() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_post
 */
class Tests_wp_ajax_delete_post extends WP_Ajax_UnitTestCase {

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
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'admin_init', 'wp_ajax_delete_post', 1 );
	}

	/**
	 * Tests successful post deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_post_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->factory->post->create();

		// Ensure post is already in trash, so wp_delete_post() will actually delete it.
		wp_trash_post( $post_id );

		$_POST = array(
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "delete-post_$post_id" ),
		);

		try {
			$this->_handleAjax( 'delete_post' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertNull( get_post( $post_id ), 'Post should be deleted.' );
	}

	/**
	 * Tests post deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_post_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = $this->factory->post->create();

		$_POST = array(
			'id'          => $post_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete_post' );
	}

	/**
	 * Tests post deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_post_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = $this->factory->post->create();

		$_POST = array(
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "delete-post_$post_id" ),
		);

		try {
			$this->_handleAjax( 'delete_post' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotNull( get_post( $post_id ), 'Post should NOT be deleted.' );
	}

	/**
	 * Tests post deletion with non-existent ID.
	 *
	 * @ticket 65252
	 */
	public function test_delete_post_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = 99999;

		$_POST = array(
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "delete-post_$post_id" ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete_post' );
	}
}
