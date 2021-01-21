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
class Test_WPApplicationPasswords_UserApplicationNameExists extends WP_UnitTestCase {

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
	 * @ticket 51941
	 * @dataProvider data_test
	 */
	public function test_exists( $expected, $name ) {
		if ( $expected ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
		}

		$this->assertSame( $expected, WP_Application_Passwords::user_application_name_exists( self::$user_id, $name ) );
	}

	public function data_test() {
		return array(
			array( false, 'test1' ),
			array( false, 'baz' ),
			array( false, 'bar' ),
			array( true, 'App 1' ),
			array( true, 'Some Test' ),
			array( true, 'Baz' ),
		);
	}
}
