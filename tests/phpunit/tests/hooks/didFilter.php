<?php

/**
 * Test did_filter().
 *
 * @group hooks
 * @covers ::did_filter
 */
class Tests_Hooks_DidFilter extends WP_UnitTestCase {

	public function test_did_filter() {
		$hook_name1 = 'filter1';
		$hook_name2 = 'filter2';
		$val        = __FUNCTION__ . '_val';

		// Apply filter $hook_name1 but not $hook_name2.
		apply_filters( $hook_name1, $val );
		$this->assertSame( 1, did_filter( $hook_name1 ) );
		$this->assertSame( 0, did_filter( $hook_name2 ) );

		// Apply filter $hook_name2 10 times.
		$count = 10;
		for ( $i = 0; $i < $count; $i++ ) {
			apply_filters( $hook_name2, $val );
		}

		// $hook_name1's count hasn't changed, $hook_name2 should be correct.
		$this->assertSame( 1, did_filter( $hook_name1 ) );
		$this->assertSame( $count, did_filter( $hook_name2 ) );
	}
}
