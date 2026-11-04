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
		update_option( 'link_manager_enabled', 1 );
	}

	/**
	 * Tests successful link deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_success(): void {
		wp_set_current_user( self::$admin_id );

		$link_id = self::factory()->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => wp_create_nonce( "delete-bookmark_$link_id" ),
		);

		try {
			$this->_handleAjax( 'delete-link' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertSame( '1', $this->_last_response );
		$this->assertNull( get_bookmark( $link_id ), 'Link should be deleted.' );
	}

	/**
	 * Tests link deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$link_id = self::factory()->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'delete-link' );
			$this->fail( 'Expected link deletion to stop due to an invalid nonce.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}

		$this->assertNotNull( get_bookmark( $link_id ), 'Link should not be deleted when the nonce is invalid.' );
	}

	/**
	 * Tests link deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_link_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$link_id = self::factory()->bookmark->create();

		$_POST = array(
			'id'          => $link_id,
			'_ajax_nonce' => wp_create_nonce( "delete-bookmark_$link_id" ),
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete-link' );
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
			$this->_handleAjax( 'delete-link' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expect success.
			$this->_last_response = $e->getMessage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expect success.
		}

		$this->assertSame( '1', $this->_last_response );
		$this->assertNull( get_bookmark( $link_id ), 'no Link and no error.' );
	}
}
