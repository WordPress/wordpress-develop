<?php
/**
 * Tests for the wp_cache_set_last_changed function.
 *
 * @group functions.php
 *
 * @covers ::wp_cache_set_last_changed
 */#
class Tests_functions_wpCacheSetLastChanged extends WP_UnitTestCase {

	/**
	 * check the cache_set_last_changed is set.
	 *
	 * @ticket 59737
	 */
	public function test_wp_cache_set_last_changed() {
		$group = 'group_name';

		$this->assertSame( wp_cache_set_last_changed( $group ), wp_cache_get( 'last_changed', $group ) );
	}

	/**
	 * Check the action is called.
	 *
	 * @ticket 59737
	 */
	public function test_wp_cache_set_last_changed_action_is_called() {

		add_action(
			'wp_cache_set_last_changed',
			function ( $group ) {
				$this->assertSame( 'group_name', $group );
			}
		);

		wp_cache_set_last_changed( 'group_name' );
	}
}
