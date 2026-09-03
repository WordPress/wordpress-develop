<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_page() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_page
 */
class Tests_wp_ajax_delete_page extends WP_Ajax_UnitTestCase {

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
		if ( isset( $_POST['action'] ) && 'delete-page' === $_POST['action'] ) {
			wp_ajax_delete_page( 'delete-page' );
		}
	}

	/**
	 * Tests successful page deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_success(): void {
		$factory = self::factory();
		wp_set_current_user( self::$admin_id );

		$page_id = $factory->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );

		$_POST = array(
			'action'      => 'delete-page',
			'id'          => $page_id,
			'_ajax_nonce' => wp_create_nonce( "delete-page_$page_id" ),
		);

		try {
			$this->_handleAjax( 'delete-page' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertNull( get_post( $page_id ), 'Page should be deleted.' );
	}

	/**
	 * Tests page deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_invalid_nonce(): void {
		$factory = self::factory();
		wp_set_current_user( self::$admin_id );

		$page_id = $factory->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );

		$_POST = array(
			'action'      => 'delete-page',
			'id'          => $page_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete-page' );
	}

	/**
	 * Tests page deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_insufficient_permissions(): void {
		$factory = self::factory();
		wp_set_current_user( self::$subscriber_id );

		$page_id = $factory->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $page_id );

		$_POST = array(
			'action'      => 'delete-page',
			'id'          => $page_id,
			'_ajax_nonce' => wp_create_nonce( "delete-page_$page_id" ),
		);

		try {
			$this->_handleAjax( 'delete-page' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotNull( get_post( $page_id ), 'Page should NOT be deleted.' );
	}

	/**
	 * Tests page deletion with non-existent ID.
	 *
	 * @ticket 65252
	 */
	public function test_delete_page_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$page_id = 99999;

		$_POST = array(
			'action'      => 'delete-page',
			'id'          => $page_id,
			'_ajax_nonce' => wp_create_nonce( "delete-page_$page_id" ),
		);

		try {
			$this->_handleAjax( 'delete-page' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for non-existent page (permission check fails first).' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 for non-existent page (permission check fails first).' );
		}
	}
}
