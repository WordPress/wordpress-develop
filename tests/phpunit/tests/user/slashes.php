<?php

/**
 * @group user
 * @group slashes
 * @ticket 21767
 */
class Tests_User_Slashes extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	protected static $author_id;
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_id   = $factory->user->create();
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );
	}

	/**
	 * Tests the controller function that expects slashed data.
	 */
	public function test_add_user() {
		$_POST                 = array();
		$_GET                  = array();
		$_REQUEST              = array();
		$_POST['user_login']   = 'slash_example_user_1';
		$_POST['pass1']        = 'password';
		$_POST['pass2']        = 'password';
		$_POST['role']         = 'subscriber';
		$_POST['email']        = 'user1@example.com';
		$_POST['first_name']   = self::SLASH_1;
		$_POST['last_name']    = self::SLASH_3;
		$_POST['nickname']     = self::SLASH_5;
		$_POST['display_name'] = self::SLASH_7;
		$_POST['description']  = self::SLASH_3;

		$_POST = add_magic_quotes( $_POST ); // The add_user() function will strip slashes.

		$user_id = add_user();
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( self::SLASH_1, $user->first_name );
		$this->assertSame( self::SLASH_3, $user->last_name );
		$this->assertSame( self::SLASH_5, $user->nickname );
		$this->assertSame( self::SLASH_7, $user->display_name );
		$this->assertSame( self::SLASH_3, $user->description );

		$_POST                 = array();
		$_GET                  = array();
		$_REQUEST              = array();
		$_POST['user_login']   = 'slash_example_user_2';
		$_POST['pass1']        = 'password';
		$_POST['pass2']        = 'password';
		$_POST['role']         = 'subscriber';
		$_POST['email']        = 'user2@example.com';
		$_POST['first_name']   = self::SLASH_2;
		$_POST['last_name']    = self::SLASH_4;
		$_POST['nickname']     = self::SLASH_6;
		$_POST['display_name'] = self::SLASH_2;
		$_POST['description']  = self::SLASH_4;

		$_POST = add_magic_quotes( $_POST ); // The add_user() function will strip slashes.

		$user_id = add_user();
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( self::SLASH_2, $user->first_name );
		$this->assertSame( self::SLASH_4, $user->last_name );
		$this->assertSame( self::SLASH_6, $user->nickname );
		$this->assertSame( self::SLASH_2, $user->display_name );
		$this->assertSame( self::SLASH_4, $user->description );
	}

	/**
	 * Tests the controller function that expects slashed data.
	 */
	public function test_edit_user() {
		$user_id = self::$user_id;

		$_POST                 = array();
		$_GET                  = array();
		$_REQUEST              = array();
		$_POST['role']         = 'subscriber';
		$_POST['email']        = 'user1@example.com';
		$_POST['first_name']   = self::SLASH_1;
		$_POST['last_name']    = self::SLASH_3;
		$_POST['nickname']     = self::SLASH_5;
		$_POST['display_name'] = self::SLASH_7;
		$_POST['description']  = self::SLASH_3;

		$_POST = add_magic_quotes( $_POST ); // The edit_user() function will strip slashes.

		$user_id = edit_user( $user_id );
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( self::SLASH_1, $user->first_name );
		$this->assertSame( self::SLASH_3, $user->last_name );
		$this->assertSame( self::SLASH_5, $user->nickname );
		$this->assertSame( self::SLASH_7, $user->display_name );
		$this->assertSame( self::SLASH_3, $user->description );

		$_POST                 = array();
		$_GET                  = array();
		$_REQUEST              = array();
		$_POST['role']         = 'subscriber';
		$_POST['email']        = 'user2@example.com';
		$_POST['first_name']   = self::SLASH_2;
		$_POST['last_name']    = self::SLASH_4;
		$_POST['nickname']     = self::SLASH_6;
		$_POST['display_name'] = self::SLASH_2;
		$_POST['description']  = self::SLASH_4;

		$_POST = add_magic_quotes( $_POST ); // The edit_user() function will strip slashes.

		$user_id = edit_user( $user_id );
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( self::SLASH_2, $user->first_name );
		$this->assertSame( self::SLASH_4, $user->last_name );
		$this->assertSame( self::SLASH_6, $user->nickname );
		$this->assertSame( self::SLASH_2, $user->display_name );
		$this->assertSame( self::SLASH_4, $user->description );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_wp_insert_user() {
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'slash_example_user_3',
				'role'         => 'subscriber',
				'user_email'   => 'user3@example.com',
				'first_name'   => self::SLASH_1,
				'last_name'    => self::SLASH_3,
				'nickname'     => self::SLASH_5,
				'display_name' => self::SLASH_7,
				'description'  => self::SLASH_3,
				'user_pass'    => '',
			)
		);
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $user->first_name );
		$this->assertSame( wp_unslash( self::SLASH_3 ), $user->last_name );
		$this->assertSame( wp_unslash( self::SLASH_5 ), $user->nickname );
		$this->assertSame( wp_unslash( self::SLASH_7 ), $user->display_name );
		$this->assertSame( wp_unslash( self::SLASH_3 ), $user->description );

		$user_id = wp_insert_user(
			array(
				'user_login'   => 'slash_example_user_4',
				'role'         => 'subscriber',
				'user_email'   => 'user4@example.com',
				'first_name'   => self::SLASH_2,
				'last_name'    => self::SLASH_4,
				'nickname'     => self::SLASH_6,
				'display_name' => self::SLASH_2,
				'description'  => self::SLASH_4,
				'user_pass'    => '',
			)
		);
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $user->first_name );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $user->last_name );
		$this->assertSame( wp_unslash( self::SLASH_6 ), $user->nickname );
		$this->assertSame( wp_unslash( self::SLASH_2 ), $user->display_name );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $user->description );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_wp_update_user() {
		$user_id = self::$user_id;
		$user_id = wp_update_user(
			array(
				'ID'           => $user_id,
				'role'         => 'subscriber',
				'first_name'   => self::SLASH_1,
				'last_name'    => self::SLASH_3,
				'nickname'     => self::SLASH_5,
				'display_name' => self::SLASH_7,
				'description'  => self::SLASH_3,
			)
		);
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $user->first_name );
		$this->assertSame( wp_unslash( self::SLASH_3 ), $user->last_name );
		$this->assertSame( wp_unslash( self::SLASH_5 ), $user->nickname );
		$this->assertSame( wp_unslash( self::SLASH_7 ), $user->display_name );
		$this->assertSame( wp_unslash( self::SLASH_3 ), $user->description );

		$user_id = wp_update_user(
			array(
				'ID'           => $user_id,
				'role'         => 'subscriber',
				'first_name'   => self::SLASH_2,
				'last_name'    => self::SLASH_4,
				'nickname'     => self::SLASH_6,
				'display_name' => self::SLASH_2,
				'description'  => self::SLASH_4,
			)
		);
		$user    = get_user_to_edit( $user_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $user->first_name );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $user->last_name );
		$this->assertSame( wp_unslash( self::SLASH_6 ), $user->nickname );
		$this->assertSame( wp_unslash( self::SLASH_2 ), $user->display_name );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $user->description );
	}

}
