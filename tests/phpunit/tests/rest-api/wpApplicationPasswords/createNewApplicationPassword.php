<?php
/**
 * Unit tests covering WP_Application_Passwords::create_new_application_password functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 */

/**
 * @covers WP_Application_Passwords::create_new_application_password
 *
 * @group  restapi
 * @group  app_password
 */
class WP_Test_WpApplicationPasswords_CreateNewApplicationPassword extends WP_UnitTestCase {

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
	 * @ticket       51941
	 * @dataProvider data_returns_wp_error
	 */
	public function test_returns_wp_error( $expected, array $args = array(), array $names = array() ) {
		// Create the existing passwords.
		foreach ( $names as $name ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
			$this->assertTrue( WP_Application_Passwords::user_application_name_exists( self::$user_id, $name ) );
		}

		$actual = WP_Application_Passwords::create_new_application_password( self::$user_id, $args );

		$this->assertInstanceOf( WP_Error::class, $actual );
		$this->assertSame( $expected['error_code'], $actual->get_error_code() );
		$this->assertSame( $expected['error_message'], $actual->get_error_message( $expected['error_code'] ) );
	}

	public function data_returns_wp_error() {
		return array(
			'application_password_empty_name when no args'      => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
			),
			'application_password_empty_name when no name'      => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
				'args'     => array( 'app_id' => 1 ),
			),
			'application_password_unique_name when name exists' => array(
				'expected' => array(
					'error_code'    => 'application_password_unique_name',
					'error_message' => 'An application name should be unique to create an application password.',
				),
				'args'     => array( 'name' => 'test2' ),
				'names'    => array( 'test1', 'test2' ),
			),
		);
	}
}
