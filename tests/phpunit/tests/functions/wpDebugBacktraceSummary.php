<?php

/**
 * Tests for the wp_debug_backtrace_summary function.
 *
 * @group functions.php
 *
 * @covers ::wp_debug_backtrace_summary
 */
class Tests_functions_wpDebugBacktraceSummary extends WP_UnitTestCase {

	/**
	 * Default test
	 *
	 * @ticket 59829
	 */
	public function test_wp_debug_backtrace_summary() {

		$backtrace = wp_debug_backtrace_summary();

		$this->assertStringContainsString( 'Tests_functions_wpDebugBacktraceSummary', $backtrace );
	}

	/**
	 * Test we get an array back
	 *
	 * @ticket 59829
	 */
	public function test_wp_debug_backtrace_summary_returns_array() {

		$backtrace = wp_debug_backtrace_summary( null, 0, false );

		$this->assertIsArray( $backtrace );
	}

	/**
	 * Test that we can skip a class
	 *
	 * @ticket 59829
	 */
	public function test_wp_debug_backtrace_summary_skips_class() {

		$backtrace = wp_debug_backtrace_summary( 'Tests_functions_wpDebugBacktraceSummary' );

		$this->assertStringNotContainsString( 'Tests_functions_wpDebugBacktraceSummary', $backtrace );
	}

	/**
	 * Test that we can skip the results of functions
	 *
	 * @ticket 59829
	 */
	public function test_wp_debug_backtrace_summary_skips() {

		$backtrace        = wp_debug_backtrace_summary( null, 0, false );
		$backtrace_skiped = wp_debug_backtrace_summary( null, 2, false );

		$this->assertSame( count( $backtrace ) - 2, count( $backtrace_skiped ) );
	}
}
