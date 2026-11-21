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

		$this->assertWPError( $actual );
		$this->assertSame( $expected['error_code'], $actual->get_error_code() );
		$this->assertSame( $expected['error_message'], $actual->get_error_message( $expected['error_code'] ) );
	}

	public static function data_create_new_application_password_validation() {
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

	public static function data_create_new_application_password() {
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

	public static function data_application_name_exists_for_user() {
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
			$expected_value = $update[ $key ] ?? $original_item[ $key ];
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

		$this->assertWPError( $actual );
		$this->assertSame( 'application_password_not_found', $actual->get_error_code() );
		$this->assertSame( 'Could not find an application password with that id.', $actual->get_error_message( 'application_password_not_found' ) );
	}

	public static function data_update_application_password() {
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

	/**
	 * @ticket 51941
	 */
	public function test_can_create_duplicate_app_password_names() {
		$created = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'My App' ) );
		$this->assertNotWPError( $created, 'First attempt to create an application password should not return an error' );
		$created = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'My App' ) );
		$this->assertNotWPError( $created, 'Second attempt to create an application password should not return an error' );
	}

	/**
	 * @ticket 63927
	 *
	 * @covers WP_Application_Passwords::create_new_application_password
	 */
	public function test_create_new_application_password_unslashes_values() {
		$name   = 'Bob\'s "Tool" \\ Backslash';
		$app_id = '\\"app\'';
		$action = new MockAction();
		add_action( 'wp_create_application_password', array( $action, 'action' ), 10, 4 );

		list( , $new_item ) = WP_Application_Passwords::create_new_application_password(
			self::$user_id,
			wp_slash(
				array(
					'name'   => $name,
					'app_id' => $app_id,
				)
			)
		);

		$this->assertSame( $name, $new_item['name'], 'The returned name should be unslashed.' );
		$this->assertSame( $app_id, $new_item['app_id'], 'The returned app ID should be unslashed.' );

		$args = $action->get_args();
		$this->assertCount( 1, $args, 'The action should fire once.' );
		$this->assertSame( $new_item, $args[0][1], 'The action should receive the unslashed item.' );
		$this->assertSame( $name, $args[0][3]['name'], 'The action should receive the unslashed name argument.' );
		$this->assertSame( $app_id, $args[0][3]['app_id'], 'The action should receive the unslashed app ID argument.' );

		$stored = WP_Application_Passwords::get_user_application_password( self::$user_id, $new_item['uuid'] );
		$this->assertSame( $name, $stored['name'], 'The stored name should match the unslashed name.' );
		$this->assertSame( $app_id, $stored['app_id'], 'The stored app ID should match the unslashed app ID.' );
	}

	/**
	 * @ticket 63927
	 *
	 * @covers WP_Application_Passwords::update_application_password
	 */
	public function test_update_application_password_unslashes_values() {
		$name = 'Bob\'s "Tool" \\ Backslash';

		list( , $original ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'App' ) );

		$action = new MockAction();
		add_action( 'wp_update_application_password', array( $action, 'action' ), 10, 3 );

		$updated = WP_Application_Passwords::update_application_password( self::$user_id, $original['uuid'], wp_slash( array( 'name' => $name ) ) );

		$this->assertTrue( $updated, 'The application password should be updated.' );

		$args = $action->get_args();
		$this->assertCount( 1, $args, 'The action should fire once.' );
		$this->assertSame( $name, $args[0][1]['name'], 'The action should receive the unslashed item name.' );
		$this->assertSame( array( 'name' => $name ), $args[0][2], 'The action should receive the unslashed update.' );

		$stored = WP_Application_Passwords::get_user_application_password( self::$user_id, $original['uuid'] );
		$this->assertSame( $name, $stored['name'], 'The stored name should match the unslashed name.' );
	}

	/**
	 * @ticket 63927
	 *
	 * @covers WP_Application_Passwords::update_application_password
	 */
	public function test_update_application_password_passes_stored_name_when_name_not_updated() {
		$name = 'Bob\'s "Tool" \\ Backslash';

		list( , $original ) = WP_Application_Passwords::create_new_application_password( self::$user_id, wp_slash( array( 'name' => $name ) ) );

		$action = new MockAction();
		add_action( 'wp_update_application_password', array( $action, 'action' ), 10, 3 );

		WP_Application_Passwords::update_application_password( self::$user_id, $original['uuid'], array() );

		$args = $action->get_args();
		$this->assertCount( 1, $args, 'The action should fire once.' );
		$this->assertSame( $name, $args[0][1]['name'] );
	}

	/**
	 * @ticket 63927
	 *
	 * @covers WP_Application_Passwords::update_application_password
	 */
	public function test_update_application_password_with_same_slashed_name_does_not_save() {
		$name = 'Bob\'s "Tool" \\ Backslash';

		list( , $original ) = WP_Application_Passwords::create_new_application_password( self::$user_id, wp_slash( array( 'name' => $name ) ) );

		$action = new MockAction();
		add_action( 'update_user_meta', array( $action, 'action' ) );

		$updated = WP_Application_Passwords::update_application_password( self::$user_id, $original['uuid'], wp_slash( array( 'name' => $name ) ) );

		$this->assertTrue( $updated, 'Updating with an unchanged name should succeed.' );
		$this->assertSame( 0, $action->get_call_count(), 'Updating with an unchanged name should not save.' );
	}

	/**
	 * Ensures saving one application password does not strip backslashes from the others.
	 *
	 * @ticket 63927
	 *
	 * @covers WP_Application_Passwords::create_new_application_password
	 * @covers WP_Application_Passwords::update_application_password
	 * @covers WP_Application_Passwords::record_application_password_usage
	 * @covers WP_Application_Passwords::delete_application_password
	 */
	public function test_backslashes_in_stored_names_survive_later_saves() {
		$name = 'C:\\Apps\\Tool';

		list( , $item )  = WP_Application_Passwords::create_new_application_password( self::$user_id, wp_slash( array( 'name' => $name ) ) );
		list( , $other ) = WP_Application_Passwords::create_new_application_password( self::$user_id, array( 'name' => 'Other' ) );
		$this->assertSame( $name, WP_Application_Passwords::get_user_application_password( self::$user_id, $item['uuid'] )['name'], 'After creating another password.' );

		WP_Application_Passwords::update_application_password( self::$user_id, $other['uuid'], array( 'name' => 'Other Updated' ) );
		$this->assertSame( $name, WP_Application_Passwords::get_user_application_password( self::$user_id, $item['uuid'] )['name'], 'After updating another password.' );

		WP_Application_Passwords::record_application_password_usage( self::$user_id, $other['uuid'] );
		$this->assertSame( $name, WP_Application_Passwords::get_user_application_password( self::$user_id, $item['uuid'] )['name'], 'After recording usage of another password.' );

		WP_Application_Passwords::delete_application_password( self::$user_id, $other['uuid'] );
		$this->assertSame( $name, WP_Application_Passwords::get_user_application_password( self::$user_id, $item['uuid'] )['name'], 'After deleting another password.' );
	}
}
