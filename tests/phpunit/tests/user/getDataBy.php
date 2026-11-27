<?php

/**
 * Tests for WP_User::get_data_by() caching behavior.
 *
 * @group user
 * @group cache
 *
 * @coversDefaultClass WP_User
 */
class Tests_User_GetDataBy extends WP_UnitTestCase {

	/**
	 * @ticket 46388
	 * @covers WP_User::get_data_by
	 */
	public function test_nonexistent_user_by_id_does_not_trigger_multiple_queries() {
		global $wpdb;

		$nonexistent_id = PHP_INT_MAX;

		$before = $wpdb->num_queries;
		get_userdata( $nonexistent_id );
		$after_first_call = $wpdb->num_queries;

		get_userdata( $nonexistent_id );
		$after_second_call = $wpdb->num_queries;

		$this->assertSame( 1, $after_first_call - $before, 'First call for non-existent user should trigger one DB query.' );
		$this->assertSame( 0, $after_second_call - $after_first_call, 'Second call for non-existent user should not trigger any DB queries.' );
	}

	/**
	 * @ticket 46388
	 * @covers WP_User::get_data_by
	 */
	public function test_nonexistent_user_by_id_is_added_to_notuser_cache() {
		$nonexistent_id = PHP_INT_MAX - 1;

		get_userdata( $nonexistent_id );

		$last_changed = wp_cache_get_last_changed( 'users' );

		$this->assertNotFalse(
			wp_cache_get( "notuser:{$nonexistent_id}:{$last_changed}", 'users' ),
			'Non-existent user ID should be stored in the negative cache after a DB miss.'
		);
	}

	/**
	 * @ticket 46388
	 * @covers WP_User::get_data_by
	 */
	public function test_existing_user_by_id_is_not_added_to_notuser_cache() {
		$user_id = self::factory()->user->create();

		get_userdata( $user_id );

		$last_changed = wp_cache_get_last_changed( 'users' );

		$this->assertFalse(
			wp_cache_get( "notuser:{$user_id}:{$last_changed}", 'users' ),
			'Existing user ID should not be stored in the negative cache.'
		);
	}

	/**
	 * Verifies that a user mutation invalidates the negative cache.
	 *
	 * Creating, updating, or deleting any user calls clean_user_cache(), which
	 * bumps the 'users' group last changed value. Because the negative cache key
	 * is salted with that value, every negative entry is invalidated at once, so
	 * a previously non-existent ID is looked up against the database again.
	 *
	 * @ticket 46388
	 * @covers WP_User::get_data_by
	 */
	public function test_user_mutation_invalidates_negative_cache() {
		global $wpdb;

		$nonexistent_id = PHP_INT_MAX - 2;

		// Prime the negative cache for a non-existent ID.
		get_userdata( $nonexistent_id );

		$queries_before = $wpdb->num_queries;
		get_userdata( $nonexistent_id );
		$this->assertSame(
			0,
			$wpdb->num_queries - $queries_before,
			'Repeated lookup should be served from the negative cache.'
		);

		// Creating a user bumps the 'users' last changed value via clean_user_cache().
		self::factory()->user->create();

		$queries_before = $wpdb->num_queries;
		get_userdata( $nonexistent_id );
		$this->assertGreaterThan(
			0,
			$wpdb->num_queries - $queries_before,
			'After the users last changed value is bumped, the lookup should query the database again.'
		);
	}

	/**
	 * Verifies that a negative cache entry for a not-yet-existing ID cannot
	 * shadow a user that is subsequently inserted with that same ID.
	 *
	 * wp_insert_user() bumps the 'users' group last changed value immediately
	 * after inserting the row, so the WP_User object constructed afterwards is
	 * built from the real database row rather than the stale negative entry.
	 *
	 * @ticket 46388
	 * @covers WP_User::get_data_by
	 */
	public function test_negative_cache_entry_does_not_shadow_newly_inserted_user() {
		global $wpdb;

		$next_user_id = (int) $wpdb->get_var( "SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '$wpdb->users'" );

		// Prime the negative cache for the ID the next insert will be assigned.
		$this->assertFalse( get_userdata( $next_user_id ), 'User should not exist yet.' );

		$last_changed = wp_cache_get_last_changed( 'users' );
		$this->assertNotFalse(
			wp_cache_get( "notuser:{$next_user_id}:{$last_changed}", 'users' ),
			'Negative cache entry should exist for the next auto-increment ID.'
		);

		$new_id = wp_insert_user(
			array(
				'user_login' => 'negative_cache_collision',
				'user_pass'  => 'password',
				'user_email' => 'negative_cache_collision@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->assertSame( $next_user_id, $new_id, 'Inserted user should be assigned the predicted auto-increment ID.' );

		$user = get_userdata( $new_id );

		$this->assertNotFalse( $user, 'The newly inserted user should be loadable.' );
		$this->assertContains( 'subscriber', $user->roles, 'The newly inserted user should have the requested role.' );
		$this->assertSame(
			array( 'subscriber' => true ),
			get_user_meta( $new_id, $wpdb->prefix . 'capabilities', true ),
			'Capabilities meta should be stored under the real user ID.'
		);
	}
}
