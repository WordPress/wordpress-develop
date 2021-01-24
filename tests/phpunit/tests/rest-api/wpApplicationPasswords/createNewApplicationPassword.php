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
class Test_WPApplicationPasswords_CreateNewApplicationPassword extends WP_UnitTestCase {

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


	/**
	 * @ticket       51941
	 * @dataProvider data_create_validation
	 */
	public function test_create_validation( $expected, array $args = array(), array $names = array() ) {
		// Create the existing passwords.
		foreach ( $names as $name ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
		}

		$actual = WP_Application_Passwords::create_new_application_password( self::$user_id, $args );

		$this->assertInstanceOf( WP_Error::class, $actual );
		$this->assertSame( $expected['error_code'], $actual->get_error_code() );
		$this->assertSame( $expected['error_message'], $actual->get_error_message( $expected['error_code'] ) );
	}

	public function data_create_validation() {
		return array(
			'application_password_empty_name when no args' => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
			),
			'application_password_empty_name when no name' => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
				'args'     => array( 'app_id' => 1 ),
			),
			'application_password_empty_name when empty name' => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
				'args'     => array( 'name' => '   ' ),
			),
			'application_password_empty_name when <script>' => array(
				'expected' => array(
					'error_code'    => 'application_password_empty_name',
					'error_message' => 'An application name is required to create an application password.',
				),
				'args'     => array( 'name' => '<script>console.log("Hello")</script>' ),
			),
			'application_password_duplicate_name when name exists' => array(
				'expected' => array(
					'error_code'    => 'application_password_duplicate_name',
					'error_message' => 'Each application name should be unique.',
				),
				'args'     => array( 'name' => 'test2' ),
				'names'    => array( 'test1', 'test2' ),
			),
		);
	}

	/**
	 * @ticket       51941
	 * @dataProvider data_creates_new_password
	 */
	public function test_creates_new_password( array $args, array $names = array() ) {
		// Create the existing passwords.
		foreach ( $names as $name ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
		}

		list( $new_password, $new_item ) = WP_Application_Passwords::create_new_application_password( self::$user_id, $args );

		$this->assertNotEmpty( $new_password );
		$this->assertSame(
			array( 'uuid', 'app_id', 'name', 'password', 'created', 'last_used', 'last_ip' ),
			array_keys( $new_item )
		);
		$this->assertSame( $args['name'], $new_item['name'] );
	}

	public function data_creates_new_password() {
		return array(
			'should create new password when no passwords exists' => array(
				'args' => array( 'name' => 'test3' ),
			),
			'should create new password when name is unique'      => array(
				'args'  => array( 'name' => 'test3' ),
				'names' => array( 'test1', 'test2' ),
			),
		);
	}
}
