<?php
/**
 * Tests for the wp_cache_get_last_changed function.
 *
 * @group functions.php
 *
 * @covers ::wp_cache_get_last_changed
 */#
class Tests_functions_wpCacheGetLastChanged extends WP_UnitTestCase {

	/**
	 * Test that a cache is ready if set.
	 *
	 * @ticket 59752
	 */
	public function test_wp_cache_get_last_changed_uses_cache_value() {

		$value = 'from_cache';
		$group = 'test_group';

		wp_cache_set( 'last_changed', $value, $group );

		$this->assertSame( wp_cache_get_last_changed( $group ), $value );
	}

	/**
	 * Test that if no value is set in the cache, A new time is set and returned.
	 *
	 * @ticket 59752
	 */
	public function test_wp_cache_get_last_changed_returns_new_time_if_no_cache_value() {

		$group = 'test_group';
		// Clear cache.
		wp_cache_delete( 'last_changed', $group );

		$this->assertSame( wp_cache_get_last_changed( $group ), wp_cache_get( 'last_changed', $group ) );
	}
}
