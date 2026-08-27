<?php

/**
 * @group admin
 * @group user
 */
class Tests_Admin_Includes_User_AddUser extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
	}

	/**
	 * @ticket 65672
	 */
	public function test_add_user_should_accept_username_with_leading_or_trailing_whitespace() {
		$_POST               = array();
		$_POST['user_login'] = ' bob ';
		$_POST['pass1']      = 'password';
		$_POST['pass2']      = 'password';
		$_POST['role']       = 'subscriber';
		$_POST['email']      = 'bob@example.com';

		$user_id = add_user();

		$this->assertIsInt( $user_id, 'add_user() should return the new user ID, not a WP_Error.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'bob', $user->user_login );
	}

	/**
	 * @ticket 65672
	 */
	public function test_add_user_should_accept_username_with_repeated_internal_whitespace() {
		$_POST               = array();
		$_POST['user_login'] = 'bob  smith';
		$_POST['pass1']      = 'password';
		$_POST['pass2']      = 'password';
		$_POST['role']       = 'subscriber';
		$_POST['email']      = 'bobsmith@example.com';

		$user_id = add_user();

		$this->assertIsInt( $user_id, 'add_user() should return the new user ID, not a WP_Error.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'bob smith', $user->user_login );
	}

	/**
	 * @ticket 65672
	 */
	public function test_add_user_should_still_reject_username_with_illegal_characters() {
		$_POST               = array();
		$_POST['user_login'] = 'bob@#&99';
		$_POST['pass1']      = 'password';
		$_POST['pass2']      = 'password';
		$_POST['role']       = 'subscriber';
		$_POST['email']      = 'bob99@example.com';

		$user_id = add_user();

		$this->assertWPError( $user_id );
		$this->assertSame( 'user_login', $user_id->get_error_code() );
	}
}
