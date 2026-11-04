<?php

/**
 * Tests for the wp_suspend_cache_addition function.
 *
 * @group functions
 *
 * @covers ::wp_suspend_cache_addition
 */
class Tests_Functions_wpSuspendCacheAddition extends WP_UnitTestCase {

	/**
	 * Restores cache additions after each test.
	 */
	public function tear_down() {
		wp_suspend_cache_addition( false );
		parent::tear_down();
	}

	/**
	 * @ticket 60017
	 */
	public function test_wp_suspend_cache_addition() {
		$this->assertFalse( wp_suspend_cache_addition() ); // check initial state.

		$this->assertTrue( wp_suspend_cache_addition( true ) ); // set to true.
		$this->assertTrue( wp_suspend_cache_addition() );
		$this->assertTrue( wp_suspend_cache_addition() ); // Call again to verify that the state remains unchanged.

		$this->assertTrue( wp_suspend_cache_addition( 'true' ) ); // not bool so state not set
		$this->assertTrue( wp_suspend_cache_addition() );
		$this->assertTrue( wp_suspend_cache_addition( 1 ) ); // not bool so state not set
		$this->assertTrue( wp_suspend_cache_addition() );

		$this->assertFalse( wp_suspend_cache_addition( false ) ); // set to false.
		$this->assertFalse( wp_suspend_cache_addition() );
		$this->assertFalse( wp_suspend_cache_addition() );  // call again make it did get reset

		$this->assertFalse( wp_suspend_cache_addition( 'true' ) ); // not bool so state not set
		$this->assertFalse( wp_suspend_cache_addition() );

		$this->assertFalse( wp_suspend_cache_addition( 1 ) ); // not bool so state not set
		$this->assertFalse( wp_suspend_cache_addition() );
	}
}
