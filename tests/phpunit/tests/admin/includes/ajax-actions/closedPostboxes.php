<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_closed_postboxes() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_closed_postboxes
 */
class Tests_wp_ajax_closed_postboxes extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
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
		if ( isset( $_POST['action'] ) && 'closed-postboxes' === $_POST['action'] ) {
			wp_ajax_closed_postboxes();
		}
	}

	/**
	 * Tests successful update of closed and hidden postboxes.
	 *
	 * @ticket 65252
	 */
	public function test_closed_postboxes_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'               => 'closed-postboxes',
			'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
			'closed'               => 'postbox1,postbox2',
			'hidden'               => 'postbox3,submitdiv',
			'page'                 => 'testpage',
		);

		try {
			$this->_handleAjax( 'closed-postboxes' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '1', $e->getMessage() );
		}

		$closed = get_user_meta( self::$admin_id, 'closedpostboxes_testpage', true );
		$this->assertSame( array( 'postbox1', 'postbox2' ), $closed );

		$hidden = get_user_meta( self::$admin_id, 'metaboxhidden_testpage', true );
		// 'submitdiv' should be removed as it is in the always-shown list.
		$this->assertSame( array( 'postbox3' ), $hidden );
	}

	/**
	 * Tests update failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_closed_postboxes_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'               => 'closed-postboxes',
			'closedpostboxesnonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'closed-postboxes' );
	}

	/**
	 * Tests update failure due to invalid page key.
	 *
	 * @ticket 65252
	 */
	public function test_closed_postboxes_invalid_page(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'               => 'closed-postboxes',
			'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
			'page'                 => 'invalid page!',
		);

		try {
			$this->_handleAjax( 'closed-postboxes' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '0', $e->getMessage() );
		}
	}
}
