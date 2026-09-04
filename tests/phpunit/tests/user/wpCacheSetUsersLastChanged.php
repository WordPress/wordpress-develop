<?php

/**
 * Tests for the wp_cache_set_users_last_changed() function.
 *
 * @group user
 * @group cache
 *
 * @covers ::wp_cache_set_users_last_changed
 */
class Tests_User_WpCacheSetUsersLastChanged extends WP_UnitTestCase {

	/**
	 * A user used across the tests.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Creates a shared user before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create();
	}

	/**
	 * Adding user meta should update the 'users' and 'user-meta'
	 * last changed values but leave 'user-queries' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_user_meta_action_updates_users_and_user_meta() {
		$users_before        = wp_cache_get_last_changed( 'users' );
		$user_meta_before    = wp_cache_get_last_changed( 'user-meta' );
		$user_queries_before = wp_cache_get_last_changed( 'user-queries' );

		add_user_meta( self::$user_id, 'test_key', 'test_value' );

		$this->assertNotSame(
			$users_before,
			wp_cache_get_last_changed( 'users' ),
			'The users last changed value should be updated.'
		);
		$this->assertNotSame(
			$user_meta_before,
			wp_cache_get_last_changed( 'user-meta' ),
			'The user-meta last changed value should be updated.'
		);
		$this->assertSame(
			$user_queries_before,
			wp_cache_get_last_changed( 'user-queries' ),
			'The user-queries last changed value should not be updated.'
		);
	}

	/**
	 * Cleaning the user cache should update the 'users' and 'user-queries'
	 * last changed values but leave 'user-meta' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_user_query_action_updates_users_and_user_queries() {
		$users_before        = wp_cache_get_last_changed( 'users' );
		$user_meta_before    = wp_cache_get_last_changed( 'user-meta' );
		$user_queries_before = wp_cache_get_last_changed( 'user-queries' );

		clean_user_cache( self::$user_id );

		$this->assertNotSame(
			$users_before,
			wp_cache_get_last_changed( 'users' ),
			'The users last changed value should be updated.'
		);
		$this->assertNotSame(
			$user_queries_before,
			wp_cache_get_last_changed( 'user-queries' ),
			'The user-queries last changed value should be updated.'
		);
		$this->assertSame(
			$user_meta_before,
			wp_cache_get_last_changed( 'user-meta' ),
			'The user-meta last changed value should not be updated.'
		);
	}
}
