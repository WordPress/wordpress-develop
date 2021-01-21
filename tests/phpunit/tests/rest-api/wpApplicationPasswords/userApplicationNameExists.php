<?php
/**
 * Unit tests covering WP_Application_Passwords::user_application_name_exists functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 */

/**
 * @covers WP_Application_Passwords::user_application_name_exists
 *
 * @group  restapi
 * @group  app_password
 */
class WP_Test_WpApplicationPasswords_UserApplicationNameExists extends WP_UnitTestCase {

	/**
	 * Administrator user id.
	 *
	 * @var int
	 */
	private static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		if ( is_multisite() ) {
			grant_super_admin( self::$user_id );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
	}

	/**
	 * @ticket51941
	 */
	public function test_should_return_false_when_does_not_exists() {
		foreach ( array( 'test1', 'test2', 'test3' ) as $name ) {
			$this->assertFalse( WP_Application_Passwords::user_application_name_exists( self::$user_id, $name ) );
		}
	}

	/**
	 * @ticket 51941
	 */
	public function test_should_return_true_when_exists() {
		foreach ( array( 'app1', 'app2', 'app3' ) as $existing_name ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $existing_name ) );

			$this->assertTrue( WP_Application_Passwords::user_application_name_exists( self::$user_id, $name ) );
		}
	}
}
