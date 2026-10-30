<?php
/**
 * Tests for edit_user() email validation.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Tests for the edit_user() function's email validation.
 *
 * @group user
 * @group admin
 */
class Tests_User_EditUserEmail extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Set up the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Clean up after the test.
	 */
	public function tear_down() {
		unset( $_POST['user_login'], $_POST['email'], $_POST['pass1'], $_POST['pass2'], $_POST['_wpnonce_create-user'], $_POST['action'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Tests that creating a user with a non-empty but invalid email address
	 * does not result in a contradictory `empty_email` error.
	 *
	 * @ticket 61594
	 */
	public function test_create_user_with_invalid_email_does_not_add_empty_email_error() {
		wp_set_current_user( self::$admin_id );

		// Simulate the POST data from the "Add User" form.
		$_POST['action']               = 'createuser';
		$_POST['user_login']           = 'testuser_invalid_email';
		$_POST['email']                = 'invalidemail'; // Non-empty, but invalid.
		$_POST['pass1']                = 'password123';
		$_POST['pass2']                = 'password123';
		$_POST['_wpnonce_create-user'] = wp_create_nonce( 'create-user' );

		// Call edit_user() without a user ID to simulate a creation attempt.
		$result = edit_user();

		// The result must be a WP_Error.
		$this->assertInstanceOf( 'WP_Error', $result );

		// The expected error code must be present.
		$this->assertContains( 'invalid_email', $result->get_error_codes(), 'The invalid_email code should be present.' );

		// The contradictory empty_email code must NOT be present.
		$this->assertNotContains( 'empty_email', $result->get_error_codes(), 'The empty_email code should NOT be present for a non-empty invalid email.' );
	}
}
