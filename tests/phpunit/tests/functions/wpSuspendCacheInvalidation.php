<?php
/**
 * Tests for the wp_suspend_cache_invalidation function.
 *
 * @group functions.php
 *
 * @covers ::wp_suspend_cache_invalidation
 */#
class Tests_functions_WpSuspendCacheInvalidation extends WP_UnitTestCase {

	/**
	 * @ticket 57264
	 */
	public function test_wp_suspend_cache_invalidation() {
		global $_wp_suspend_cache_invalidation;
		$default = $_wp_suspend_cache_invalidation;

		$this->assertEmpty( $_wp_suspend_cache_invalidation, 'Check global' );
		$this->assertEmpty( wp_suspend_cache_invalidation(), 'call default' );

		$this->assertTrue( $_wp_suspend_cache_invalidation, 'check is true' );
		// checked for not empty as this how it is used in core
		$this->assertNotEmpty( $_wp_suspend_cache_invalidation, 'check is true' );
		$this->assertTrue( wp_suspend_cache_invalidation( false ), 'Set to false' );
		$this->assertEmpty( $_wp_suspend_cache_invalidation, 'check is still false' );

		wp_suspend_cache_invalidation( $default );
	}
}
