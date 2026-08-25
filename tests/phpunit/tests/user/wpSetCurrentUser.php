<?php

/**
 * @group user
 */
class Tests_User_wpSetCurrentUser extends WP_UnitTestCase {
	protected static $user_id;
	protected static $user_id2;
	protected static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id    = $factory->user->create();
		self::$user_ids[] = self::$user_id;
		self::$user_id2   = $factory->user->create( array( 'user_login' => 'foo' ) );
		self::$user_ids[] = self::$user_id2;
	}

	public function test_set_by_id() {
		$user = wp_set_current_user( self::$user_id );

		$this->assertSame( self::$user_id, $user->ID );
		$this->assertSame( $user, wp_get_current_user() );
		$this->assertSame( self::$user_id, get_current_user_id() );
	}

	public function test_name_should_be_ignored_if_id_is_not_null() {
		$user = wp_set_current_user( self::$user_id, 'foo' );

		$this->assertSame( self::$user_id, $user->ID );
		$this->assertSame( $user, wp_get_current_user() );
		$this->assertSame( self::$user_id, get_current_user_id() );
	}

	public function test_should_set_by_name_if_id_is_null_and_current_user_is_nonempty() {
		wp_set_current_user( self::$user_id );
		$this->assertSame( self::$user_id, get_current_user_id() );

		$user = wp_set_current_user( null, 'foo' );

		$this->assertSame( self::$user_id2, $user->ID );
		$this->assertSame( $user, wp_get_current_user() );
		$this->assertSame( self::$user_id2, get_current_user_id() );
	}

	/**
	 * Test that you can set the current user by the name parameter when the current user is 0.
	 *
	 * @ticket 20845
	 */
	public function test_should_set_by_name_if_id_is_null() {
		wp_set_current_user( 0 );
		$this->assertSame( 0, get_current_user_id() );

		$user = wp_set_current_user( null, 'foo' );

		$this->assertSame( self::$user_id2, $user->ID );
		$this->assertSame( $user, wp_get_current_user() );
		$this->assertSame( self::$user_id2, get_current_user_id() );
	}

	/**
	 * Ensure user switching doesn't occur for the same user, even if type is non-int.
	 *
	 * @ticket 64628
	 *
	 * @dataProvider data_should_not_switch_to_same_user_type_equivalency
	 */
	public function test_should_not_switch_to_same_user_type_equivalency( string $type_function ) {
		wp_set_current_user( self::$user_id );
		$this->assertSame( self::$user_id, get_current_user_id(), "Current user's ID should match the ID of the user switched to." );

		$action = new MockAction();
		add_action( 'set_current_user', array( $action, 'action' ) );

		wp_set_current_user( $type_function( self::$user_id ) );
		$this->assertSame( 0, $action->get_call_count(), 'set_current_user should not be fired when switching to the same user.' );
	}

	/**
	 * Data provider for test_should_not_switch_to_same_user_type_equivalency.
	 *
	 * @return array[] Data provider.
	 */
	public function data_should_not_switch_to_same_user_type_equivalency(): array {
		return array(
			'integer' => array( 'type_function' => 'intval' ),
			'string'  => array( 'type_function' => 'strval' ),
		);
	}
}
