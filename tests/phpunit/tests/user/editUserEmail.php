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
	 * Backup of the $_POST superglobal.
	 *
	 * @var array
	 */
	protected $backuppost = array();

	/**
	 * Backup of the $_REQUEST superglobal.
	 *
	 * @var array
	 */
	protected $backuprequest = array();

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->backuppost    = $_POST ?? array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->backuprequest = $_REQUEST ?? array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Clean up after the test.
	 */
	public function tear_down() {
		$_POST    = $this->backuppost;
		$_REQUEST = $this->backuprequest;

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Tests that creating a user with a non-empty but invalid email address
	 * does not result in a contradictory `empty_email` error.
	 *
	 * @ticket 66422
	 */
	public function test_create_user_with_invalid_email_does_not_add_empty_email_error() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// 2. Simulate the POST data from the "Add User" form.
		$_POST['action']               = 'createuser';
		$_POST['user_login']           = 'testuser_invalid_email';
		$_POST['email']                = 'invalidemail'; // Non-empty, but invalid.
		$_POST['pass1']                = 'password123';
		$_POST['pass2']                = 'password123';
		$_POST['_wpnonce_create-user'] = wp_create_nonce( 'create-user' );

		// 3. Call edit_user() without a user ID to simulate a creation attempt.
		$result = edit_user();

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertContains( 'invalid_email', $result->get_error_codes(), 'The invalid_email code should be present.' );
		$this->assertNotContains( 'empty_email', $result->get_error_codes(), 'The empty_email code should NOT be present for a non-empty invalid email.' );
	}
}
