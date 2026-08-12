<?php

/**
 * Tests for the wp_suspend_cache_invalidation function.
 *
 * @group Functions
 *
 * @covers ::wp_suspend_cache_invalidation
 */
class Tests_Functions_wpSuspendCacheInvalidation extends WP_UnitTestCase {

	/**
	 * @ticket 60015
	 */
	public function test_wp_suspend_cache_invalidation() {
		global $_wp_suspend_cache_invalidation;

		$this->assertNull( $_wp_suspend_cache_invalidation );
		$this->assertEmpty( $_wp_suspend_cache_invalidation );

		$this->assertNull( wp_suspend_cache_invalidation() );
		$this->assertTrue( $_wp_suspend_cache_invalidation );

		$this->assertTrue( wp_suspend_cache_invalidation( false ) );
		$this->assertFalse( $_wp_suspend_cache_invalidation );

		$this->assertFalse( wp_suspend_cache_invalidation() );
		$this->assertTrue( $_wp_suspend_cache_invalidation );

		$this->assertTrue( wp_suspend_cache_invalidation( 'false' ) );
		$this->assertNotEmpty( $_wp_suspend_cache_invalidation );
		$this->assertTrue( $_wp_suspend_cache_invalidation );

		$this->assertNotEmpty( wp_suspend_cache_invalidation( 'not_empty' ) );
		$this->assertNotEmpty( $_wp_suspend_cache_invalidation );

		$this->assertNotEmpty( wp_suspend_cache_invalidation( '' ) );
		$this->assertEmpty( $_wp_suspend_cache_invalidation );
		$this->assertFalse( $_wp_suspend_cache_invalidation );

		$this->assertEmpty( wp_suspend_cache_invalidation( 0 ) );
		$this->assertEmpty( $_wp_suspend_cache_invalidation );
		$this->assertFalse( $_wp_suspend_cache_invalidation );
	}
}
