<?php

/**
 * @group user
 * @group capabilities
 *
 * @covers ::get_authordata
 */
class Tests_User_GetAuthordata extends WP_UnitTestCase {

	/**
	 * @var WP_User[]
	 */
	protected static $users = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users = array(
			'administrator' => $factory->user->create_and_get( array( 'role' => 'administrator' ) ),
			'contributor'   => $factory->user->create_and_get( array( 'role' => 'contributor' ) ),
		);
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_authordata_returns_false_for_invalid_user() {
		$this->assertFalse( get_authordata( 0 ), 'get_authordata( 0 ) should return false.' );
		$this->assertFalse( get_authordata( 999999 ), 'get_authordata() for a nonexistent user ID should return false.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_authordata_defers_loading_capabilities() {
		$id   = self::$users['contributor']->ID;
		$user = get_authordata( $id );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( array(), $user->caps, 'caps should be empty before any capability-triggering method runs.' );
		$this->assertSame( array(), $user->roles, 'roles should be empty before any capability-triggering method runs.' );
		$this->assertSame( array(), $user->allcaps, 'allcaps should be empty before any capability-triggering method runs.' );

		// Any capability-triggering method call loads the data.
		$this->assertTrue( $user->has_cap( 'edit_posts' ) );
		$this->assertSame( array( 'contributor' ), $user->roles );
		$this->assertNotEmpty( $user->caps );
		$this->assertNotEmpty( $user->allcaps );
	}

	/**
	 * Regression test for a latent infinite-recursion bug: load_capability_data() calls
	 * get_role_caps(), which itself calls load_capability_data(). If the short_init flag
	 * isn't cleared before get_role_caps() runs, this recurses indefinitely.
	 *
	 * @ticket 58001
	 */
	public function test_get_authordata_has_cap_does_not_recurse() {
		$id   = self::$users['administrator']->ID;
		$user = get_authordata( $id );

		$this->assertTrue( $user->has_cap( 'manage_options' ) );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_authordata_get_role_caps_does_not_recurse() {
		$id   = self::$users['contributor']->ID;
		$user = get_authordata( $id );

		$role_caps = $user->get_role_caps();
		$this->assertIsArray( $role_caps );
		$this->assertArrayHasKey( 'edit_posts', $role_caps );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_authordata_remove_all_caps_before_any_lazy_trigger() {
		$id   = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$user = get_authordata( $id );

		// Called before has_cap()/get_role_caps() has ever run on this short-init instance.
		$user->remove_all_caps();

		$this->assertSame( array(), $user->caps );
		$this->assertFalse( $user->has_cap( 'edit_posts' ) );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_authordata_array_mutation_still_works() {
		$id   = self::$users['contributor']->ID;
		$user = get_authordata( $id );
		$user->has_cap( 'edit_posts' ); // Trigger the lazy load.

		$user->roles[] = 'subscriber';
		$this->assertContains( 'subscriber', $user->roles );

		$user->roles[0] = 'editor';
		$this->assertSame( 'editor', $user->roles[0] );
	}

	/**
	 * Ensures get_object_vars()/var_export()/wp_json_encode() behave identically for a
	 * normal get_userdata() user and a get_authordata() (short-init) user, both before and
	 * after capability data has been lazily loaded. This is the exact failure mode that
	 * caused the previous protected-property + magic-method approach to be reverted.
	 *
	 * @ticket 58001
	 */
	public function test_get_authordata_serialization_unaffected() {
		$id = self::$users['contributor']->ID;

		$eager_user = get_userdata( $id );
		$short_user = get_authordata( $id );

		foreach ( array( 'eager_user', 'short_user' ) as $var_name ) {
			$user = $$var_name;

			// get_object_vars() and wp_json_encode() only surface properties visible from
			// outside the class, so 'short_init' (private) must never appear in either.
			$vars = get_object_vars( $user );
			$this->assertArrayHasKey( 'caps', $vars, "get_object_vars() should include 'caps' for {$var_name}." );
			$this->assertArrayHasKey( 'roles', $vars, "get_object_vars() should include 'roles' for {$var_name}." );
			$this->assertArrayHasKey( 'allcaps', $vars, "get_object_vars() should include 'allcaps' for {$var_name}." );
			$this->assertArrayNotHasKey( 'short_init', $vars, "get_object_vars() should never expose short_init for {$var_name}." );

			$encoded = wp_json_encode( $user );
			$this->assertStringNotContainsString( 'short_init', $encoded, "wp_json_encode() should never expose short_init for {$var_name}." );

			// var_export() dumps ALL properties including private ones (by design, unlike
			// get_object_vars()/json_encode()), so it's expected to include 'short_init' here
			// as it always has for existing private properties like $site_id -- this isn't
			// a regression, just confirming var_export() still works without erroring.
			var_export( $user, true );
		}

		// Trigger the lazy load and confirm roles now show up identically to the eager user.
		$short_user->has_cap( 'edit_posts' );
		$this->assertSame( $eager_user->roles, $short_user->roles );

		$encoded_after = wp_json_encode( $short_user );
		$this->assertStringContainsString( 'contributor', $encoded_after, 'wp_json_encode() should include roles once loaded.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_the_author_meta_roles_field_loads_short_init_authordata() {
		global $authordata;

		$id                  = self::$users['contributor']->ID;
		$authordata          = get_authordata( $id );
		$original_authordata = $authordata;

		$this->assertSame( array(), $original_authordata->roles, 'roles should start empty for a short-init authordata.' );
		$this->assertSame( array( 'contributor' ), get_the_author_meta( 'roles' ) );

		unset( $authordata );
	}
}
