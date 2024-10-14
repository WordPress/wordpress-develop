<?php

/**
 * Tests for the wp_suspend_cache_addition function.
 *
 * @group Functions.php
 *
 * @covers ::wp_suspend_cache_addition
 */
class Tests_Functions_wpSuspendCacheAddition extends WP_UnitTestCase {

	/**
	 * @ticket 60017
	 */
	public function test_wp_suspend_cache_addition() {
		$this->assertFalse( wp_suspend_cache_addition() ); // check initial state.

		$this->assertTrue( wp_suspend_cache_addition( true ) ); // set to true.
		$this->assertTrue( wp_suspend_cache_addition() );
		$this->assertTrue( wp_suspend_cache_addition() ); // call again make it did get reset

		$this->assertFalse( wp_suspend_cache_addition( false ) ); // set to false.
		$this->assertFalse( wp_suspend_cache_addition() );
		$this->assertFalse( wp_suspend_cache_addition() );  // call again make it did get reset

		$this->assertFalse( wp_suspend_cache_addition( 'true' ) ); // not bool so state not set
		$this->assertFalse( wp_suspend_cache_addition() );

		$this->assertFalse( wp_suspend_cache_addition( 1 ) ); // not bool so state not set
		$this->assertFalse( wp_suspend_cache_addition() );
	}
}
