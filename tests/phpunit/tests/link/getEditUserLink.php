<?php
/**
 * Tests for the `get_edit_user_link()` function.
 *
 * @group link
 * @group user
 *
 * @covers ::get_edit_user_link
 */
class Tests_Link_GetEditUserLink extends WP_UnitTestCase {

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
	 * Test author user ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );

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
		self::delete_user( self::$author_id );
	}

	/**
	 * Sets up current user before each test method.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tests that get_edit_user_link() defaults to the current user's profile URL.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_defaults_to_current_user() {
		$expected = get_edit_profile_url( self::$admin_id );

		$this->assertSame( $expected, get_edit_user_link() );
	}

	/**
	 * Tests that get_edit_user_link() returns the profile URL when explicitly passing the current user ID.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_for_current_user_with_explicit_id() {
		$expected = get_edit_profile_url( self::$admin_id );

		$this->assertSame( $expected, get_edit_user_link( self::$admin_id ) );
	}

	/**
	 * Tests that get_edit_user_link() returns user-edit.php URL when an administrator edits another user.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_for_other_user_as_admin() {
		$expected = add_query_arg( 'user_id', self::$subscriber_id, self_admin_url( 'user-edit.php' ) );

		$this->assertSame( $expected, get_edit_user_link( self::$subscriber_id ) );
	}

	/**
	 * Tests that get_edit_user_link() accepts a numeric string user ID.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_accepts_numeric_string() {
		$expected = add_query_arg( 'user_id', self::$subscriber_id, self_admin_url( 'user-edit.php' ) );

		$this->assertSame( $expected, get_edit_user_link( (string) self::$subscriber_id ) );
	}

	/**
	 * Tests that get_edit_user_link() allows a subscriber to get their own profile URL.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_subscriber_editing_self() {
		wp_set_current_user( self::$subscriber_id );

		$expected = get_edit_profile_url( self::$subscriber_id );

		$this->assertSame( $expected, get_edit_user_link() );
		$this->assertSame( $expected, get_edit_user_link( self::$subscriber_id ) );
	}

	/**
	 * Tests that get_edit_user_link() returns an empty string when a subscriber tries to edit another user.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_for_other_user_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$this->assertSame( '', get_edit_user_link( self::$author_id ) );
	}

	/**
	 * Tests that get_edit_user_link() returns an empty string when the user is logged out.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_logged_out_returns_empty_string() {
		wp_set_current_user( 0 );

		$this->assertSame( '', get_edit_user_link() );
		$this->assertSame( '', get_edit_user_link( self::$subscriber_id ) );
	}

	/**
	 * Tests that get_edit_user_link() returns an empty string for non-existent user IDs.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_non_existent_user_ids
	 *
	 * @param int $user_id Non-existent user ID.
	 */
	public function test_get_edit_user_link_non_existent_user( $user_id ) {
		$this->assertSame( '', get_edit_user_link( $user_id ) );
	}

	/**
	 * Data provider for test_get_edit_user_link_non_existent_user().
	 *
	 * @return array[]
	 */
	public static function data_non_existent_user_ids() {
		return array(
			'negative ID'           => array( -1 ),
			'large non-existent ID' => array( 9999999 ),
		);
	}

	/**
	 * Tests that get_edit_user_link() falls back to the current user ID for falsy arguments when logged in.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_falsy_user_ids
	 *
	 * @param mixed $falsy_id Falsy user ID.
	 */
	public function test_get_edit_user_link_falsy_id_falls_back_to_current_user( $falsy_id ) {
		$expected = get_edit_profile_url( self::$admin_id );

		$this->assertSame( $expected, get_edit_user_link( $falsy_id ) );
	}

	/**
	 * Data provider for test_get_edit_user_link_falsy_id_falls_back_to_current_user().
	 *
	 * @return array[]
	 */
	public static function data_falsy_user_ids() {
		return array(
			'zero int'     => array( 0 ),
			'zero string'  => array( '0' ),
			'empty string' => array( '' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}

	/**
	 * Tests that the get_edit_user_link filter hook modifies the returned URL and receives the user ID.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_user_link_applies_filter() {
		$filter_received_user_id = null;
		$filter_callback         = static function ( $link, $user_id ) use ( &$filter_received_user_id ) {
			$filter_received_user_id = $user_id;
			return 'https://example.org/custom-edit-user/' . $user_id;
		};

		add_filter( 'get_edit_user_link', $filter_callback, 10, 2 );
		$actual = get_edit_user_link( self::$subscriber_id );
		remove_filter( 'get_edit_user_link', $filter_callback, 10 );

		$this->assertSame( 'https://example.org/custom-edit-user/' . self::$subscriber_id, $actual );
		$this->assertSame( self::$subscriber_id, $filter_received_user_id );
	}
}
