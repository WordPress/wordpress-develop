<?php

/**
 * Tests for the wp_cache_get_last_changed() function.
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_cache_get_last_changed
 */
class Tests_Functions_wpCacheGetLastChanged extends WP_UnitTestCase {

	/**
	 * When no last_changed value is cached yet, the function should set one and return it.
	 *
	 * @ticket 37464
	 */
	public function test_wp_cache_get_last_changed_sets_value_when_not_already_set() {
		$group = 'group_name';

		$last_changed = wp_cache_get_last_changed( $group );

		$this->assertSame( $last_changed, wp_cache_get( 'last_changed', $group ) );
	}

	/**
	 * When a last_changed value is already cached, the function should return it unchanged.
	 *
	 * @ticket 37464
	 */
	public function test_wp_cache_get_last_changed_returns_existing_value_when_already_set() {
		$group = 'group_name';

		wp_cache_set( 'last_changed', 'existing-value', $group );

		$this->assertSame( 'existing-value', wp_cache_get_last_changed( $group ) );
	}

	/**
	 * Repeated calls should return the same value instead of regenerating it each time.
	 *
	 * @ticket 37464
	 */
	public function test_wp_cache_get_last_changed_is_idempotent() {
		$group = 'group_name';

		$first_call  = wp_cache_get_last_changed( $group );
		$second_call = wp_cache_get_last_changed( $group );

		$this->assertSame( $first_call, $second_call );
	}
}
