<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_link() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_link
 */
class Tests_wp_ajax_delete_link extends WP_Ajax_UnitTestCase {

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
		update_option( 'link_manager_enabled', 1 );
		add_action( 'admin_init', 'wp_ajax_delete_link', 1 );
	}

	/**
	 * Tests successful link deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_success(): void {
		wp_set_current_user( self::$admin_id );

		$link_id = $this->factory->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => wp_create_nonce( "delete-bookmark_$link_id" ),
		);

		try {
			$this->_handleAjax( 'delete_link' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertNull( get_bookmark( $link_id ), 'Link should be deleted.' );
	}

	/**
	 * Tests link deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$link_id = $this->factory->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete_link' );
	}

	/**
	 * Tests link deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$link_id = $this->factory->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => wp_create_nonce( "delete-bookmark_$link_id" ),
		);

		try {
			$this->_handleAjax( 'delete_link' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotNull( get_bookmark( $link_id ), 'Link should NOT be deleted.' );
	}

	/**
	 * Tests link deletion with non-existent ID.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$link_id = 99999;

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => wp_create_nonce( "delete-bookmark_$link_id" ),
		);

		try {
			$this->_handleAjax( 'delete_link' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 if link doesn\'t exist (idempotent behavior).' );
		}
	}
}
