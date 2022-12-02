<?php
/**
 * Tests for the wp_suspend_cache_addition function.
 *
 * @group functions.php
 *
 * @covers ::wp_suspend_cache_addition
 */#
class Tests_Function_WpSuspendCacheAddition extends WP_UnitTestCase {

	/**
	 * @ticket 57263
	 */
	public function test_WpSuspendCacheAddition() {
		$default = wp_suspend_cache_addition();
		$this->assertFalse( $default, 'default' );
		$this->assertFalse( wp_suspend_cache_addition( true ), 'set true' );
		$this->assertTrue( wp_suspend_cache_addition(), 'check is still true' );


		$this->assertTrue( wp_suspend_cache_addition( 'true' ), 'Try to set string' );
		$this->assertTrue( wp_suspend_cache_addition(), 'check is still true' );

		// reset to default
		$this->assertTrue( wp_suspend_cache_addition( $default ), 'set false and back to default' );
	}
}
