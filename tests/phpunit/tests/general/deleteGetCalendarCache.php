<?php

/**
 * Tests for the `delete_get_calendar_cache()` function.
 *
 * @group general
 *
 * @covers ::delete_get_calendar_cache
 */
class Tests_General_DeleteGetCalendarCache extends WP_UnitTestCase {

	/**
	 * Tests that delete_get_calendar_cache() clears the calendar cache.
	 *
	 * @ticket 65819
	 */
	public function test_delete_get_calendar_cache_clears_cache() {
		wp_cache_set( 'get_calendar', array( 'sample_key' => 'calendar_content' ), 'calendar' );
		$this->assertSame(
			array( 'sample_key' => 'calendar_content' ),
			wp_cache_get( 'get_calendar', 'calendar' )
		);

		delete_get_calendar_cache();

		$this->assertFalse( wp_cache_get( 'get_calendar', 'calendar' ) );
	}

	/**
	 * Tests that delete_get_calendar_cache() executes without error when cache does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_delete_get_calendar_cache_when_empty() {
		wp_cache_delete( 'get_calendar', 'calendar' );

		delete_get_calendar_cache();

		$this->assertFalse( wp_cache_get( 'get_calendar', 'calendar' ) );
	}
}
