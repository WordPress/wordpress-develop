<?php
/**
 * Test cases for the `register_new_user()` function.
 *
 * @package WordPress
 *
 * @group user
 * @covers ::register_new_user
 */
class Tests_User_RegisterNewUser extends WP_UnitTestCase {

	/**
	 * An existing user used to trigger "already exists" errors.
	 *
	 * @var WP_User
	 */
	protected static $existing_user;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$existing_user = $factory->user->create_and_get(
			array(
				'user_login' => 'registernewuserexisting',
				'user_email' => 'registernewuserexisting@example.com',
			)
		);
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_returns_user_id_on_success() {
		$result = register_new_user( 'registernewusersuccess', 'registernewusersuccess@example.com' );

		$this->assertIsInt( $result, 'A successful registration should return an integer user ID.' );

		$user = get_user_by( 'id', $result );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( 'registernewusersuccess', $user->user_login );
		$this->assertSame( 'registernewusersuccess@example.com', $user->user_email );
		$this->assertSame( '1', get_user_meta( $result, 'default_password_nag', true ), 'The password change nag should be set for a newly registered user.' );
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_fires_register_new_user_action_on_success() {
		$fired_with_user_id = null;
		$action             = static function ( $user_id ) use ( &$fired_with_user_id ) {
			$fired_with_user_id = $user_id;
		};
		add_action( 'register_new_user', $action );

		$result = register_new_user( 'registernewuseraction', 'registernewuseraction@example.com' );

		remove_action( 'register_new_user', $action );

		$this->assertSame( $result, $fired_with_user_id, 'The "register_new_user" action should fire once with the new user ID.' );
	}

	/**
	 * @ticket 53631
	 *
	 * @dataProvider data_register_new_user_single_field_errors
	 *
	 * @param string $user_login The username to register with.
	 * @param string $user_email The email address to register with.
	 * @param string $error_code The expected, sole, error code.
	 */
	public function test_register_new_user_returns_wp_error_for_invalid_input( $user_login, $user_email, $error_code ) {
		$result = register_new_user( $user_login, $user_email );

		$this->assertWPError( $result );
		$this->assertSame( array( $error_code ), $result->get_error_codes() );
	}

	/**
	 * Data provider for single-field validation errors.
	 *
	 * Each case keeps the field not under test valid and unique so that
	 * exactly one error code is produced.
	 *
	 * @return array[]
	 */
	public function data_register_new_user_single_field_errors() {
		return array(
			'empty username'   => array( '', 'registernewuservalid1@example.com', 'empty_username' ),
			'invalid username' => array( 'invalid*user', 'registernewuservalid2@example.com', 'invalid_username' ),
			'empty email'      => array( 'registernewuservalid3', '', 'empty_email' ),
			'invalid email'    => array( 'registernewuservalid4', 'not-an-email', 'invalid_email' ),
		);
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_returns_wp_error_for_existing_username() {
		$result = register_new_user( self::$existing_user->user_login, 'registernewuseruniqueemail@example.com' );

		$this->assertWPError( $result );
		$this->assertSame( array( 'username_exists' ), $result->get_error_codes() );
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_returns_wp_error_for_existing_email() {
		$result = register_new_user( 'registernewuseruniquelogin', self::$existing_user->user_email );

		$this->assertWPError( $result );
		$this->assertSame( array( 'email_exists' ), $result->get_error_codes() );
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_accumulates_errors_for_username_and_email() {
		$result = register_new_user( '', '' );

		$this->assertWPError( $result );
		$this->assertSame( array( 'empty_username', 'empty_email' ), $result->get_error_codes() );
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_honors_registration_errors_filter_to_block_registration() {
		$filter = static function ( $errors ) {
			$errors->add( 'custom_block', 'Blocked by filter.' );
			return $errors;
		};
		add_filter( 'registration_errors', $filter );

		$result = register_new_user( 'registernewuserfiltered', 'registernewuserfiltered@example.com' );

		remove_filter( 'registration_errors', $filter );

		$this->assertWPError( $result );
		$this->assertContains( 'custom_block', $result->get_error_codes() );
		$this->assertFalse( username_exists( 'registernewuserfiltered' ), 'No user should be created when the registration_errors filter adds an error.' );
	}

	/**
	 * @ticket 53631
	 */
	public function test_register_new_user_fires_register_post_action_before_user_creation() {
		$fired_args = null;
		$action     = static function ( $sanitized_user_login, $user_email, $errors ) use ( &$fired_args ) {
			$fired_args = array( $sanitized_user_login, $user_email, $errors );
		};
		add_action( 'register_post', $action, 10, 3 );

		register_new_user( 'registernewuserpost', 'registernewuserpost@example.com' );

		remove_action( 'register_post', $action, 10 );

		$this->assertNotNull( $fired_args, 'The "register_post" action should fire.' );
		$this->assertSame( 'registernewuserpost', $fired_args[0] );
		$this->assertSame( 'registernewuserpost@example.com', $fired_args[1] );
		$this->assertInstanceOf( 'WP_Error', $fired_args[2] );
		$this->assertFalse( $fired_args[2]->has_errors(), 'No errors should be present yet for a valid registration.' );
	}
}
