<?php

/**
 * @group post
 */
class Tests_Post_wpListUsers extends WP_UnitTestCase {

	public static $users;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users[] = get_userdata( $factory->user->create( array( 'role' => 'editor' ) ) );
		self::$users[] = get_userdata( $factory->user->create( array( 'role' => 'subscriber' ) ) );
		self::$users[] = get_userdata( $factory->user->create( array( 'role' => 'author' ) ) );
		self::$users[] = get_userdata( $factory->user->create( array( 'role' => 'author' ) ) );
		$factory->user->create( array( 'role' => 'admin' ) );
	}

	function test_wp_list_users_exclude() {
		$args = array(
			'echo'    => false,
			'exclude' => 2,
		);

		$expected = '';
		$users    = self::$users;
		unset( $users[0] );

		foreach ( $users as $user ) {
			$expected .= '<li>' . $user->user_login . '</li>';
		}

		$this->assertSameIgnoreEOL( $expected, wp_list_users( $args ) );
	}

	function test_wp_list_pages_exclude_admin() {
		$args = array(
			'echo'  => false,
			'roles' => array( 'subscriber', 'author', 'editor' ),
		);

		$expected = '';
		$users    = self::$users;

		foreach ( $users as $user ) {
			$expected .= '<li>' . $user->user_login . '</li>';
		}

		$this->assertSameIgnoreEOL( $expected, wp_list_users( $args ) );
	}
}
