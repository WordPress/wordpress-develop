<?php

/**
 * Tests for the `get_edit_profile_url()` function.
 *
 * @group link
 * @group user
 *
 * @covers ::get_edit_profile_url
 */
class Tests_Link_GetEditProfileUrl extends WP_UnitTestCase {

	/**
	 * Test administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Test subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Cleans up shared fixtures after the test class runs.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}

		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Tests that get_edit_profile_url() defaults to current user ID when none passed.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_profile_url_for_current_user() {
		wp_set_current_user( self::$admin_id );

		$filter = new MockAction();
		add_filter( 'edit_profile_url', array( $filter, 'filter' ), 10, 3 );

		$url = get_edit_profile_url();

		$this->assertSame(
			get_dashboard_url( self::$admin_id, 'profile.php' ),
			$url
		);
		$this->assertSame( self::$admin_id, $filter->get_args()[0][1], 'The filter should receive the current user ID.' );
	}

	/**
	 * Tests that get_edit_profile_url() respects the scheme parameter.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_profile_url_with_scheme() {
		$this->assertSame(
			get_dashboard_url( self::$admin_id, 'profile.php', 'https' ),
			get_edit_profile_url( self::$admin_id, 'https' )
		);
		$this->assertSame(
			get_dashboard_url( self::$admin_id, 'profile.php', 'http' ),
			get_edit_profile_url( self::$admin_id, 'http' )
		);
	}

	/**
	 * Tests that get_edit_profile_url() applies the edit_profile_url filter with arguments.
	 *
	 * @ticket 65819
	 */
	public function test_edit_profile_url_filter() {
		$filter = new MockAction();
		add_filter( 'edit_profile_url', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'edit_profile_url',
			static function ( $url, $user_id, $scheme ) {
				return $url . '?custom_param=1';
			},
			20,
			3
		);

		$url = get_edit_profile_url( self::$admin_id, 'https' );

		$expected_args = array(
			get_dashboard_url( self::$admin_id, 'profile.php', 'https' ),
			self::$admin_id,
			'https',
		);

		$this->assertSame( $expected_args, $filter->get_args()[0], 'The edit_profile_url filter should receive url, user_id, and scheme.' );
		$this->assertSame( $expected_args[0] . '?custom_param=1', $url, 'The filtered URL should be returned.' );
	}

	/**
	 * Tests get_edit_profile_url() in user admin screen.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_profile_url_in_user_admin() {
		set_current_screen( 'dashboard-user' );

		$this->assertSame(
			user_admin_url( 'profile.php', 'admin' ),
			get_edit_profile_url( self::$admin_id )
		);
	}

	/**
	 * Tests get_edit_profile_url() in network admin screen.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_profile_url_in_network_admin() {
		set_current_screen( 'dashboard-network' );

		$this->assertSame(
			network_admin_url( 'profile.php', 'admin' ),
			get_edit_profile_url( self::$admin_id )
		);
	}
}
