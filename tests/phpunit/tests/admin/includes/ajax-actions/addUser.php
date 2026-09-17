<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_add_user() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_add_user
 */
class Tests_wp_ajax_add_user extends WP_Ajax_UnitTestCase {

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
		if ( isset( $_POST['action'] ) && 'add-user' === $_POST['action'] ) {
			wp_ajax_add_user( 'add-user' );
		}
	}

	/**
	 * Tests successful user creation.
	 *
	 * @ticket 65252
	 */
	public function test_add_user_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'add-user',
			'_ajax_nonce' => wp_create_nonce( 'add-user' ),
			'user_login'  => 'newuser',
			'email'       => 'newuser@example.com',
			'pass1'       => 'password',
			'pass2'       => 'password',
			'role'        => 'subscriber',
		);

		try {
			$this->_handleAjax( 'add-user' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( 'newuser', $response );
			$this->assertStringContainsString( 'User <a href="#user-', $response );
			$this->assertStringContainsString( 'newuser</a> added', $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( 'newuser', $response );
			$this->assertStringContainsString( 'User <a href="#user-', $response );
			$this->assertStringContainsString( 'newuser</a> added', $response );
		}

		$user = get_user_by( 'login', 'newuser' );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'newuser@example.com', $user->user_email );
	}

	/**
	 * Tests addition failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_add_user_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'add-user',
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'add-user' );
	}

	/**
	 * Tests addition failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_add_user_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action'      => 'add-user',
			'_ajax_nonce' => wp_create_nonce( 'add-user' ),
		);

		try {
			$this->_handleAjax( 'add-user' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Tests addition failure when edit_user() returns an error (e.g. invalid email).
	 *
	 * @ticket 65252
	 */
	public function test_add_user_invalid_email(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'add-user',
			'_ajax_nonce' => wp_create_nonce( 'add-user' ),
			'user_login'  => 'newuser2',
			'email'       => 'invalid-email',
			'pass1'       => 'password',
			'pass2'       => 'password',
		);

		try {
			$this->_handleAjax( 'add-user' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( 'invalid_email', $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( 'invalid_email', $response );
		}
	}
}
