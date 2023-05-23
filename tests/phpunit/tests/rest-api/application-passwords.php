<?php
/**
 * Unit tests covering WP_Application_Passwords functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 *
 * @group  restapi
 * @group  app_password
 */
class Test_WP_Application_Passwords extends WP_UnitTestCase {

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
	 * @covers       WP_Application_Passwords::create_new_application_password
	 * @ticket       51941
	 * @dataProvider data_create_new_application_password_validation
	 */
	public function test_create_new_application_password_validation( $expected, array $args = array(), array $names = array() ) {
		// Create the existing passwords.
		foreach ( $names as $name ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
		}

		$actual = WP_Application_Passwords::create_new_application_password( self::$user_id, $args );

		$this->assertInstanceOf( WP_Error::class, $actual );
		$this->assertSame( $expected['error_code'], $actual->get_error_code() );
		$this->assertSame( $expected['error_message'], $actual->get_error_message( $expected['error_code'] ) );
	}

	public function data_create_new_application_password_validation() {
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
	 * @covers       WP_Application_Passwords::create_new_application_password
	 * @ticket       51941
	 * @dataProvider data_create_new_application_password
	 */
	public function test_create_new_application_password( array $args, array $names = array() ) {
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

	public function data_create_new_application_password() {
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

	/**
	 * @covers       WP_Application_Passwords::application_name_exists_for_user
	 * @ticket       51941
	 * @dataProvider data_application_name_exists_for_user
	 */
	public function test_application_name_exists_for_user( $expected, $name ) {
		if ( $expected ) {
			WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => $name ) );
		}

		$this->assertSame( $expected, WP_Application_Passwords::application_name_exists_for_user( self::$user_id, $name ) );
	}

	public function data_application_name_exists_for_user() {
		return array(
			array( false, 'test1' ),
			array( false, 'baz' ),
			array( false, 'bar' ),
			array( true, 'App 1' ),
			array( true, 'Some Test' ),
			array( true, 'Baz' ),
		);
	}

	/**
	 * @covers       WP_Application_Passwords::update_application_password
	 * @ticket       51941
	 * @dataProvider data_update_application_password
	 */
	public function test_update_application_password( array $update, array $existing ) {
		// Create the original item.
		list( , $original_item ) = WP_Application_Passwords::create_new_application_password( self::$user_id, $existing );
		$uuid                    = $original_item['uuid'];

		$actual = WP_Application_Passwords::update_application_password( self::$user_id, $uuid, $update );

		$this->assertTrue( $actual );

		// Check updated only given values.
		$updated_item = WP_Application_Passwords::get_user_application_password( self::$user_id, $uuid );
		foreach ( $updated_item as $key => $update_value ) {
			$expected_value = isset( $update[ $key ] ) ? $update[ $key ] : $original_item[ $key ];
			$this->assertSame( $expected_value, $update_value );
		}
	}

	/**
	 * @covers       WP_Application_Passwords::update_application_password
	 * @ticket       51941
	 * @dataProvider data_update_application_password
	 */
	public function test_update_application_password_when_no_password_found( array $update ) {
		$actual = WP_Application_Passwords::update_application_password( self::$user_id, '', $update );

		$this->assertInstanceOf( WP_Error::class, $actual );
		$this->assertSame( 'application_password_not_found', $actual->get_error_code() );
		$this->assertSame( 'Could not find an application password with that id.', $actual->get_error_message( 'application_password_not_found' ) );
	}

	public function data_update_application_password() {
		return array(
			'should not update when no values given to update' => array(
				'update'   => array(),
				'existing' => array( 'name' => 'Test' ),
			),
			'should not update when given same name' => array(
				'update'   => array( 'name' => 'Test' ),
				'existing' => array( 'name' => 'Test' ),
			),
			'should update name'                     => array(
				'update'   => array( 'name' => 'Test Updated' ),
				'existing' => array( 'name' => 'Test' ),
			),
		);
	}
}
