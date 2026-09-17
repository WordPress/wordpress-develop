<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_trash_post() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_trash_post
 */
class Tests_wp_ajax_trash_post extends WP_Ajax_UnitTestCase {

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
		add_action( 'admin_init', array( $this, 'hook_ajax_handler' ), 1 );
	}

	/**
	 * Hooks the AJAX handler to admin_init.
	 */
	public function hook_ajax_handler(): void {
		if ( isset( $_POST['action'] ) && 'trash-post' === $_POST['action'] ) {
			wp_ajax_trash_post( 'trash-post' );
		}
	}

	/**
	 * Tests successful post trashing.
	 *
	 * @ticket 65252
	 */
	public function test_trash_post_success(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();

		$_POST = array(
			'action'      => 'trash-post',
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "trash-post_$post_id" ),
		);

		try {
			$this->_handleAjax( 'trash-post' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertSame( 'trash', get_post_status( $post_id ), 'Post status should be trash.' );
	}

	/**
	 * Tests post trashing failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_trash_post_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();

		$_POST = array(
			'action'      => 'trash-post',
			'id'          => $post_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'trash-post' );
	}

	/**
	 * Tests post trashing failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_trash_post_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create();

		$_POST = array(
			'action'      => 'trash-post',
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "trash-post_$post_id" ),
		);

		try {
			$this->_handleAjax( 'trash-post' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotSame( 'trash', get_post_status( $post_id ), 'Post should NOT be trashed.' );
	}

	/**
	 * Tests post trashing with non-existent ID.
	 *
	 * @ticket 65252
	 */
	public function test_trash_post_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = 99999;

		$_POST = array(
			'action'      => 'trash-post',
			'id'          => $post_id,
			'_ajax_nonce' => wp_create_nonce( "trash-post_$post_id" ),
		);

		try {
			$this->_handleAjax( 'trash-post' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for non-existent post (permission check fails first).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for non-existent post (permission check fails first).' );
		}
	}
}
