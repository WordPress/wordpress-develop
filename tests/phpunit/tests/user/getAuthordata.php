<?php

/**
 * @group user
 * @group capabilities
 *
 * @covers ::get_authordata
 */
class Tests_User_GetAuthordata extends WP_UnitTestCase {

	/**
	 * Contributor user.
	 *
	 * @var WP_User
	 */
	protected static $contributor;

	/**
	 * Creates the shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$contributor = $factory->user->create_and_get( array( 'role' => 'contributor' ) );
	}

	/**
	 * @ticket 58001
	 */
	public function test_returns_false_for_invalid_user() {
		$this->assertFalse( get_authordata( 0 ), 'get_authordata( 0 ) should return false.' );
		$this->assertFalse( get_authordata( PHP_INT_MAX ), 'get_authordata() for a nonexistent user ID should return false.' );
		$this->assertFalse( get_authordata( 'not-a-number' ), 'get_authordata() for a non-numeric value should return false.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_accepts_numeric_string_user_id() {
		$user = get_authordata( (string) self::$contributor->ID );

		$this->assertInstanceOf( WP_User::class, $user, 'get_authordata() should accept a numeric string.' );
		$this->assertSame( self::$contributor->ID, $user->ID, 'User ID should match.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_returns_user_with_display_data() {
		$user = get_authordata( self::$contributor->ID );

		$this->assertInstanceOf( WP_User::class, $user, 'get_authordata() should return a WP_User.' );
		$this->assertSame( self::$contributor->ID, $user->ID, 'User ID should match.' );
		$this->assertSame( self::$contributor->display_name, $user->display_name, 'Display name should match.' );
		$this->assertSame( self::$contributor->to_array(), $user->to_array(), 'to_array() should match get_userdata().' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_defers_loading_capability_data() {
		wp_cache_delete( self::$contributor->ID, 'user_meta' );

		$user = get_authordata( self::$contributor->ID );

		$this->assertFalse( wp_cache_get( self::$contributor->ID, 'user_meta' ), 'User meta should not be loaded.' );
		$this->assertArrayNotHasKey( 'roles', get_object_vars( $user ), 'Roles should not be loaded.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_capability_data_matches_get_userdata_once_loaded() {
		$user = get_authordata( self::$contributor->ID );

		$this->assertSame( array( 'contributor' ), $user->roles, 'Roles should be loaded on first access.' );
		$this->assertEquals( get_object_vars( get_userdata( self::$contributor->ID ) ), get_object_vars( $user ), 'Loaded public properties should match get_userdata().' );
		$this->assertSame( wp_json_encode( get_userdata( self::$contributor->ID ) ), wp_json_encode( get_authordata( self::$contributor->ID ) ), 'JSON encoding should match get_userdata().' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_the_author_meta_roles_field() {
		global $authordata;

		$original_authordata = $authordata;
		$authordata          = get_authordata( self::$contributor->ID );

		$roles = get_the_author_meta( 'roles' );

		$authordata = $original_authordata;

		$this->assertSame( array( 'contributor' ), $roles, 'The roles field should be loaded on demand.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_generate_postdata_uses_short_init_authordata() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$contributor->ID ) );

		$data = generate_postdata( $post_id );

		$this->assertInstanceOf( WP_User::class, $data['authordata'], 'authordata should be a WP_User.' );
		$this->assertArrayNotHasKey( 'roles', get_object_vars( $data['authordata'] ), 'authordata should not load the roles.' );
		$this->assertSame( array( 'contributor' ), $data['authordata']->roles, 'authordata roles should load on demand.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_author_archive_authordata_uses_short_init() {
		self::factory()->post->create( array( 'post_author' => self::$contributor->ID ) );

		$this->go_to( get_author_posts_url( self::$contributor->ID ) );

		$this->assertTrue( is_author(), 'Should be an author archive.' );
		$this->assertArrayNotHasKey( 'roles', get_object_vars( $GLOBALS['authordata'] ), 'authordata global should not load the roles.' );
		$this->assertArrayNotHasKey( 'roles', get_object_vars( get_queried_object() ), 'Queried author should not load the roles.' );
		$this->assertSame( array( 'contributor' ), get_queried_object()->roles, 'Queried author roles should load on demand.' );
	}
}
