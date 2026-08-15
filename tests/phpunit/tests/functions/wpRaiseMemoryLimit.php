<?php

/**
 * @group functions
 *
 * @covers ::wp_raise_memory_limit
 */
class Tests_Functions_WpRaiseMemoryLimit extends WP_UnitTestCase {
	/**
	 * Tests raising the memory limit.
	 *
	 * Unfortunately as the default for 'WP_MAX_MEMORY_LIMIT' in the
	 * test suite is -1, we can not test the memory limit negotiations.
	 *
	 * @ticket 32075
	 */
	public function test_wp_raise_memory_limit() {
		if ( -1 !== WP_MAX_MEMORY_LIMIT ) {
			$this->markTestSkipped( 'WP_MAX_MEMORY_LIMIT should be set to -1.' );
		}

		$ini_limit_before = ini_get( 'memory_limit' );
		$raised_limit     = wp_raise_memory_limit();
		$ini_limit_after  = ini_get( 'memory_limit' );

		$this->assertSame( $ini_limit_before, $ini_limit_after );
		$this->assertFalse( $raised_limit );
		$this->assertEquals( WP_MAX_MEMORY_LIMIT, $ini_limit_after );
	}
}
